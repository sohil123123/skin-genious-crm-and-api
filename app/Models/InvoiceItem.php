<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ClinicInventory;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_snapshot',
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
        'product_snapshot' => 'array',
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

    /**
     * The product as it was when this line was invoiced — same shape as
     * UserPackageItem::$service_snapshot — so the invoice keeps its original name / price
     * even if the product is renamed or repriced later.
     */
    public static function snapshotOf(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'hsn_sac_code' => $product->hsn_sac_code,
            'sell_price' => $product->sell_price,
            'gst' => $product->gst,
            'description' => $product->description,
            'captured_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Name to print on the invoice: the product's name when it was invoiced (snapshot),
     * falling back to the current product for lines saved before snapshots existed.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => ($this->product_snapshot['name'] ?? null)
            ?: $this->product?->name
            ?: 'Item #' . $this->product_id);
    }

    protected static function booted()
    {
        // Every way of creating a line (invoice form repeater, package invoices, ...) gets a
        // snapshot; changing the product on an existing line takes a fresh one.
        static::saving(function ($item) {
            if ($item->product_id && (empty($item->product_snapshot) || $item->isDirty('product_id'))) {
                $item->product_snapshot = self::snapshotOf(Product::find($item->product_id));
            }
        });

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
