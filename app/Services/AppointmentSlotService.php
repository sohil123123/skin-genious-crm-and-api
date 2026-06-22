<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicHoliday;
use App\Models\Holiday;
use App\Models\TreatmentSession;
use Carbon\Carbon;

class AppointmentSlotService
{
    public static function getDuration($type, $treatmentSessionId = null)
    {
        $typeStr = $type instanceof \BackedEnum ? $type->value : $type;
        if ($typeStr === 'consult' || $typeStr === 'other') {
            return 30;
        }

        return get_treatment_session_duration($treatmentSessionId);
    }

    public static function getTherapistDayLeaves($therapistId, $day)
    {
        return Holiday::where('user_id', $therapistId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->where('status', 'approved')
            ->exists();
    }

    // public static function getClinicHolidays($clinicId, $day)
    // {
    //     return ClinicHoliday::where('clinic_id', $clinicId)
    //         ->whereDate('holiday_date', $day)
    //         ->exists();
    // }

    public static function generateSchedule($therapistId, $clinicId, $day)
    {
        return Appointment::with('user')
            ->where('therapist_id', $therapistId)
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_datetime', $day)
            ->orderBy('appointment_datetime')
            ->get()
            ->map(function ($a) {
                $start = Carbon::parse($a->appointment_datetime);
                $duration = self::getDuration($a->type, $a->treatment_session_id);
                $end = (clone $start)->addMinutes($duration);

                return [
                    'start' => $start,
                    'end'   => $end,
                    'user'  => $a->user->name,
                ];
            });
    }

    public static function isOverlapping($start, $end, $therapistId, $clinicId, $ignoreId = null)
    {
        return Appointment::where('therapist_id', $therapistId)
            ->where('clinic_id', $clinicId)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('appointment_datetime', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('appointment_datetime', '<', $start)
                         ->whereRaw("DATE_ADD(appointment_datetime, INTERVAL 90 MINUTE) > ?", [$start]);
                  });
            })
            ->exists();
    }

    public static function getNextAvailableSlot($therapistId, $clinicId, $type, $treatmentSessionId, $fromDateTime)
    {
        $duration = self::getDuration($type, $treatmentSessionId);

        $start = Carbon::parse($fromDateTime);
        $day = $start->toDateString();

        // If therapist is on leave or clinic is closed → move to next day
        while (
            self::getTherapistDayLeaves($therapistId, $day)
        ) {
            $start->addDay()->startOfDay();
            $day = $start->toDateString();
        }

        // Cycle through every 5 minutes until a free slot is found
        for ($i = 0; $i < 1000; $i++) {
            $end = (clone $start)->addMinutes($duration);

            if (!self::isOverlapping($start, $end, $therapistId, $clinicId)) {
                return $start->format('Y-m-d H:i:s');
            }

            $start->addMinutes(5);
        }

        return null;
    }

    public static function generateAvailableSlotsGrid($therapistId, $clinicId, $day, $type, $treatmentSessionId)
    {
        if (self::getTherapistDayLeaves($therapistId, $day)) {
            return [];
        }

        $duration = self::getDuration($type, $treatmentSessionId);

        $slots = [];
        $start = Carbon::parse($day.' 09:00'); // Clinic opening time
        $endDay = Carbon::parse($day.' 21:00'); // Clinic closing time

        while ($start < $endDay) {
            $slotEnd = (clone $start)->addMinutes($duration);

            if (!self::isOverlapping($start, $slotEnd, $therapistId, $clinicId)) {
                $slots[] = [
                    'label' => $start->format('h:i A').' - '.$slotEnd->format('h:i A'),
                    'value' => $start->format('Y-m-d H:i:s'),
                ];
            }

            $start->addMinutes(30); // Show only 30-min spaced slots
        }

        return $slots;
    }
}
