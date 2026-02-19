<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IvSessionBag extends Model
{
    protected $fillable = [
        'iv_session_id',
        'bag_label',
        'carrier',
        'volume_ml',
        'min_duration_minutes',
        'rate_profile',
    ];

    protected $casts = [
        'rate_profile' => 'array',
    ];

    public function ivSession()
    {
        return $this->belongsTo(IVSession::class);
    }

    public function ingredients()
    {
        return $this->hasMany(IVSessionIngredient::class, 'iv_session_bag_id');
    }
}
