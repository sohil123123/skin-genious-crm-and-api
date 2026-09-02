<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TreatmentSession extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

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
        'status',
        'iv_prep_data',
        'post_feature_packet',
        'post_diagnosis',
    ];

    protected $casts = [
        'preparations_checklist_for_therapist' => 'array',
        'concerns_addressed' => 'array',
        'steps' => 'array',
        'daily_home_care_routine' => 'array',
        'iv_prep_data' => 'array',
        'week' => 'integer',
        'session_number' => 'integer',
        'post_feature_packet' => 'array',
        'post_diagnosis' => 'array',
    ];

    protected static function booted() {
        static::saved(function ($session) {
            // Update summary when session attributes change
            if ($session->wasChanged(['status', 'post_diagnosis', 'iv_prep_data', 'title'])) {
                $assessment = $session->assessment;
                if ($assessment) {
                    $assessment->updateAiSummary();
                    $assessment->saveQuietly();
                }
            }
        });
    }

    protected $appends = ['post_images'];

    public function getPostImagesAttribute()
    {
        $media = $this->getMedia('post_treatment_images');
        if ($media->isEmpty()) {
            $media = $this->getMedia('user_post_assessment_images');
        }
        return $media->map(function (Media $media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name,
                'custom_properties' => $media->custom_properties
            ];
        });
    }

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

    public function ivSession()
    {
        return $this->hasOne(IvSession::class);
    }
}
