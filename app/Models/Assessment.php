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

class Assessment extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'assessment_id',
        'user_id',
        'clinic_id',
        'created_by',
        'age',
        'daily_sun_exposure_hours',
        'social_event',
        'upcoming_travel',
        'medical_history',
        'allergies',
        'is_pregnant',
        'breastfeeding',
        'diagnosis',
        'parameters_with_abnormal_scores',
        'treatment_plan_type',
        'treatment_plan',
    ];

    protected $casts = [
        'medical_history' => 'array',
        'allergies' => 'array',
        'diagnosis' => 'array',
        'parameters_with_abnormal_scores' => 'array',
        'treatment_plan' => 'array',
        'is_pregnant' => 'boolean',
    ];

    protected static function booted() {
        static::creating(function ($assessment) {
            $selectedUser = User::find($assessment->user_id);
            if ($selectedUser && $selectedUser->clinic_id) {
                $assessment->clinic_id = $selectedUser->clinic_id;
            }
        });
    }

    protected $appends = ['images'];
    
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
            ];
        });
    }

     /**
     * Relationships
     */

    /**
     * Parent Assessment (if applicable)
     */
    public function parentAssessment()
    {
        return $this->belongsTo(self::class, 'assessment_id');
    }

    /**
     * User associated with the assessment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clinic associated with the assessment
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Creator of the assessment
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Child assessments (if self-referencing)
     */
    public function children()
    {
        return $this->hasMany(self::class, 'assessment_id');
    }
}
