<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\LoyaltyPointService;
use Illuminate\Support\Facades\Log;
use App\Models\LoyaltyPointTransaction;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'transaction_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    protected static function booted()
    {
        static::creating(function ($payment) {
            if (empty($payment->transaction_id)) {
                $date = now()->format('Ymd');
                $latest = static::whereDate('created_at', now())->latest('id')->first();

                if ($latest && preg_match('/PAY-' . $date . '-(\d+)$/', $latest->transaction_id, $matches)) {
                    $number = intval($matches[1]) + 1;
                } else {
                    $number = 1;
                }

                $payment->transaction_id = 'PAY-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
            }
        });

        static::created(function ($payment) {
            $payment->invoice->recalculatePaymentStatus();

            // Auto-earn loyalty points (skips loyalty_points payment method)
            try {
                app(LoyaltyPointService::class)->earnPoints($payment);
            } catch (\Throwable $e) {
                Log::error('Loyalty earn failed: ' . $e->getMessage());
            }
        });

        static::updated(function ($payment) {
            $payment->invoice->recalculatePaymentStatus();
        });

        static::deleting(function ($payment) {
            // Reverse any loyalty points earned/redeemed for this payment
            // We do this in 'deleting' because the database will nullify the 
            // invoice_payment_id in transactions upon deletion (nullOnDelete).
            try {
                app(LoyaltyPointService::class)->reversePoints($payment);
            } catch (\Throwable $e) {
                Log::error('Loyalty reverse failed: ' . $e->getMessage());
            }
        });

        static::deleted(function ($payment) {
            $payment->invoice->recalculatePaymentStatus();
        });
    }
}
