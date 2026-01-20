<?php

namespace App\Services\Availability;

use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Clinic;
use App\Models\UserWeeklySchedule;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UnavailableSlotService
{
    /**
     * Priority: higher wins when overlaps happen
     */
    private const PRIORITY = [
        'appointment'              => 100,
        'override_hours'           => 90,
        'blocked_hours'            => 80,
        'leave_full_day'           => 70,
        'leave_partial'            => 70,
        'out_of_therapist_schedule' => 10,
    ];

    public function getUnavailableSlots(
        int $clinicId,
        int $therapistId,
        string $fromDate,
        string $toDate
    ): array {

        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->startOfDay();

        // Clinic hours (fallback 00:00-23:59 if not present)
        $clinic = Clinic::select('id', 'name', 'start_time', 'end_time')->find($clinicId);

        $clinicStart = $clinic?->start_time ? Carbon::createFromFormat('H:i:s', $clinic->start_time)->format('H:i') : '00:00';
        $clinicEnd   = $clinic?->end_time   ? Carbon::createFromFormat('H:i:s', $clinic->end_time)->format('H:i')   : '23:59';

        $clinicOpen = [
            $this->toMinutes($clinicStart),
            $this->toMinutes($clinicEnd),
        ];

        // ----------------------------
        // FAST: prefetch everything for range
        // ----------------------------

        // Weekly schedules once (group by weekday)
        $weekly = UserWeeklySchedule::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where('user_id', $therapistId)
            ->get(['day_of_week', 'start_time', 'end_time'])
            ->groupBy('day_of_week');

        // Exceptions once
        $exceptions = AvailabilityException::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where(function ($q) use ($therapistId, $clinicId) {
                $q->where(fn ($qq) =>
                    $qq->where('exceptionable_type', 'App\\Models\\User')
                       ->where('exceptionable_id', $therapistId)
                )->orWhere(fn ($qq) =>
                    $qq->where('exceptionable_type', 'App\\Models\\Clinic')
                       ->where('exceptionable_id', $clinicId)
                );
            })
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get(['id', 'type', 'start_date', 'end_date', 'start_time', 'end_time'])
            ->all();

        // Appointments once
        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('therapist_id', $therapistId)
            // ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('start_datetime', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['therapist:id,first_name,last_name', 'clinic:id,name', 'client:id,first_name,last_name'])
            ->get();

        $output = [];

        // Iterate day by day (in-memory filtering)
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $weekday = $cursor->isoWeekday(); // 1=Mon..7=Sun

            // Base availability from weekly schedule
            $baseAvail = $this->weeklyWindowsForDay($weekly, $weekday, $clinicOpen);

            // Exceptions for this date
            $dayExceptions = $this->exceptionsForDate($exceptions, $date);

            // If any leave_full_day applies => full day leave
            if ($this->hasFullDayLeave($dayExceptions)) {
                $output[] = $this->makeBlock(
                    type: 'leave_full_day',
                    startDate: $date,
                    startMin: 0,
                    endMin: 24 * 60 - 1,
                    title: 'Leave full day'
                );

                // Still add appointments blocks if you want (optional).
                // If you do NOT want them when leave_full_day, comment below.
                foreach ($this->appointmentsForDate($appointments, $date) as $apptBlock) {
                    $output[] = $apptBlock;
                }

                $cursor->addDay();
                continue;
            }

            // Parse exceptions into windows
            $overrideWindows = $this->windowsOfType($dayExceptions, $date, 'override_hours', $clinicOpen);
            $extraWindows    = $this->windowsOfType($dayExceptions, $date, 'extra_hours', $clinicOpen);
            $blockedWindows  = $this->windowsOfType($dayExceptions, $date, 'blocked_hours', $clinicOpen);
            $leavePartial    = $this->windowsOfType($dayExceptions, $date, 'leave_partial', $clinicOpen);

            // Final availability logic
            if (!empty($overrideWindows)) {
                // Override replaces base roster for the day
                $finalAvail = $this->mergeIntervals($overrideWindows);

                // Unavailable reasons:
                // 1) base shifts removed by override => override_hours
                $overrideUnavailable = $this->subtractIntervals($baseAvail, $finalAvail);

                // 2) everything else outside base and outside override => out_of_therapist_schedule
                $outsideRoster = $this->subtractIntervals(
                    [$clinicOpen],
                    $this->mergeIntervals(array_merge($baseAvail, $finalAvail))
                );

                $reasonBlocks = [];

                foreach ($overrideUnavailable as $iv) {
                    $reasonBlocks[] = $this->makeBlock(
                        type: 'override_hours',
                        startDate: $date,
                        startMin: $iv[0],
                        endMin: $iv[1],
                        title: 'Override hours'
                    );
                }

                foreach ($outsideRoster as $iv) {
                    $reasonBlocks[] = $this->makeBlock(
                        type: 'out_of_therapist_schedule',
                        startDate: $date,
                        startMin: $iv[0],
                        endMin: $iv[1],
                        title: 'Out of therapist schedule'
                    );
                }

                // Note: on override-day, we do NOT apply leave_partial/blocked/extra to roster
                // unless your business requires it. If you want them to still apply, tell me.

                $output = array_merge($output, $this->mergeBlocksByPriority($reasonBlocks));
            } else {
                // Normal day: weekly + extra, then subtract leave_partial and blocked_hours
                $finalAvail = $this->mergeIntervals(array_merge($baseAvail, $extraWindows));
                $finalAvail = $this->subtractIntervals($finalAvail, $leavePartial);
                $finalAvail = $this->subtractIntervals($finalAvail, $blockedWindows);

                // Out of schedule = clinicOpen - finalAvail
                $outOfSchedule = $this->subtractIntervals([$clinicOpen], $finalAvail);

                $reasonBlocks = [];

                foreach ($outOfSchedule as $iv) {
                    $reasonBlocks[] = $this->makeBlock(
                        type: 'out_of_therapist_schedule',
                        startDate: $date,
                        startMin: $iv[0],
                        endMin: $iv[1],
                        title: 'Out of therapist schedule'
                    );
                }

                foreach ($leavePartial as $iv) {
                    $reasonBlocks[] = $this->makeBlock(
                        type: 'leave_partial',
                        startDate: $date,
                        startMin: $iv[0],
                        endMin: $iv[1],
                        title: 'Leave partial'
                    );
                }

                foreach ($blockedWindows as $iv) {
                    $reasonBlocks[] = $this->makeBlock(
                        type: 'blocked_hours',
                        startDate: $date,
                        startMin: $iv[0],
                        endMin: $iv[1],
                        title: 'Blocked hours'
                    );
                }

                // Priority merge ensures leave/blocked override out_of_schedule if overlaps
                $output = array_merge($output, $this->mergeBlocksByPriority($reasonBlocks));
            }

            // Appointments (always returned as separate blocks)
            foreach ($this->appointmentsForDate($appointments, $date) as $apptBlock) {
                $output[] = $apptBlock;
            }

            $cursor->addDay();
        }

        // Final unique + stable ordering
        return collect($output)
            ->filter(fn ($b) => ($b['duration'] ?? 0) > 0)
            ->unique(fn ($b) => $b['id'] . '|' . $b['start_date'] . '|' . $b['start_time'] . '|' . $b['type'])
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------------
    // Windows builders
    // ---------------------------------------------------------------------

    private function weeklyWindowsForDay($weeklyGrouped, int $weekday, array $clinicOpen): array
    {
        $rows = $weeklyGrouped->get($weekday, collect());

        $ivs = [];
        foreach ($rows as $r) {
            $s = $this->toMinutes(Carbon::parse($r->start_time)->format('H:i'));
            $e = $this->toMinutes(Carbon::parse($r->end_time)->format('H:i'));
            $iv = $this->intersect([$s, $e], $clinicOpen);
            if ($iv) $ivs[] = $iv;
        }

        return $this->mergeIntervals($ivs);
    }

    private function exceptionsForDate(array $exceptions, string $date): array
    {
        // In-memory filter: exception is active for date if start_date <= date <= end_date
        return array_values(array_filter($exceptions, function ($ex) use ($date) {
            return $ex->start_date->toDateString() <= $date && $ex->end_date->toDateString() >= $date;
        }));
    }

    private function hasFullDayLeave(array $dayExceptions): bool
    {
        foreach ($dayExceptions as $ex) {
            $type = is_object($ex->type) ? $ex->type->value : $ex->type;
            if ($type === 'leave_full_day') return true;

            // also treat NULL time block as full-day leave if type says leave_full_day
            if ($type === 'leave_full_day' || ($type === 'leave_full_day' && !$ex->start_time && !$ex->end_time)) {
                return true;
            }
        }
        return false;
    }

    private function windowsOfType(array $dayExceptions, string $date, string $type, array $clinicOpen): array
    {
        $out = [];
        foreach ($dayExceptions as $ex) {
            $t = is_object($ex->type) ? $ex->type->value : $ex->type;
            if ($t !== $type) continue;

            // If time NULL, treat as whole clinic open window for that day
            if (!$ex->start_time && !$ex->end_time) {
                $out[] = $clinicOpen;
                continue;
            }

            $s = $this->toMinutes(Carbon::parse($ex->start_time)->format('H:i'));
            $e = $this->toMinutes(Carbon::parse($ex->end_time)->format('H:i'));

            $iv = $this->intersect([$s, $e], $clinicOpen);
            if ($iv) $out[] = $iv;
        }

        return $this->mergeIntervals($out);
    }

    // ---------------------------------------------------------------------
    // Appointments mapping
    // ---------------------------------------------------------------------

    private function appointmentsForDate($appointments, string $date): array
    {
        $out = [];

        foreach ($appointments as $a) {
            $start = Carbon::parse($a->start_datetime);
            if ($start->toDateString() !== $date) continue;

            $end = Carbon::parse($a->end_datetime);

            $out[] = [
                'id'         => $a->id,
                'title'      => Str::ucfirst(is_object($a->status) ? $a->status->value : $a->status),
                'start_date' => $date,
                'end_date'   => $date,
                'start_time' => $start->format('H:i'),
                'end_time'   => $end->format('H:i'),
                'duration'   => $start->diffInMinutes($end),
                'type'       => 'appointment',
                'status'     => is_object($a->status) ? $a->status->value : $a->status,
                'bgcolor'    => $this->statusColor(is_object($a->status) ? $a->status->value : $a->status),
                'textcolor'  => $this->statusTextColor(is_object($a->status) ? $a->status->value : $a->status),
                'meta'       => [
                    'type'      => $a->type->value,
                    'clinic_id' => $a->clinic_id,
                    'clinic'    => $a->clinic?->name,
                    'client_id' => $a->user_id,
                    'client'    => $a->client?->name,
                    'therapist_id' => $a->therapist_id,
                    'therapist' => $a->therapist?->name,
                    'assessment_id' => $a->assessment_id,
                    'assessment' => $a->assessment?->name,
                    'treatment_session_id' => $a->treatment_session_id,
                    'session_title' => $a->treatmentSession?->title,
                ],
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Priority merge for reason blocks (non-overlapping final list)
    // ---------------------------------------------------------------------

    private function mergeBlocksByPriority(array $blocks): array
    {
        if (empty($blocks)) return [];

        // Convert to intervals with priority
        $ivs = array_map(function ($b) {
            return [
                'start'    => $this->toMinutes($b['start_time']),
                'end'      => $this->toMinutes($b['end_time']),
                'type'     => $b['type'],
                'title'    => $b['title'],
                'start_date' => $b['start_date'],
                'priority' => self::PRIORITY[$b['type']] ?? 0,
            ];
        }, $blocks);

        // Build a non-overlapping timeline by slicing on all boundaries
        $points = [];
        foreach ($ivs as $iv) {
            $points[] = $iv['start'];
            $points[] = $iv['end'];
        }
        $points = array_values(array_unique($points));
        sort($points);

        $result = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $segS = $points[$i];
            $segE = $points[$i + 1];
            if ($segE <= $segS) continue;

            $covering = array_values(array_filter($ivs, fn ($iv) =>
                $iv['start'] < $segE && $iv['end'] > $segS
            ));

            if (empty($covering)) continue;

            usort($covering, fn ($a, $b) => $b['priority'] <=> $a['priority']);
            $winner = $covering[0];

            $result[] = $this->makeBlock(
                type: $winner['type'],
                startDate: $winner['start_date'],
                startMin: $segS,
                endMin: $segE,
                title: $winner['title']
            );
        }

        // Merge adjacent same-type segments
        return $this->mergeAdjacentBlocks($result);
    }

    private function mergeAdjacentBlocks(array $blocks): array
    {
        if (empty($blocks)) return [];

        usort($blocks, function ($a, $b) {
            if ($a['start_date'] === $b['start_date']) {
                return strcmp($a['start_time'], $b['start_time']);
            }
            return strcmp($a['start_date'], $b['start_date']);
        });

        $merged = [];
        foreach ($blocks as $b) {
            if (empty($merged)) {
                $merged[] = $b;
                continue;
            }

            $last = &$merged[count($merged) - 1];

            if (
                $last['type'] === $b['type'] &&
                $last['start_date'] === $b['start_date'] &&
                $last['end_time'] === $b['start_time']
            ) {
                // extend
                $last['end_time'] = $b['end_time'];
                $last['duration'] += $b['duration'];
                $last['id'] = $last['type'] . '_' . $last['start_time'];
            } else {
                $merged[] = $b;
            }
        }

        return $merged;
    }

    // ---------------------------------------------------------------------
    // Interval utilities (fast, O(n log n))
    // ---------------------------------------------------------------------

    private function mergeIntervals(array $intervals): array
    {
        $intervals = array_values(array_filter($intervals, fn ($iv) => isset($iv[0], $iv[1]) && $iv[1] > $iv[0]));
        if (empty($intervals)) return [];

        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $out = [$intervals[0]];
        foreach (array_slice($intervals, 1) as $iv) {
            $lastIdx = count($out) - 1;
            $last = $out[$lastIdx];

            if ($iv[0] <= $last[1]) {
                $out[$lastIdx][1] = max($last[1], $iv[1]);
            } else {
                $out[] = $iv;
            }
        }

        return $out;
    }

    private function subtractIntervals(array $source, array $subtract): array
    {
        $source = $this->mergeIntervals($source);
        $subtract = $this->mergeIntervals($subtract);

        if (empty($source)) return [];
        if (empty($subtract)) return $source;

        $out = [];

        foreach ($source as $s) {
            $parts = [$s];

            foreach ($subtract as $b) {
                $newParts = [];
                foreach ($parts as $p) {
                    // no overlap
                    if ($b[1] <= $p[0] || $b[0] >= $p[1]) {
                        $newParts[] = $p;
                        continue;
                    }
                    // left
                    if ($b[0] > $p[0]) $newParts[] = [$p[0], $b[0]];
                    // right
                    if ($b[1] < $p[1]) $newParts[] = [$b[1], $p[1]];
                }
                $parts = $newParts;
                if (empty($parts)) break;
            }

            $out = array_merge($out, $parts);
        }

        return $this->mergeIntervals($out);
    }

    private function intersect(array $a, array $b): ?array
    {
        $s = max($a[0], $b[0]);
        $e = min($a[1], $b[1]);
        return ($e > $s) ? [$s, $e] : null;
    }

    private function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        return ($h * 60) + $m;
    }

    private function fromMinutes(int $min): string
    {
        $min = max(0, min(24 * 60 - 1, $min));
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    // ---------------------------------------------------------------------
    // Block formatting
    // ---------------------------------------------------------------------

    private function makeBlock(string $type, string $startDate, int $startMin, int $endMin, string $title): array
    {
        $startTime = $this->fromMinutes($startMin);
        $endTime   = $this->fromMinutes($endMin);

        $duration = max(0, $endMin - $startMin);

        return [
            'id'         => $type . '_' . $startTime,
            'title'      => $title,
            'start_date' => $startDate,
            'end_date'   => $startDate,
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'duration'   => $duration,
            'type'       => $type,
            'bgcolor'    => $this->exceptionColor($type),
            'textcolor'  => $this->exceptionTextColor($type),
        ];
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'confirmed'   => '#C8E6C9', // soft green
            'pending'     => '#FFECB3', // soft amber
            'in_progress' => '#BBDEFB', // soft blue
            'completed'   => '#EEEEEE', // light grey
            'cancelled'   => '#FFCDD2', // soft red
            'no_show'     => '#F8BBD0', // soft pink
            default       => '#ECEFF1', // light blue-grey
        };
    }

    private function statusTextColor(string $status): string
    {
        return match ($status) {
            'confirmed'   => '#1B5E20', // dark green
            'pending'     => '#5D4037', // brown
            'in_progress' => '#0D47A1', // dark blue
            'completed'   => '#37474F', // dark grey
            'cancelled'   => '#B71C1C', // dark red
            'no_show'     => '#880E4F', // dark pink
            default       => '#37474F', // fallback
        };
    }

    private function exceptionColor(string $type): string
    {
        return match ($type) {
            'override_hours'            => '#D0E6FA', // medium light blue
            'blocked_hours'             => '#E0E0E0', // medium grey
            'leave_full_day'            => '#FFD6D9', // medium light red
            'leave_partial'             => '#FFE0B2', // medium light orange
            'out_of_therapist_schedule' => '#DADFE3', // medium blue-grey
            default                     => '#DADFE3',
        };
    }

    private function exceptionTextColor(string $type): string
    {
         return match ($type) {
            'override_hours'            => '#0D47A1', // deep blue
            'blocked_hours'             => '#424242', // dark grey
            'leave_full_day'            => '#B71C1C', // deep red
            'leave_partial'             => '#E65100', // deep orange
            'out_of_therapist_schedule' => '#455A64', // blue-grey dark
            default                     => '#37474F',
        };
    }

}
