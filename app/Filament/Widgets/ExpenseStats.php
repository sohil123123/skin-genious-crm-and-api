<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpenseStats extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Expense Overview';

    protected static ?int $sort = 20;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $query = Expense::query()
            ->forCurrentClinic()
            ->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()]);

        $total = (clone $query)->sum('amount');
        $count = (clone $query)->count();
        $average = $count > 0 ? $total / $count : 0;

        return [
            Stat::make('Total Expenses This Month', 'Rs. ' . number_format($total, 2))
                ->icon('heroicon-m-banknotes')
                ->color('danger'),
            Stat::make('Expense Entries', number_format($count))
                ->icon('heroicon-m-document-text')
                ->color('info'),
            Stat::make('Average Expense', 'Rs. ' . number_format($average, 2))
                ->icon('heroicon-m-calculator')
                ->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'clinic_manager']) ?? false;
    }
}
