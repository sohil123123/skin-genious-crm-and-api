<?php

namespace App\Filament\ReportWidgets;

use App\Models\InvoiceItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductSalesDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Top Selling Products';

    // protected ?string $maxHeight = '300px';

    protected static ?int $sort = 2; // Show after the time-based chart

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

        $query = InvoiceItem::query()
            ->select('products.name', DB::raw('SUM(invoice_items.line_total) as total_revenue'))
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereDate('invoices.invoice_date', '>=', $startDate)
            ->whereDate('invoices.invoice_date', '<=', $endDate);

        if ($this->productType) {
            $query->where('products.type', $this->productType);
        }

        $data = $query->groupBy('products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('total_revenue')->toArray(),
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
