<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Models\Clinic;
use App\Models\User;
use App\Models\TreatmentSession;

use Carbon\Carbon;

use App\Rules\TherapistAvailabilityRule;
use App\Rules\ClinicBedAvailabilityRule;

class AppointmentRequest extends FormRequest
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
    // public function rules(): array
    // {
    //     $user = auth()->user();

    //     return [
    //         'type' => 'required|in:consult,treatment',
    //         'clinic_id' => [
    //             // required only for admin roles
    //             $user->hasRole(['super_admin']) ? 'required' : 'nullable',

    //             // if therapist/manager, clinic_id should match user's clinic
    //             function ($attr, $value, $fail) use ($user) {

    //                 if ($user->hasRole(['therapist','clinic_manager'])) {

    //                     // Replace clinic_id with user's clinic_id automatically
    //                     $this->merge(['clinic_id' => $user->clinic_id]);

    //                     // Block if they try to override clinic_id manually
    //                     if ($value && $value != $user->clinic_id) {
    //                         $fail("You cannot select another clinic.");
    //                     }
    //                 }
    //             }
    //         ],

    //         'user_id' => [
    //             'required',
    //             'exists:users,id',
    //             function($attr, $value, $fail) use ($user) {
    //                 $clinicId = $user->clinic_id ?? $this->clinic_id;

    //                 $exists = User::where('id', $value)
    //                     ->where('clinic_id', $clinicId)
    //                     ->exists();

    //                 if (! $exists) {
    //                     $fail("Selected user does not belong to this clinic.");
    //                 }
    //             }
    //         ],

    //         'therapist_id' => [
    //             // Required only for admin / clinic manager
    //             $user->hasRole(['therapist'])
    //                 ? 'nullable'
    //                 : 'required',

    //             'exists:users,id',

    //             function($attr, $value, $fail) use ($user) {

    //                 // If therapist logged in → auto-set therapist_id
    //                 if ($user->hasRole('therapist')) {

    //                     $this->merge(['therapist_id' => $user->id]);

    //                     // Prevent someone manually passing different therapist_id
    //                     if ($value && $value != $user->id) {
    //                         $fail("You cannot assign another therapist.");
    //                     }
    //                 }

    //                 // Check therapist belongs to clinic
    //                 $clinicId = $user->clinic_id ?? $this->clinic_id;

    //                 $exists = User::where('id', $this->therapist_id)
    //                     ->where('clinic_id', $clinicId)
    //                     ->exists();

    //                 if (! $exists) {
    //                     $fail("Selected therapist does not belong to this clinic.");
    //                 }
    //             }
    //         ],

    //         'assessment_id' => [
    //             'required_unless:type,consult',
    //             'nullable'
    //         ],
    //         'treatment_session_id' => [
    //             'required_unless:type,consult',
    //             'nullable'
    //         ],
    //         'appointment_datetime' => [
    //             'required',
    //             'date',
    //             'after_or_equal:now',  // prevent past dates
    //             Rule::unique('appointments', 'appointment_datetime')
    //                 ->where(fn($q) => $q
    //                     ->where('therapist_id', $this->therapist_id)
    //                     ->where('clinic_id', $this->clinic_id)
    //                 )
    //                 ->ignore($this->route('appointment')), // for EDIT mode
    //         ],
    //         'status' => 'nullable|in:scheduled,confirmed,in_progress,completed,cancelled',
    //         'notes' => 'nullable|string',
    //     ];
    // }

    public function rules()
    {
        $user = auth()->user();

        return [
            'type' => ['required', 'in:treatment,consult'],

            // ----------------------------
            // CLINIC
            // ----------------------------
            'clinic_id' => [
                // required only for admin roles
                $user->hasRole(['super_admin']) ? 'required' : 'nullable',
                'exists:clinics,id',
            ],

            // ----------------------------
            // PATIENT
            // ----------------------------
            'user_id' => [
                'required',
                'exists:users,id',
                function($attr, $value, $fail) use ($user) {
                    $clinicId = $user->clinic_id ?? $this->clinic_id;

                    $exists = User::where('id', $value)
                        ->where('clinic_id', $clinicId)
                        ->exists();

                    if (! $exists) {
                        $fail("Selected user does not belong to this clinic.");
                    }
                }
            ],

            // ----------------------------
            // THERAPIST
            // ----------------------------
            'therapist_id' => [
                // Required only for admin / clinic manager
                $user->hasRole(['therapist']) ? 'nullable' : 'required',
                'exists:users,id',
                function($attr, $value, $fail) use ($user) {

                    if(!$user->hasRole(['therapist'])){
                        // Check therapist belongs to clinic
                        $clinicId = $user->clinic_id ?? $this->clinic_id;

                        $exists = User::where('id', $this->therapist_id)
                            ->where('clinic_id', $clinicId)
                            ->exists();

                        if (! $exists) {
                            $fail("Selected therapist does not belong to this clinic.");
                        }
                    }
                }
            ],

            // ----------------------------
            // TYPE-BASED REQUIRED FIELDS
            // ----------------------------
            'assessment_id' => ['required_unless:type,consult', 'nullable', 'exists:assessments,id'],
            'treatment_session_id' => ['required_unless:type,consult', 'nullable', 'exists:treatment_sessions,id'],

            // ----------------------------
            // APPOINTMENT DATE/TIME
            // ----------------------------
            'appointment_datetime' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {

                    if (!$value) return;

                    $datetime = Carbon::parse($value);
                    $now = Carbon::now();

                    $duration = $this->type === 'consult' ? config('project.appointment_consult_duration') : get_treatment_session_duration($this->treatment_session_id);

                    // 1️⃣ Block Past Date/Time
                    if ($datetime->isPast())
                        return $fail("❌ You cannot select a past date/time.");

                    if ($datetime->isToday() && $datetime->lt($now))
                        return $fail("❌ Selected time has already passed.");

                    // 2️⃣ Clinic Hours Check
                    $clinic = Clinic::find($this->clinic_id);
                    if (!$clinic) return;

                    $clinicStart = Carbon::parse($clinic->start_time);
                    $clinicEnd   = Carbon::parse($clinic->end_time)->subMinutes($duration);

                    if ($datetime->format('H:i:s') < ($clinicStart->format('H:i:s')) || $datetime->format('H:i:s') > ($clinicEnd->format('H:i:s'))) {
                        return $fail("❌ Appointment must be within clinic hours: " .
                            $clinicStart->format('h:i A') . " – " . $clinicEnd->format('h:i A'));
                    }

                    // 3️⃣ Therapist Availability Check
                    $therapistRule = new TherapistAvailabilityRule(
                        therapistId: $this->therapist_id,
                        clinicId: $this->clinic_id,
                        start: $datetime,
                        duration: $duration,
                        excludeId: $this->route('appointment')?->id,
                    );
                    $therapistRule->validate($attribute, $value, $fail);

                    // 4️⃣ Bed Availability Check
                    $bedRule = new ClinicBedAvailabilityRule(
                        clinic: $clinic,
                        start: $datetime,
                        duration: $duration,
                        excludeId: $this->route('appointment')?->id
                    );
                    $bedRule->validate($attribute, $value, $fail);

                },
            ],
            'notes' => 'nullable|string',
        ];

    }

    protected function prepareForValidation()
    {
        $user = auth()->user();

        // Auto fill clinic_id for therapist/clinic manager
        if ($user->hasRole(['therapist', 'clinic_manager'])) {
            $this->merge([
                'clinic_id' => $user->clinic_id,
            ]);
        }

        // Auto fill therapist_id for therapist role
        if ($user->hasRole('therapist')) {
            $this->merge([
                'therapist_id' => $user->id,
            ]);
        }
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
