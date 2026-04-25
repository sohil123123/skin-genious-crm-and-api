<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\HasAuditColumns;
class ConsumableTransfer extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'clinic_id',
        'transfer_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsumableTransferItem::class);
    }
}
