<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

use App\Models\Appointment;

class TherapistAvailabilityRule implements ValidationRule
{
    public function __construct(
        protected ?int $therapistId,
        protected ?int $clinicId,
        protected ?\DateTime $start,
        protected ?int $duration,
        protected ?int $excludeId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = Carbon::instance($this->start);
        $end   = (clone $start)->addMinutes($this->duration);

        $conflict = Appointment::with('client')
            ->where('therapist_id', $this->therapistId)
            ->where('clinic_id', $this->clinicId)
            ->where('status', '!=', 'cancelled')
            ->when($this->excludeId, fn($q) => $q->where('id', '!=', $this->excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->where('appointment_datetime', '<', $end)
                ->whereRaw("DATE_ADD(appointment_datetime, INTERVAL duration MINUTE) > ?", [$start]);
            })
            ->first();

        if ($conflict) {
            $cs = Carbon::parse($conflict->appointment_datetime);
            $ce = (clone $cs)->addMinutes($conflict->duration);

            $fail("❌ Therapist unavailable.<br>
                <strong>{$conflict->client->name}</strong> has an appointment from
                {$cs->format('h:i A')} to {$ce->format('h:i A')}.");
        }
    }

    // public function validate(string $attribute, mixed $value, Closure $fail): void
    // {
    //     if (! $this->therapistId || ! $this->clinicId || ! $this->start || ! $this->end) return;

    //     $overlaps = Appointment::overlapping(
    //         $this->therapistId,
    //         $this->clinicId,
    //         $this->start,
    //         $this->end,
    //         $this->excludeId,
    //         $this->duration
    //     )
    //     ->exists();
    //     // ->ddRawSql();
    //     // dd($overlaps);

    //     if ($overlaps) {
    //         $fail("❌ This time slot is already booked for the selected therapist and clinic. Please leave at least a {$this->duration}-minute gap before or after another appointment.");
    //     }
    // }
}
