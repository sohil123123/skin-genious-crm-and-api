<?php

namespace App\Services\Availability;

use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserWeeklySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public const SLOT_MINUTES = 15;

    /**
     * Appointment statuses that block time & beds
     */
    public const BLOCKING_STATUSES = [
        'pending',
        'confirmed',
        'in_progress',
    ];

    /* ============================================================
     | PUBLIC API
     |============================================================ */

    /**
     * Returns available slot start times (HH:MM)
     */
    public function getAvailableStartTimes(
        int $clinicId,
        int $therapistId,
        CarbonImmutable $date,
        int $durationMinutes,
        int $bedsRequired = 1
    ): array {
        $durationMinutes = $this->normalizeDuration($durationMinutes);

        // 1️⃣ Base schedule
        $windows = $this->getWeeklyWindows($clinicId, $therapistId, $date);

        // 2️⃣ Apply exceptions
        $windows = $this->applyExceptions($windows, $clinicId, $therapistId, $date);

        if ($windows->isEmpty()) {
            return [];
        }

        // 3️⃣ Expand to slots
        $slots = $this->expandWindowsToSlots($windows, $durationMinutes);

        if ($slots->isEmpty()) {
            return [];
        }

        // 4️⃣ Remove therapist conflicts
        $slots = $this->removeTherapistConflicts(
            $slots,
            $clinicId,
            $therapistId,
            $date,
            $durationMinutes
        );

        if ($slots->isEmpty()) {
            return [];
        }

        // 5️⃣ Remove bed capacity conflicts
        $slots = $this->removeBedCapacityConflicts(
            $slots,
            $clinicId,
            $date,
            $durationMinutes,
            $bedsRequired
        );

        return $slots
            ->mapWithKeys(fn ($t) => [$t => $t])
            ->all();
    }

    /**
     * HARD-LOCKED booking entry point
     */
    public function bookWithLock(
        int $clinicId,
        int $therapistId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $bedsRequired,
        callable $createCallback,
        ?int $ignoreAppointmentId = null
    ) {
        return DB::transaction(function () use (
            $clinicId,
            $therapistId,
            $start,
            $end,
            $bedsRequired,
            $createCallback,
            $ignoreAppointmentId
        ) {
            /**
             * 1️⃣ Lock clinic row
             */
            $clinic = Clinic::query()
                ->where('id', $clinicId)
                ->lockForUpdate()
                ->firstOrFail();

            /**
             * 2️⃣ Therapist overlap (HARD)
             */
            $therapistOverlap = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->where('therapist_id', $therapistId)
                ->whereIn('status', self::BLOCKING_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                      ->where('end_datetime', '>', $start);
                });

            if ($ignoreAppointmentId) {
                $therapistOverlap->where('id', '!=', $ignoreAppointmentId);
            }

            if ($therapistOverlap->exists()) {
                throw new \RuntimeException('Therapist already booked in this time slot.');
            }

            /**
             * 3️⃣ Bed capacity overlap (HARD)
             */
            $bedsInUse = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('status', self::BLOCKING_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                      ->where('end_datetime', '>', $start);
                })
                ->selectRaw("
                    COALESCE(
                        SUM(JSON_EXTRACT(resources_used, '$.beds')),
                        0
                    ) as beds_used
                ")
                ->value('beds_used');

            if (($bedsInUse + $bedsRequired) > (int) $clinic->beds) {
                throw new \RuntimeException('No bed available for this time slot.');
            }

            /**
             * 4️⃣ All checks passed → create appointment
             */
            return $createCallback();
        }, 5);
    }

    /* ============================================================
     | WEEKLY + EXCEPTION LOGIC
     |============================================================ */

    private function getWeeklyWindows(
        int $clinicId,
        int $therapistId,
        CarbonImmutable $date
    ): Collection {
        $dow = $date->isoWeekday();

        return UserWeeklySchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $therapistId)
            ->where('day_of_week', $dow)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time'])
            ->map(function ($row) use ($date) {
                return [
                    'start' => CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $row->start_time),
                    'end'   => CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $row->end_time),
                ];
            })
            ->filter(fn ($w) => $w['end']->gt($w['start']))
            ->values();
    }

    private function applyExceptions(
        Collection $windows,
        int $clinicId,
        int $therapistId,
        CarbonImmutable $date
    ): Collection {
        $exceptions = AvailabilityException::query()
            ->where('clinic_id', $clinicId)
            ->where('exceptionable_type', User::class)
            ->where('exceptionable_id', $therapistId)
            ->where('is_active', true)
            ->whereIn('status', ['approved'])
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->get();

        // Override wipes weekly schedule
        if ($override = $exceptions->firstWhere('type', 'override_hours')) {
            return $this->windowsFromException($override, $date);
        }

        // Full day leave wipes everything
        if ($exceptions->contains('type', 'leave_full_day')) {
            return collect();
        }

        // Add extra hours
        foreach ($exceptions->where('type', 'extra_hours') as $ex) {
            $windows = $windows->merge($this->windowsFromException($ex, $date));
        }

        $windows = $this->mergeWindows($windows);

        // Subtract blocked / partial leave
        foreach ($exceptions->whereIn('type', ['leave_partial', 'blocked_hours']) as $ex) {
            foreach ($this->windowsFromException($ex, $date) as $block) {
                $windows = $this->subtractWindow(
                    $windows,
                    $block['start'],
                    $block['end']
                );
            }
        }

        return $this->mergeWindows($windows);
    }

    private function windowsFromException($exception, CarbonImmutable $date): Collection
    {
        $start = $exception->start_time
            ? CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $exception->start_time)
            : $date->startOfDay();

        $end = $exception->end_time
            ? CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $exception->end_time)
            : $date->endOfDay()->addSecond();

        return collect([[
            'start' => $start,
            'end'   => $end,
        ]]);
    }

    /* ============================================================
     | SLOT & CONFLICT LOGIC
     |============================================================ */

    private function expandWindowsToSlots(
        Collection $windows,
        int $durationMinutes
    ): Collection {
        $slots = collect();

        foreach ($windows as $w) {
            $start = $this->alignUp($w['start']);
            $lastStart = $w['end']->subMinutes($durationMinutes);

            for ($t = $start; $t->lte($lastStart); $t = $t->addMinutes(self::SLOT_MINUTES)) {
                $slots->push($t->format('H:i'));
            }
        }

        return $slots->unique()->values();
    }

    private function removeTherapistConflicts(
        Collection $slots,
        int $clinicId,
        int $therapistId,
        CarbonImmutable $date,
        int $durationMinutes
    ): Collection {
        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('therapist_id', $therapistId)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->get(['start_datetime', 'end_datetime']);

        return $slots->filter(function ($hm) use ($appointments, $date, $durationMinutes) {
            $start = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $hm);
            $end = $start->addMinutes($durationMinutes);

            foreach ($appointments as $a) {
                if ($start->lt($a->end_datetime) && $end->gt($a->start_datetime)) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    private function removeBedCapacityConflicts(
        Collection $slots,
        int $clinicId,
        CarbonImmutable $date,
        int $durationMinutes,
        int $bedsRequired
    ): Collection {
        if ($bedsRequired <= 0) return $slots;

        $clinicBeds = Clinic::where('id', $clinicId)->value('beds');

        return $slots->filter(function ($hm) use (
            $clinicId,
            $date,
            $durationMinutes,
            $bedsRequired,
            $clinicBeds
        ) {
            $start = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $hm);
            $end = $start->addMinutes($durationMinutes);

            $bedsInUse = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('status', self::BLOCKING_STATUSES)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                      ->where('end_datetime', '>', $start);
                })
                ->selectRaw("
                    COALESCE(
                        SUM(JSON_EXTRACT(resources_used, '$.beds')),
                        0
                    ) as beds_used
                ")
                ->value('beds_used');

            return ($bedsInUse + $bedsRequired) <= $clinicBeds;
        })->values();
    }

    /* ============================================================
     | HELPERS
     |============================================================ */

    private function mergeWindows(Collection $windows): Collection
    {
        $windows = $windows->sortBy(fn ($w) => $w['start']->timestamp)->values();
        if ($windows->isEmpty()) return $windows;

        $merged = collect([$windows->first()]);

        foreach ($windows->slice(1) as $w) {
            $last = $merged->last();

            if ($w['start']->lte($last['end'])) {
                $last['end'] = $last['end']->max($w['end']);
                $merged[$merged->count() - 1] = $last;
            } else {
                $merged->push($w);
            }
        }

        return $merged->values();
    }

    private function subtractWindow(
        Collection $windows,
        CarbonImmutable $blockStart,
        CarbonImmutable $blockEnd
    ): Collection {
        return $windows->flatMap(function ($w) use ($blockStart, $blockEnd) {
            $parts = [];

            if ($blockEnd->lte($w['start']) || $blockStart->gte($w['end'])) {
                return [$w];
            }

            if ($blockStart->gt($w['start'])) {
                $parts[] = [
                    'start' => $w['start'],
                    'end'   => $blockStart,
                ];
            }

            if ($blockEnd->lt($w['end'])) {
                $parts[] = [
                    'start' => $blockEnd,
                    'end'   => $w['end'],
                ];
            }

            return $parts;
        })
        ->filter(fn ($w) => $w['end']->gt($w['start']))
        ->values();
    }

    private function normalizeDuration(int $minutes): int
    {
        $minutes = max(self::SLOT_MINUTES, $minutes);
        $rem = $minutes % self::SLOT_MINUTES;
        return $rem === 0 ? $minutes : $minutes + (self::SLOT_MINUTES - $rem);
    }

    private function alignUp(CarbonImmutable $dt): CarbonImmutable
    {
        $rem = $dt->minute % self::SLOT_MINUTES;
        return $rem === 0
            ? $dt->setSecond(0)
            : $dt->addMinutes(self::SLOT_MINUTES - $rem)->setSecond(0);
    }
}
