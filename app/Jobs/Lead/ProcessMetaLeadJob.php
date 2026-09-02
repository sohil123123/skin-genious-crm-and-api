<?php

declare(strict_types=1);

namespace App\Jobs\Lead;

use App\Models\MetaLeadSyncLog;
use App\Services\Meta\MetaLeadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches and files one Meta lead, off the webhook's request cycle.
 *
 * Meta expects a webhook response within seconds and redelivers the
 * notification when it does not get one — so doing the Graph API round trip
 * inline would not merely be slow, it would actively manufacture duplicates.
 *
 * ShouldBeUnique is the fourth duplicate defence: if Meta redelivers while an
 * earlier attempt is still queued, the second dispatch is dropped before it
 * runs rather than racing the first.
 */
class ProcessMetaLeadJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public int $syncLogId,
    ) {
        $this->timeout = (int) config('meta.queue.timeout', 120);
        $this->tries = (int) config('meta.queue.tries', 5);

        $this->onQueue((string) config('meta.queue.name', 'meta-leads'));

        if ($connection = config('meta.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'meta-lead-' . $this->syncLogId;
    }

    /**
     * How long the uniqueness lock survives if the worker dies mid-job.
     */
    public function uniqueFor(): int
    {
        return (int) config('meta.queue.timeout', 120) * 2;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('meta.queue.backoff', [30, 120, 300, 900]);
    }

    public function handle(MetaLeadService $leadService): void
    {
        $syncLog = MetaLeadSyncLog::with('metaPage')->find($this->syncLogId);

        if ($syncLog === null) {
            Log::channel('meta_leads')->warning('Meta sync log no longer exists.', [
                'sync_log_id' => $this->syncLogId,
            ]);

            return;
        }

        // Guards against a redelivery that slipped past the unique dispatch
        // lock — for instance one queued after the first had already finished.
        if ($syncLog->isSettled()) {
            return;
        }

        $leadService->process($syncLog);
    }

    /**
     * Record the final failure, once the retries are exhausted.
     *
     * MetaLeadService already writes a message for each attempt; this exists so
     * a job that dies for a reason the service never saw — a timeout, a worker
     * restart — does not leave the record stuck on "processing" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $syncLog = MetaLeadSyncLog::find($this->syncLogId);

        if ($syncLog === null || $syncLog->isSettled()) {
            return;
        }

        $syncLog->markFailed($exception?->getMessage() ?? 'The job failed without reporting a reason.');

        Log::channel('meta_leads')->error('Meta lead processing failed permanently.', [
            'sync_log_id' => $this->syncLogId,
            'leadgen_id' => $syncLog->leadgen_id,
            'attempts' => $syncLog->attempts,
        ]);
    }
}
