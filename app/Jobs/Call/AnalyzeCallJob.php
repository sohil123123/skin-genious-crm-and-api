<?php

declare(strict_types=1);

namespace App\Jobs\Call;

use App\Enums\Call\CallAnalysisStatus;
use App\Models\Call;
use App\Models\CallAnalysis;
use App\Models\Setting;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs AI analysis over a transcribed call.
 *
 * Shipped as a working pipeline with no analyser behind it yet, on purpose. The
 * dispatch points, the status transitions, the versioned call_analyses row and
 * the CRM screens that read it all exist and are exercised now, so switching on
 * analysis later is a setting and one service class — not a change to the call
 * schema, the ingestion path or anything the clinic already depends on.
 *
 * Until then the job settles cleanly as NotAvailable rather than failing, so a
 * disabled feature does not fill the failed-jobs table.
 */
class AnalyzeCallJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public int $callId,
    ) {
        $this->tries = (int) config('calls.queue.tries', 5);
        $this->timeout = (int) config('calls.queue.timeout', 180);

        $this->onQueue((string) config('calls.queue.analysis', 'call-recordings'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'call-analysis-' . $this->callId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('calls.queue.backoff', [30, 120, 300, 900, 1800]);
    }

    public function handle(CallAnalysisServiceInterface $analyser): void
    {
        $call = Call::with('currentTranscription')->find($this->callId);

        if ($call === null || $call->analysis_status?->isSettled()) {
            return;
        }

        if (! $analyser->isEnabled()) {
            // Not a failure. Nothing is wrong; the feature is off, and the call
            // should stop being asked about until it is turned on.
            $call->forceFill(['analysis_status' => CallAnalysisStatus::NotAvailable])->saveQuietly();

            return;
        }

        $transcription = $call->currentTranscription;

        if ($transcription === null || ! $transcription->hasText()) {
            // Nothing to analyse. Settled for the call as it stands; a later
            // transcription dispatches this job again.
            $call->forceFill(['analysis_status' => CallAnalysisStatus::NotAvailable])->saveQuietly();

            return;
        }

        $call->forceFill(['analysis_status' => CallAnalysisStatus::Processing])->saveQuietly();

        $startedAt = now();

        try {
            $result = $analyser->analyse($call, $transcription);
        } catch (Throwable $exception) {
            $call->forceFill([
                'analysis_status' => CallAnalysisStatus::Failed,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->saveQuietly();

            throw $exception;
        }

        if ($result === null || ! $result->isUseful()) {
            // A model with nothing to say — usually a transcript of hold music.
            // Storing it would put an empty AI panel in front of staff and
            // teach them the feature does not work.
            $call->forceFill(['analysis_status' => CallAnalysisStatus::NotAvailable])->saveQuietly();

            return;
        }

        DB::transaction(function () use ($call, $transcription, $result, $analyser, $startedAt): void {
            $analysis = CallAnalysis::create(array_merge($result->toAttributes(), [
                'call_id' => $call->getKey(),
                'call_transcription_id' => $transcription->getKey(),
                'analysis_version' => $analyser->version(),
                'provider' => $analyser->name(),
                'status' => CallAnalysisStatus::Completed,
                'is_current' => true,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ]));

            // Demotes any earlier analysis, so exactly one is current even when
            // two versions finish close together.
            $analysis->makeCurrent();

            // Only ai_summary and the status. The call's own crm_outcome,
            // crm_note and follow_up_* belong to whoever typed them, and a
            // model must never overwrite a staff member's judgement — that
            // separation is what the whole schema is built around.
            $call->forceFill([
                'analysis_status' => CallAnalysisStatus::Completed,
                'ai_summary' => $result->summary,
                'last_error' => null,
            ])->saveQuietly();
        });

        Log::channel('calls')->info('Call analysed.', [
            'call_id' => $call->getKey(),
            'version' => $analyser->version(),
            'tokens' => ($result->inputTokens ?? 0) + ($result->outputTokens ?? 0),
        ]);
    }

    protected function isEnabled(): bool
    {
        return (bool) Setting::getValue('call_analysis_enabled', config('calls.analysis.enabled', false));
    }

    public function failed(?Throwable $exception): void
    {
        $call = Call::find($this->callId);

        if ($call === null || $call->analysis_status?->isSettled()) {
            return;
        }

        $call->forceFill([
            'analysis_status' => CallAnalysisStatus::Failed,
            'last_error' => mb_substr(
                $exception?->getMessage() ?? 'Analysis failed without reporting a reason.',
                0,
                1000,
            ),
        ])->saveQuietly();
    }
}
