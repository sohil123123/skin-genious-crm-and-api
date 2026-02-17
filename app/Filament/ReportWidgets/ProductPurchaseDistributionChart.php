<?php

namespace App\Filament\ReportWidgets;

use App\Models\StockTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductPurchaseDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Top Purchased Products';
    
    // protected ?string $maxHeight = '300px';

    protected static ?int $sort = 2;

    protected function getMaxHeight(): ?string
    {
        return '300px';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'maintainAspectRatio' => false,
            'responsive' => true,
        ];
    }

    public ?string $startDate = null;
    public ?string $endDate = null;

    protected $listeners = ['updateReportDates' => 'updateDates'];

    public function updateDates(string $startDate, string $endDate): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $startDate = $this->startDate ? Carbon::parse($this->startDate) : now()->startOfMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->endOfMonth();

        $data = StockTransaction::query()
            ->select('products.name', DB::raw('SUM(stock_transactions.quantity) as total_quantity'))
            ->join('products', 'stock_transactions.product_id', '=', 'products.id')
            ->where('stock_transactions.type', 'purchase')
            ->whereDate('stock_transactions.created_at', '>=', $startDate)
            ->whereDate('stock_transactions.created_at', '<=', $endDate)
            ->groupBy('products.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Quantity',
                    'data' => $data->pluck('total_quantity')->toArray(),
                    'backgroundColor' => [
                        '#86efac', '#93c5fd', '#fca5a5', '#fcd34d', '#a5b4fc',
                        '#d8b4fe', '#f9a8d4', '#5eead4', '#fdba74', '#cbd5e1'
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
