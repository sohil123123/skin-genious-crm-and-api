<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'purchase_price',
        'gst',
        'discount',
        'gst_amount',
        'total',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::created(function ($item) {
            $clinicId = $item->purchase->clinic_id;
            
            $inventory = ClinicInventory::firstOrCreate(
                ['clinic_id' => $clinicId, 'product_id' => $item->product_id],
                ['stock_quantity' => 0]
            );
            
            $inventory->increment('stock_quantity', $item->quantity);
        });

        static::updated(function ($item) {
            $clinicId = $item->purchase->clinic_id;

            if ($item->isDirty('product_id')) {
                // Remove stock from old product
                $oldInventory = ClinicInventory::where('clinic_id', $clinicId)
                    ->where('product_id', $item->getOriginal('product_id'))
                    ->first();
                if ($oldInventory) {
                    $oldInventory->decrement('stock_quantity', $item->getOriginal('quantity'));
                }

                // Add stock to new product
                $newInventory = ClinicInventory::firstOrCreate(
                    ['clinic_id' => $clinicId, 'product_id' => $item->product_id],
                    ['stock_quantity' => 0]
                );
                $newInventory->increment('stock_quantity', $item->quantity);
            } elseif ($item->isDirty('quantity')) {
                $inventory = ClinicInventory::firstOrCreate(
                    ['clinic_id' => $clinicId, 'product_id' => $item->product_id],
                    ['stock_quantity' => 0]
                );
                $diff = $item->quantity - $item->getOriginal('quantity');
                $inventory->increment('stock_quantity', $diff);
            }
        });

        static::deleted(function ($item) {
            $clinicId = $item->purchase->clinic_id;
            
            $inventory = ClinicInventory::where('clinic_id', $clinicId)
                ->where('product_id', $item->product_id)
                ->first();
                
            if ($inventory) {
                $inventory->decrement('stock_quantity', $item->quantity);
            }
        });
    }
}
