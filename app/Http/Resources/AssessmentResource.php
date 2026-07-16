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
            'name' => $this->user->first_name . ' ' . $this->user->last_name,
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
            'skin_temp_for_head' => $this->skin_temp_for_head,
            'left_cheek_temp' => $this->left_cheek_temp,
            'right_cheek_temp' => $this->right_cheek_temp,
            'recent_peel_or_laser' => $this->recent_peel_or_laser,
            'retinol_used_last_night' => $this->retinol_used_last_night,
            'is_pregnant' => $this->is_pregnant,
            'breastfeeding' => $this->breastfeeding,
            'iv_inputs' => $this->iv_inputs,
            'pigmentation_inputs' => $this->pigmentation_inputs,
            'recommended_full_plan' => $this->recommended_full_plan,
            'assessment_type' => $this->assessment_type,
            'feature_packet' => $this->feature_packet,
            'diagnosis' => $this->diagnosis,
            'post_diagnosis' => $this->post_diagnosis,
            'parameters_with_abnormal_scores' => $this->parameters_with_abnormal_scores,
            'iv_treatment_plan' => $this->iv_treatment_plan,
            'iv_selected_option' => $this->iv_selected_option,
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
