<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Undoes `legacy:import` using legacy_import_map.
 *
 * Only rows the import CREATED are touched (never "matched" production rows),
 * and a created row is kept if anything added after the import now points at
 * it — e.g. a new invoice for an imported client, a payment added to an
 * imported invoice. Several foreign keys to users cascade on delete, so the
 * reference check runs before every user/product/clinic delete.
 *
 * Dry run unless --commit.
 */
class RollbackLegacyImport extends Command
{
    protected $signature = 'legacy:rollback
        {--commit : Actually delete. Without it everything is rolled back (dry run).}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Remove the rows created by legacy:import (dry run unless --commit)';

    private array $stats = [];
    /** @var list<string> */
    private array $kept = [];

    public function handle(): int
    {
        if (! Schema::hasTable('legacy_import_map')) {
            $this->info('legacy_import_map does not exist — nothing to roll back.');

            return self::SUCCESS;
        }

        $commit = (bool) $this->option('commit');
        $this->line('Database : <options=bold>' . DB::connection()->getDatabaseName() . '</> (' . app()->environment() . ')');
        $this->line('Mode     : ' . ($commit ? '<fg=red;options=bold>COMMIT — rows will be deleted</>' : '<fg=green>DRY RUN</>'));
        if ($commit && ! $this->option('force') && ! $this->confirm('Delete the imported old-CRM rows from this database?', false)) {
            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $this->rollbackLoyalty();
            $this->rollbackAppointments();
            $this->rollbackInvoices();
            $this->rollbackReferrals();
            $this->rollbackOpeningStock();
            $this->rollbackRows('product', 'products');
            $this->rollbackUsers();
            $this->rollbackRows('estate', 'clinics');
            $this->forgetNonCreated();

            $commit ? DB::commit() : DB::rollBack();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('ROLLBACK FAILED, nothing was changed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Entity', 'Result', 'Rows'], collect($this->stats)->flatMap(
            fn ($counts, $entity) => collect($counts)->map(fn ($n, $what) => [$entity, $what, $n])->values()
        )->all());
        foreach (array_slice($this->kept, 0, 50) as $k) {
            $this->line("  kept: {$k}");
        }
        if (count($this->kept) > 50) {
            $this->line('  ... and ' . (count($this->kept) - 50) . ' more');
        }
        $this->info($commit ? 'Rollback committed.' : 'Dry run — nothing was deleted. Re-run with --commit to apply.');

        return self::SUCCESS;
    }

    private function created(string $entity): Collection
    {
        return DB::table('legacy_import_map')->where('entity', $entity)->where('action', 'created')->get(['id', 'old_id', 'new_id']);
    }

    private function rollbackLoyalty(): void
    {
        foreach ($this->created('loyalty_opening') as $m) {
            $tx = DB::table('loyalty_point_transactions')->where('id', $m->new_id)->first();
            if ($tx) {
                DB::table('users')->where('id', $tx->user_id)->update(['loyalty_points' => DB::raw('loyalty_points - ' . (int) $tx->points)]);
                DB::table('loyalty_point_transactions')->where('id', $tx->id)->delete();
                $after = (int) DB::table('users')->where('id', $tx->user_id)->value('loyalty_points');
                if ($after < 0) {
                    $this->kept[] = "user #{$tx->user_id} loyalty_points is now {$after} (points were redeemed after the import) — review by hand";
                }
            }
            DB::table('legacy_import_map')->where('id', $m->id)->delete();
            $this->bump('loyalty_opening', 'removed');
        }
    }

    private function rollbackAppointments(): void
    {
        foreach ($this->created('appointment') as $m) {
            $a = DB::table('appointments')->where('id', $m->new_id)->first(['id', 'updated_by']);
            if ($a && $a->updated_by !== null) {
                $this->kept[] = "appointment #{$a->id} (edited after import)";
                $this->bump('appointment', 'kept');
                continue;
            }
            DB::table('appointments')->where('id', $m->new_id)->delete();
            DB::table('legacy_import_map')->where('id', $m->id)->delete();
            $this->bump('appointment', 'removed');
        }
    }

    private function rollbackInvoices(): void
    {
        foreach ($this->created('sale')->chunk(500) as $chunk) {
            $ids = $chunk->pluck('new_id')->all();
            $touched = DB::table('invoices')->whereIn('id', $ids)->whereNotNull('updated_by')->pluck('id')
                ->merge(DB::table('invoice_payments')->whereIn('invoice_id', $ids)->where('transaction_id', 'not like', 'OLD-PAY-%')->pluck('invoice_id'))
                ->merge(DB::table('user_package_usages')->whereIn('invoice_id', $ids)->pluck('invoice_id'))
                ->unique()->flip();

            foreach ($chunk as $m) {
                if (isset($touched[$m->new_id])) {
                    $this->kept[] = "invoice #{$m->new_id} (edited / paid / used after import)";
                    $this->bump('sale', 'kept');
                    continue;
                }
                // invoice_items and invoice_payments cascade with the invoice.
                DB::table('invoices')->where('id', $m->new_id)->delete();
                DB::table('legacy_import_map')->where('id', $m->id)->delete();
                $this->bump('sale', 'removed');
            }
        }

        $this->releaseInvoiceBlocks();
    }

