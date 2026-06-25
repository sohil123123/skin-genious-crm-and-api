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
    public ?int $clinicId = null;

    protected $listeners = ['updateReportDates' => 'updateDates'];

    public function updateDates(string $startDate, string $endDate, ?int $clinicId = null): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->clinicId = $clinicId;
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $startDate = $this->startDate ? Carbon::parse($this->startDate) : now()->startOfMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->endOfMonth();

        $data = \App\Models\PurchaseItem::query()
            ->select('products.name', 'products.type', DB::raw('SUM(purchase_items.quantity) as total_quantity'))
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereIn('products.type', ['product', 'iv_product'])
            ->whereDate('purchases.purchase_date', '>=', $startDate)
            ->whereDate('purchases.purchase_date', '<=', $endDate)
            ->when($this->clinicId, fn($q) => $q->where('purchases.clinic_id', $this->clinicId))
            ->when(!$this->clinicId && !auth()->user()->hasRole('super_admin'), fn($q) => $q->where('purchases.clinic_id', auth()->user()->clinic_id))
            ->groupBy('products.name', 'products.type')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Quantity',
                    'data' => $data->pluck('total_quantity')->toArray(),
                    'backgroundColor' => [
                        '#86efac',
                        '#93c5fd',
                        '#fca5a5',
                        '#fcd34d',
                        '#a5b4fc',
                        '#d8b4fe',
                        '#f9a8d4',
                        '#5eead4',
                        '#fdba74',
                        '#cbd5e1'
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $data->map(fn($item) => "{$item->name}")->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
