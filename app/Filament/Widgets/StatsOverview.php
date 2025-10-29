<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\User;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters, HasWidgetShield;

    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '5s';

    protected static bool $isLazy = false;

    protected ?string $heading = 'States Overview';

    protected function getStats(): array
    {
        // Example: calculate totals dynamically
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();

        // 👥 New Users
        $currentCustomers = User::role('user')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $previousCustomers = User::role('user')
            ->whereBetween('created_at', [$previousMonth, $currentMonth])
            ->count();

        $customerChange = $this->calculatePercentageChange($previousCustomers, $currentCustomers);
        $customerTrend = $this->generateTrend('created_at');

        return [
            // 👥 New Users
            Stat::make('New users', $currentCustomers)
                ->description($this->getChangeText($customerChange))
                ->descriptionIcon($this->getTrendIcon($customerChange))
                ->chart($customerTrend)
                ->color($this->getTrendColor($customerChange)),
        ];
    }

    private function generateTrend(string $dateColumn, string $sumColumn = null): array
    {
        $data = collect();

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);

            $value = User::role('user')
                ->whereDate($dateColumn, $day)
                ->when($sumColumn, fn($q) => $q->sum($sumColumn), fn($q) => $q->count());

            $data->push($value);
        }

        return $data->toArray();
    }

    /**
     * 📈 Calculate percentage change
     */
    private function calculatePercentageChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * 🔼 Get text like “7% increase” / “3% decrease”
     */
    private function getChangeText($change): string
    {
        if ($change == 0) return 'No change';
        return abs($change) . '% ' . ($change > 0 ? 'increase' : 'decrease');
    }

    /**
     * 🎯 Get icon based on trend
     */
    private function getTrendIcon($change): string
    {
        if ($change > 0) return 'heroicon-m-arrow-trending-up';
        if ($change < 0) return 'heroicon-m-arrow-trending-down';
        return 'heroicon-m-minus';
    }

    /**
     * 🎨 Get color based on trend
     */
    private function getTrendColor($change): string
    {
        if ($change > 0) return 'success';
        if ($change < 0) return 'danger';
        return 'gray';
    }
}
