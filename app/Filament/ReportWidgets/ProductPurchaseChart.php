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

        // Use raw DB query for aggregation
        $dateFormat = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', purchases.purchase_date)",
            'pgsql' => "to_char(purchases.purchase_date, 'YYYY-MM-DD')",
            default => "DATE(purchases.purchase_date)", // MySQL, MariaDB
        };

        $data = \App\Models\PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->selectRaw("$dateFormat as date, SUM(purchase_items.quantity) as aggregate")
            ->whereIn('products.type', ['product', 'iv_product'])
            ->whereDate('purchases.purchase_date', '>=', $startDate)
            ->whereDate('purchases.purchase_date', '<=', $endDate)
            ->when($this->clinicId, fn ($q) => $q->where('purchases.clinic_id', $this->clinicId))
            ->when(!$this->clinicId && !auth()->user()->hasRole('super_admin'), fn ($q) => $q->where('purchases.clinic_id', auth()->user()->clinic_id))
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
