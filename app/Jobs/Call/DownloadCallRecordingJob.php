<?php

declare(strict_types=1);

namespace App\Jobs\Call;

use App\Enums\Call\RecordingDownloadStatus;
use App\Models\CallRecording;
use App\Services\Call\CallRecordingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches one call recording onto the clinic's own storage.
 *
 * On its own queue, deliberately. A 40 MB download must never sit in front of
 * the webhook processing that decides which patient a call belongs to — audio
 * can wait ten minutes, but a call appearing in the wrong place cannot.
 *
 * The first attempt is delayed rather than immediate: providers publish a
 * recording URL before the file behind it is finalised, so downloading the
 * instant the webhook lands is the reliable way to fetch a 404.
 */
class DownloadCallRecordingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public int $recordingId,
    ) {
        $this->tries = (int) config('calls.queue.tries', 5);
        // Generous: a long consultation on a slow line takes time to transfer,
        // and a download killed halfway is a download that has to start again.
        $this->timeout = max(300, (int) config('calls.recording.timeout', 120) * 2);

        $this->onQueue((string) config('calls.queue.recordings', 'call-recordings'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }

        $this->delay(now()->addSeconds(30));
    }

    public function uniqueId(): string
    {
        return 'call-recording-' . $this->recordingId;
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

    public function handle(CallRecordingService $recordings): void
    {
        $recording = CallRecording::with('call')->find($this->recordingId);

        if ($recording === null) {
            return;
        }

        // Settled means downloaded or permanently skipped. Either way there is
        // nothing left to do, and a queued duplicate must not restart it.
        if ($recording->download_status->isSettled()) {
            return;
        }

        if (! $recordings->download($recording)) {
            // A permanent failure. The recording has already recorded why, and
            // no exception is thrown because there is nothing to retry.
            return;
        }

        TranscribeCallRecordingJob::dispatch($recording->getKey());
    }

    /**
     * Leave a clear final state once the retries run out.
     *
     * Without this a recording whose worker was restarted mid-download would
     * sit on "downloading" forever, invisible to both the retry sweep and the
     * health screen.
     */
    public function failed(?Throwable $exception): void
    {
        $recording = CallRecording::find($this->recordingId);

        if ($recording === null || $recording->download_status->isSettled()) {
            return;
        }

        $recording->forceFill([
            'download_status' => RecordingDownloadStatus::Failed,
            'error_message' => mb_substr(
                $exception?->getMessage() ?? 'The download failed without reporting a reason.',
                0,
                1000,
            ),
        ])->save();

        Log::channel('calls')->error('Call recording download failed permanently.', [
            'recording_id' => $this->recordingId,
            'attempts' => $recording->download_attempts,
        ]);
    }
}
