<?php

namespace App\Filament\ReportWidgets;

use App\Models\InvoicePayment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Collection Distribution';

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
                    'display' => true,
                    'position' => 'bottom',
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

    protected static function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'upi' => 'UPI',
            'bank_transfer' => 'Bank Transfer',
            'loyalty_points' => 'Loyalty Points',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    protected function getData(): array
    {
        $startDate = $this->startDate ? Carbon::parse($this->startDate) : now()->startOfMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->endOfMonth();

        $query = InvoicePayment::query()
            ->select('invoice_payments.payment_method', DB::raw('SUM(invoice_payments.amount) as total_amount'))
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereDate('invoice_payments.payment_date', '>=', $startDate)
            ->whereDate('invoice_payments.payment_date', '<=', $endDate);

        if ($this->clinicId) {
            $query->where('invoices.clinic_id', $this->clinicId);
        } elseif (!check_role('super_admin')) {
            $query->where('invoices.clinic_id', auth()->user()->clinic_id);
        }

        $data = $query->groupBy('invoice_payments.payment_method')
            ->get();

        $labels = [];
        $amounts = [];
        $backgroundColors = [];

        $colorsMap = [
            'cash' => '#10b981',           // Emerald
            'card' => '#3b82f6',           // Blue
            'upi' => '#8b5cf6',            // Purple
            'bank_transfer' => '#f59e0b',  // Amber
            'loyalty_points' => '#ec4899', // Pink
            'other' => '#6b7280',          // Gray
        ];

        foreach ($data as $row) {
            $labels[] = self::getPaymentMethodLabel($row->payment_method);
            $amounts[] = (float) $row->total_amount;
            $backgroundColors[] = $colorsMap[$row->payment_method] ?? '#cbd5e1';
        }

        // If dataset is completely empty, show a grey placeholder segment
        if (empty($labels)) {
            $labels = ['No Collections'];
            $amounts = [0];
            $backgroundColors = ['#e5e7eb'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Amount',
                    'data' => $amounts,
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
