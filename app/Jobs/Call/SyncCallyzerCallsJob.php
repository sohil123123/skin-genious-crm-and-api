<?php

declare(strict_types=1);

namespace App\Jobs\Call;

use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSyncStatus;
use App\Models\CallSyncRun;
use App\Services\Call\CallProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls Callyzer call history on a schedule or on demand.
 *
 * Uniqueness is enforced twice over, and both are needed. ShouldBeUnique stops
 * two dispatches of the same job overlapping; the CallSyncRun check stops a
 * manual "Sync Now" running alongside the scheduled job. Either overlap would
 * double the request rate against an API that permits one call every two
 * seconds, turning a working sync into a wall of 429s.
 *
 * The window is deliberately generous. Incremental sync resumes from the last
 * completed run's cursor, with a lookback on top of it, because a call edited
 * in the Callyzer app days after it happened comes back with a new modified_at
 * and has to be picked up again.
 */
class SyncCallyzerCallsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public array $filters = [],
        public string $trigger = 'scheduled',
        public ?int $triggeredBy = null,
    ) {
        $this->onQueue((string) config('calls.queue.sync', 'calls'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'callyzer-sync';
    }

    public function uniqueFor(): int
    {
        return 1800;
    }

    /**
     * Retries are off on purpose.
     *
     * A failed sync must not be replayed automatically: the usual causes are a
     * revoked token or a rate limit, and hammering either makes it worse. The
     * next scheduled run covers the same window anyway, because a failed run
     * never advances the cursor.
     */
    public function handle(CallProviderManager $providers): void
    {
        $adapter = $providers->syncable(CallProvider::Callyzer);

        if ($adapter === null || ! $adapter->isSyncEnabled()) {
            return;
        }

        if (CallSyncRun::isRunning(CallProvider::Callyzer)) {
            Log::channel('calls')->info('Callyzer sync skipped: another run is already in progress.');

            return;
        }

        [$from, $to] = $this->window();

        $run = CallSyncRun::create([
            'provider' => CallProvider::Callyzer->value,
            'trigger' => $this->trigger,
            'triggered_by' => $this->triggeredBy,
            'status' => CallSyncStatus::Running->value,
            'window_from' => $from,
            'window_to' => $to,
            'parameters' => $this->filters ?: null,
            'started_at' => now(),
        ]);

        try {
            $adapter->syncCalls($run, $from, $to, $this->filters);
        } catch (Throwable $exception) {
            $run->finish(CallSyncStatus::Failed, $exception->getMessage());

            Log::channel('calls')->error('Callyzer sync failed.', [
                'run_id' => $run->getKey(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * The period to ask Callyzer about.
     *
     * An explicit range wins. Otherwise the window starts at the last completed
     * run's cursor minus the configured lookback — the lookback is what catches
     * calls whose notes or outcomes were edited after the fact.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function window(): array
    {
        $to = filled($this->to) ? Carbon::parse($this->to) : now();

        if (filled($this->from)) {
            return [Carbon::parse($this->from), $to];
        }

        $lookback = (int) config('calls.callyzer.sync.lookback_hours', 48);
        $cursor = CallSyncRun::lastCursor(CallProvider::Callyzer);

        $from = $cursor !== null
            ? $cursor->copy()->subHours($lookback)
            : now()->subHours($lookback);

        return [$from, $to];
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('calls')->error('Callyzer sync job failed permanently.', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
