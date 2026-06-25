<?php

namespace App\Models;

use App\Enums\PackageDiscountType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
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
            // Check if the package has exactly 1 full invoice matching the package amount
            if ($package->invoices()->count() === 1) {
                $invoice = $package->invoice;
                if ($invoice && abs((float) $invoice->grand_total - (float) $package->final_amount) < 0.01) {
                    $package->syncInvoiceItemsForInvoice($invoice, $package->final_amount);
                }
            }
        });
    }

    /**
     * Create an invoice for this package.
     */
    public function createInvoice(?float $invoiceAmount = null): Invoice
    {
        $invoicedTotal = (float) $this->invoices()->sum('grand_total');
        $remainingAmount = max(0, (float) $this->final_amount - $invoicedTotal);

        if ($invoiceAmount === null || $invoiceAmount > $remainingAmount) {
            $invoiceAmount = $remainingAmount;
        }

        $invoice = Invoice::create([
            'clinic_id' => $this->clinic_id,
            'user_id' => $this->user_id,
            'package_id' => $this->id,
            'invoice_type' => 'package',
            'invoice_date' => now(),
            'source_note' => "Package: {$this->package_name}" . ($invoicedTotal > 0 ? " (Installment)" : ""),
            'subtotal' => 0,
            'discount_total' => 0,
            'taxable_value' => 0,
            'gst_total' => 0,
            'grand_total' => $invoiceAmount,
            'amount_due' => $invoiceAmount,
            'status' => 'unpaid',
            'created_by' => auth()->id(),
        ]);

        $this->syncInvoiceItemsForInvoice($invoice, $invoiceAmount);

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

        $this->syncInvoiceItemsForInvoice($this->invoice, (float) $this->invoice->grand_total);
    }

    /**
     * Sync invoice items and update invoice financials for a specific invoice amount.
     */
    public function syncInvoiceItemsForInvoice(Invoice $invoice, float $invoiceAmount): void
    {
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
        $ratio = $this->final_amount > 0 ? ($invoiceAmount / $this->final_amount) : 0;

        $itemsCount = $this->items->count();
        $processedCount = 0;

        foreach ($this->items as $item) {
            $processedCount++;
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

            $origMetrics = $calculationService->calculateLineItem(
                $item->quantity,
                $item->price_per_unit,
                $itemDiscountType,
                $itemDiscountValue,
                $gstPercentage
            );

            $targetLineTotal = round($origMetrics['line_total'] * $ratio, 2);

            // Last item rounding adjustment to match requested invoice amount exactly
            if ($processedCount === $itemsCount) {
                $targetLineTotal = round($invoiceAmount - $grandTotal, 2);
            }

            $lineTotal = $targetLineTotal;
            $taxableValue = round(($lineTotal * 100) / (100 + $gstPercentage), 2);
            $gstAmount = round($lineTotal - $taxableValue, 2);

            $invoice->items()->create([
                'product_id' => $item->service_id,
                'hsn_sac_code' => $hsnSacCode,
                'quantity' => 0,
                'unit_price' => $item->price_per_unit,
                'discount_type' => null,
                'discount_value' => 0,
                'valid_discount_amount' => 0,
                'taxable_value' => $taxableValue,
                'gst_percentage' => $gstPercentage,
                'gst_amount' => $gstAmount,
                'line_total' => $lineTotal,
            ]);

            $subtotalTotal += $lineTotal;
            $discountTotal += 0;
            $taxableValueTotal += $taxableValue;
            $gstTotal += $gstAmount;
            $grandTotal += $lineTotal;
        }

        $invoice->update([
            'subtotal' => $subtotalTotal,
            'discount_total' => $discountTotal,
            'taxable_value' => $taxableValueTotal,
            'gst_total' => $gstTotal,
            'grand_total' => $invoiceAmount,
            'amount_due' => max(0, $invoiceAmount - ($invoice->amount_paid ?? 0)),
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'package_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'package_id')->latestOfMany();
    }

    /**
     * Get the total amount paid so far for this package.
     */
    public function getPaidAmount(): float
    {
        return (float) $this->invoices()->sum('amount_paid');
    }

    /**
     * Get the outstanding balance of this package.
     */
    public function getOutstandingAmount(): float
    {
        return max(0.0, (float) $this->final_amount - $this->getPaidAmount());
    }

    /**
     * Record a package payment, auto-generating a fully paid invoice.
     */
    public function recordPayment(float $amount, array $paymentData): Invoice
    {
        $outstanding = $this->getOutstandingAmount();

        if (round($amount, 2) > round($outstanding, 2)) {
            throw new \InvalidArgumentException("Payment amount (₹" . number_format($amount, 2) . ") cannot exceed package outstanding balance (₹" . number_format($outstanding, 2) . ").");
        }

        // 1. Create a partial/installment invoice for this payment amount
        $invoice = $this->createInvoice($amount);

        // 2. Create the invoice payment record
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => $paymentData['payment_date'] ?? now(),
            'amount' => $amount,
            'payment_method' => $paymentData['payment_method'] ?? 'cash',
            'reference_number' => $paymentData['reference_number'] ?? null,
            'notes' => $paymentData['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // 3. Recalculate status of the invoice to make it fully paid
        $invoice->recalculatePaymentStatus();

        return $invoice;
    }
}
