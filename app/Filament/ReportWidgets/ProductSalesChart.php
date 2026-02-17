<?php

namespace App\Filament\ReportWidgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ProductSalesChart extends ChartWidget
{
    protected ?string $heading = 'Sales Over Time';
    
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

        // Use raw DB query for aggregation to avoid external dependency issues
        // Assuming MySQL/MariaDB for DATE() function. 
        // If SQLite, this might need adjustment to strftime('%Y-%m-%d', invoice_date)
        
        $dateFormat = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', invoice_date)",
            'pgsql' => "to_char(invoice_date, 'YYYY-MM-DD')",
            default => "DATE(invoice_date)", // MySQL, MariaDB, SQL Server
        };

        $data = Invoice::query()
            ->selectRaw("$dateFormat as date, SUM(grand_total) as aggregate")
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates with 0 to ensure continuous chart line
        $period = CarbonPeriod::create($startDate, $endDate);
        $labels = [];
        $values = [];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            // Check if we have data for this date
            // Note: $data items will have 'date' attribute from selectRaw
            $record = $data->first(fn($item) => $item->date === $dateString);
            
            $labels[] = $date->format('M d');
            $values[] = $record ? $record->aggregate : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales Revenue',
                    'data' => $values,
                    'borderColor' => '#3b82f6', // blue-500
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
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
