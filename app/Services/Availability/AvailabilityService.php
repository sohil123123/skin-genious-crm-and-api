<?php

namespace App\Services\Availability;

use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\UserWeeklySchedule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

use App\Models\Clinic;

class AvailabilityService
{
     /* ============================
     | Error Codes
     |============================ */
    private const ERR_OUTSIDE_WORKING_HOURS = 'OUTSIDE_WORKING_HOURS';
    private const ERR_CLINIC_HOLIDAY        = 'CLINIC_HOLIDAY';
    private const ERR_THERAPIST_LEAVE       = 'THERAPIST_LEAVE';
    private const ERR_BLOCKED_HOURS         = 'BLOCKED_HOURS';
    private const ERR_ALREADY_BOOKED        = 'ALREADY_BOOKED';
    private const ERR_NO_BED_AVAILABLE      = 'NO_BED_AVAILABLE';
    private const ERR_PENDING_LIMIT         = 'PENDING_LIMIT_EXCEEDED';

    private const WARN_BED_CAPACITY         = 'BED_CAPACITY_EXCEEDED';

    /* ============================
     | PUBLIC API
     |============================ */

    public function getSlotsForDate(
        int $clinicId,
        int $therapistId,
        string $date,
        int $slotIntervalMinutes = 15,
        int $appointmentDurationMinutes = 15
    ): array {

        $day = Carbon::parse($date);
        $weekday = $day->isoWeekday();

        $baseWindows = $this->getBaseWindowsFromWeeklySchedule($clinicId, $therapistId, $weekday);

        $exceptions = $this->getExceptionsForDate($clinicId, $therapistId, $day);
        $windows    = $this->applyExceptionsToWindows($baseWindows, $exceptions, $day);

        $candidateSlots = $this->generateSlotsFromWindows(
            $windows,
            $day,
            $slotIntervalMinutes,
            $appointmentDurationMinutes
        );

        $bookedBlocks = $this->getBookedBlocks($clinicId, $therapistId, $day);
        [$available, $unavailableBooked] = $this->filterBookedSlots($candidateSlots, $bookedBlocks);

        $unavailableByExceptions = $this->summarizeExceptionBlocks($exceptions, $day, $slotIntervalMinutes);

        return [
            'date'     => $day->toDateString(),
            'interval' => $slotIntervalMinutes,

            'events' => array_values(array_merge(
                $this->formatBookedEventsForCalendar($unavailableBooked, $day),
                $this->formatExceptionEventsForCalendar($unavailableByExceptions, $day)
            )),

            'available_slots' => array_map(fn ($s) => [
                'start'      => $day->toDateString().' '.$s['start'],
                'end'        => $day->toDateString().' '.$s['end'],
                'isDisabled' => false,
            ], $available),
        ];
    }

    /**
     * Used by STORE / UPDATE
     */
    // public function assertBookable(
    //     int $clinicId,
    //     int $therapistId,
    //     Carbon $start,
    //     Carbon $end,
    //     ?int $ignoreAppointmentId = null
    // ): void {

    //     // 1️⃣ Outside weekly schedule
    //     if (!$this->isWithinWeeklySchedule($clinicId, $therapistId, $start, $end)) {
    //         $this->throwAvailabilityError(
    //             self::ERR_OUTSIDE_WORKING_HOURS,
    //             'Selected time is outside therapist working hours.'
    //         );
    //     }

    //     // 2️⃣ Clinic / therapist blocking exceptions
    //     if ($exception = $this->findBlockingException($clinicId, $therapistId, $start, $end)) {

    //         match ($exception['category']) {
    //             'clinic' => $this->throwAvailabilityError(
    //                 self::ERR_CLINIC_HOLIDAY,
    //                 'Clinic is closed on the selected date.',
    //                 $exception
    //             ),

    //             'therapist_leave' => $this->throwAvailabilityError(
    //                 self::ERR_THERAPIST_LEAVE,
    //                 'Therapist is unavailable during the selected time.',
    //                 $exception
    //             ),

    //             'blocked_hours' => $this->throwAvailabilityError(
    //                 self::ERR_BLOCKED_HOURS,
    //                 'This time slot is blocked.',
    //                 $exception
    //             ),

    //             default => null
    //         };
    //     }

