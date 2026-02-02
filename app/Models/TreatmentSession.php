<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TreatmentSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'user_id',
        'plan_type',
        'session_number',
        'title',
        'treatment_time',
        'week',
        'preparations_checklist_for_therapist',
        'concerns_addressed',
        'steps',
        'daily_home_care_routine',
        'audio_text',
        'status'
    ];

    protected $casts = [
        'preparations_checklist_for_therapist' => 'array',
        'concerns_addressed' => 'array',
        'steps' => 'array',
        'daily_home_care_routine' => 'array',
        'week' => 'integer',
        'session_number' => 'integer',
    ];

    // ---------------------- Relationship ----------------------

    /**
     * Relationship: TreatmentSession belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: TreatmentSession belongs to an Assessment.
     * (Assuming each plan is tied to an assessment record)
     */
    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }
}
