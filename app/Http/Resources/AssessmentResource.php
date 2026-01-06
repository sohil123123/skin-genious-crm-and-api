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
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
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
            'treatment_sessions' => $this->treatment_sessions,
            'selected_plan_type' => $this->selected_plan_type,
            'status' => $this->status,
            'therapist_notes' => $this->therapist_notes,
            'images' => $this->images,
            'post_images' => $this->post_images,
            'user' => new UserResource($this->whenLoaded('user')),
            'createdBy' => new UserResource($this->whenLoaded('createdBy')),
            // 'parentAssessment' => new AssessmentResource($this->whenLoaded('parentAssessment')),
            // 'children' => AssessmentResource::collection($this->whenLoaded('children')),
        ];
    }
}
