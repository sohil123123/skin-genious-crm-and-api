<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\CallAnalysisStatus;
use App\Enums\Call\RecordingDownloadStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Jobs\Call\AnalyzeCallJob;
use App\Jobs\Call\DownloadCallRecordingJob;
use App\Jobs\Call\TranscribeCallRecordingJob;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\Setting;
use App\Services\Call\CallIngestionService;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
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
        {--analyses : Re-queue failed AI analyses.}
        {--matching : Re-run customer matching over unmatched calls.}
        {--limit=200 : Maximum items per category.}';

    protected $description = 'Re-queue call recordings, transcriptions, analyses and customer matching that did not complete';

    public function handle(CallIngestionService $ingestion): int
    {
        // No flags means every category the settings allow: the scheduled
        // invocation should not have to list them, and forgetting one is how a
        // category silently stops being swept.
        //
        // A flag is a person asking, and overrides the setting. Someone typing
        // --analyses has decided to retry analyses today; refusing because a
        // toggle is off would be the command arguing with them.
        $chosen = array_filter([
            'recordings' => (bool) $this->option('recordings'),
            'transcriptions' => (bool) $this->option('transcriptions'),
            'analyses' => (bool) $this->option('analyses'),
            'matching' => (bool) $this->option('matching'),
        ]);

        $scheduled = $chosen === [];
        $limit = (int) $this->option('limit');

        foreach (['recordings', 'transcriptions', 'analyses', 'matching'] as $stage) {
            if (! $scheduled && ! isset($chosen[$stage])) {
                continue;
            }

            if ($scheduled && ! $this->stageEnabled($stage)) {
                $this->line(sprintf('  %s: switched off in Call Settings.', ucfirst($stage)));

                continue;
            }

            match ($stage) {
                'recordings' => $this->retryDownloads($limit),
                'transcriptions' => $this->retryTranscriptions($limit),
                'analyses' => $this->retryAnalyses($limit),
                'matching' => $this->info(sprintf(
                    'Customer matching: %d call(s) newly attributed.',
                    $ingestion->rematchUnmatched(limit: $limit),
                )),
            };
        }

        return self::SUCCESS;
    }

    /**
     * Whether the scheduled sweep should touch this stage.
     */
    protected function stageEnabled(string $stage): bool
    {
        return (bool) Setting::getConfigured(
            'call_retry_' . $stage . '_enabled',
            config('calls.retry.' . $stage, true),
        );
    }

    /**
     * The ceiling on how many times anything is retried.
     *
     * Past this, a retry is not what fixes it — usually a provider URL that has
     * expired — and continuing crowds out work that can still succeed.
     */
    protected function maxAttempts(): int
    {
        return max(1, (int) Setting::getConfigured(
            'call_retry_max_attempts',
            config('calls.retry.max_attempts', 5),
        ));
    }

    /**
     * How long something must sit untouched before it counts as stalled.
     */
    protected function staleMinutes(): int
    {
        return max(1, (int) Setting::getConfigured(
            'call_retry_stale_minutes',
            config('calls.retry.stale_minutes', 30),
        ));
    }

    protected function retryDownloads(int $limit): void
    {
        $recordings = CallRecording::query()
            ->where('download_status', RecordingDownloadStatus::Failed->value)
            // A recording tried this many times is failing for a reason a retry
            // will not fix — usually a URL the provider has expired — and
            // retrying it forever crowds out work that can still succeed.
            ->where('download_attempts', '<', $this->maxAttempts())
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

    /**
     * Re-queue analyses that failed or stalled.
     *
     * Deliberately narrower than the other two. Failed is retried, and so is a
     * Processing that has sat still long enough to mean a worker died holding
     * it — but NotAvailable is left alone: that is the analyser saying this
     * transcript can never be analysed, usually because it is below the word
     * floor, and sweeping it up would send the same refusal round for ever at
     * the cost of a model call each time.
     */
    protected function retryAnalyses(int $limit): void
    {
        if (! app(CallAnalysisServiceInterface::class)->isEnabled()) {
            $this->line('  Analyses: no analyser configured.');

            return;
        }

        $calls = Call::query()
            ->where('transcription_status', TranscriptionStatus::Completed->value)
            ->whereIn('analysis_status', [
                CallAnalysisStatus::Failed->value,
                CallAnalysisStatus::Processing->value,
            ])
            ->where('updated_at', '<', now()->subMinutes($this->staleMinutes()))
            // Only calls that actually have something to read. Without this a
            // transcript deleted after a failure is retried until the ceiling.
            ->whereHas('currentTranscription', fn ($query) => $query->whereNotNull('transcript'))
            ->limit($limit)
            ->get();

        $calls->each(function (Call $call): void {
            $call->forceFill([
                'analysis_status' => CallAnalysisStatus::Pending,
                'last_error' => null,
            ])->saveQuietly();

            AnalyzeCallJob::dispatch($call->getKey());
        });

        $this->info(sprintf('Analyses: re-queued %d.', $calls->count()));
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
            ->where('transcription_attempts', '<', $this->maxAttempts())
            ->where('updated_at', '<', now()->subMinutes($this->staleMinutes()))
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
