<?php

namespace App\Models;

use App\Enums\PackageDiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'package_name',
        'user_id',
        'service_id',
        'service_snapshot',
        'notes',
        'quantity',
        'used_sessions',
        'price_per_unit',
        'total_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'final_amount',
        'is_active',
        'expired_at',
        'created_by',
    ];

    protected $casts = [
        'service_snapshot'  => 'array',
        'quantity'          => 'integer',
        'used_sessions'     => 'integer',
        'price_per_unit'    => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'discount_value'    => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'final_amount'      => 'decimal:2',
        'discount_type'     => PackageDiscountType::class,
        'is_active'         => 'boolean',
        'expired_at'        => 'date',
    ];

    // ----------- Computed Accessors -------------------------

    /**
     * Remaining sessions that can still be consumed.
     */
    public function getRemainingSessions(): int
    {
        return max(0, $this->quantity - $this->used_sessions);
    }

    /**
     * Derive whether the package is effectively exhausted or expired.
     */
    public function isExhausted(): bool
    {
        return $this->used_sessions >= $this->quantity;
    }

    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast();
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(UserPackageUsage::class);
    }

    // ----------- Business Logic -------------------------

    /**
     * Record a session consumption. Returns false if over-usage would occur.
     */
    public function consumeSessions(int $sessions = 1, array $extraData = []): bool
    {
        if ($this->used_sessions + $sessions > $this->quantity) {
            return false;
        }

        $this->usages()->create(array_merge([
            'sessions_used' => $sessions,
            'recorded_by'   => auth()->id(),
        ], $extraData));

        $this->increment('used_sessions', $sessions);

        // Auto-deactivate when fully consumed
        if ($this->used_sessions >= $this->quantity) {
            $this->update(['is_active' => false]);
        }

        return true;
    }
}
