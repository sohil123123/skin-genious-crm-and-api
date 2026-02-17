<?php

namespace App\Filament\ReportWidgets;

use App\Models\StockTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ProductPurchaseChart extends ChartWidget
{
    protected ?string $heading = 'Purchases Over Time';
    
    // protected ?string $maxHeight = '300px';

    protected static ?int $sort = 1;

    protected function getMaxHeight(): ?string
    {
        return '300px';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxTicksLimit' => 10,
                        'autoSkip' => true,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'maintainAspectRatio' => false,
            'responsive' => true,
        ];
    }

    public ?string $filter = 'month';

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

        // Use raw DB query for aggregation
        $dateFormat = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => "DATE(created_at)", // MySQL, MariaDB
        };

        $data = StockTransaction::query()
            ->selectRaw("$dateFormat as date, SUM(quantity) as aggregate")
            ->where('type', 'purchase')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates with 0
        $period = CarbonPeriod::create($startDate, $endDate);
        $labels = [];
        $values = [];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $record = $data->first(fn($item) => $item->date === $dateString);
            
            $labels[] = $date->format('M d');
            $values[] = $record ? $record->aggregate : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Quantity Purchased',
                    'data' => $values,
                    'borderColor' => '#10b981', // green-500
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
