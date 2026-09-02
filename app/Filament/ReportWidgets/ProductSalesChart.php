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

        // Use raw DB query for aggregation to avoid external dependency issues
        $dateFormatPayment = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', invoice_payments.payment_date)",
            'pgsql' => "to_char(invoice_payments.payment_date, 'YYYY-MM-DD')",
            default => "DATE(invoice_payments.payment_date)", // MySQL, MariaDB, SQL Server
        };

        $dateFormatPackage = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', user_packages.created_at)",
            'pgsql' => "to_char(user_packages.created_at, 'YYYY-MM-DD')",
            default => "DATE(user_packages.created_at)",
        };

        $invoiceData = [];
        $packageData = [];

        // Standard Invoice Payments (excluding package invoices)
        $queryInvoice = DB::table('invoice_items')
            ->selectRaw("$dateFormatPayment as date, SUM(invoice_items.line_total * COALESCE(invoice_payments.amount / NULLIF(invoices.grand_total, 0), 1)) as aggregate")
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('invoice_payments', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('invoices.invoice_type')
                  ->orWhere('invoices.invoice_type', '!=', 'package');
            })
            ->whereNull('invoices.package_id')
            ->whereDate('invoice_payments.payment_date', '>=', $startDate)
            ->whereDate('invoice_payments.payment_date', '<=', $endDate);

        if ($clinicId) {
            $queryInvoice->where('invoices.clinic_id', $clinicId);
        }

        if ($this->productType) {
            $queryInvoice->where('products.type', $this->productType);
        }

        $invoiceData = $queryInvoice->groupBy('date')->get()->pluck('aggregate', 'date')->toArray();

        // User Packages
        $queryPackage = DB::table('user_package_items')
            ->selectRaw("$dateFormatPackage as date, SUM(user_package_items.total_amount * COALESCE(user_packages.final_amount / NULLIF(user_packages.subtotal, 0), 1)) as aggregate")
            ->join('user_packages', 'user_package_items.user_package_id', '=', 'user_packages.id')
            ->join('products', 'user_package_items.service_id', '=', 'products.id')
            ->whereDate('user_packages.created_at', '>=', $startDate)
            ->whereDate('user_packages.created_at', '<=', $endDate);

        if ($clinicId) {
            $queryPackage->where('user_packages.clinic_id', $clinicId);
        }

        if ($this->productType) {
            $queryPackage->where('products.type', $this->productType);
        }

        $packageData = $queryPackage->groupBy('date')->get()->pluck('aggregate', 'date')->toArray();

        // Fill missing dates with 0 to ensure continuous chart line
        $period = CarbonPeriod::create($startDate, $endDate);
        $labels = [];
        $values = [];

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $val = 0;
            if (isset($invoiceData[$dateString])) {
                $val += (float) $invoiceData[$dateString];
            }
            if (isset($packageData[$dateString])) {
                $val += (float) $packageData[$dateString];
            }

            $labels[] = $date->format('M d');
            $values[] = $val;
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
