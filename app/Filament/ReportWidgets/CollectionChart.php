<?php

namespace App\Filament\ReportWidgets;

use App\Models\InvoicePayment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class CollectionChart extends ChartWidget
{
    protected ?string $heading = 'Collections Over Time';
    
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

        // Use raw DB query for aggregation to avoid external dependency issues
        $dateFormat = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', invoice_payments.payment_date)",
            'pgsql' => "to_char(invoice_payments.payment_date, 'YYYY-MM-DD')",
            default => "DATE(invoice_payments.payment_date)", // MySQL, MariaDB, SQL Server
        };

        $query = InvoicePayment::query()
            ->selectRaw("$dateFormat as date, SUM(invoice_payments.amount) as aggregate")
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereDate('invoice_payments.payment_date', '>=', $startDate)
            ->whereDate('invoice_payments.payment_date', '<=', $endDate);

        if ($this->clinicId) {
            $query->where('invoices.clinic_id', $this->clinicId);
        } elseif (!check_role('super_admin')) {
            $query->where('invoices.clinic_id', auth()->user()->clinic_id);
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
            $record = $data->first(fn($item) => $item->date === $dateString);
            
            $labels[] = $date->format('M d');
            $values[] = $record ? (float) $record->aggregate : 0.0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Collections',
                    'data' => $values,
                    'borderColor' => '#10b981', // emerald-500
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
