<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills invoice_items.product_snapshot for lines saved before snapshots existed.
 *
 * The real "at time of sale" data of those lines is gone, so the snapshot is taken from
 * the product as it is NOW and marked "backfilled": true — it freezes today's name for
 * those invoices from here on, it does not recover the historic one.
 *
 * Only empty snapshots are written (never overwrites one), with the query builder so no
 * InvoiceItem hooks run (no stock changes, no activity log). Safe to re-run.
 * Dry run unless --commit.
 */
class BackfillInvoiceItemSnapshots extends Command
{
    protected $signature = 'invoices:backfill-product-snapshots
        {--commit : Actually write. Without it nothing is changed (dry run).}';

    protected $description = 'Fill product_snapshot on existing invoice lines from the current product (dry run unless --commit)';

    public function handle(): int
    {
        $pending = DB::table('invoice_items')->whereNull('product_snapshot');
        $total = (clone $pending)->count();
        $this->line('Invoice lines without a snapshot: <options=bold>' . number_format($total) . '</>');

        if ($total === 0) {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        $sample = (clone $pending)->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->orderBy('invoice_items.id')->limit(5)
            ->get(['invoices.invoice_number', 'products.name']);
        $this->table(['Invoice', 'Product name that will be frozen'], $sample->map(fn ($r) => [$r->invoice_number, $r->name])->all());

        if (! $this->option('commit')) {
            $this->info('Dry run — nothing was changed. Run again with --commit to write.');

            return self::SUCCESS;
        }

        $now = now()->toDateTimeString();
        $done = 0;
        DB::table('invoice_items')->whereNull('product_snapshot')->orderBy('id')
            ->chunkById(500, function ($items) use ($now, &$done) {
                $products = DB::table('products')->whereIn('id', $items->pluck('product_id')->unique()->all())
                    ->get(['id', 'name', 'sku', 'type', 'hsn_sac_code', 'sell_price', 'gst', 'description'])->keyBy('id');

                DB::transaction(function () use ($items, $products, $now, &$done) {
                    foreach ($items as $item) {
                        $p = $products[$item->product_id] ?? null;
                        if (! $p) {
                            continue;
                        }
                        // same keys as InvoiceItem::snapshotOf(), plus the backfilled marker
                        $snapshot = (array) $p + ['captured_at' => $now, 'backfilled' => true];
                        $done += DB::table('invoice_items')->where('id', $item->id)->whereNull('product_snapshot')
                            ->update(['product_snapshot' => json_encode($snapshot)]);
                    }
                });
            });

        $this->info("Snapshot written for {$done} invoice line(s).");

        return self::SUCCESS;
    }
}
