<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\CallProvider;
use App\Jobs\Call\SyncCallyzerCallsJob;
use App\Models\CallSyncRun;
use App\Services\Call\CallProviderManager;
use Illuminate\Console\Command;

/**
 * Pulls Callyzer call history.
 *
 * Scheduled hourly, and available by hand for backfilling a period the webhook
 * missed. Both paths write through the same ingestion service, so a backfill
 * over a range that already synced updates those calls rather than duplicating
 * them — which is what makes running this safely repeatable.
 */
class SyncCallyzerCalls extends Command
{
    protected $signature = 'calls:sync-callyzer
        {--from= : Start of the window (Y-m-d). Defaults to the last completed sync, minus the configured lookback.}
        {--to= : End of the window (Y-m-d). Defaults to now.}
        {--employee=* : Restrict to specific employee numbers.}
        {--sync : Run inline instead of queueing, for a one-off backfill.}';

    protected $description = 'Pull Callyzer call history into the unified call records';

    public function handle(CallProviderManager $providers): int
    {
        $adapter = $providers->syncable(CallProvider::Callyzer);

        if ($adapter === null || ! $adapter->isSyncEnabled()) {
            $this->warn('Callyzer sync is switched off, or no API token is configured.');
            $this->line('Turn it on under Calls → Call Settings.');

            return self::SUCCESS;
        }

        if (CallSyncRun::isRunning(CallProvider::Callyzer)) {
            // Two runs at once would double the request rate against an API
            // that permits one call every two seconds.
            $this->warn('A Callyzer sync is already running. Skipping.');

            return self::SUCCESS;
        }

        $filters = [];

        if ($employees = $this->option('employee')) {
            $filters['employee_numbers'] = $employees;
        }

        $job = new SyncCallyzerCallsJob(
            from: $this->option('from') ?: null,
            to: $this->option('to') ?: null,
            filters: $filters,
            trigger: $this->option('from') ? 'backfill' : 'scheduled',
        );

        // Inline is offered for backfills so an operator can watch it finish
        // and see the failure immediately if it does not.
        if ($this->option('sync')) {
            $this->info('Syncing Callyzer calls...');

            dispatch_sync($job);

            $run = CallSyncRun::query()->ofProvider(CallProvider::Callyzer)->latest('id')->first();

            if ($run !== null) {
                $this->table(
                    ['Status', 'Received', 'Created', 'Updated', 'Skipped', 'Failed'],
                    [[
                        $run->status?->getLabel(),
                        $run->records_received,
                        $run->calls_created,
                        $run->calls_updated,
                        $run->calls_skipped,
                        $run->calls_failed,
                    ]],
                );

                if ($run->last_error) {
                    $this->error($run->last_error);
                }
            }

            return self::SUCCESS;
        }

        dispatch($job);

        $this->info('Callyzer sync queued. Progress appears under Calls → Integration Health.');

        return self::SUCCESS;
    }
}
