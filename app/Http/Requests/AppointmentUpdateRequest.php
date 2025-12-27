<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Models\Clinic;
use App\Models\User;
use App\Models\TreatmentSession;

use Carbon\Carbon;

class AppointmentUpdateRequest extends FormRequest
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
    public function rules()
    {
        $user = auth()->user();
        $appointment = $this->route('appointment');

        return [
            'type'                => ['nullable', 'in:consult,treatment,express'],
            'clinic_id'           => ['nullable', 'integer', 'exists:clinics,id'],
            'therapist_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function($attr, $value, $fail) use ($user, $appointment) {
                    $clinicId = $user->clinic_id ?? $this->clinic_id ?? $appointment->clinic_id;

                    $exists = User::where('id', $value)->where('clinic_id', $clinicId)->exists();

                    if (! $exists) {
                        $fail("Selected therapist does not belong to this clinic.");
                    }
                }
            ],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function($attr, $value, $fail) use ($user, $appointment) {
                    $clinicId = $user->clinic_id ?? $this->clinic_id ?? $appointment->clinic_id;

                    $exists = User::where('id', $value)->where('clinic_id', $clinicId)->exists();

                    if (! $exists) {
                        $fail("Selected user does not belong to this clinic.");
                    }
                }
            ],

            'assessment_id'        => ['nullable', 'required_if:type,treatment', 'exists:assessments,id'],
            'treatment_session_id' => ['nullable', 'required_if:type,treatment', 'exists:treatment_sessions,id'],

            'start_datetime'       => ['nullable', 'date_format:Y-m-d H:i', 'after_or_equal:now'],
            'end_datetime'         => ['nullable', 'date_format:Y-m-d H:i', 'after_or_equal:start_datetime'],

            'notes' => 'nullable|string',
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

            $therapistId = $this->input('therapist_id');

            if ($therapistId) {
                $therapist = User::find($therapistId);

                // Check if therapist exists and role is "therapist"
                if (! $therapist || $therapist->hasRole('therapist') === false) {
                    $validator->errors()->add('therapist_id', 'The selected therapist must have the therapist role.');
                }
            }
        });
    }
}
