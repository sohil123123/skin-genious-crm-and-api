<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;

use Filament\Widgets\Widget;

use Carbon\Carbon;

class AiCapacityGaps extends Widget
{
    protected string $view = 'filament.widgets.ai-capacity-gaps';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    /**
     * Get today's and tomorrow's capacity data.
     */
    public function getCapacityData(): array
    {
        $user = auth()->user();

        $clinicQuery = Clinic::where('is_active', true);

        if ($user && !$user->hasRole('super_admin') && $user->clinic_id) {
            $clinicQuery->where('id', $user->clinic_id);
        }

        $clinics = $clinicQuery->get();
        $data = [];

        foreach ($clinics as $clinic) {
            $todaySlots = $this->getSlotsForDate($clinic, Carbon::today());
            $tomorrowSlots = $this->getSlotsForDate($clinic, Carbon::tomorrow());

            $data[] = [
                'clinic_name' => $clinic->name,
                'today' => $todaySlots,
                'tomorrow' => $tomorrowSlots,
            ];
        }

        return $data;
    }

    /**
     * Get slot summary for a clinic on a date.
     */
    protected function getSlotsForDate(Clinic $clinic, Carbon $date): array
    {
        // Skip Sundays
        if ($date->isSunday()) {
            return [
                'date_label' => $date->format('D, d M'),
                'total_slots' => 0,
                'booked_slots' => 0,
                'empty_slots' => 0,
                'utilisation' => 0,
                'is_closed' => true,
                'booked_appointments' => [],
            ];
        }

        $totalSlots = $this->estimateDailySlots($clinic);

        $appointments = Appointment::where('clinic_id', $clinic->id)
            ->whereDate('start_datetime', $date)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            ->with('client:id,first_name,last_name')
            ->orderBy('start_datetime')
            ->get();

        $bookedSlots = $appointments->count();
        $emptySlots = max(0, $totalSlots - $bookedSlots);
        $utilisation = $totalSlots > 0 ? round(($bookedSlots / $totalSlots) * 100) : 0;

        $bookedList = $appointments->map(function ($apt) {
            $statusValue = $apt->status instanceof AppointmentStatus
                ? $apt->status->value
                : (string) $apt->status;

            return [
                'time' => Carbon::parse($apt->start_datetime)->format('g:i A'),
                'client' => $apt->client ? $apt->client->first_name . ' ' . ($apt->client->last_name ?? '') : 'Unknown',
                'status' => $statusValue,
            ];
        })->toArray();

        return [
            'date_label' => $date->format('D, d M'),
            'total_slots' => $totalSlots,
            'booked_slots' => $bookedSlots,
            'empty_slots' => $emptySlots,
            'utilisation' => $utilisation,
            'is_closed' => false,
            'booked_appointments' => $bookedList,
        ];
    }

    /**
     * Estimate daily slots based on operating hours (90-min slots).
     */
    protected function estimateDailySlots(Clinic $clinic): int
    {
        if (!$clinic->start_time || !$clinic->end_time) {
            return 5; // Default: ~8 hrs / 1.5 hrs = 5 slots
        }

        $start = Carbon::parse($clinic->start_time);
        $end = Carbon::parse($clinic->end_time);
        $minutes = max(90, $start->diffInMinutes($end));

        return (int) floor($minutes / 90);
    }
}
