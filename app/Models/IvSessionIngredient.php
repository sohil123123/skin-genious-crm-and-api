<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IvSessionIngredient extends Model
{
    protected $fillable = [
        'iv_session_id',
        'iv_session_bag_id',
        'ingredient_name',
        'dose_value',
        'dose_unit',
        'is_hero',
    ];

    protected $casts = [
        'is_hero' => 'boolean',
    ];

    public function ivSession()
    {
        return $this->belongsTo(IvSession::class);
    }

    public function bag()
    {
        return $this->belongsTo(IvSessionBag::class, 'iv_session_bag_id');
    }
}
