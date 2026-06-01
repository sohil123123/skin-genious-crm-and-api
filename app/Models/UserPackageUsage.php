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
        'user_package_item_id',
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

    public function packageItem(): BelongsTo
    {
        return $this->belongsTo(UserPackageItem::class, 'user_package_item_id');
    }

    /**
     * Access the parent package through the item.
     */
    public function package()
    {
        return $this->hasOneThrough(
            UserPackage::class,
            UserPackageItem::class,
            'id',                    // user_package_items.id
            'id',                    // user_packages.id
            'user_package_item_id',  // user_package_usages.user_package_item_id
            'user_package_id',       // user_package_items.user_package_id
        );
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
