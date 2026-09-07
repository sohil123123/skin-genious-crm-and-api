<?php

declare(strict_types=1);

use App\Enums\Call\CallSource;
use App\Enums\Call\RecordingDownloadStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Jobs\Call\DownloadCallRecordingJob;
use App\Jobs\Call\TranscribeCallRecordingJob;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\CallTranscription;
use App\Models\Setting;
use App\Services\Call\CallIngestionService;
use App\Services\Call\CallRecordingService;
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;
use App\Services\Call\Exceptions\CallProviderException;
use App\Services\Call\Providers\Exotel\ExotelCallMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Getting call audio off the provider's server and onto the clinic's.
 *
 * The reason this pipeline exists: a provider recording URL is a loan, not a
 * possession. Both providers expire theirs, so a CRM that stores only the URL
 * ends up with a call history of dead links — and nobody notices until someone
 * needs the one call that mattered.
 *
 * The validation tests matter more than they look. An expired provider URL
 * typically answers 200 with an HTML error page, so a naive save writes a page
 * of markup to disk under an .mp3 extension and reports success.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Storage::fake('local');

    $this->ingestion = app(CallIngestionService::class);
    $this->recordings = app(CallRecordingService::class);
    $this->mapper = app(ExotelCallMapper::class);

    config()->set('calls.recording.disk', 'local');
});

function callWithRecording(string $url = 'https://rec.exotel.test/a.mp3'): Call
{
    return test()->ingestion->ingest(test()->mapper->map([
        'CallSid' => 'rec-call-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => '2026-08-29 10:15:00',
        'DialCallDuration' => '272',
        'RecordingUrl' => $url,
    ], CallSource::Webhook));
}

/**
 * Bytes that pass the audio sniff: not markup, and long enough to be real.
 */
function fakeAudio(): string
{
    return "ID3\x03\x00\x00\x00" . str_repeat("\x00\xFF\xFB\x90", 200);
}

// ──────────────── Nothing downloads inside a webhook ────────────────

it('queues a recording download rather than fetching it inline', function (): void {
    Queue::fake();

    $call = callWithRecording();

    expect($call->recordings)->toHaveCount(1)
        ->and($call->has_recording)->toBeTrue();

    $recording = $call->recordings()->first();

    expect($recording->download_status)->toBe(RecordingDownloadStatus::Pending)
        // Honest starting state: the audio is still on the provider's server.
        ->and($recording->storage_status)->toBe(RecordingStorageStatus::RemoteOnly)
        ->and($recording->source_url)->toBe('https://rec.exotel.test/a.mp3');

    Queue::assertPushed(DownloadCallRecordingJob::class);
});

it('does not attach the same recording twice when the provider resends it', function (): void {
    Queue::fake();

    callWithRecording();
    callWithRecording();

    expect(Call::count())->toBe(1)
        ->and(CallRecording::count())->toBe(1);
});

// ──────────────── Downloading ────────────────

