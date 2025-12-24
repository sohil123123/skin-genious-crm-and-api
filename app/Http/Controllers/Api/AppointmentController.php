<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Http\Requests\AppointmentRequest;

use App\Http\Resources\AppointmentResource;

use App\Services\Availability\AvailabilityService;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\TreatmentSession;

use Carbon\Carbon;

class AppointmentController extends BaseApiController
{
    public function __construct(Appointment $model, Request $request)
    {
        parent::__construct($model, $request, 'Appointment', 'Api');
    }

    public function store(AppointmentRequest $request)
    {
        // Create the assessment record
        $appointment = $this->model->create($request->validated());

        // Wrap in resource for clean, formatted API output
        $resource = new AppointmentResource($appointment);

        return $this->success('Appointment created successfully', $resource);
    }

    public function update(AppointmentRequest $request, Appointment $appointment)
    {
        // Create the assessment record
        $update_input = $request->validated();

        $appointment->update($update_input);

        // Wrap in resource for clean, formatted API output
        $resource = new AppointmentResource($appointment);

        return $this->success('Appointment updated successfully', $resource);
    }

    public function updateTreatmentSessionId(Request $request, $appointment_id)
    {
        $appointment = $this->model->find($appointment_id);

        if($appointment->type->value !== 'consult')
            return $this->error('Error', ['Invalid appointment type'], HTTP_BAD_REQUEST);

        $validated = $request->validate([
            'assessment_id' => [
                'required',
                'exists:assessments,id',
                function($attr, $value, $fail) use ($appointment) {
                    $userId = $appointment->user_id;

                    $exists = Assessment::where('id', $value)
                        ->where('user_id', $userId)
                        ->exists();

                    if (! $exists) {
                        $fail("Selected assessment does not belong to this appointment.");
                    }
                }
            ],
            'treatment_session_id' => [
                'required',
                'exists:treatment_sessions,id',
                function($attr, $value, $fail) use ($appointment) {
                    $userId = $appointment->user_id;

                    $exists = TreatmentSession::where('id', $value)
                        ->where('user_id', $userId)
                        ->exists();

                    if (! $exists) {
                        $fail("Selected treatment session does not belong to this appointment.");
                    }
                }
            ],
        ]);

        $appointment->update($validated);

        // Wrap in resource for clean, formatted API output
        $resource = new AppointmentResource($appointment->fresh());

        return $this->success('Appointment treatment session updated successfully', $resource);
    }

    public function updateStatus(Request $request, $appointment_id)
    {
        $appointment = $this->model->find($appointment_id);

        $validated = $request->validate([
            'status' => [
                'required',
                'in:scheduled,confirmed,in_progress,completed,cancelled',
            ],
        ]);

        $appointment->update($validated);

        $treatment_session = TreatmentSession::where('id', $appointment->treatment_session_id)->first();

        if ($treatment_session) {
            $treatment_session->update([
                'status' => $validated['status'],
            ]);
        }

        // Wrap in resource for clean, formatted API output
        $resource = new AppointmentResource($appointment->fresh());

        return $this->success('Appointment status updated successfully', $resource);
    }

    public function slots(Request $request, AvailabilityService $availability)
    {
        $validated = $request->validate([
            'clinic_id'      => ['required', 'integer', 'exists:clinics,id'],
            'therapist_id'   => ['required', 'integer', 'exists:users,id'],
            'date'           => ['required', 'date'],
            'slot_interval'  => ['nullable', 'integer', 'in:5,10,15,20,30,60'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $slotInterval = (int)($validated['slot_interval'] ?? 15);
        $duration     = (int)($validated['duration_minutes'] ?? 15);

        $result = $availability->getSlotsForDate(
            clinicId: (int)$validated['clinic_id'],
            therapistId: (int)$validated['therapist_id'],
            date: $validated['date'],
            slotIntervalMinutes: $slotInterval,
            appointmentDurationMinutes: $duration
        );

        return $this->success('Slots fetched successfully', $result);
    }

}
