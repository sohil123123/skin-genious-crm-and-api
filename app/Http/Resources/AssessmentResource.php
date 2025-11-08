<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
// use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AssessmentResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'user_id' => $this->user_id,
            'clinic_id' => $this->clinic_id,
            'created_by' => $this->created_by,
            'age' => $this->age,
            'daily_sun_exposure_hours' => $this->daily_sun_exposure_hours,
            'social_event' => $this->social_event,
            'upcoming_travel' => $this->upcoming_travel,
            'medical_history' => $this->medical_history,
            'allergies' => $this->allergies,
            'is_pregnant' => $this->is_pregnant,
            'breastfeeding' => $this->breastfeeding,
            'diagnosis' => $this->diagnosis,
            'parameters_with_abnormal_scores' => $this->parameters_with_abnormal_scores,
            'treatment_plan_type' => $this->treatment_plan_type,
            'treatment_plan' => $this->treatment_plan,
            'status' => $this->status,
            'therapist_notes' => $this->therapist_notes,
            'images' => $this->getMedia('assessment_images')->map(function (Media $media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                ];
            })->toArray(),
            'post_images' => $this->getMedia('post_assessment_images')->map(function (Media $media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                ];
            })->toArray(),
            'user' => new UserResource($this->whenLoaded('user')),
            'createdBy' => new UserResource($this->whenLoaded('createdBy')),
            'parentAssessment' => new AssessmentResource($this->whenLoaded('parentAssessment')),
            'children' => AssessmentResource::collection($this->whenLoaded('children')),
        ];
    }
}