    /**
     * Gives the reserved INV-{STATE} block back once every old invoice is gone: the counter
     * is wound back only if no new invoice was numbered after the block, otherwise the
     * block's numbers simply stay unused (a gap) — live invoice numbers are never touched.
     */
    private function releaseInvoiceBlocks(): void
    {
        $blocks = DB::table('legacy_import_map')->where('entity', 'like', 'invoice_block:%')->get();
        if ($blocks->isEmpty()) {
            return;
        }
        if (DB::table('legacy_import_map')->where('entity', 'sale')->where('action', 'created')->exists()) {
            $this->kept[] = 'reserved invoice number block(s) — some imported invoices were kept';

            return;
        }

        // --renumber-existing-invoices: the old invoices are gone, so give this CRM's own
        // invoices their original numbers back (those numbers are free again).
        foreach (DB::table('legacy_import_map')->where('entity', 'renumbered_invoice')->get() as $r) {
            $previous = (string) $r->note;
            $taken = DB::table('invoices')->where('invoice_number', $previous)->where('id', '!=', $r->old_id)->exists();
            if (! $taken && preg_match('/-(\d+)$/', $previous, $m)) {
                DB::table('invoices')->where('id', $r->old_id)->update(['invoice_number' => $previous, 'sequence_number' => (int) $m[1]]);
                $this->bump('invoice numbers', 'existing invoices given their number back');
            } else {
                $this->kept[] = "invoice #{$r->old_id} keeps its new number ({$previous} is in use)";
            }
            DB::table('legacy_import_map')->where('id', $r->id)->delete();
        }

        foreach ($blocks as $b) {
            $state = substr($b->entity, strlen('invoice_block:'));
            $before = (int) $b->new_id;
            $end = $before + (int) $b->old_id;
            $row = DB::table('state_invoice_sequences')->where('state_code', $state)->lockForUpdate()->first();
            // after a renumbering run the counter goes back to what it was before the import
            $counterBefore = DB::table('legacy_import_map')->where('entity', "invoice_counter_before:{$state}")->value('new_id');
            $restoreTo = $counterBefore !== null ? (int) $counterBefore : $before;
            DB::table('legacy_import_map')->where('entity', "invoice_counter_before:{$state}")->delete();

            if ($row && (int) $row->last_number === $end) {
                DB::table('state_invoice_sequences')->where('id', $row->id)->update(['last_number' => $restoreTo, 'updated_at' => now()]);
                $this->bump('invoice numbers', "{$state} counter back to {$restoreTo}");
            } else {
                $this->kept[] = sprintf('INV-%s-%04d … INV-%s-%04d stay unused: new invoices were numbered after the block', $state, $before + 1, $state, $end);
                $this->bump('invoice numbers', "{$state} block left as a gap");
            }
            DB::table('legacy_import_map')->where('id', $b->id)->delete();
        }
    }

    /**
     * Removes the opening-stock purchases and takes their quantity back out of
     * clinic_inventories. A purchase edited after the import, or one that has an Expense,
     * is kept. An inventory row that ends at 0 is removed so the product can go too; if
     * stock moved since (sales / transfers), the row stays and so does the product.
     */
    private function rollbackOpeningStock(): void
    {
        foreach ($this->created('opening_stock') as $m) {
            $purchase = DB::table('purchases')->where('id', $m->new_id)->first(['id', 'clinic_id', 'updated_by']);
            if ($purchase) {
                $hasExpense = Schema::hasTable('expenses') && DB::table('expenses')
                    ->where('reference_type', 'purchase')->where('reference_id', $purchase->id)->exists();
                if ($purchase->updated_by !== null || $hasExpense) {
                    $this->kept[] = "purchase #{$purchase->id} (edited after import)";
                    $this->bump('opening_stock', 'kept');
                    continue;
                }
                foreach (DB::table('purchase_items')->where('purchase_id', $purchase->id)->get(['product_id', 'quantity']) as $item) {
                    $inv = DB::table('clinic_inventories')->where('clinic_id', $purchase->clinic_id)->where('product_id', $item->product_id)->lockForUpdate()->first();
                    if (! $inv) {
                        continue;
                    }
                    $left = (int) $inv->stock_quantity - (int) $item->quantity;
                    $left === 0
                        ? DB::table('clinic_inventories')->where('id', $inv->id)->delete()
                        : DB::table('clinic_inventories')->where('id', $inv->id)->update(['stock_quantity' => $left, 'updated_at' => now()]);
                }
                DB::table('purchases')->where('id', $purchase->id)->delete(); // purchase_items cascade
            }
            DB::table('legacy_import_map')->where('id', $m->id)->delete();
            $this->bump('opening_stock', 'removed');
        }
    }

