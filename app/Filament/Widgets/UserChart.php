<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

use App\Models\User;
use Illuminate\Support\Carbon;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class UserChart extends ChartWidget
{
    use InteractsWithPageFilters, HasWidgetShield;

    protected ?string $heading = 'Total users this year';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '5s';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data = [];
        $cumulative = [];
        $sum = 0;

        for ($i = 1; $i <= 12; $i++) {
            $count = User::role('user')
                ->whereMonth('created_at', $i)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();
            $data[] = $count;
            $sum += $count;
            $cumulative[] = $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $cumulative,
                    'fill' => 'start',
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