    //     // 3️⃣ Clinic bed capacity check
    //     if (!$this->hasAvailableBed($clinicId, $start, $end, $ignoreAppointmentId)) {
    //         $this->throwAvailabilityError(
    //             self::ERR_NO_BED_AVAILABLE,
    //             'No treatment bed is available for the selected time slot.',
    //             [
    //                 'category' => 'bed_capacity',
    //                 'from' => $start->format('Y-m-d H:i'),
    //                 'to'   => $end->format('Y-m-d H:i'),
    //             ]
    //         );
    //     }

    //     // 4️ Appointment overlap
    //     $overlap = Appointment::query()
    //         ->where('clinic_id', $clinicId)
    //         ->where('therapist_id', $therapistId)
    //         ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
    //         ->whereNotIn('status', ['cancelled', 'no_show'])
    //         ->where(function ($q) use ($start, $end) {
    //             $q->where('start_datetime', '<', $end)
    //               ->where('end_datetime', '>', $start);
    //         })
    //         ->first();

    //     if ($overlap) {
    //         $this->throwAvailabilityError(
    //             self::ERR_ALREADY_BOOKED,
    //             'This slot is already booked.',
    //             [
    //                 'category'   => 'booking_conflict',
    //                 'client_name'       => $overlap->client?->name,
    //                 'therapist_name'    => $overlap->therapist?->name,
    //                 'from'     => $overlap->start_datetime->format('Y-m-d H:i'),
    //                 'to'       => $overlap->end_datetime->format('Y-m-d H:i'),
    //             ]
    //         );
    //     }
    // }

    public function assertBookable(
        int $clinicId,
        int $therapistId,
        Carbon $start,
        Carbon $end,
        string $status,
        ?int $ignoreAppointmentId = null
    ): array {

        $warning = [];
        $isSuperAdmin = check_role('super_admin');

        // 1️⃣ Weekly schedule
        if (!$this->isWithinWeeklySchedule($clinicId, $therapistId, $start, $end)) {
            $this->throwAvailabilityError(self::ERR_OUTSIDE_WORKING_HOURS, 'Outside working hours.');
        }

        // 2️⃣ Blocking exceptions (unless super admin)
        if (!$isSuperAdmin && ($ex = $this->findBlockingException($clinicId, $therapistId, $start, $end))) {
            $this->throwAvailabilityError(strtoupper($ex['category']), 'Slot unavailable.', $ex);
        }

        // 3️⃣ Pending limit (strict)
        if ($status === 'pending') {
            $pendingCount = $this->countPending(
                $clinicId,
                $therapistId,
                $start,
                $end,
                $ignoreAppointmentId
            );

            if ($pendingCount >= config('project.pending_limit')) {
                $this->throwAvailabilityError(self::ERR_PENDING_LIMIT, 'Maximum pending appointments reached.', ['pending_count' => $pendingCount]);
            }
        }

        // 4️⃣ Bed capacity rules
        $bed = $this->getBedUsageDetailed($clinicId, $start, $end, $ignoreAppointmentId);

        // 🔴 Confirmed appointment already consuming all beds
        if ($bed['confirmed'] >= $bed['capacity']) {

            // Therapist → BLOCK
            if (!$isSuperAdmin) {
                $this->throwAvailabilityError(
                    self::ERR_NO_BED_AVAILABLE,
                    'Clinic bed capacity is fully occupied.',
                    [
                        'capacity'  => $bed['capacity'],
                        'confirmed' => $bed['confirmed'],
                        'pending'   => $bed['pending'],
                        'from'      => $start->format('Y-m-d H:i'),
                        'to'        => $end->format('Y-m-d H:i'),
                    ]
                );
            }

            // Super admin → WARN
            $warning = [
                'code'    => self::ERR_NO_BED_AVAILABLE,
                'message' => 'Clinic bed capacity exceeded. Emergency override applied.',
                'emergency' => [
                    'message' => 'Clinic bed capacity exceeded. Emergency override applied.',
                    'capacity'   => $bed['capacity'] ?? null,
                    'confirmed'  => $bed['confirmed'] ?? null,
                    'is_emergency' => true,
                ],
                'details' => $bed,
            ];
        }

        // 🟠 Pending already exists but capacity not exceeded
        elseif ($bed['pending'] >= $bed['capacity']) {
            $warning = [
                'code'    => self::WARN_BED_CAPACITY,
                'message' => 'Pending appointment exceeds bed capacity.',
                'details' => $bed,
            ];
        }

        // 5️⃣ Therapist overlap
        if (!$isSuperAdmin) {
            $overlap = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->where('therapist_id', $therapistId)
                ->whereNotIn('status', ['pending', 'cancelled', 'no_show'])
                ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
                ->where(fn ($q) => $q->where('start_datetime', '<', $end)->where('end_datetime', '>', $start))
                ->exists();

            if ($overlap) {
                $this->throwAvailabilityError(self::ERR_ALREADY_BOOKED, 'Therapist already booked.');
            }
        }

        return $warning;
    }

