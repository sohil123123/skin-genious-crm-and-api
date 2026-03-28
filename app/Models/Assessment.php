<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use App\Models\User;

use App\Enums\AssessmentSessionType;
use App\Enums\AssessmentStatus;

class Assessment extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = ['parent_id', 'conversation_id', 'assessment_id', 'clinic_id', 'user_id', 'name', 'age', 'daily_sun_exposure_hours', 'social_event', 'upcoming_travel', 'medical_history', 'allergies', 'skin_temp_for_head', 'left_cheek_temp', 'right_cheek_temp', 'recent_peel_or_laser', 'retinol_used_last_night', 'is_pregnant', 'breastfeeding', 'iv_inputs', 'assessment_type', 'feature_packet', 'diagnosis', 'post_diagnosis', 'parameters_with_abnormal_scores', 'selected_plan_type', 'total_time', 'recommended_full_plan', 'iv_treatment_plan', 'iv_selected_option', 'status', 'therapist_notes', 'created_by'];

    protected $casts = [
        'medical_history' => 'array',
        'allergies' => 'array',
        'feature_packet' => 'array',
        'diagnosis' => 'array',
        'post_diagnosis' => 'array',
        'parameters_with_abnormal_scores' => 'array',
        'recommended_full_plan' => 'array',
        'iv_treatment_plan' => 'array',
        'iv_selected_option' => 'array',
        'is_pregnant' => 'boolean',
        'iv_inputs' => 'array',
        'selected_plan_type' => AssessmentSessionType::class,
        'status' => AssessmentStatus::class,
    ];

    protected static function booted() {
        static::creating(function ($assessment) {
            $assessment->name = 'assessment #'. $assessment->user->assessments?->count() + 1;
            $assessment->created_by = auth()->user()->id;
            $selectedUser = User::find($assessment->user_id);
            if ($selectedUser && $selectedUser->clinic_id) {
                $assessment->clinic_id = $selectedUser->clinic_id;
            }
        });
    }

    protected $appends = ['images', 'post_images',  'treatment_sessions'];

    public function setParametersWithAbnormalScoresAttribute($value)
    {
        if (isset($value['parameters_with_abnormal_scores'])) {
            foreach ($value['parameters_with_abnormal_scores'] as &$item) {
                $item['is_primary_concern'] = filter_var($item['is_primary_concern'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->attributes['parameters_with_abnormal_scores'] = json_encode($value);
    }

    public function getTreatmentSessionsAttribute()
    {
        $sessions = $this->treatmentSessions()->orderBy('session_number')->get();
        if($sessions->isEmpty())
            return [];

        return [
            "total_time" => $this->total_time,
            "treatments" => $sessions->map(function ($s) {
                return [
                    "id" => $s->id,
                    "session_number" => $s->session_number,
                    "title" => $s->title,
                    "treatment_time" => $s->treatment_time,
                    "week" => $s->week,
                    "preparations_checklist_for_therapist" => $s->preparations_checklist_for_therapist ?? [],
                    "concerns_addressed" => $s->concerns_addressed ?? [],
                    "steps" => $s->steps ?? [],
                    "daily_home_care_routine" => $s->daily_home_care_routine ?? [],
                    "script" => $s->audio_text ?? ''
                ];
            })
        ];
    }

    public function getImagesAttribute()
    {
        return $this->getMedia('assessment_images')->map(function (Media $media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                // 'thumb_url' => $media->getUrl('thumb'), // If conversions are defined
                'name' => $media->name,
                // 'mime_type' => $media->mime_type,
                // 'size' => $media->size,
                'custom_properties' => $media->custom_properties
            ];
        });
    }

    public function getPostImagesAttribute()
    {
        return $this->getMedia('post_assessment_images')->map(function (Media $media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name,
                'custom_properties' => $media->custom_properties
            ];
        });
    }

    // // 🧠 Virtual attribute for total_time
    // public function getRecommendedTotalTimeAttribute()
    // {
    //     $plan = $this->recommended_full_plan ?? [];
    //     return $plan['total_time'] ?? null;
    // }

    // // 🧠 Virtual attribute for treatments
    // public function getRecommendedTreatmentsListAttribute()
    // {
    //     $plan = $this->recommended_full_plan ?? [];
    //     return $plan['treatments'] ?? [];
    // }

    // ---------------------------- Relationships --------------------------------
    public function parentAssessment()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function treatmentSessions()
    {
        return $this->hasMany(TreatmentSession::class);
    }

}
