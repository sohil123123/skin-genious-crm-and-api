<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Purchase extends Model
{
    use HasAuditColumns;

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