    private function rollbackReferrals(): void
    {
        // referred_by was only ever set on users the import created; clear it so those users
        // are not "referenced" by each other when deleting.
        $ids = $this->created('client')->pluck('new_id');
        foreach ($ids->chunk(1000) as $chunk) {
            DB::table('users')->whereIn('id', $chunk->all())->whereIn('referred_by', $ids->all())->update(['referred_by' => null]);
        }
    }

    private function rollbackUsers(): void
    {
        $maps = $this->created('client')->merge($this->created('staff'));
        // A staff and a client map row can point at the same user only via "matched", so ids are unique here.
        foreach ($maps->chunk(500) as $chunk) {
            $ids = $chunk->pluck('new_id')->unique()->all();
            $referenced = $this->referencedIds('users', $ids);
            $deletable = array_values(array_diff($ids, $referenced));

            foreach ($referenced as $id) {
                $this->kept[] = "user #{$id} (referenced by data added after import)";
            }
            if ($deletable) {
                DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->whereIn('model_id', $deletable)->delete();
                DB::table('model_has_permissions')->where('model_type', \App\Models\User::class)->whereIn('model_id', $deletable)->delete();
                DB::table('users')->whereIn('id', $deletable)->delete();
            }
            $this->forgetMapRowsPointingAt('users', $deletable);
            $this->bump('users', 'removed', count($deletable));
            $this->bump('users', 'kept', count($referenced));
        }
    }

    private function rollbackRows(string $entity, string $table): void
    {
        $maps = $this->created($entity);
        $referenced = array_flip($this->referencedIds($table, $maps->pluck('new_id')->all()));
        foreach ($maps as $m) {
            if (isset($referenced[$m->new_id])) {
                $this->kept[] = "{$table} #{$m->new_id} (still referenced)";
                $this->bump($entity, 'kept');
                continue;
            }
            DB::table($table)->where('id', $m->new_id)->delete();
            $this->forgetMapRowsPointingAt($table, [$m->new_id]);
            $this->bump($entity, 'removed');
        }
    }

    /**
     * Removes every map row (created OR matched) that points at deleted rows, so a later
     * re-import never links an old record to an id that no longer exists — e.g. two old
     * clients with the same mobile: one "created" the user, the other "matched" it.
     */
    private function forgetMapRowsPointingAt(string $table, array $ids): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table('legacy_import_map')->where('new_table', $table)->whereIn('new_id', $chunk)->delete();
        }
    }

    /** Drop matched/skipped map rows only once nothing created remains. */
    private function forgetNonCreated(): void
    {
        if (DB::table('legacy_import_map')->where('action', 'created')->exists()) {
            return;
        }
        $n = DB::table('legacy_import_map')->delete();
        $this->bump('map', 'matched/skipped rows cleared', $n);
    }

    /**
     * Ids in $ids that any other row in the database still points at — through a real
     * foreign key, or through a conventionally named user column that has no constraint.
     *
     * @return list<int>
     */
    private function referencedIds(string $table, array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $schema = DB::connection()->getDatabaseName();
        $columns = collect(DB::select(
            'SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?',
            [$schema, $table, 'id']
        ))->map(fn ($r) => [$r->t, $r->c]);

        if ($table === 'users') {
            $userColumns = ['user_id', 'customer_user_id', 'agent_user_id', 'assigned_user_id', 'matched_user_id',
                'therapist_id', 'referred_by', 'created_by', 'updated_by', 'recorded_by', 'approved_by', 'assigned_to', 'matched_by'];
            $columns = $columns->merge(collect(DB::select(
                'SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN (' . implode(',', array_fill(0, count($userColumns), '?')) . ")
                   AND TABLE_NAME NOT IN ('legacy_import_map', 'sessions')",
                [$schema, ...$userColumns]
            ))->map(fn ($r) => [$r->t, $r->c]));
        }

        $found = [];
        foreach ($columns->unique(fn ($c) => $c[0] . '.' . $c[1]) as [$t, $c]) {
            foreach (DB::table($t)->whereIn($c, $ids)->distinct()->pluck($c) as $id) {
                $found[(int) $id] = true;
            }
        }

        return array_keys($found);
    }

    private function bump(string $entity, string $what, int $by = 1): void
    {
        $this->stats[$entity][$what] = ($this->stats[$entity][$what] ?? 0) + $by;
    }
}
