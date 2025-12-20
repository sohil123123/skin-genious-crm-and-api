<?php

namespace App\Services\Scheduling;

use App\Models\UserWeeklySchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class UserWeeklyScheduleService
{
    public function saveOrUpdate(array $data): void
    {
        DB::transaction(function () use ($data) {

            $auth_id = auth()->user()->id;
            $clinicId = $data['clinic_id'];
            $userId   = $data['user_id'];
            $days     = $data['days'] ?? [];

            foreach ($days as $dayOfWeek => $dayData) {

                $shifts = $dayData['shifts'] ?? [];

                // 1️⃣ Validate
                $this->validateDayShifts($shifts, $dayOfWeek);

                // 2️⃣ IDs coming from form
                $incomingIds = collect($shifts)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();

                // 3️⃣ Remove deleted shifts
                UserWeeklySchedule::where([
                    'clinic_id'   => $clinicId,
                    'user_id'     => $userId,
                    'day_of_week' => $dayOfWeek,
                ])
                ->whereNotIn('id', $incomingIds)
                // ->update(['is_active' => false]);
                ->delete();

                /**
                 * Upsert shifts
                 */
                foreach ($shifts as $shift) {

                    // Existing shift → UPDATE
                    if (! empty($shift['id'])) {

                        $schedule = UserWeeklySchedule::find($shift['id']);

                        if (! $schedule) continue;

                        // Assign new values
                        $schedule->start_time = $shift['start_time'];
                        $schedule->end_time   = $shift['end_time'];
                        $schedule->is_active  = $shift['is_active'];
                        $schedule->updated_by = $auth_id;

                        // ❌ Nothing changed → skip
                        if (! $schedule->isDirty()) continue;

                        // Detect if time changed
                        $timeChanged = $schedule->isDirty(['start_time', 'end_time']);

                        // Save only when dirty
                        $schedule->save();

                        // Recalculate overlap only if time changed
                        if ($timeChanged) {
                            $schedule->has_overlap = $schedule->detectOverlap();
                            $schedule->saveQuietly();
                        }

                        continue;
                    }

                    // New shift → INSERT
                    $schedule = UserWeeklySchedule::create([
                        'clinic_id'   => $clinicId,
                        'user_id'     => $userId,
                        'day_of_week' => $dayOfWeek,
                        'start_time'  => $shift['start_time'],
                        'end_time'    => $shift['end_time'],
                        'is_active'   => $shift['is_active'],
                        'created_by'  => $auth_id,

                    ]);

                    // Always calculate overlap for new shift
                    $schedule->has_overlap = $schedule->detectOverlap();
                    $schedule->saveQuietly();

                }
            }
        });
    }

    /**
     * Validate shifts of a single day
     */
    protected function validateDayShifts(array $shifts, int $dayOfWeek): void
    {
        // $ranges = [];

        foreach ($shifts as $index => $shift) {

            $start = $shift['start_time'] ?? null;
            $end   = $shift['end_time'] ?? null;

            if (! $start || ! $end) {
                throw ValidationException::withMessages([
                    "days.$dayOfWeek.shifts.$index" =>
                        'Start time and end time are required.',
                ]);
            }

            $startAt = Carbon::createFromFormat('H:i:s', $start);
            $endAt   = Carbon::createFromFormat('H:i:s', $end);

            if (! $startAt->lt($endAt)) {
                throw ValidationException::withMessages([
                    "days.$dayOfWeek.shifts.$index" =>
                        'End time must be after start time.',
                ]);
            }

            // $ranges[] = [
            //     'start' => $startAt,
            //     'end'   => $endAt,
            //     'index' => $index,
            // ];
        }

        /**
         * Internal overlap check
         */
        // foreach ($ranges as $i => $current) {
        //     foreach ($ranges as $j => $other) {
        //         if ($i === $j) continue;

        //         if (
        //             $current['start']->lt($other['end']) &&
        //             $current['end']->gt($other['start'])
        //         ) {
        //             throw ValidationException::withMessages([
        //                 "data.days.$dayOfWeek.shifts.$index.start_time" => 'This shift overlaps another shift on the same day.',
        //             ]);
        //         }
        //     }
        // }
    }
}
