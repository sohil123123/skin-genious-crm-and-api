<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\StockTransaction;

class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     */
    public function creating(Product $product): void
    {
        if (empty($product->sku)) {
            $product->sku = 'SKU-' . strtoupper(\Illuminate\Support\Str::random(8));
        }
        
        // Ensure stock defaults to 0 if not set, though DB default handles this.
        if (!isset($product->stock)) {
            $product->stock = 0;
        }
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        //
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
