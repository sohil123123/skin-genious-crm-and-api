<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ClinicInventory;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'hsn_sac_code',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'valid_discount_amount',
        'taxable_value',
        'gst_percentage',
        'gst_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'valid_discount_amount' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::created(function ($item) {
            $product = $item->product;
            if ($product && $product->type !== 'service') {
                $clinicId = $item->invoice->clinic_id;
                $inventory = ClinicInventory::where('clinic_id', $clinicId)
                    ->where('product_id', $item->product_id)
                    ->first();
                
                if ($inventory) {
                    $inventory->decrement('stock_quantity', $item->quantity);
                }
            }
        });

        static::updated(function ($item) {
            $product = $item->product;
            if ($product && $product->type !== 'service') {
                $clinicId = $item->invoice->clinic_id;
                
                // If product changed
                if ($item->isDirty('product_id')) {
                    // Restore stock to old product
                    $oldProduct = Product::find($item->getOriginal('product_id'));
                    if ($oldProduct && $oldProduct->type !== 'service') {
                        $oldInventory = ClinicInventory::where('clinic_id', $clinicId)
                            ->where('product_id', $item->getOriginal('product_id'))
                            ->first();
                        if ($oldInventory) {
                            $oldInventory->increment('stock_quantity', $item->getOriginal('quantity'));
                        }
                    }

                    // Deduct stock from new product
                    $newInventory = ClinicInventory::where('clinic_id', $clinicId)
                        ->where('product_id', $item->product_id)
                        ->first();
                    if ($newInventory) {
                        $newInventory->decrement('stock_quantity', $item->quantity);
                    }
                } elseif ($item->isDirty('quantity')) {
                    // Just quantity changed
                    $inventory = ClinicInventory::where('clinic_id', $clinicId)
                        ->where('product_id', $item->product_id)
                        ->first();
                    if ($inventory) {
                        $diff = $item->quantity - $item->getOriginal('quantity');
                        $inventory->decrement('stock_quantity', $diff);
                    }
                }
            }
        });

        static::deleted(function ($item) {
            $product = $item->product;
            if ($product && $product->type !== 'service') {
                $clinicId = $item->invoice->clinic_id;
                $inventory = ClinicInventory::where('clinic_id', $clinicId)
                    ->where('product_id', $item->product_id)
                    ->first();
                
                if ($inventory) {
                    $inventory->increment('stock_quantity', $item->quantity);
                }
            }
        });
    }
}
