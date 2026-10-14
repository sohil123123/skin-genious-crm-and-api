<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ProductSku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives existing products the name-based SKU (App\Support\ProductSku), replacing the old
 * random "SKU-XXXXXXXX" / "LEGACY-<id>" codes. Products that already have the new format
 * are skipped (use --all to rebuild them after a format change), so it is safe to re-run.
 * Invoice / package snapshots keep the SKU they were
 * captured with. Dry run unless --commit.
 */
class RegenerateProductSkus extends Command
{
    protected $signature = 'products:regenerate-skus
        {--commit : Actually write. Without it nothing is changed (dry run).}
        {--all : Also rebuild SKUs that already have the name-based format (e.g. after the format changed)}';

    protected $description = 'Replace existing product SKUs with the name-based format, e.g. DDHGS-P-0006 (dry run unless --commit)';

    public function handle(): int
    {
        $changes = DB::table('products')->orderBy('id')->get(['id', 'name', 'type', 'sku'])
            ->reject(fn ($p) => ! $this->option('all') && ProductSku::isGenerated($p->sku, (int) $p->id))
            ->map(fn ($p) => [$p->id, $p->name, $p->sku, ProductSku::make($p->name, $p->type, (int) $p->id)])
            ->reject(fn ($c) => $c[2] === $c[3]) // already correct
            ->values();

        $this->line('Products to update: <options=bold>' . $changes->count() . '</>');
        if ($changes->isEmpty()) {
            $this->info('Nothing to do — every product already has a name-based SKU.');

            return self::SUCCESS;
        }
        $this->table(['ID', 'Name', 'Current SKU', 'New SKU'], $changes->take(15)->map(fn ($c) => [$c[0], mb_strimwidth((string) $c[1], 0, 45, '…'), $c[2], $c[3]])->all());

        if (! $this->option('commit')) {
            $this->info('Dry run — nothing was changed. Run again with --commit to write.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            // the id is part of the new SKU, so new values never collide with each other
            foreach ($changes as [$id, , , $sku]) {
                DB::table('products')->where('id', $id)->update(['sku' => $sku]);
            }
        });
        $this->info("SKU updated for {$changes->count()} product(s).");

        return self::SUCCESS;
    }
}
