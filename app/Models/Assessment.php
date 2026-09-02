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

    protected $fillable = ['parent_id', 'conversation_id', 'assessment_id', 'clinic_id', 'user_id', 'name', 'age', 'daily_sun_exposure_hours', 'social_event', 'upcoming_travel', 'medical_history', 'allergies', 'skin_temp_for_head', 'left_cheek_temp', 'right_cheek_temp', 'recent_peel_or_laser', 'retinol_used_last_night', 'is_pregnant', 'breastfeeding', 'iv_inputs', 'pigmentation_inputs', 'assessment_type', 'feature_packet', 'post_feature_packet', 'diagnosis', 'post_diagnosis', 'parameters_with_abnormal_scores', 'selected_plan_type', 'total_time', 'recommended_full_plan', 'iv_treatment_plan', 'iv_selected_option', 'nurse_run_sheet', 'status', 'therapist_notes', 'created_by'];

    protected $casts = [
        'medical_history' => 'array',
        'allergies' => 'array',
        'feature_packet' => 'array',
        'post_feature_packet' => 'array',
        'diagnosis' => 'array',
        'post_diagnosis' => 'array',
        'parameters_with_abnormal_scores' => 'array',
        'recommended_full_plan' => 'array',
        'iv_treatment_plan' => 'array',
        'iv_selected_option' => 'array',
        'nurse_run_sheet' => 'array',
        'is_pregnant' => 'boolean',
        'iv_inputs' => 'array',
        'pigmentation_inputs' => 'array',
        'selected_plan_type' => AssessmentSessionType::class,
        'status' => AssessmentStatus::class,
    ];

    protected static function booted() {
        static::creating(function ($assessment) {
            $assessment->name = 'assessment #'. (($assessment->user->assessments?->count() ?? 0) + 1);
            $assessment->created_by = auth()->user()?->id;
            $selectedUser = User::find($assessment->user_id);
            if ($selectedUser && $selectedUser->clinic_id) {
                $assessment->clinic_id = $selectedUser->clinic_id;
            }
            if ($assessment->user_id) {
                $firstAssessment = self::where('user_id', $assessment->user_id)
                    ->where('assessment_type', $assessment->assessment_type)
                    ->whereNull('parent_id')
                    ->orderBy('created_at', 'asc')
                    ->first();
                if ($firstAssessment) {
                    $assessment->parent_id = $firstAssessment->id;
                }
            }
        });

        static::saving(function ($assessment) {
            // Update summary dynamically on changes
            if ($assessment->isDirty(['diagnosis', 'pigmentation_inputs', 'iv_inputs', 'recommended_full_plan', 'iv_treatment_plan', 'parameters_with_abnormal_scores'])) {
                $assessment->updateAiSummary();
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
                    "iv_prep_data" => $s->iv_prep_data ?? [],
                    "script" => $s->audio_text ?? '',
                    "post_images" => $s->post_images ?? [],
                    "post_feature_packet" => $s->post_feature_packet ?? null,
                    "post_diagnosis" => $s->post_diagnosis ?? null,
                ];
            })
        ];
    }

    public function getImagesAttribute()
    {
        $collection = $this->assessment_type === 'pigmentation' ? 'pigmentation_pre_assessment_images' : 'assessment_images';
        return $this->getMedia($collection)->map(function (Media $media) {
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
        $collection = $this->assessment_type === 'pigmentation' ? 'pigmentation_post_assessment_images' : 'post_assessment_images';
        return $this->getMedia($collection)->map(function (Media $media) {
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

    /**
     * Generates and saves the latest text-only clinical summary.
     */
    public function updateAiSummary()
    {
        $patient = $this->user;
        $lines = [
            "[SYSTEM INITIALIZATION: CLINICAL HISTORICAL RECORD SEED]",
            "Patient Name: " . ($patient->name ?? 'N/A') . " (Age: {$this->age}, Gender: " . ($this->gender ?? 'N/A') . ")",
            "Assessment Category: " . strtoupper($this->assessment_type),
            "",
            "## 1. BASELINE CLINICAL VALUES",
        ];

        // Dynamic inputs to scan and serialize
        $dynamicInputs = [
            'diagnosis' => 'Diagnosis Analysis',
            'parameters_with_abnormal_scores' => 'Concerns & Abnormal Scores',
            'pigmentation_inputs' => 'Pigmentation Baseline Inputs',
            'iv_inputs' => 'IV Intake Inputs',
        ];

        foreach ($dynamicInputs as $column => $label) {
            if (!empty($this->$column)) {
                $lines[] = "### {$label}:";
                $lines[] = $this->formatArrayToMarkdownSummary($this->$column);
                $lines[] = "";
            }
        }

        // Active treatment plans
        $activePlans = [
            'recommended_full_plan' => 'Facial & Pigmentation Treatment Roadmap',
            'iv_treatment_plan' => 'IV Therapy Plan',
        ];

        foreach ($activePlans as $column => $label) {
            if (!empty($this->$column)) {
                $lines[] = "### {$label}:";
                $lines[] = $this->formatArrayToMarkdownSummary($this->$column);
                $lines[] = "";
            }
        }

        // Completed sessions history
        $lines[] = "## 2. HISTORICAL TREATMENT SESSIONS LOG";
        $completedSessions = $this->treatmentSessions()
            ->where('status', 'completed')
            ->orderBy('session_number')
            ->get();

        if ($completedSessions->isEmpty()) {
            $lines[] = "No clinical treatment sessions have been executed yet.";
        } else {
            foreach ($completedSessions as $session) {
                $lines[] = "- **Session #{$session->session_number}**: {$session->title}";
                if (!empty($session->post_diagnosis)) {
                    $lines[] = "  *Reassessment Outcome:*";
                    $lines[] = $this->formatArrayToMarkdownSummary($session->post_diagnosis, 1, "    ");
                }
            }
        }

        $lines[] = "";
        $lines[] = "Use the baseline history above as reference. Evaluate the new session data relative to this baseline.";

        $this->ai_summary = implode("\n", $lines);
        return $this->ai_summary;
    }

    /**
     * Helper to recursively format arrays to markdown summary.
     */
    private function formatArrayToMarkdownSummary($data, $maxDepth = 2, $indent = "", $currentDepth = 0)
    {
        if (!is_array($data)) {
            return $indent . "- " . (is_bool($data) ? ($data ? 'Yes' : 'No') : $data);
        }

        $lines = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['images', 'post_images', 'steps', 'script', 'raw_response', 'metadata', 'fixed_protocol'])) {
                continue;
            }

            $formattedKey = ucwords(str_replace('_', ' ', $key));

            if (is_array($value)) {
                if ($currentDepth < $maxDepth) {
                    $lines[] = $indent . "- **{$formattedKey}**:";
                    $subResult = $this->formatArrayToMarkdownSummary($value, $maxDepth, $indent . "  ", $currentDepth + 1);
                    if (!empty($subResult)) {
                        $lines[] = $subResult;
                    }
                }
            } else {
                if ($value !== null && $value !== '') {
                    $displayVal = is_bool($value) ? ($value ? 'Yes' : 'No') : $value;
                    $lines[] = $indent . "- **{$formattedKey}**: {$displayVal}";
                }
            }
        }
        return implode("\n", $lines);
    }
}
