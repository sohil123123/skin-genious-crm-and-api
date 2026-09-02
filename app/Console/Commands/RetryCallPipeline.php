<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\RecordingDownloadStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Jobs\Call\DownloadCallRecordingJob;
use App\Jobs\Call\TranscribeCallRecordingJob;
use App\Models\CallRecording;
use App\Services\Call\CallIngestionService;
use Illuminate\Console\Command;

/**
 * Sweeps up work that stalled.
 *
 * Three things reliably get stuck without a sweep like this, and none of them
 * announces itself:
 *
 *  - A recording whose URL was published before the file behind it existed.
 *    The first download 404s, the job exhausts its retries within the hour, and
 *    the audio is then available forever afterwards with nothing left to fetch
 *    it.
 *  - A transcription that failed while the speech API was down.
 *  - A call from someone who was a stranger on Monday and is a patient by
 *    Friday. Nothing re-examines it, so their history has a hole in it.
 *
 * Deliberately conservative: it only picks up work that is genuinely unfinished
 * and never touches anything a person decided by hand.
 */
class RetryCallPipeline extends Command
{
    protected $signature = 'calls:retry
        {--recordings : Re-queue failed recording downloads.}
        {--transcriptions : Re-queue failed transcriptions.}
        {--matching : Re-run customer matching over unmatched calls.}
        {--limit=200 : Maximum items per category.}';

    protected $description = 'Re-queue call recordings, transcriptions and customer matching that did not complete';

    public function handle(CallIngestionService $ingestion): int
    {
        // No flags means all three: the scheduled invocation should not have to
        // list them, and forgetting one is how a category silently stops being
        // swept.
        $all = ! $this->option('recordings')
            && ! $this->option('transcriptions')
            && ! $this->option('matching');

        $limit = (int) $this->option('limit');

        if ($all || $this->option('recordings')) {
            $this->retryDownloads($limit);
        }

        if ($all || $this->option('transcriptions')) {
            $this->retryTranscriptions($limit);
        }

        if ($all || $this->option('matching')) {
            $matched = $ingestion->rematchUnmatched(limit: $limit);
            $this->info(sprintf('Customer matching: %d call(s) newly attributed.', $matched));
        }

        return self::SUCCESS;
    }

    protected function retryDownloads(int $limit): void
    {
        $recordings = CallRecording::query()
            ->where('download_status', RecordingDownloadStatus::Failed->value)
            // A recording tried this many times is failing for a reason a retry
            // will not fix — usually a URL the provider has expired — and
            // retrying it forever crowds out work that can still succeed.
            ->where('download_attempts', '<', 5)
            ->where('storage_status', '!=', RecordingStorageStatus::Purged->value)
            ->limit($limit)
            ->get();

        $recordings->each(function (CallRecording $recording): void {
            $recording->forceFill([
                'download_status' => RecordingDownloadStatus::Pending,
            ])->save();

            DownloadCallRecordingJob::dispatch($recording->getKey());
        });

        $this->info(sprintf('Recordings: re-queued %d download(s).', $recordings->count()));
    }

    protected function retryTranscriptions(int $limit): void
    {
        $recordings = CallRecording::query()
            ->where('storage_status', RecordingStorageStatus::Stored->value)
            ->whereIn('transcription_status', [
                TranscriptionStatus::Failed->value,
                // Also picks up anything left mid-flight by a worker that was
                // restarted, which would otherwise sit on "processing" forever.
                TranscriptionStatus::Processing->value,
            ])
            ->where('transcription_attempts', '<', 5)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->limit($limit)
            ->get();

        $recordings->each(function (CallRecording $recording): void {
            $recording->forceFill([
                'transcription_status' => TranscriptionStatus::Pending,
            ])->save();

            TranscribeCallRecordingJob::dispatch($recording->getKey());
        });

        $this->info(sprintf('Transcriptions: re-queued %d.', $recordings->count()));
    }
}
