<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Support\ProductSku;

class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     */
    public function creating(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "created" event.
     *
     * The SKU is built from the name and the new id (e.g. DDHGS-P-0006), so it is set
     * right after the insert; saveQuietly() keeps it to one activity-log entry.
     */
    public function created(Product $product): void
    {
        if (empty($product->sku)) {
            $product->sku = ProductSku::make($product->name, $product->type, (int) $product->id);
            $product->saveQuietly();
        }
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }
}
