<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableTransferItem extends Model
{
    protected $fillable = [
        'consumable_transfer_id',
        'product_id',
        'quantity_used',
    ];

    public function consumableTransfer(): BelongsTo
    {
        return $this->belongsTo(ConsumableTransfer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::created(function ($item) {
            $clinicId = $item->consumableTransfer->clinic_id;
            
            $inventory = ClinicInventory::where('clinic_id', $clinicId)
                ->where('product_id', $item->product_id)
                ->first();
                
            if ($inventory) {
                $inventory->decrement('stock_quantity', $item->quantity_used);
            }
        });

        static::updated(function ($item) {
            $clinicId = $item->consumableTransfer->clinic_id;

            if ($item->isDirty('product_id')) {
                // Restore stock to old product
                $oldInventory = ClinicInventory::where('clinic_id', $clinicId)
                    ->where('product_id', $item->getOriginal('product_id'))
                    ->first();
                if ($oldInventory) {
                    $oldInventory->increment('stock_quantity', $item->getOriginal('quantity_used'));
                }

                // Deduct stock from new product
                $newInventory = ClinicInventory::where('clinic_id', $clinicId)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($newInventory) {
                    $newInventory->decrement('stock_quantity', $item->quantity_used);
                }
            } elseif ($item->isDirty('quantity_used')) {
                $inventory = ClinicInventory::where('clinic_id', $clinicId)
                    ->where('product_id', $item->product_id)
                    ->first();
                if ($inventory) {
                    $diff = $item->quantity_used - $item->getOriginal('quantity_used');
                    $inventory->decrement('stock_quantity', $diff);
                }
            }
        });

        static::deleted(function ($item) {
            $clinicId = $item->consumableTransfer->clinic_id;
            
            $inventory = ClinicInventory::where('clinic_id', $clinicId)
                ->where('product_id', $item->product_id)
                ->first();
                
            if ($inventory) {
                $inventory->increment('stock_quantity', $item->quantity_used);
            }
        });
    }
}
