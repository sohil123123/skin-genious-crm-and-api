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
        protected ?\DateTime $end,
        protected ?int $excludeId = null,
        protected int $gap = 30
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->start || ! $this->end) return;

        $start = Carbon::instance($this->start)->subMinutes($this->gap);
        $end = Carbon::instance($this->end)->addMinutes($this->gap);

        // Count existing appointments at the exact timestamp
        $count = $this->clinic->appointments()
            ->whereBetween('appointment_datetime', [$start,$end])
            ->when($this->excludeId, fn ($q) =>
                $q->where('id', '<>', $this->excludeId)
            )
            ->where('status', '<>', 'cancelled')
            ->count();

        if ($count >= $this->clinic->number_of_beds) {
            $fail("❌ No beds available at this time ({$this->start->format('d M Y h:i A')} to {$end->format('d M Y h:i A')}) in this clinic. Please leave at least a 30-minute gap before or after another appointment.");
        }
    }
}
