<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

use App\Services\InvoiceNumberService;

class Invoice extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('invoice');
    }
    protected $fillable = [
        'clinic_id',
        'user_id',
        'invoice_number',
        'invoice_date',
        'payment_mode',
        'source_note',
        'subtotal',
        'discount_total',
        'taxable_value',
        'gst_total',
        'grand_total',
        'amount_paid',
        'amount_due',
        'status',
        'invoice_type',
        'package_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'gst_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'package_id');
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $service = new InvoiceNumberService();
                $service->generate($invoice);
            }
        });

        static::saving(function ($invoice) {
            $invoice->amount_due = max(0, $invoice->grand_total - ($invoice->amount_paid ?? 0));

            // Auto update status if not being set manually to something specific like 'cancelled' or 'draft'
            if (!in_array($invoice->status, ['cancelled', 'draft'])) {
                if ($invoice->amount_paid <= 0) {
                    $invoice->status = 'unpaid';
                } elseif ($invoice->amount_paid >= $invoice->grand_total) {
                    $invoice->status = 'paid';
                } else {
                    $invoice->status = 'partial';
                }
            }
        });
    }

    public function recalculatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->sum('amount');
        $this->amount_paid = $totalPaid;
        $this->amount_due = max(0, $this->grand_total - $totalPaid);

        if ($this->amount_paid <= 0) {
            $this->status = 'unpaid';
        } elseif ($this->amount_paid >= $this->grand_total) {
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }

        $this->saveQuietly();
    }
}
