<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpenseTrendChart extends ChartWidget
{
    protected ?string $heading = 'Daily Expense Trend';

    protected static ?int $sort = 22;

    protected function getMaxHeight(): ?string
    {
        return '300px';
    }

    protected function getData(): array
    {
        $startDate = now()->subDays(13)->startOfDay();
        $endDate = now()->endOfDay();

        $dateFormat = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', expense_date)",
            'pgsql' => "to_char(expense_date, 'YYYY-MM-DD')",
            default => 'DATE(expense_date)',
        };

        $data = Expense::query()
            ->forCurrentClinic()
            ->selectRaw("$dateFormat as date, SUM(amount) as total_amount")
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $values = [];

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $dateString = $date->format('Y-m-d');
            $record = $data->first(fn ($item) => $item->date === $dateString);

            $labels[] = $date->format('M d');
            $values[] = $record ? (float) $record->total_amount : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Expenses',
                    'data' => $values,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.12)',
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'clinic_manager']) ?? false;
    }
}
