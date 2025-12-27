<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Http\Requests\AppointmentRequest;
use App\Http\Requests\AppointmentUpdateRequest;

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

    // public function store(AppointmentRequest $request)
    // {
    //     // Create the assessment record
    //     $appointment = $this->model->create($request->validated());

    //     // Wrap in resource for clean, formatted API output
    //     $resource = new AppointmentResource($appointment);

    //     return $this->success('Appointment created successfully', $resource);
    // }

    public function store(AppointmentRequest $request, AvailabilityService $availability)
    {
        $data = $request->validated();

        // validate slot availability
        $availability->assertBookable(
            clinicId: (int)$data['clinic_id'],
            therapistId: (int)$data['therapist_id'],
            start: Carbon::parse($data['start_datetime']),
            end: Carbon::parse($data['end_datetime'])
        );
        exit;

        // Create the assessment record
        $appointment = $this->model->create($data);

        // Wrap in resource for clean, formatted API output
        $resource = new AppointmentResource($appointment);

        return $this->success('Appointment created successfully', $resource);
    }


    // public function update(AppointmentRequest $request, Appointment $appointment)
    // {
    //     // Create the assessment record
    //     $update_input = $request->validated();

    //     $appointment->update($update_input);

    //     // Wrap in resource for clean, formatted API output
    //     $resource = new AppointmentResource($appointment);

    //     return $this->success('Appointment updated successfully', $resource);
    // }

    public function update(AppointmentUpdateRequest $request, Appointment $appointment, AvailabilityService $availability)
    {
        // if ($request->user()->cannot('update', $appointment)) {
        //     return $this->error('Unauthorized', ['You are not allowed to update this appointment.'], 403);
        // }

        $this->authorize('update', $appointment);

        $data = $request->validated();

        $newTherapistId = $data['therapist_id'] ?? $appointment->therapist_id;
        $newClinicId    = $data['clinic_id'] ?? $appointment->clinic_id;

        $start = $appointment->start_datetime ? Carbon::parse($appointment->start_datetime) : null;
        $end   = $appointment->end_datetime ? Carbon::parse($appointment->end_datetime) : null;

        $timeChanging = false;

        if (isset($data['start_datetime']) || isset($data['end_datetime'])) {
            $start = Carbon::parse($data['start_datetime'] ?? $appointment->start_datetime);
            $end   = Carbon::parse($data['end_datetime'] ?? $appointment->end_datetime);

            if ($end->lt($start)) {
                return $this->error('Validation Error', ['end_datetime' => ['The end datetime must be after or equal to the start datetime.']], 422);
            }

            $timeChanging = true;
        }

        $therapistChanging = isset($data['therapist_id']) && ((int)$data['therapist_id'] !== (int)$appointment->therapist_id);

        // Re-validate if therapist/time/clinic changed
        if ($timeChanging || $therapistChanging || isset($data['clinic_id'])) {
            $availability->assertBookable(
                clinicId: (int)$newClinicId,
                therapistId: (int)$newTherapistId,
                start: $start,
                end: $end,
                ignoreAppointmentId: $appointment->id
            );
        }

        $appointment->update($data);

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
                'in:pending,confirmed,in_progress,completed,cancelled,no_show',
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
            'clinic_id'        => ['required', 'integer', 'exists:clinics,id'],
            'therapist_id'     => ['required', 'integer', 'exists:users,id'],

            // single date
            'date'             => ['nullable', 'date'],

            // range
            'from_date'        => ['nullable', 'date'],
            'to_date'          => ['nullable', 'date', 'after_or_equal:from_date'],

            'slot_interval'    => ['nullable', 'integer', 'in:5,10,15,20,30,60'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $slotInterval = (int)($validated['slot_interval'] ?? 15);
        $duration     = (int)($validated['duration_minutes'] ?? 15);

        // 🟢 Single date
        if (!empty($validated['date'])) {
            $data = $availability->getSlotsForDate(
                clinicId: (int)$validated['clinic_id'],
                therapistId: (int)$validated['therapist_id'],
                date: $validated['date'],
                slotIntervalMinutes: $slotInterval,
                appointmentDurationMinutes: $duration
            );

            return $this->success('Slots fetched successfully', [
                'mode' => 'single',
                'data' => $data,
            ]);
        }

        // 🟠 Date range
        if (!empty($validated['from_date']) && !empty($validated['to_date'])) {
            $data = $availability->getSlotsForDateRange(
                clinicId: (int)$validated['clinic_id'],
                therapistId: (int)$validated['therapist_id'],
                fromDate: $validated['from_date'],
                toDate: $validated['to_date'],
                slotIntervalMinutes: $slotInterval,
                appointmentDurationMinutes: $duration
            );

            return $this->success('Slots fetched successfully', [
                'mode' => 'range',
                'from' => $validated['from_date'],
                'to'   => $validated['to_date'],
                'data' => $data,
            ]);
        }

        return $this->error('Either date or from_date & to_date is required', 422);

    }

}