    public function assertConfirmable(Appointment $appointment): array
    {
        $start = Carbon::parse($appointment->start_datetime);
        $end   = Carbon::parse($appointment->end_datetime);

        $clinicId    = $appointment->clinic_id;
        $therapistId = $appointment->therapist_id;

        $isSuperAdmin = check_role('super_admin');
        $emergency = [];

        /*
        |--------------------------------------------------------------------------
        | NORMAL USERS (Therapist / Staff)
        |--------------------------------------------------------------------------
        */
        if (!$isSuperAdmin) {

            // 1️⃣ Working hours
            if (!$this->isWithinWeeklySchedule($clinicId, $therapistId, $start, $end)) {
                $this->throwAvailabilityError(self::ERR_OUTSIDE_WORKING_HOURS, 'Therapist is outside working hours.');
            }

            // 2️⃣ Clinic / therapist blocking exceptions
            if ($exception = $this->findBlockingException($clinicId, $therapistId, $start, $end)) {
                $this->throwAvailabilityError(
                    match ($exception['category']) {
                        'clinic'          => self::ERR_CLINIC_HOLIDAY,
                        'therapist_leave' => self::ERR_THERAPIST_LEAVE,
                        'blocked_hours'   => self::ERR_BLOCKED_HOURS,
                        default           => self::ERR_BLOCKED_HOURS,
                    },
                    'Appointment cannot be confirmed due to availability restrictions.',
                    $exception
                );
            }

            // 3️⃣ Therapist overlap
            $overlap = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->where('therapist_id', $therapistId)
                ->where('id', '!=', $appointment->id)
                ->whereNotIn('status', ['pending', 'cancelled', 'no_show'])
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $end)
                    ->where('end_datetime',   '>', $start);
                })
                ->exists();

            if ($overlap) {
                $this->throwAvailabilityError(self::ERR_ALREADY_BOOKED, 'Therapist already has an appointment during this time.');
            }

            // 4️⃣ Bed capacity (confirmed only)
            $bed = $this->getBedUsageDetailed($clinicId, $start, $end, $appointment->id);

            if ($bed['confirmed'] >= $bed['capacity']) {
                $this->throwAvailabilityError(self::ERR_NO_BED_AVAILABLE, 'Clinic bed capacity exceeded.', $bed);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMIN → EMERGENCY OVERRIDE
        |--------------------------------------------------------------------------
        */

        // Collect violations only for logging / warning
        $violations = [];

        if (!$this->isWithinWeeklySchedule($clinicId, $therapistId, $start, $end)) {
            $violations[] = 'outside_working_hours';
        }

        if ($exception = $this->findBlockingException($clinicId, $therapistId, $start, $end)) {
            $violations[] = $exception['category'];
        }

        $overlap = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('therapist_id', $therapistId)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['pending', 'cancelled', 'no_show'])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_datetime', '<', $end)->where('end_datetime',   '>', $start);
            })
            ->exists();

        if ($overlap) {
            $violations[] = 'therapist_overlap';
        }

        $bed = $this->getBedUsageDetailed($clinicId, $start, $end, $appointment->id);

        if ($bed['confirmed'] >= $bed['capacity']) {
            $violations[] = 'bed_capacity_exceeded';
        }

        // Store emergency metadata for controller
        if (!empty($violations)) {
            $emergency = [
                'violations' => $violations,
                'capacity'   => $bed['capacity'] ?? null,
                'confirmed'  => $bed['confirmed'] ?? null,
                'is_emergency' => true,
            ];
        }
        return $emergency;
    }


    /* ============================
     | INTERNALS
     |============================ */

    private function isWithinWeeklySchedule(
        int $clinicId,
        int $therapistId,
        Carbon $start,
        Carbon $end
    ): bool {
        return UserWeeklySchedule::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $therapistId)
            ->where('day_of_week', $start->isoWeekday())
            ->where('start_time', '<=', $start->format('H:i'))
            ->where('end_time', '>=', $end->format('H:i'))
            ->exists();
    }

    private function findBlockingException(
        int $clinicId,
        int $therapistId,
        Carbon $start,
        Carbon $end
    ): ?array {

        $exceptions = $this->getExceptionsForDate($clinicId, $therapistId, $start);

        foreach ($exceptions as $ex) {

            $type = $ex->type->value ?? $ex->type;

            // 🏥 Clinic holiday
            if ($ex->exceptionable_type === Clinic::class) {
                return [
                    'category' => 'clinic',
                    'type'     => $type,
                    'from'     => $ex->clinic->start_time ?? '00:00',
                    'to'       => $ex->clinic->end_time ?? '23:59',
                    'reason'   => $ex->reason,
                ];
            }

            // 👨‍⚕️ Therapist full-day leave
            if ($type === 'leave_full_day') {
                return [
                    'category'   => 'therapist_leave',
                    'type'       => $type,
                    'from'     => $ex->clinic->start_time ?? '00:00',
                    'to'       => $ex->clinic->end_time ?? '23:59',
                    'leave_type' => $ex->leave_type,
                ];
            }

            // ⛔ Partial leave / blocked
            if (in_array($type, ['leave_partial', 'blocked_hours'], true)) {

                $exStart = Carbon::parse($start->toDateString().' '.$ex->start_time);
                $exEnd   = Carbon::parse($start->toDateString().' '.$ex->end_time);

                if ($start->lt($exEnd) && $end->gt($exStart)) {
                    return [
                        'category'   => $type === 'leave_partial' ? 'therapist_leave' : 'blocked_hours',
                        'type'       => $type,
                        'from'       => $exStart->format('H:i'),
                        'to'         => $exEnd->format('H:i'),
                        'leave_type' => $ex->leave_type,
                    ];
                }
            }
        }

        return null;
    }

    private function throwAvailabilityError(
        string $code,
        string $message,
        array $details = []
    ): void {
        throw ValidationException::withMessages([
            'availability' => [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ]
        ]);
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

    public function getSlotsForDateRange(
        int $clinicId,
        int $therapistId,
        string $fromDate,
        string $toDate,
        int $slotIntervalMinutes = 15,
        int $appointmentDurationMinutes = 15
    ): array {
        $start = Carbon::parse($fromDate);
        $end   = Carbon::parse($toDate);

        $days = [];

        while ($start->lte($end)) {
            $days[] = $this->getSlotsForDate(
                clinicId: $clinicId,
                therapistId: $therapistId,
                date: $start->toDateString(),
                slotIntervalMinutes: $slotIntervalMinutes,
                appointmentDurationMinutes: $appointmentDurationMinutes
            );

            $start->addDay();
        }

        return $days;
    }

    private function countPending(
        int $clinicId,
        int $therapistId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreId
    ): int {
        return Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('therapist_id', $therapistId)
            ->where('status', 'pending')
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(fn ($q) =>
                $q->where('start_datetime', '<', $end)->where('end_datetime',   '>', $start)
            )
            ->count();
    }

    private function getBedUsageDetailed(
        int $clinicId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreId = null
    ): array {

        $clinic = Clinic::select('id', 'number_of_beds')->find($clinicId);

        if (!$clinic || !$clinic->number_of_beds) {
            return [
                'capacity'  => PHP_INT_MAX,
                'confirmed' => 0,
                'pending'   => 0,
            ];
        }

        $baseQuery = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($start, $end) {
                $q->where('start_datetime', '<', $end)
                ->where('end_datetime',   '>', $start);
            });

        return [
            'capacity'  => (int) $clinic->number_of_beds,
            'confirmed' => (clone $baseQuery)->where('status', 'confirmed')->count(),
            'pending'   => (clone $baseQuery)->where('status', 'pending')->count(),
        ];
    }

}
