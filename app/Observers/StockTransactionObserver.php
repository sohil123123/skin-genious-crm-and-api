<?php

namespace App\Observers;

use App\Models\StockTransaction;

class StockTransactionObserver
{
    /**
     * Handle the StockTransaction "created" event.
     */
    public function created(StockTransaction $stockTransaction): void
    {
        $product = $stockTransaction->product;
        
        // Define stock impact based on type
        // Ensure we handle 'initial' if migration needs it, but mostly user will pick types.
        if (in_array($stockTransaction->type, ['purchase', 'return_in', 'initial', 'adjustment_add'])) {
            $product->increment('stock', $stockTransaction->quantity);
        } elseif (in_array($stockTransaction->type, ['sale', 'damage', 'internal_use', 'return_out', 'adjustment_remove'])) {
            // Decrement but ensure we don't go below 0 if business logic requires it, 
            // though negative stock might be allowed. Assuming standard logic.
            // But 'quantity' in transaction is likely positive.
            $product->decrement('stock', $stockTransaction->quantity);
        }
    }

    /**
     * Handle the StockTransaction "updated" event.
     */
    public function updated(StockTransaction $stockTransaction): void
    {
        //
    }

    /**
     * Handle the StockTransaction "deleted" event.
     */
    public function deleted(StockTransaction $stockTransaction): void
    {
        $product = $stockTransaction->product;
        
        if (in_array($stockTransaction->type, ['purchase', 'return_in', 'initial', 'adjustment_add'])) {
            $product->decrement('stock', $stockTransaction->quantity);
        } elseif (in_array($stockTransaction->type, ['sale', 'damage', 'internal_use', 'return_out', 'adjustment_remove'])) {
            $product->increment('stock', $stockTransaction->quantity);
        }
    }

    /**
     * Handle the StockTransaction "restored" event.
     */
    public function restored(StockTransaction $stockTransaction): void
    {
        //
    }

    /**
     * Handle the StockTransaction "force deleted" event.
     */
    public function forceDeleted(StockTransaction $stockTransaction): void
    {
        //
    }
}
