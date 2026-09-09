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
 * Pulls Exotel call history on demand.
 *
 * Exotel pushes every call as it happens, so this is a backstop rather than the
 * primary path: it recovers calls whose Passthru never arrived and recordings
 * that Exotel had not finalised when the last webhook fired.
 *
 * Uniqueness is enforced twice over, and both are needed. ShouldBeUnique stops
 * two dispatches of this job overlapping; the CallSyncRun check stops a manual
 * press running alongside anything else already pulling from Exotel. Two
 * concurrent runs would ingest the same records twice over and race each other
 * on the counters.
 */
class SyncExotelCallsJob implements ShouldBeUnique, ShouldQueue
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
        public string $trigger = 'manual',
        public ?int $triggeredBy = null,
    ) {
        $this->onQueue((string) config('calls.queue.sync', 'calls'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'exotel-sync';
    }

    public function uniqueFor(): int
    {
        return 1800;
    }

    /**
     * Retries are off on purpose.
     *
     * A failed sync must not be replayed automatically: the usual causes are
     * revoked credentials or a rate limit, and hammering either makes it worse.
     * A failed run never advances the cursor, so the next one covers the same
     * window anyway.
     */
    public function handle(CallProviderManager $providers): void
    {
        $adapter = $providers->syncable(CallProvider::Exotel);

        if ($adapter === null || ! $adapter->isSyncEnabled()) {
            return;
        }

        if (CallSyncRun::isRunning(CallProvider::Exotel)) {
            Log::channel('calls')->info('Exotel sync skipped: another run is already in progress.');

            return;
        }

        [$from, $to] = $this->window();

        $run = CallSyncRun::create([
            'provider' => CallProvider::Exotel->value,
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

            Log::channel('calls')->error('Exotel sync failed.', [
                'run_id' => $run->getKey(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * The period to ask Exotel about.
     *
     * An explicit range wins. Otherwise the window starts at the last completed
     * run's cursor minus the configured lookback, which is what re-collects a
     * recording that was not published the first time the call was fetched.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function window(): array
    {
        $to = filled($this->to) ? Carbon::parse($this->to) : now();

        if (filled($this->from)) {
            return [Carbon::parse($this->from), $to];
        }

        $lookback = (int) config('calls.exotel.sync.lookback_hours', 24);
        $cursor = CallSyncRun::lastCursor(CallProvider::Exotel);

        $from = $cursor !== null
            ? $cursor->copy()->subHours($lookback)
            : now()->subHours($lookback);

        return [$from, $to];
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('calls')->error('Exotel sync job failed permanently.', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
