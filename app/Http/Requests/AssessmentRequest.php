<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Models\User;

class AssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => 'nullable|string',
            'assessment_id' => 'sometimes|integer|exists:assessments,id',
            'user_id' => 'required|exists:users,id',
            'age' => 'nullable|integer|min:0|max:120',
            'daily_sun_exposure_hours' => 'nullable|string',
            'social_event' => 'nullable|in:yes,no',
            'upcoming_travel' => 'nullable|in:yes,no',
            'medical_history' => 'nullable|array',
            'allergies' => 'nullable|array',
            'skin_temp_for_head' => 'nullable|string',
            'left_cheek_temp' => 'nullable|string',
            'right_cheek_temp' => 'nullable|string',
            'recent_peel_or_laser' => 'nullable|string',
            'retinol_used_last_night' => 'nullable|string',
            'is_pregnant' => 'nullable|boolean',
            'breastfeeding' => 'nullable|in:yes,no',
            'iv_inputs' => 'nullable|array',
            'assessment_type' => 'nullable|string|in:iv,instant-normal,normal,instant-iv',
            'feature_packet' => 'nullable|array',
            'diagnosis' => 'nullable|array',
            'post_diagnosis' => 'nullable|array',
            'parameters_with_abnormal_scores' => 'nullable|array',
            'selected_plan_type' => 'nullable|string|in:single,express,multiple,single_session_option_1,single_session_option_2,plan_option,budget_option',
        'treatment_plans' => 'nullable|array',
            'treatment_sessions' => 'nullable|array',
            'iv_treatment_plan' => 'nullable|array',
            'iv_selected_option' => 'nullable',
            'nurse_run_sheet' => 'nullable',
            'status' => 'nullable|in:in_progress,pending,completed,incomplete,cancelled,overdue',
            'therapist_notes' => 'nullable|string',
            // 'images' => 'required|array|min:1',
            // 'images.*' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // Each image: max 2MB
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $userId = $this->input('user_id');

            if ($userId) {
                $user = User::find($userId);

                // Check if user exists and role is "client"
                if (! $user || $user->hasRole('client') === false) {
                    $validator->errors()->add('user_id', 'The selected user must have the user role.');
                }
            }
        });
    }
}
