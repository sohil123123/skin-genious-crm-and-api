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
    public ?int $clinicId = null;

    protected $listeners = ['updateReportDates' => 'updateDates'];

    public function updateDates(string $startDate, string $endDate, ?string $productType = null, ?int $clinicId = null): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->productType = $productType;
        $this->clinicId = $clinicId;
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $startDate = $this->startDate ? Carbon::parse($this->startDate) : now()->startOfMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->endOfMonth();

        $clinicId = $this->clinicId;
        if ($clinicId === null && auth()->check() && !check_role('super_admin')) {
            $clinicId = auth()->user()->clinic_id;
        }

        $invoiceQuery = DB::table('invoice_items')
            ->select('products.name', DB::raw('SUM(invoice_items.line_total * COALESCE(invoice_payments.amount / NULLIF(invoices.grand_total, 0), 1)) as total_revenue'))
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('invoice_payments', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('invoices.invoice_type')
                  ->orWhere('invoices.invoice_type', '!=', 'package');
            })
            ->whereNull('invoices.package_id')
            ->whereDate('invoice_payments.payment_date', '>=', $startDate)
            ->whereDate('invoice_payments.payment_date', '<=', $endDate)
            ->when($this->productType, fn($q, $type) => $q->where('products.type', $type))
            ->when($clinicId, fn($q) => $q->where('invoices.clinic_id', $clinicId))
            ->groupBy('products.name');

        $packageQuery = DB::table('user_package_items')
            ->select('products.name', DB::raw('SUM(user_package_items.total_amount * COALESCE(user_packages.final_amount / NULLIF(user_packages.subtotal, 0), 1)) as total_revenue'))
            ->join('products', 'user_package_items.service_id', '=', 'products.id')
            ->join('user_packages', 'user_package_items.user_package_id', '=', 'user_packages.id')
            ->whereDate('user_packages.created_at', '>=', $startDate)
            ->whereDate('user_packages.created_at', '<=', $endDate)
            ->when($this->productType, fn($q, $type) => $q->where('products.type', $type))
            ->when($clinicId, fn($q) => $q->where('user_packages.clinic_id', $clinicId))
            ->groupBy('products.name');

        $unionQuery = DB::table(DB::raw("({$invoiceQuery->toSql()} UNION ALL {$packageQuery->toSql()}) as combined"))
            ->mergeBindings($invoiceQuery)
            ->mergeBindings($packageQuery)
            ->select('name', DB::raw('SUM(total_revenue) as total_revenue'))
            ->groupBy('name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
        $data = $unionQuery;

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('total_revenue')->toArray(),
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
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
