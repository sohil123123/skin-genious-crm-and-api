<?php

declare(strict_types=1);

use App\Enums\Call\TranscriptionStatus;
use App\Models\{Call, CallRecording, Setting};
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;
use App\Services\Call\Transcription\{NullTranscriptionService, OpenAiTranscriptionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Illuminate\Support\Str;

/**
 * Turning transcription on from the settings screen.
 *
 * Two ordering traps live here, and both fail silently.
 *
 * The driver used to come only from config, so flipping the toggle left the
 * null driver bound and nothing was transcribed — no error, just recordings
 * quietly settling as "not available".
 *
 * And NotAvailable is terminal, which is where every recording lands while the
 * feature is off. Switching it on later therefore changes nothing for the calls
 * already in the system unless something deliberately revisits them.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Storage::fake('local');
    config()->set('calls.recording.disk', 'local');

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'tr-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'started_at' => now(),
    ]);
});

function settledRecording(Call $call, string $status = 'not_available', int $duration = 245): CallRecording
{
    Storage::disk('local')->put('call-recordings/tr.mp3', str_repeat('x', 2048));

    return CallRecording::create([
        'call_id' => $call->getKey(),
        'provider' => 'exotel',
        'source_url' => 'https://rec.exotel.test/' . Str::random(8) . '.mp3',
        'source_url_hash' => hash('sha256', Str::random(12)),
        'storage_disk' => 'local',
        'storage_path' => 'call-recordings/tr.mp3',
        'download_status' => 'downloaded',
        'storage_status' => 'stored',
        'transcription_status' => $status,
        'duration_seconds' => $duration,
    ]);
}

// ──────────────── The driver is settable from the UI ────────────────

it('binds the null driver when no provider is chosen', function (): void {
    expect(app(CallTranscriptionServiceInterface::class))->toBeInstanceOf(NullTranscriptionService::class);
});

/**
 * The gap this closes: the driver could only be changed in .env, so the
 * settings screen's toggle was decorative.
 */
it('binds the real driver from the settings screen, not just .env', function (): void {
    Setting::setValue('call_transcription_driver', 'openai');
    Setting::flushRuntimeCache();
    app()->forgetInstance(CallTranscriptionServiceInterface::class);

    expect(app(CallTranscriptionServiceInterface::class))->toBeInstanceOf(OpenAiTranscriptionService::class);
});

it('reports itself disabled until a key is saved', function (): void {
    Setting::setValue('call_transcription_driver', 'openai');
    Setting::setValue('call_transcription_enabled', '1');
    Setting::flushRuntimeCache();
    app()->forgetInstance(CallTranscriptionServiceInterface::class);

    expect(app(CallTranscriptionServiceInterface::class)->isEnabled())->toBeFalse();

    Setting::setValue('call_transcription_api_key', 'sk-test');
    Setting::flushRuntimeCache();

    expect(app(CallTranscriptionServiceInterface::class)->isEnabled())->toBeTrue();
});

// ──────────────── Existing recordings can be reached ────────────────

it('refuses to queue anything while transcription is off', function (): void {
    settledRecording($this->call);

    $this->artisan('calls:transcribe')
        ->expectsOutputToContain('Transcription is not enabled')
        ->assertFailed();
});

it('skips recordings that settled while the feature was off, unless asked', function (): void {
    Queue::fake();
    enableTranscription();
    settledRecording($this->call);

    $this->artisan('calls:transcribe')
        ->expectsOutputToContain('Nothing to transcribe')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

/**
 * The whole reason the flag exists.
 */
it('transcribes the back catalogue when asked with --all', function (): void {
    Queue::fake();
    enableTranscription();
    $recording = settledRecording($this->call);

    $this->artisan('calls:transcribe', ['--all' => true])->assertSuccessful();

    Queue::assertPushed(\App\Jobs\Call\TranscribeCallRecordingJob::class);

    // Reset to Pending, or the job would return immediately on a settled row.
    expect($recording->fresh()->transcription_status)->toBe(TranscriptionStatus::Pending);
});

/**
 * A four-second recording is a ring-out. Transcribing it costs money and
 * returns nothing.
 */
it('does not queue audio too short to carry speech', function (): void {
    Queue::fake();
    enableTranscription();
    settledRecording($this->call, 'not_available', duration: 2);

    $this->artisan('calls:transcribe', ['--all' => true])
        ->expectsOutputToContain('under the')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('changes nothing on a dry run', function (): void {
    Queue::fake();
    enableTranscription();
    $recording = settledRecording($this->call);

    $this->artisan('calls:transcribe', ['--all' => true, '--dry-run' => true])->assertSuccessful();

    Queue::assertNothingPushed();
    expect($recording->fresh()->transcription_status)->toBe(TranscriptionStatus::NotAvailable);
});

function enableTranscription(): void
{
    Setting::setValue('call_transcription_driver', 'openai');
    Setting::setValue('call_transcription_enabled', '1');
    Setting::setValue('call_transcription_api_key', 'sk-test');
    Setting::flushRuntimeCache();
    app()->forgetInstance(CallTranscriptionServiceInterface::class);
}

// ──────────────── Getting the language right ────────────────

/**
 * The bug that prompted these: a Hindi and English call came back written in
 * Urdu. Hindi and Urdu are the same spoken language in two scripts, so Whisper
 * moves between them freely unless something pins it down.
 */
it('sends the language and style prompt so the script is not left to chance', function (): void {
    enableTranscription();
    Setting::setValue('call_transcription_language', 'hi');
    Setting::setValue('call_transcription_prompt', 'यह एक स्किन क्लिनिक की कॉल है।');
    Setting::flushRuntimeCache();

    \Illuminate\Support\Facades\Http::fake([
        '*/audio/transcriptions' => \Illuminate\Support\Facades\Http::response([
            'text' => 'नमस्ते, मैं HydraFacial के बारे में पूछना चाहती थी।',
            'language' => 'hindi',
            'duration' => 58,
            'segments' => [],
        ]),
    ]);

    $recording = settledRecording($this->call);

    $result = app(CallTranscriptionServiceInterface::class)->transcribe($recording);

    expect($result)->not->toBeNull()
        ->and($result->languageCode)->toBe('hi');

    \Illuminate\Support\Facades\Http::assertSent(function ($request): bool {
        $parts = multipartFields($request);

        return ($parts['language'] ?? null) === 'hi'
            && str_contains($parts['prompt'] ?? '', 'क्लिनिक');
    });
});

/**
 * Only whisper-1 returns segment timings. Asking a gpt-4o transcription model
 * for verbose_json is a 400, so the format has to follow the model.
 */
it('asks for timestamps only from a model that has them', function (string $model, string $format): void {
    enableTranscription();
    Setting::setValue('call_transcription_model', $model);
    Setting::flushRuntimeCache();

    \Illuminate\Support\Facades\Http::fake([
        '*/audio/transcriptions' => \Illuminate\Support\Facades\Http::response(['text' => 'hello', 'segments' => []]),
    ]);

    app(CallTranscriptionServiceInterface::class)->transcribe(settledRecording($this->call));

    \Illuminate\Support\Facades\Http::assertSent(
        fn ($request): bool => (multipartFields($request)['response_format'] ?? null) === $format
    );
})->with([
    'whisper keeps timings' => ['whisper-1', 'verbose_json'],
    'gpt-4o cannot' => ['gpt-4o-transcribe', 'json'],
]);

/**
 * str_word_count() only sees Latin letters, so every Hindi, Gujarati or Urdu
 * transcript reported "0 words" next to a screen full of text.
 */
it('counts words in a non-Latin script', function (): void {
    $result = new \App\DTOs\Call\TranscriptionResult(
        transcript: 'नमस्ते मैं HydraFacial के बारे में पूछना चाहती थी',
    );

    expect($result->wordCount())->toBe(9)
        ->and($result->isMeaningful())->toBeTrue();
});

/**
 * Multipart fields as a name => value map.
 *
 * The file part is skipped: the service closes that stream once the request is
 * sent, which is correct, and reading the raw body afterwards throws.
 *
 * @return array<string, string>
 */
function multipartFields(\Illuminate\Http\Client\Request $request): array
{
    $fields = [];

    foreach ($request->data() as $part) {
        if (($part['name'] ?? null) === 'file' || ! is_string($part['contents'] ?? null)) {
            continue;
        }

        $fields[$part['name']] = $part['contents'];
    }

    return $fields;
}

// ──────────────── Repetition loops ────────────────

/**
 * The failure this guards against, seen on a real 3-minute call: the model got
 * stuck emitting one sentence twenty-one times. The transcript looked complete
 * — 706 words, plausible header — and was unreadable.
 *
 * A known failure of greedy decoding, which is why the request no longer pins
 * temperature to 0: that suppressed the provider's own fallback, whose whole
 * job is detecting this and retrying hotter until it breaks out.
 */
it('collapses a sentence the model repeated twenty times', function (): void {
    $line = 'पर एक बार डॉक्टर सुनाली देखेंगे बताएंगे, तो उसमें एक सेशन का पैकेज कुछ 45,000 का है.';
    $text = 'नमस्ते, मैं पूछना चाहती थी. ' . str_repeat($line . ' ', 20) . 'ठीक है, धन्यवाद.';

    [$collapsed, $warnings] = \App\Services\Call\Transcription\OpenAiTranscriptionService::collapseRepetition($text);

    expect(substr_count($collapsed, $line))->toBe(2)
        ->and($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('20 times')
        // The real content either side of the loop survives.
        ->and($collapsed)->toContain('नमस्ते')
        ->toContain('धन्यवाद');
});

/**
 * People really do repeat themselves on a phone call. Twice is speech; twenty
 * times is not something a human said.
 */
it('leaves a sentence said twice alone', function (): void {
    $text = 'Hello? Hello? Can you hear me now.';

    [$collapsed, $warnings] = \App\Services\Call\Transcription\OpenAiTranscriptionService::collapseRepetition($text);

    expect($collapsed)->toBe($text)
        ->and($warnings)->toBeEmpty();
});

it('leaves an ordinary transcript untouched', function (): void {
    $text = 'नमस्ते. मैं HydraFacial के बारे में पूछना चाहती थी. कितना खर्च आएगा?';

    [$collapsed, $warnings] = \App\Services\Call\Transcription\OpenAiTranscriptionService::collapseRepetition($text);

    expect($collapsed)->toBe($text)->and($warnings)->toBeEmpty();
});

/**
 * Pinning temperature is what caused the loop, so the request must not send one
 * unless somebody deliberately sets it.
 */
it('does not pin temperature unless asked', function (): void {
    enableTranscription();

    \Illuminate\Support\Facades\Http::fake([
        '*/audio/transcriptions' => \Illuminate\Support\Facades\Http::response(['text' => 'hello there.', 'segments' => []]),
    ]);

    app(CallTranscriptionServiceInterface::class)->transcribe(settledRecording($this->call));

    \Illuminate\Support\Facades\Http::assertSent(
        fn ($request): bool => ! array_key_exists('temperature', multipartFields($request))
    );
});
