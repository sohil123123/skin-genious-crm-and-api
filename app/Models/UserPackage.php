<?php

namespace App\Models;

use App\Enums\PackageDiscountType;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserPackage extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::updated(function ($package) {
            // Check if the package has an invoice
            if ($package->invoice) {
                $package->syncInvoiceItems();
            }
        });
    }

    /**
     * Create an invoice for this package if it doesn't exist.
     */
    public function createInvoice(): Invoice
    {
        if ($this->invoice) {
            return $this->invoice;
        }

        $invoice = Invoice::create([
            'clinic_id' => $this->clinic_id,
            'user_id' => $this->user_id,
            'package_id' => $this->id,
            'invoice_type' => 'package',
            'invoice_date' => now(),
            'source_note' => "Package: {$this->package_name}",
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_amount,
            'taxable_value' => 0, // Will be updated by syncInvoiceItems
            'gst_total' => 0, // Will be updated by syncInvoiceItems
            'grand_total' => $this->final_amount, // Will be updated by syncInvoiceItems
            'amount_due' => $this->final_amount, // Will be updated by syncInvoiceItems
            'status' => 'unpaid',
            'created_by' => auth()->id(),
        ]);

        $this->load('invoice');
        $this->syncInvoiceItems();

        return $invoice->fresh();
    }

    /**
     * Sync invoice items and update invoice financials.
     */
    public function syncInvoiceItems(): void
    {
        if (!$this->invoice) {
            return;
        }

        $invoice = $this->invoice;
        $invoice->items()->delete();

        $discountType = $this->discount_type instanceof PackageDiscountType
            ? $this->discount_type->value
            : ($this->discount_type ?? 'flat');

        $subtotalTotal = 0;
        $discountTotal = 0;
        $taxableValueTotal = 0;
        $gstTotal = 0;
        $grandTotal = 0;

        $calculationService = new InvoiceCalculationService();

        foreach ($this->items as $item) {
            $product = Product::find($item->service_id);
            $gstPercentage = $product ? ($product->gst ?? 18) : 18;
            $hsnSacCode = $product ? $product->hsn_sac_code : null;
            $hsnSacCode = $hsnSacCode ?: ($product && $product->type === 'service' ? '999729' : '330499');

            if ($discountType === 'percentage') {
                $itemDiscountType = 'percentage';
                $itemDiscountValue = (float) ($this->discount_value ?? 0);
            } else {
                $itemDiscountType = 'flat';
                $itemDiscountValue = $this->subtotal > 0
                    ? round(($item->total_amount / $this->subtotal) * ($this->discount_value ?? 0), 2)
                    : 0.0;
            }

            $metrics = $calculationService->calculateLineItem(
                $item->quantity,
                $item->price_per_unit,
                $itemDiscountType,
                $itemDiscountValue,
                $gstPercentage
            );

            $invoice->items()->create([
                'product_id' => $item->service_id,
                'hsn_sac_code' => $hsnSacCode,
                'quantity' => $item->quantity,
                'unit_price' => $item->price_per_unit,
                'discount_type' => $itemDiscountType,
                'discount_value' => $itemDiscountValue,
                'valid_discount_amount' => $metrics['discount_amount'],
                'taxable_value' => $metrics['taxable_value'],
                'gst_percentage' => $gstPercentage,
                'gst_amount' => $metrics['gst_amount'],
                'line_total' => $metrics['line_total'],
            ]);

            $subtotalTotal += $metrics['gross_amount'];
            $discountTotal += $metrics['discount_amount'];
            $taxableValueTotal += $metrics['taxable_value'];
            $gstTotal += $metrics['gst_amount'];
            $grandTotal += $metrics['line_total'];
        }

        $invoice->update([
            'subtotal' => $subtotalTotal,
            'discount_total' => $discountTotal,
            'taxable_value' => $taxableValueTotal,
            'gst_total' => $gstTotal,
            'grand_total' => $grandTotal,
            'amount_due' => max(0, $grandTotal - ($invoice->amount_paid ?? 0)),
        ]);

        $invoice->recalculatePaymentStatus();
    }

    protected $fillable = [
        'clinic_id',
        'package_name',
        'user_id',
        'notes',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'final_amount',
        'is_active',
        'expired_at',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'discount_type' => PackageDiscountType::class,
        'is_active' => 'boolean',
        'expired_at' => 'date',
    ];

    // ----------- Computed Accessors -------------------------

    /**
     * Total sessions across all items.
     */
    public function getTotalSessions(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Total used sessions across all items.
     */
    public function getTotalUsedSessions(): int
    {
        return $this->items->sum('used_sessions');
    }

    /**
     * Total remaining sessions across all items.
     */
    public function getTotalRemainingSessions(): int
    {
        return max(0, $this->getTotalSessions() - $this->getTotalUsedSessions());
    }

    /**
     * Whether all sessions across all items are exhausted.
     */
    public function isExhausted(): bool
    {
        return $this->items->every(fn($item) => $item->isExhausted());
    }

    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast();
    }

    /**
     * Check if all services are exhausted and auto-deactivate.
     */
    public function checkAndUpdateStatus(): void
    {
        if ($this->isExhausted()) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Recalculate subtotal from items and apply discount.
     */
    public function recalculateFromItems(): void
    {
        $subtotal = $this->items()->sum('total_amount');
        $discountType = $this->discount_type instanceof PackageDiscountType
            ? $this->discount_type->value
            : ($this->discount_type ?? 'flat');
        $discountValue = (float) ($this->discount_value ?? 0);

        $discountAmount = $discountType === 'percentage'
            ? $subtotal * ($discountValue / 100)
            : $discountValue;

        $discountAmount = min($discountAmount, $subtotal);
        $finalAmount = max(0, $subtotal - $discountAmount);

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
        ]);
    }

    // ----------- Scopes -------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ----------- Relationships -------------------------

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(UserPackageItem::class);
    }

    public function usages(): HasManyThrough
    {
        return $this->hasManyThrough(
            UserPackageUsage::class,
            UserPackageItem::class,
        );
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'package_id');
    }
}
