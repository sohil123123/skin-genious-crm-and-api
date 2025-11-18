<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
// use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppointmentResource extends BaseResource
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
            'type' => $this->clinic_id,
            'appointment_datetime' => $this->appointment_datetime,
            'status' => $this->status,
            'notes' => $this->notes,
            'clinic' => $this->whenLoaded('clinic'),
            'client' => new UserResource($this->whenLoaded('client')),
            'therapist' => new UserResource($this->whenLoaded('therapist')),
            'createdBy' => new UserResource($this->whenLoaded('createdBy')),
            'assessment' => $this->whenLoaded('assessment'),
            'treatment_session' => $this->whenLoaded('treatmentSession'),
        ];
    }
}
