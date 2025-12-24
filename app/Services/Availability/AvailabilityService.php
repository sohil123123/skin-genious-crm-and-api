<?php

namespace App\Services\Availability;

use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\UserWeeklySchedule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function getSlotsForDate(
        int $clinicId,
        int $therapistId,
        string $date,
        int $slotIntervalMinutes = 15,
        int $appointmentDurationMinutes = 15
    ): array {
        $day = Carbon::parse($date);
        $weekday = $day->isoWeekday(); // 1=Mon ... 7=Sun

        // 1) Base slots from weekly schedule
        $baseWindows = $this->getBaseWindowsFromWeeklySchedule($clinicId, $therapistId, $weekday);

        // 2) Apply exceptions (block/add/override)
        $exceptions = $this->getExceptionsForDate($clinicId, $therapistId, $day);
        $windows = $this->applyExceptionsToWindows($baseWindows, $exceptions, $day);

        // 3) Generate slots from final windows
        $candidateSlots = $this->generateSlotsFromWindows(
            $windows,
            $day,
            $slotIntervalMinutes,
            $appointmentDurationMinutes
        );
        // dd($candidateSlots);

        // 4) Remove booked overlaps
        $bookedBlocks = $this->getBookedBlocks($clinicId, $therapistId, $day);
        [$available, $unavailableBooked] = $this->filterBookedSlots($candidateSlots, $bookedBlocks);

        // 5) Unavailable because of leave/block/closed (optional: provide blocks summary)
        $unavailableByExceptions = $this->summarizeExceptionBlocks($exceptions, $day, $slotIntervalMinutes);

        // return [
        //     'date' => $day->toDateString(),
        //     'slot_interval' => $slotIntervalMinutes,
        //     'appointment_duration_minutes' => $appointmentDurationMinutes,
        //     'available_slots' => $available,
        //     'unavailable_slots' => array_values(array_merge($unavailableBooked, $unavailableByExceptions)),
        // ];

        return [
            'date' => $day->toDateString(),
            'interval' => $slotIntervalMinutes,

            // QCalendar-ready events
            'events' => array_values(array_merge(
                $this->formatBookedEventsForCalendar($unavailableBooked, $day),
                $this->formatExceptionEventsForCalendar($unavailableByExceptions, $day)
            )),

            // clickable slots only
            'available_slots' => array_map(fn ($s) => [
                'start' => $day->toDateString().' '.$s['start'],
                'end'   => $day->toDateString().' '.$s['end'],
                'isDisabled' => false
            ], $available),
        ];
    }

    public function assertBookable(
        int $clinicId,
        int $therapistId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreAppointmentId = null
    ): void {
        // Only allow 15-min aligned start/end by default (optional)
        if (!$this->isAlignedToMinutes($start, 15) || !$this->isAlignedToMinutes($end, 15)) {
            throw ValidationException::withMessages([
                'slot' => 'Slot must be aligned to 15-minute boundaries.',
            ]);
        }

        // Get availability windows for that date and ensure requested range is within any window
        $result = $this->getSlotsForDate(
            clinicId: $clinicId,
            therapistId: $therapistId,
            date: $start->toDateString(),
            slotIntervalMinutes: 15,
            appointmentDurationMinutes: (int)$start->diffInMinutes($end)
        );

        $requested = [
            'start' => $start->format('H:i'),
            'end'   => $end->format('H:i'),
        ];

        $ok = collect($result['available_slots'])->contains(function ($s) use ($requested) {
            return $s['start'] === $requested['start'] && $s['end'] === $requested['end'];
        });

        if (!$ok) {
            throw ValidationException::withMessages([
                'slot' => 'Selected slot is unavailable.',
            ]);
        }

        // Also ensure no overlapping appointment exists (hard check)
        $overlap = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('therapist_id', $therapistId)
            ->when($ignoreAppointmentId, fn($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_datetime', '<', $end)
                  ->where('end_datetime',   '>', $start);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'slot' => 'Selected slot overlaps an existing appointment.',
            ]);
        }
    }

    // ------------------------
    // Internals
    // ------------------------

    private function getBaseWindowsFromWeeklySchedule(int $clinicId, int $therapistId, int $weekday): array
    {
        $shifts = UserWeeklySchedule::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $therapistId)
            ->where('day_of_week', $weekday)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        // If no schedule exists, windows empty (unless exceptions add/override)
        return $shifts->map(fn($s) => [
            'start' => Carbon::parse($s->start_time)->format('H:i'),
            'end'   => Carbon::parse($s->end_time)->format('H:i'),
        ])->all();
    }

    private function getExceptionsForDate(int $clinicId, int $therapistId, Carbon $day)
    {
        // exceptionable polymorph: for therapist, exceptionable_type = User::class & id = therapistId
        // You also have clinic-level exceptions via exceptionable_type = Clinic::class & id = clinicId
        $date = $day->toDateString();

        return AvailabilityException::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where(function ($q) use ($therapistId, $clinicId) {
                $q->where(function ($qq) use ($therapistId) {
                    $qq->where('exceptionable_type', 'App\\Models\\User')
                       ->where('exceptionable_id', $therapistId);
                })->orWhere(function ($qq) use ($clinicId) {
                    $qq->where('exceptionable_type', 'App\\Models\\Clinic')
                       ->where('exceptionable_id', $clinicId);
                });
            })
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByRaw("FIELD(type,'override_hours','blocked_hours','leave_partial','leave_full_day','extra_hours')") // priority-ish
            ->get();
    }

    private function applyExceptionsToWindows(array $baseWindows, $exceptions, Carbon $day): array
    {
        $windows = $baseWindows;

        foreach ($exceptions as $ex) {
            $exStart = $ex->start_time ? Carbon::parse($ex->start_time)->format('H:i') : null;
            $exEnd   = $ex->end_time ? Carbon::parse($ex->end_time)->format('H:i') : null;

            $isFullDay = is_null($exStart) && is_null($exEnd);

            // "override" means replace schedule (for that date)
            if ($ex->effect === 'override' || $ex->type->value === 'override_hours') {
                if ($isFullDay) {
                    // override full day: treat as open full day (rare) -> 00:00-23:59
                    $windows = [['start' => '00:00', 'end' => '23:59']];
                } else {
                    $windows = [['start' => $exStart, 'end' => $exEnd]];
                }
                continue;
            }

            // "add" means add availability window
            if ($ex->effect === 'add' || $ex->type->value === 'extra_hours') {
                if ($isFullDay) {
                    $windows[] = ['start' => '00:00', 'end' => '23:59'];
                } else {
                    $windows[] = ['start' => $exStart, 'end' => $exEnd];
                }
                $windows = $this->mergeWindows($windows);
                continue;
            }

            // "block" means remove availability
            if ($ex->effect === 'block' || in_array($ex->type->value, ['blocked_hours','leave_partial','leave_full_day'], true)) {
                if ($isFullDay) {
                    $windows = []; // fully unavailable
                } else {
                    $windows = $this->subtractWindow($windows, ['start' => $exStart, 'end' => $exEnd]);
                }
                continue;
            }
        }

        return $this->mergeWindows($windows);
    }

    private function generateSlotsFromWindows(array $windows, Carbon $day, int $slotIntervalMinutes, int $durationMinutes): array
    {
        $slots = [];

        foreach ($windows as $w) {
            $start = Carbon::parse($day->toDateString() . ' ' . $w['start']);
            $end   = Carbon::parse($day->toDateString() . ' ' . $w['end']);

            // ensure end >= start
            if ($end->lte($start)) continue;

            $cursor = $start->copy();

            while ($cursor->copy()->addMinutes($durationMinutes)->lte($end)) {
                $slotEnd = $cursor->copy()->addMinutes($durationMinutes);

                $slots[] = [
                    'start' => $cursor->format('H:i'),
                    'end'   => $slotEnd->format('H:i'),
                ];

                $cursor->addMinutes($slotIntervalMinutes);
            }
        }

        // unique + sorted
        $slots = collect($slots)
            ->unique(fn($s) => $s['start'].'-'.$s['end'])
            ->sortBy('start')
            ->values()
            ->all();

        return $slots;
    }

    private function getBookedBlocks(int $clinicId, int $therapistId, Carbon $day): array
    {
        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('therapist_id', $therapistId)
            ->whereDate('start_datetime', $day->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get();

        return $appointments->map(function ($a) {
            return [
                'start' => Carbon::parse($a->start_datetime)->format('H:i'),
                'end'   => Carbon::parse($a->end_datetime)->format('H:i'),

                'appointment' => [
                    'id'             => $a->id,
                    'status'         => $a->status,
                    'status_color'   => $this->appointmentStatusColor($a->status->value),
                    'therapist_name' => $a->therapist?->name,
                    'client_name'    => $a->user?->name,
                    'clinic_name'    => $a->clinic?->name,
                ],
            ];
        })->all();
    }

    private function filterBookedSlots(array $slots, array $bookedBlocks): array
    {
        $available = [];
        $unavailable = [];

        foreach ($slots as $s) {
            $isBooked = false;

            foreach ($bookedBlocks as $b) {
                if ($this->timeOverlap($s['start'], $s['end'], $b['start'], $b['end'])) {
                    $isBooked = true;

                    $unavailable[] = [
                        'start' => $s['start'],
                        'end'   => $s['end'],
                        'reason'=> 'booked',
                        'appointment' => $b['appointment'],
                    ];

                    break;
                }
            }

            if (!$isBooked) {
                $available[] = $s;
            }
        }

        return [$available, $unavailable];
    }

    // private function summarizeExceptionBlocks($exceptions, Carbon $day): array
    // {
    //     $out = [];

    //     foreach ($exceptions as $ex) {
    //         $isBlocky = ($ex->effect === 'block') || in_array($ex->type->value, ['blocked_hours','leave_partial','leave_full_day'], true);
    //         if (!$isBlocky) continue;

    //         $st = $ex->start_time ? Carbon::parse($ex->start_time)->format('H:i') : '00:00';
    //         $en = $ex->end_time ? Carbon::parse($ex->end_time)->format('H:i') : '23:59';

    //         $out[] = [
    //             'start'  => $st,
    //             'end'    => $en,
    //             'reason' => $ex->type->value ?? 'blocked',
    //         ];
    //     }

    //     return $out;
    // }

    private function summarizeExceptionBlocks($exceptions, Carbon $day, int $slotIntervalMinutes = 15): array
    {
        $out = [];

        foreach ($exceptions as $ex) {

            $type = is_object($ex->type) ? $ex->type->value : $ex->type;

            $isPartialBlock = in_array($type, ['leave_partial', 'blocked_hours'], true);
            $isFullDayBlock = in_array($type, ['leave_full_day'], true);

            // Skip non-blocking exceptions
            if (!$isPartialBlock && !$isFullDayBlock) {
                continue;
            }

            // Full-day leave → keep as single block (optional)
            if ($isFullDayBlock) {
                $out[] = [
                    'start'  => '00:00',
                    'end'    => '23:59',
                    'reason' => $type,
                ];
                continue;
            }

            // Partial blocks → SPLIT INTO 15-MIN SLOTS
            $start = Carbon::parse($day->toDateString().' '.($ex->start_time ?? '00:00'));
            $end   = Carbon::parse($day->toDateString().' '.($ex->end_time ?? '23:59'));

            $cursor = $start->copy();

            while ($cursor->copy()->addMinutes($slotIntervalMinutes)->lte($end)) {
                $out[] = [
                    'start'  => $cursor->format('H:i'),
                    'end'    => $cursor->copy()->addMinutes($slotIntervalMinutes)->format('H:i'),
                    'reason' => $type,
                ];

                $cursor->addMinutes($slotIntervalMinutes);
            }
        }

        return $out;
    }

    // ------------------------
    // Time utilities
    // ------------------------

    private function isAlignedToMinutes(Carbon $t, int $step): bool
    {
        return ((int)$t->format('i') % $step) === 0 && ((int)$t->format('s') === 0);
    }

    private function timeOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        $aS = Carbon::createFromFormat('H:i', $aStart);
        $aE = Carbon::createFromFormat('H:i', $aEnd);
        $bS = Carbon::createFromFormat('H:i', $bStart);
        $bE = Carbon::createFromFormat('H:i', $bEnd);

        return $aS->lt($bE) && $aE->gt($bS);
    }

    private function mergeWindows(array $windows): array
    {
        if (empty($windows)) return [];

        $sorted = collect($windows)->sortBy('start')->values()->all();
        $merged = [];

        foreach ($sorted as $w) {
            if (empty($merged)) {
                $merged[] = $w;
                continue;
            }

            $last = &$merged[count($merged)-1];

            $lastEnd = Carbon::createFromFormat('H:i', $last['end']);
            $curStart= Carbon::createFromFormat('H:i', $w['start']);
            $curEnd  = Carbon::createFromFormat('H:i', $w['end']);

            // overlap or touching
            if ($curStart->lte($lastEnd)) {
                if ($curEnd->gt($lastEnd)) {
                    $last['end'] = $curEnd->format('H:i');
                }
            } else {
                $merged[] = $w;
            }
        }

        return $merged;
    }

    private function subtractWindow(array $windows, array $block): array
    {
        $result = [];

        $bS = Carbon::createFromFormat('H:i', $block['start']);
        $bE = Carbon::createFromFormat('H:i', $block['end']);

        foreach ($windows as $w) {
            $wS = Carbon::createFromFormat('H:i', $w['start']);
            $wE = Carbon::createFromFormat('H:i', $w['end']);

            // no overlap
            if ($wE->lte($bS) || $wS->gte($bE)) {
                $result[] = $w;
                continue;
            }

            // left remainder
            if ($wS->lt($bS)) {
                $result[] = ['start' => $wS->format('H:i'), 'end' => $bS->format('H:i')];
            }

            // right remainder
            if ($wE->gt($bE)) {
                $result[] = ['start' => $bE->format('H:i'), 'end' => $wE->format('H:i')];
            }
        }

        return $this->mergeWindows($result);
    }

    private function appointmentStatusColor(string $status): string
    {
        return match ($status) {
            'confirmed'   => '#4CAF50', // green
            'pending'     => '#FFC107', // amber
            'in_progress' => '#2196F3', // blue
            'completed'   => '#9E9E9E', // grey
            'cancelled'   => '#F44336', // red
            'no_show'     => '#E91E63', // pink
            default       => '#607D8B', // fallback
        };
    }

    private function formatBookedEventsForCalendar(array $unavailableBooked, Carbon $day): array
    {
        return array_map(function ($slot) use ($day) {
            return [
                'id'    => 'appt_'.$slot['appointment']['id'],
                'start' => $day->toDateString().' '.$slot['start'],
                'end'   => $day->toDateString().' '.$slot['end'],
                'title' => $slot['appointment']['client_name'] ?? 'Booked',
                'type'  => 'appointment',
                'status'=> $slot['appointment']['status'],
                'color' => $slot['appointment']['status_color'],
                'isDisabled' => true,
                'meta' => [
                    'therapist' => $slot['appointment']['therapist_name'],
                    'clinic'    => $slot['appointment']['clinic_name'],
                ]
            ];
        }, $unavailableBooked);
    }

    private function formatExceptionEventsForCalendar(array $exceptionSlots, Carbon $day): array
    {
        $colorMap = [
            'leave_partial' => '#FF7043',
            'leave_full_day'=> '#E53935',
            'blocked_hours' => '#9E9E9E',
        ];

        return array_map(function ($slot) use ($day, $colorMap) {
            return [
                'id'    => $slot['reason'].'_'.$slot['start'],
                'start' => $day->toDateString().' '.$slot['start'],
                'end'   => $day->toDateString().' '.$slot['end'],
                'title' => ucfirst(str_replace('_', ' ', $slot['reason'])),
                'type'  => $slot['reason'],
                'color' => $colorMap[$slot['reason']] ?? '#BDBDBD',
                'isDisabled' => true,
            ];
        }, $exceptionSlots);
}
}
