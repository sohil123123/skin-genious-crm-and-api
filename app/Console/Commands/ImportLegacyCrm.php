<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LegacyImport\LegacyCrmImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Merges the old Lumen CRM database into this CRM.
 *
 * Dry run is the DEFAULT: nothing is written unless --commit is passed.
 * Runbook: docs/legacy-crm-merge.md
 */
class ImportLegacyCrm extends Command
{
    protected $signature = 'legacy:import
        {--step=* : Steps to run (default: all, in order): clinics, staff, clients, referrals, products, stock, invoices, appointments, loyalty}
        {--commit : Actually write. Without it the whole run is rolled back (dry run).}
        {--force : Skip the confirmation prompts (for non-interactive runs)}
        {--verify : Only print the old-vs-new comparison report (read-only).}
        {--only-estate=* : Old estate ids (clinics) to import; default config legacy_import.only_estates = [3] (AM Dermatology)}
        {--all-estates : Import every old clinic (ignores only_estates)}
        {--clinic-map= : Old estate_id to new clinic id, e.g. 3:1,9:2}
        {--create-missing-clinics : Create (inactive) clinics for old clinics that have data but no match}
        {--activate-staff : Create new staff users active (default: inactive, no new logins)}
        {--fallback-user= : Existing user id used as created_by / therapist when the old one is unknown (default: first super_admin)}
        {--include-deleted-sales : Import sales deleted in the old CRM as cancelled invoices (default: config legacy_import.include_deleted_sales)}
        {--include-future-appointments : Import appointments dated today or later as confirmed (default: config legacy_import.include_future_appointments)}
        {--include-deleted-product-stock : Also add opening stock for products deleted in the old CRM}
        {--renumber-existing-invoices : With invoice_number_order=same_as_old: move the existing INV-MH (same state) invoices after the old ones so old bills keep their numbers (changes issued numbers!)}
        {--loyalty-source=client : client = clients.points_balance (the balance the old CRM showed and honoured), ledger = only points whose old expiry date has not passed}
        {--retry-skipped : Retry rows skipped by an earlier run}
        {--chunk= : Rows per transaction (default from config)}';

    protected $description = 'Import the old CRM (clinic-management) database into this CRM — dry run unless --commit';

