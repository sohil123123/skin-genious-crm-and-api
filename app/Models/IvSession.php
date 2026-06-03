<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IvSession extends Model
{
    protected $fillable = [
        'treatment_session_id',
        'assessment_id',
        'user_id',
        'selected_protocol_id',
        'selected_option_type',
        'is_plan',
        'plan_week_index',
        'status',
        'engine_versions',
    ];

    protected $casts = [
        'is_plan' => 'boolean',
        'engine_versions' => 'array',
    ];

    public function treatmentSession()
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function snapshots()
    {
        return $this->hasMany(IvSessionSnapshot::class);
    }

    public function bags()
    {
        return $this->hasMany(IvSessionBag::class);
    }

    public function ingredients()
    {
        return $this->hasMany(IvSessionIngredient::class);
    }

}
