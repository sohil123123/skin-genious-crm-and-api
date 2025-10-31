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
            'assessment_id' => 'sometimes|integer|exists:assessments,id',
            'user_id' => 'required|exists:users,id',
            'age' => 'nullable|integer|min:0|max:120',
            'daily_sun_exposure_hours' => 'nullable|string',
            'social_event' => 'nullable|in:yes,no',
            'upcoming_travel' => 'nullable|in:yes,no',
            'medical_history' => 'nullable|array',
            'allergies' => 'nullable|array',
            'is_pregnant' => 'nullable|boolean',
            'breastfeeding' => 'nullable|in:yes,no',
            'diagnosis' => 'nullable|array',
            'parameters_with_abnormal_scores' => 'nullable|array',
            'treatment_plan_type' => 'nullable|string',
            'treatment_plan' => 'nullable|array',
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

                // Check if user exists and role is "user"
                if (! $user || $user->hasRole('user') === false) {
                    $validator->errors()->add('user_id', 'The selected user must have the user role.');
                }
            }
        });
    }
}