    public function handle(): int
    {
        $steps = $this->option('step') ?: LegacyCrmImporter::STEPS;
        $unknown = array_diff($steps, LegacyCrmImporter::STEPS);
        if ($unknown) {
            $this->error('Unknown step(s): ' . implode(', ', $unknown) . '. Valid: ' . implode(', ', LegacyCrmImporter::STEPS));

            return self::FAILURE;
        }
        // Always run in dependency order regardless of how the options were typed.
        $steps = array_values(array_intersect(LegacyCrmImporter::STEPS, $steps));

        if (! in_array($this->option('loyalty-source'), ['ledger', 'client'], true)) {
            $this->error('--loyalty-source must be "ledger" or "client".');

            return self::FAILURE;
        }

        $onlyEstates = $this->option('all-estates')
            ? null
            : (array_map('intval', $this->option('only-estate')) ?: config('legacy_import.only_estates') ?: null);

        $commit = (bool) $this->option('commit');
        $importer = new LegacyCrmImporter([
            'dry_run' => ! $commit,
            'only_estates' => $onlyEstates,
            'clinic_map' => $this->parseClinicMap((string) $this->option('clinic-map')),
            'create_missing_clinics' => (bool) $this->option('create-missing-clinics'),
            'activate_staff' => (bool) $this->option('activate-staff'),
            'include_deleted_sales' => $this->option('include-deleted-sales') || config('legacy_import.include_deleted_sales', false),
            'include_future_appointments' => $this->option('include-future-appointments') || config('legacy_import.include_future_appointments', false),
            'include_deleted_product_stock' => (bool) $this->option('include-deleted-product-stock'),
            'renumber_existing_invoices' => $this->option('renumber-existing-invoices') || config('legacy_import.renumber_existing_invoices', false),
            'fallback_user_id' => $this->option('fallback-user') ? (int) $this->option('fallback-user') : null,
            'loyalty_source' => (string) $this->option('loyalty-source'),
            'retry_skipped' => (bool) $this->option('retry-skipped'),
            'chunk' => max(50, (int) ($this->option('chunk') ?: config('legacy_import.chunk', 500))),
        ], function (string $level, string $message) {
            if ($level === 'step') {
                $this->newLine();
                $this->line("<fg=cyan;options=bold>== {$message} ==</>");
            } elseif ($level === 'warn') {
                $this->warn($message);
            } elseif ($level === 'progress') {
                if ($this->output->isVerbose()) {
                    $this->line("  <fg=gray>{$message}</>");
                }
            } else {
                $this->line($message);
            }
        });

        try {
            $this->info('Preflight checks (read-only)...');
            $importer->preflight();
        } catch (Throwable $e) {
            $this->error('PREFLIGHT FAILED — nothing was written.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('verify')) {
            $this->table(['Check', 'Old CRM', 'New CRM', ''], $importer->verify());

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Database : <options=bold>' . config('database.connections.' . config('database.default') . '.database') . '</> (' . app()->environment() . ')');
        $this->line('Source   : <options=bold>' . config('database.connections.legacy.database') . '</>');
        $this->line('Clinics  : ' . ($onlyEstates ? 'old estate(s) ' . implode(', ', $onlyEstates) . ' only' : '<fg=yellow>ALL old clinics</>'));
        $this->line('Steps    : ' . implode(' → ', $steps));
        $this->line('Mode     : ' . ($commit ? '<fg=red;options=bold>COMMIT — rows will be written</>' : '<fg=green>DRY RUN — everything is rolled back at the end</>'));

        if ($commit) {
            $this->warn('Make sure a fresh backup of this database exists (php artisan backup:run --only-db).');
            if (! $this->option('force') && ! $this->confirm('Write the old CRM data into this database now?', false)) {
                $this->info('Cancelled.');

                return self::FAILURE;
            }
        } elseif (app()->isProduction()) {
            $this->warn('Dry run on PRODUCTION holds one long transaction (row locks on inserted rows). Prefer running it on a restored copy.');
            if (! $this->option('force') && ! $this->confirm('Continue with the dry run here?', false)) {
                return self::FAILURE;
            }
        }

        $failed = null;
        try {
            $importer->run($steps);
        } catch (Throwable $e) {
            $failed = $e;
        }

        $this->report($importer, $commit);

        if ($failed) {
            $this->newLine();
            $this->error('IMPORT STOPPED: ' . $failed->getMessage());
            $this->line($failed->getFile() . ':' . $failed->getLine());
            $this->line($commit
                ? 'Chunks committed before the error stay in place and are recorded in legacy_import_map; fix the cause and run the same command again to continue.'
                : 'Dry run — nothing was written.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($commit
            ? 'Done. Check the numbers with: php artisan legacy:import --verify'
            : 'Dry run finished. If the numbers above look right, run again with --commit.');

        return self::SUCCESS;
    }

    private function report(LegacyCrmImporter $importer, bool $commit): void
    {
        $rows = [];
        foreach ($importer->stats() as $step => $counts) {
            foreach ($counts as $what => $n) {
                $rows[] = [$step, $what, number_format($n)];
            }
        }
        if ($rows) {
            $this->newLine();
            $this->table(['Step', 'Result', 'Rows'], $rows);
        }

        $issues = $importer->issues();
        if (! $issues) {
            return;
        }

        $dir = storage_path('logs/legacy-import');
        File::ensureDirectoryExists($dir);
        $file = $dir . '/issues-' . now()->format('Ymd-His') . ($commit ? '' : '-dryrun') . '.csv';
        $fh = fopen($file, 'w');
        fputcsv($fh, ['step', 'old_id', 'issue']);
        foreach ($issues as $i) {
            fputcsv($fh, [$i['step'], $i['old_id'], $i['issue']]);
        }
        fclose($fh);

        $byStep = collect($issues)->countBy('step')->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ');
        $this->warn(count($issues) . " issue(s) ({$byStep}) written to {$file}");
    }

    /** @return array<int, int> */
    private function parseClinicMap(string $value): array
    {
        $map = [];
        foreach (array_filter(array_map('trim', explode(',', $value))) as $pair) {
            if (! preg_match('/^(\d+):(\d+)$/', $pair, $m)) {
                throw new \InvalidArgumentException("Bad --clinic-map entry '{$pair}', expected old_estate_id:new_clinic_id");
            }
            $map[(int) $m[1]] = (int) $m[2];
        }

        return $map;
    }
}
