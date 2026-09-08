<?php

declare(strict_types=1);

namespace App\Jobs\Call;

use App\DTOs\Call\TranscriptSegment;
use App\Enums\Call\TranscriptionStatus;
use App\Models\CallRecording;
use App\Models\CallTranscription;
use App\Models\CallTranscriptSegment;
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns one stored recording into text.
 *
 * Never runs inside a webhook or a sync: transcription is minutes of work per
 * call, and a provider waiting on it would time out and retry the delivery.
 *
 * The distinction the whole job turns on is what the transcriber returning null
 * means versus throwing. Null is permanent — audio too short, a format the
 * service will not take, transcription switched off — and settles the recording
 * as NotAvailable so nothing tries again. An exception is transient and lets
 * the backoff run. Collapsing the two would give either a permanent retry loop
 * or a silently dropped conversation.
 */
class TranscribeCallRecordingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public int $recordingId,
    ) {
        $this->tries = (int) config('calls.queue.tries', 5);
        $this->timeout = (int) config('calls.transcription.timeout', 600) + 60;

        $this->onQueue((string) config('calls.queue.transcription', 'call-recordings'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'call-transcription-' . $this->recordingId;
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

    public function handle(CallTranscriptionServiceInterface $transcriber): void
    {
        $recording = CallRecording::with('call')->find($this->recordingId);

        if ($recording === null || $recording->transcription_status->isSettled()) {
            return;
        }

        if (! $transcriber->isEnabled()) {
            // Not a failure. Nothing is wrong; the feature is off, and the
            // recording should stop being asked about until it is turned on.
            $recording->forceFill([
                'transcription_status' => TranscriptionStatus::NotAvailable,
            ])->save();

            $recording->call?->refreshPipelineFlags();

            return;
        }

        if (! $recording->fileExists()) {
            $recording->forceFill([
                'transcription_status' => TranscriptionStatus::NotAvailable,
                'error_message' => 'The stored audio is missing, so it cannot be transcribed.',
            ])->save();

            $recording->call?->refreshPipelineFlags();

            return;
        }

        $recording->forceFill([
            'transcription_status' => TranscriptionStatus::Processing,
            'transcription_attempts' => $recording->transcription_attempts + 1,
            'transcription_started_at' => now(),
        ])->save();

        try {
            $result = $transcriber->transcribe($recording);
        } catch (Throwable $exception) {
            $recording->forceFill([
                'transcription_status' => TranscriptionStatus::Failed,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            $recording->call?->refreshPipelineFlags();

            throw $exception;
        }

        if ($result === null || ! $result->isMeaningful()) {
            $recording->forceFill([
                'transcription_status' => TranscriptionStatus::NotAvailable,
                'transcription_completed_at' => now(),
            ])->save();

            $recording->call?->refreshPipelineFlags();

            return;
        }

        DB::transaction(function () use ($recording, $result, $transcriber): void {
            $transcription = CallTranscription::create([
                'call_id' => $recording->call_id,
                'call_recording_id' => $recording->getKey(),
                'provider' => $transcriber->name(),
                'model' => $result->model,
                'version' => (string) config('calls.transcription.driver', 'null'),
                'language' => $result->language,
                'language_code' => $result->languageCode,
                'transcript' => $result->transcript,
                // The provider response, plus anything the CRM noticed about it.
                // Kept together so the raw payload stays recoverable while the
                // reader is still told the transcript is degraded.
                'transcript_json' => array_filter([
                    'provider' => $result->raw ?: null,
                    '_crm_warnings' => $result->warnings ?: null,
                ]) ?: null,
                'confidence' => $result->confidence,
                'duration_seconds' => $result->durationSeconds ?? $recording->duration_seconds,
                'word_count' => $result->wordCount(),
                'status' => TranscriptionStatus::Completed,
                'is_current' => true,
                'started_at' => $recording->transcription_started_at,
                'completed_at' => now(),
                'purge_after' => $this->transcriptPurgeDate(),
            ]);

            // Demotes any earlier transcript for the same call, so exactly one
            // is current even when two recordings finish at once.
            $transcription->makeCurrent();

            $this->storeSegments($transcription, $result->segments);

            $recording->forceFill([
                'transcription_status' => TranscriptionStatus::Completed,
                'transcription_completed_at' => now(),
                'error_message' => null,
            ])->save();
        });

        $recording->call?->refreshPipelineFlags();

        Log::channel('calls')->info('Call recording transcribed.', [
            'recording_id' => $recording->getKey(),
            'call_id' => $recording->call_id,
            'words' => $result->wordCount(),
        ]);

        // Analysis reads the transcript, so it can only start now. Dispatched
        // rather than run inline so a slow model does not hold a transcription
        // worker.
        if ($recording->call_id !== null) {
            AnalyzeCallJob::dispatch($recording->call_id);
        }
    }

    /**
     * @param  array<int, TranscriptSegment>  $segments
     */
    protected function storeSegments(CallTranscription $transcription, array $segments): void
    {
        if ($segments === []) {
            return;
        }

        $rows = [];
        $sequence = 0;

        foreach ($segments as $segment) {
            if (! $segment instanceof TranscriptSegment) {
                continue;
            }

            $rows[] = [
                'call_transcription_id' => $transcription->getKey(),
                'call_id' => $transcription->call_id,
                'sequence' => $sequence++,
                'speaker' => $segment->speaker,
                'speaker_type' => $segment->speakerType->value,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
                'text' => $segment->text,
                'confidence' => $segment->confidence,
                'metadata' => $segment->metadata !== [] ? json_encode($segment->metadata) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Chunked: a long consultation produces hundreds of segments, and a
        // single insert with that many bound parameters exceeds the driver's
        // placeholder limit.
        foreach (array_chunk($rows, 200) as $chunk) {
            CallTranscriptSegment::insert($chunk);
        }
    }

    protected function transcriptPurgeDate(): ?Carbon
    {
        $days = config('calls.retention.transcript_days');

        return filled($days) ? now()->addDays((int) $days) : null;
    }

    public function failed(?Throwable $exception): void
    {
        $recording = CallRecording::find($this->recordingId);

        if ($recording === null || $recording->transcription_status->isSettled()) {
            return;
        }

        $recording->forceFill([
            'transcription_status' => TranscriptionStatus::Failed,
            'error_message' => mb_substr(
                $exception?->getMessage() ?? 'Transcription failed without reporting a reason.',
                0,
                1000,
            ),
        ])->save();
    }
}
