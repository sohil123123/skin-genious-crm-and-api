<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\MorphOne;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use App\Enums\ExpenseReferenceType;

class Purchase extends Model implements HasMedia
{
    use HasAuditColumns, LogsActivity, InteractsWithMedia;

    public const INVOICE_COLLECTION = 'invoice';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::INVOICE_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    /**
     * Mirror this purchase's invoice onto the expense generated from it, so the
     * expense shows the same file. Call after media is saved (the Filament
     * upload field saves media after the record, so the observer is too early).
     */
    public function syncInvoiceToExpense(): void
    {
        $expense = Expense::query()
            ->where('reference_type', ExpenseReferenceType::Purchase->value)
            ->where('reference_id', $this->getKey())
            ->first();

        if (! $expense) {
            return;
        }

        $invoice = $this->load('media')->getFirstMedia(self::INVOICE_COLLECTION);
        $current = $expense->getFirstMedia(Expense::INVOICE_COLLECTION);
        $currentSourceId = $current?->getCustomProperty('purchase_media_id');

        if (! $invoice) {
            // Only remove a copy that came from this purchase, never an own upload.
            if ($current && $currentSourceId) {
                $current->delete();
            }

            return;
        }

        if ($current && $currentSourceId === $invoice->getKey()) {
            return;
        }

        $invoice->copy(
            $expense,
            Expense::INVOICE_COLLECTION,
            fileAdderCallback: fn (FileAdder $adder) => $adder->withCustomProperties(['purchase_media_id' => $invoice->getKey()]),
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('purchase');
    }

    protected $fillable = [
        'clinic_id',
        'supplier_name',
        'total_amount',
        'purchase_date',
        'created_by',
        'updated_by',
        'payment_mode',
        'status',
        'notes',
        'subtotal',
        'total_gst',
        'total_discount',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expense(): MorphOne
    {
        return $this->morphOne(Expense::class, 'reference', 'reference_type', 'reference_id');
    }
}
