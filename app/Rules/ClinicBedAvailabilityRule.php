<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use App\Models\Clinic;

use Carbon\Carbon;

class ClinicBedAvailabilityRule implements ValidationRule
{
    public function __construct(
        protected Clinic $clinic,
        protected ?\DateTime $start,
        protected int $duration,
        protected ?int $excludeId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = Carbon::instance($this->start);
        $end   = (clone $start)->addMinutes($this->duration);

        $conflicts = $this->clinic->appointments()
            ->with('client')
            ->where('status', '!=', 'cancelled')
            ->when($this->excludeId, fn($q) => $q->where('id', '!=', $this->excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->where('appointment_datetime', '<', $end)
                ->whereRaw("DATE_ADD(appointment_datetime, INTERVAL duration MINUTE) > ?", [$start]);
            })
            ->get();

        if ($conflicts->count() >= $this->clinic->number_of_beds) {

            $details = "";
            foreach ($conflicts as $c) {
                $cs = Carbon::parse($c->appointment_datetime);
                $ce = (clone $cs)->addMinutes($c->duration);
                $details .= "• <strong>{$c->client->name}</strong>: {$cs->format('h:i A')} → {$ce->format('h:i A')}<br>";
            }

            $fail("❌ No beds available.<br><strong>Conflicting appointments:</strong><br>{$details}");
        }
    }

    // public function validate(string $attribute, mixed $value, Closure $fail): void
    // {
    //     if (! $this->start || ! $this->end) return;

    //     $start = Carbon::instance($this->start)->subMinutes($this->duration);
    //     $end = Carbon::instance($this->end)->addMinutes($this->duration);

    //     // Count existing appointments at the exact timestamp
    //     $count = $this->clinic->appointments()
    //         ->whereBetween('appointment_datetime', [$start,$end])
    //         ->when($this->excludeId, fn ($q) =>
    //             $q->where('id', '<>', $this->excludeId)
    //         )
    //         ->where('status', '<>', 'cancelled')
    //         ->count();

    //     if ($count >= $this->clinic->number_of_beds) {
    //         $fail("❌ No beds available at this time in this clinic. Please leave at least a {$this->duration}-minute gap before or after another appointment.");
    //     }
    // }
}
