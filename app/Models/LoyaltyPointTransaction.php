<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPointTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_payment_id',
        'type',
        'points',
        'balance_after',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'points'        => 'integer',
        'balance_after' => 'integer',
        'metadata'      => 'array',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoicePayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
