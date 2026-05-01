<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryBreakdownChart extends ChartWidget
{
    protected ?string $heading = 'Expenses by Category';

    protected static ?int $sort = 21;

    protected function getMaxHeight(): ?string
    {
        return '300px';
    }

    protected function getData(): array
    {
        $data = Expense::query()
            ->forCurrentClinic()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.expense_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total_amount'))
            ->groupBy('expense_categories.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Amount',
                    'data' => $data->pluck('total_amount')->map(fn ($value) => (float) $value)->toArray(),
                    'backgroundColor' => [
                        '#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6',
                        '#14b8a6', '#f97316', '#64748b', '#ec4899', '#84cc16',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'clinic_manager']) ?? false;
    }
}
