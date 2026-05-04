<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyOtp extends Model
{
    protected $fillable = [
        'user_id',
        'otp',
        'purpose',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ─────────────────────────────────────

    /**
     * Check if OTP is still valid (not expired and not used).
     */
    public function isValid(): bool
    {
        return !$this->used_at && $this->expires_at->isFuture();
    }

    /**
     * Mark OTP as used.
     */
    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
