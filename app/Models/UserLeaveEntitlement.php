<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Illuminate\Database\Eloquent\Casts\Attribute;

use App\Enums\HolidayType;

class UserLeaveEntitlement extends Model
{
    protected $fillable = ['user_id', 'year', 'leave_type', 'total_allowed', 'remaining', 'used'];

    protected $casts = [
        'leave_type' => HolidayType::class,
        'total_allowed' => 'integer',
        'remaining' => 'integer',
        'used' => 'integer',
    ];

    protected static function booted() {
        static::creating(function ($model) {
            $model->remaining = $model->total_allowed - $model->used;
        });

        static::updating(function ($model) {
            $model->remaining = $model->total_allowed - $model->used;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapists()
    {
        return $this->user()->whereHas('roles', fn ($q) => $q->where('name', 'therapist'));
    }
}
