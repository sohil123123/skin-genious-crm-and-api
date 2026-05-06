<?php

namespace App\Models;

use App\Enums\PackageDiscountType;
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
                $invoice = $package->invoice;
                
                // Update the invoice financials
                $invoice->update([
                    'subtotal' => $package->subtotal,
                    'discount_total' => $package->discount_amount,
                    'taxable_value' => $package->final_amount,
                    'grand_total' => $package->final_amount,
                    'amount_due' => max(0, $package->final_amount - $invoice->amount_paid),
                ]);

                // Sync invoice items with package items
                $invoice->items()->delete();
                foreach ($package->items as $item) {
                    $invoice->items()->create([
                        'product_id' => $item->service_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->price_per_unit,
                        'discount_type' => null,
                        'discount_value' => 0,
                        'valid_discount_amount' => 0,
                        'line_total' => $item->total_amount,
                    ]);
                }
                
                // Recalculate invoice status
                $invoice->recalculatePaymentStatus();
            }
        });
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
        'subtotal'        => 'decimal:2',
        'discount_value'  => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount'    => 'decimal:2',
        'discount_type'   => PackageDiscountType::class,
        'is_active'       => 'boolean',
        'expired_at'      => 'date',
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
        return $this->items->every(fn ($item) => $item->isExhausted());
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
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'final_amount'    => $finalAmount,
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
