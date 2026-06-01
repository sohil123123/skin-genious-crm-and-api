<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_package_id',
        'service_id',
        'service_snapshot',
        'quantity',
        'used_sessions',
        'price_per_unit',
        'total_amount',
    ];

    protected $casts = [
        'service_snapshot' => 'array',
        'quantity'         => 'integer',
        'used_sessions'    => 'integer',
        'price_per_unit'   => 'decimal:2',
        'total_amount'     => 'decimal:2',
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
     * Whether all sessions for this item have been used.
     */
    public function isExhausted(): bool
    {
        return $this->used_sessions >= $this->quantity;
    }

    // ----------- Relationships -------------------------

    public function package(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_package_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(UserPackageUsage::class);
    }

    // ----------- Business Logic -------------------------

    /**
     * Record session consumption. Returns false if over-usage would occur.
     */
    public function consumeSessions(int $sessions = 1, array $extraData = []): bool
    {
        if ($this->used_sessions + $sessions > $this->quantity) {
            return false;
        }

        $this->usages()->create(array_merge([
            'user_package_id' => $this->user_package_id,
            'sessions_used' => $sessions,
            'recorded_by'   => auth()->id(),
        ], $extraData));

        $this->increment('used_sessions', $sessions);

        // Check if entire package is now exhausted
        $this->package->checkAndUpdateStatus();

        return true;
    }
}
