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
    // Error Codes
    private const ERR_OUTSIDE_WORKING_HOURS = 'OUTSIDE_WORKING_HOURS';
    private const ERR_CLINIC_HOLIDAY        = 'CLINIC_HOLIDAY';
    private const ERR_THERAPIST_LEAVE       = 'THERAPIST_LEAVE';
    private const ERR_BLOCKED_HOURS         = 'BLOCKED_HOURS';
    private const ERR_ALREADY_BOOKED        = 'ALREADY_BOOKED';
    private const ERR_NO_BED_AVAILABLE      = 'NO_BED_AVAILABLE';
    private const ERR_PENDING_LIMIT         = 'PENDING_LIMIT_EXCEEDED';
    private const WARN_BED_CAPACITY         = 'BED_CAPACITY_EXCEEDED';

    public function assertBookable(int $clinicId, int $therapistId, Carbon $start, Carbon $end, string $status = null, ?int $ignoreAppointmentId = null): array
    {
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

    private function isWithinWeeklySchedule(int $clinicId, int $therapistId, Carbon $start, Carbon $end): bool {
        return UserWeeklySchedule::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $therapistId)
            ->where('day_of_week', $start->isoWeekday())
            ->where('start_time', '<=', $start->format('H:i'))
            ->where('end_time', '>=', $end->format('H:i'))
            ->exists();
    }

    private function findBlockingException(int $clinicId, int $therapistId, Carbon $start, Carbon $end): ?array {

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

    private function countPending(int $clinicId, int $therapistId, Carbon $start, Carbon $end, ?int $ignoreId): int {
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

    private function getBedUsageDetailed(int $clinicId, Carbon $start, Carbon $end, ?int $ignoreId = null): array {

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

    private function throwAvailabilityError(string $code, string $message, array $details = []): void {
        throw ValidationException::withMessages([
            'availability' => [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ]
        ]);
    }
}
