<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use App\Models\Appointment;

class AppointmentAvailability implements ValidationRule
{
    public function __construct(
        protected ?int $therapistId,
        protected ?int $clinicId,
        protected ?\DateTime $start,
        protected ?\DateTime $end,
        protected ?int $excludeId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->therapistId || ! $this->clinicId || ! $this->start || ! $this->end) {
            return;
        }

        $overlaps = Appointment::overlapping(
            $this->therapistId,
            $this->clinicId,
            $this->start,
            $this->end,
            $this->excludeId
        )
        ->exists();
        // ->ddRawSql();
        // dd($overlaps);

        if ($overlaps) {
            $fail('❌ This time slot is already booked for the selected therapist and clinic. Please leave at least a 30-minute gap before or after another appointment.');
        }
    }
}