it('stores the audio and records its checksum and size', function (): void {
    Queue::fake();

    $call = callWithRecording();
    $recording = $call->recordings()->first();

    $audio = fakeAudio();

    Http::fake([
        'rec.exotel.test/*' => Http::response($audio, 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    expect($this->recordings->download($recording))->toBeTrue();

    $recording->refresh();

    expect($recording->download_status)->toBe(RecordingDownloadStatus::Downloaded)
        ->and($recording->storage_status)->toBe(RecordingStorageStatus::Stored)
        ->and($recording->file_size)->toBe(strlen($audio))
        ->and($recording->checksum)->toBe(hash('sha256', $audio))
        ->and($recording->extension)->toBe('mp3');

    Storage::disk('local')->assertExists($recording->storage_path);

    // The path must not leak anything about the patient.
    expect($recording->storage_path)->toContain($call->uuid)
        ->not->toContain('9876543210');
});

/**
 * The failure this catches: an expired link answering 200 with an error page.
 * Storing it would leave a call showing a playable recording that plays
 * nothing.
 */
it('refuses to store an HTML error page dressed as audio', function (): void {
    Queue::fake();

    $recording = callWithRecording()->recordings()->first();

    Http::fake([
        'rec.exotel.test/*' => Http::response(
            '<html><body>Link expired</body></html>',
            200,
            ['Content-Type' => 'audio/mpeg'],
        ),
    ]);

    expect($this->recordings->download($recording))->toBeFalse();

    $recording->refresh();

    expect($recording->download_status)->toBe(RecordingDownloadStatus::Skipped)
        ->and($recording->storage_path)->toBeNull();

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('refuses a response that is not audio at all', function (): void {
    Queue::fake();

    $recording = callWithRecording()->recordings()->first();

    Http::fake([
        'rec.exotel.test/*' => Http::response('{"error":"gone"}', 200, ['Content-Type' => 'application/json']),
    ]);

    expect($this->recordings->download($recording))->toBeFalse();
    expect($recording->fresh()->download_status)->toBe(RecordingDownloadStatus::Skipped);
});

/**
 * A deleted recording is never coming back, so it must stop being retried —
 * otherwise it occupies the queue forever, crowding out work that can succeed.
 */
it('gives up permanently when the provider no longer has the recording', function (): void {
    Queue::fake();

    $recording = callWithRecording()->recordings()->first();

    Http::fake(['rec.exotel.test/*' => Http::response('Not found', 404)]);

    expect($this->recordings->download($recording))->toBeFalse();

    $recording->refresh();

    expect($recording->download_status)->toBe(RecordingDownloadStatus::Skipped)
        ->and($recording->download_status->isSettled())->toBeTrue();
});

/**
 * A 500 is the provider having a bad minute. Throwing lets the job's backoff
 * run rather than discarding the audio.
 */
it('raises a retryable failure on a provider server error', function (): void {
    Queue::fake();

    $recording = callWithRecording()->recordings()->first();

    Http::fake(['rec.exotel.test/*' => Http::response('boom', 503)]);

    expect(fn () => $this->recordings->download($recording))
        ->toThrow(CallProviderException::class);

    $recording->refresh();

    expect($recording->download_status)->toBe(RecordingDownloadStatus::Failed)
        // Not settled: something should try this again.
        ->and($recording->download_status->isSettled())->toBeFalse();
});

it('skips downloads entirely when the feature is switched off', function (): void {
    Queue::fake();

    $recording = callWithRecording()->recordings()->first();

    config()->set('calls.recording.enabled', false);

    expect($this->recordings->download($recording))->toBeFalse();
    expect($recording->fresh()->download_status)->toBe(RecordingDownloadStatus::Skipped);
});

// ──────────────── The download job hands off ────────────────

it('queues transcription once the audio is stored', function (): void {
    // Faked before ingestion: this suite runs the queue synchronously, so
    // ingesting would otherwise fire the real download before the HTTP fake is
    // in place.
    Queue::fake();

    $recording = callWithRecording()->recordings()->first();

    Http::fake([
        'rec.exotel.test/*' => Http::response(fakeAudio(), 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    (new DownloadCallRecordingJob($recording->getKey()))->handle($this->recordings);

    Queue::assertPushed(TranscribeCallRecordingJob::class);
});

// ──────────────── Transcription ────────────────

/**
 * With no speech provider configured the pipeline must settle cleanly rather
 * than fail — a disabled feature should not fill the failed-jobs table.
 */
it('settles transcription as unavailable when no transcriber is configured', function (): void {
    Queue::fake();

    $call = callWithRecording();
    $recording = $call->recordings()->first();

    $recording->markDownloaded([
        'storage_disk' => 'local',
        'storage_path' => 'call-recordings/test.mp3',
    ]);

    Storage::disk('local')->put('call-recordings/test.mp3', fakeAudio());

    (new TranscribeCallRecordingJob($recording->getKey()))
        ->handle(app(CallTranscriptionServiceInterface::class));

    $recording->refresh();

    expect($recording->transcription_status)->toBe(TranscriptionStatus::NotAvailable)
        ->and($recording->transcription_status->isSettled())->toBeTrue()
        ->and(CallTranscription::count())->toBe(0);
});

// ──────────────── Retention ────────────────

/**
 * Retention deletes a conversation, not the evidence that a conversation
 * happened. The row survives so the call history stays honest and an audit can
 * tell deliberate deletion from data loss.
 */
it('purges the audio but keeps the record that it existed', function (): void {
    Queue::fake();

    $call = callWithRecording();
    $recording = $call->recordings()->first();

    Storage::disk('local')->put('call-recordings/purge-me.mp3', fakeAudio());

    $recording->markDownloaded([
        'storage_disk' => 'local',
        'storage_path' => 'call-recordings/purge-me.mp3',
    ]);

    $this->recordings->purge($recording);

    $recording->refresh();

    expect($recording->exists)->toBeTrue()
        ->and($recording->storage_status)->toBe(RecordingStorageStatus::Purged)
        ->and($recording->storage_path)->toBeNull()
        ->and($recording->purged_at)->not->toBeNull()
        // The provider URL is kept: it is the record of where the audio came from.
        ->and($recording->source_url)->toBe('https://rec.exotel.test/a.mp3');

    Storage::disk('local')->assertMissing('call-recordings/purge-me.mp3');
});

it('reports no retention deletions while no policy is configured', function (): void {
    config()->set('calls.retention.recording_days', null);
    config()->set('calls.retention.transcript_days', null);
    config()->set('calls.retention.payload_days', null);
    config()->set('calls.retention.webhook_event_days', null);

    $this->artisan('calls:prune')
        ->expectsOutputToContain('No retention periods are configured')
        ->assertSuccessful();
});
