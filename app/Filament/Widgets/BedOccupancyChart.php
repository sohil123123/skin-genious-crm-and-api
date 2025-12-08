<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

use Carbon\Carbon;
use App\Models\Clinic;
use App\Models\Appointment;

class BedOccupancyChart extends ChartWidget
{
    protected ?string $heading = 'Bed Occupancy Chart';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $user = auth()->user();
        // -------------------------------
        // ROLE-BASED CLINIC FILTERING
        // -------------------------------

        if ($user->hasRole('super_admin')) {
            // Super Admin → show ALL CLINICS
            $clinics = Clinic::all();
        } else {
            // Others → show ONLY assigned clinic
            $clinics = Clinic::where('id', $user->clinic_id)->get();
        }

        $labels = [];
        $usageData = [];
        $capacityData = [];

        foreach ($clinics as $clinic) {
            $labels[] = $clinic->name;

            // Count today's appointments
            $count = Appointment::where('clinic_id', $clinic->id)
                ->whereDate('appointment_datetime', Carbon::today())
                ->count();

            $usageData[] = $count;
            $capacityData[] = $clinic->number_of_beds;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Beds Used Today',
                    'data' => $usageData,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                ],
                [
                    'label' => 'Total Beds',
                    'data' => $capacityData,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
