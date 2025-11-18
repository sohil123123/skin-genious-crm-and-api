<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Http\Requests\AppointmentRequest;

use App\Http\Resources\AppointmentResource;

use App\Models\Appointment;

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

}
