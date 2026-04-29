<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPackageUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_package_id',
        'sessions_used',
        'notes',
        'appointment_id',
        'invoice_id',
        'recorded_by',
    ];

    protected $casts = [
        'sessions_used' => 'integer',
    ];

    // ----------- Relationships -------------------------

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
