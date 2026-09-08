<?php

declare(strict_types=1);

use App\Enums\Call\CallAnalysisStatus;
use App\Models\{Call, CallTranscription, Setting};
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The sweep is what makes a transient failure temporary rather than permanent —
 * nothing else revisits a settled row. So it has to be controllable without a
 * deploy, and it must not quietly stop sweeping a category.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Queue::fake();

    Setting::setValue('call_analysis_driver', 'openai');
    Setting::setValue('call_analysis_enabled', '1');
    Setting::setValue('call_transcription_api_key', 'sk-test');
    Setting::flushRuntimeCache();
    app()->forgetInstance(CallAnalysisServiceInterface::class);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'retry-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'transcription_status' => 'completed',
        'analysis_status' => 'failed',
        'started_at' => now()->subDay(),
    ]);

    CallTranscription::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'openai',
        'transcript' => 'नमस्ते, मैं HydraFacial के बारे में पूछना चाहती थी. कितना खर्च आएगा? पैकेज पैंतालीस हज़ार का है.',
        'status' => 'completed',
        'is_current' => true,
    ]);

    // Older than the stall window, so the sweep considers it abandoned.
    $this->call->forceFill(['updated_at' => now()->subHours(2)])->saveQuietly();
});

it('re-queues a failed analysis', function (): void {
    $this->artisan('calls:retry')->expectsOutputToContain('Analyses: re-queued 1')->assertSuccessful();

    expect($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::Pending);
});

it('leaves the stage alone when it is switched off', function (): void {
    Setting::setValue('call_retry_analyses_enabled', '0');
    Setting::flushRuntimeCache();

    $this->artisan('calls:retry')
        ->expectsOutputToContain('Analyses: switched off in Call Settings')
        ->assertSuccessful();

    expect($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::Failed);
});

/**
 * A flag is a person asking. Refusing because a toggle is off would be the
 * command arguing with the operator who typed it.
 */
it('still runs a stage asked for explicitly', function (): void {
    Setting::setValue('call_retry_analyses_enabled', '0');
    Setting::flushRuntimeCache();

    $this->artisan('calls:retry', ['--analyses' => true])
        ->expectsOutputToContain('Analyses: re-queued 1')
        ->assertSuccessful();
});

/**
 * "Not available" is the analyser saying this transcript can never be analysed,
 * usually because it is below the word floor. Sweeping it would send the same
 * refusal round for ever at the cost of a model call each time.
 */
it('never retries a call settled as not available', function (): void {
    $this->call->forceFill([
        'analysis_status' => 'not_available',
        'updated_at' => now()->subHours(2),
    ])->saveQuietly();

    $this->artisan('calls:retry')->expectsOutputToContain('Analyses: re-queued 0')->assertSuccessful();
});

/**
 * A worker may still be holding it. Sweeping something touched moments ago
 * would fight the job that is running.
 */
it('waits out the stall window before touching a processing call', function (): void {
    $this->call->forceFill([
        'analysis_status' => 'processing',
        'updated_at' => now(),
    ])->saveQuietly();

    $this->artisan('calls:retry')->expectsOutputToContain('Analyses: re-queued 0')->assertSuccessful();

    Setting::setValue('call_retry_stale_minutes', '1');
    Setting::flushRuntimeCache();

    $this->call->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

    $this->artisan('calls:retry')->expectsOutputToContain('Analyses: re-queued 1')->assertSuccessful();
});

it('reads the attempt ceiling from settings', function (): void {
    Setting::setValue('call_retry_max_attempts', '9');
    Setting::flushRuntimeCache();

    // Proven through the recordings stage, which is the one that counts attempts.
    $this->artisan('calls:retry', ['--recordings' => true])->assertSuccessful();

    expect((int) Setting::getConfigured('call_retry_max_attempts', 5))->toBe(9);
});
