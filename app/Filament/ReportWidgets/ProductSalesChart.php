<?php

namespace App\Filament\ReportWidgets;

use App\Models\Invoice;
use App\Models\InvoiceItem;
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
    public ?string $productType = null;

    protected $listeners = ['updateReportDates' => 'updateDates'];

    public function updateDates(string $startDate, string $endDate, ?string $productType = null): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->productType = $productType;
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $startDate = $this->startDate ? Carbon::parse($this->startDate) : now()->startOfMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->endOfMonth();

        // Use raw DB query for aggregation to avoid external dependency issues
        $dateFormat = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', invoices.invoice_date)",
            'pgsql' => "to_char(invoices.invoice_date, 'YYYY-MM-DD')",
            default => "DATE(invoices.invoice_date)", // MySQL, MariaDB, SQL Server
        };

        $query = InvoiceItem::query()
            ->selectRaw("$dateFormat as date, SUM(invoice_items.line_total) as aggregate")
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereDate('invoices.invoice_date', '>=', $startDate)
            ->whereDate('invoices.invoice_date', '<=', $endDate);

        if ($this->productType) {
            $query->where('products.type', $this->productType);
        }

        $data = $query->groupBy('date')
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
