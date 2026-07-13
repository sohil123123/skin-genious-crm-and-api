<?php

namespace App\Exports;

use App\Models\InvoicePayment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Database\Eloquent\Builder;

class CollectionReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
    protected ?string $startDate;
    protected ?string $endDate;
    protected ?int $clinicId;

    public function __construct(?string $startDate, ?string $endDate, ?int $clinicId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->clinicId = $clinicId;
    }

    public function collection(): Collection
    {
        $payments = InvoicePayment::query()
            ->selectRaw('payment_method, COUNT(invoice_payments.id) as transaction_count, SUM(invoice_payments.amount) as total_amount')
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->when($this->startDate, fn(Builder $q) => $q->whereDate('invoice_payments.payment_date', '>=', $this->startDate))
            ->when($this->endDate, fn(Builder $q) => $q->whereDate('invoice_payments.payment_date', '<=', $this->endDate))
            ->where(function (Builder $query) {
                if ($this->clinicId) {
                    $query->where('invoices.clinic_id', $this->clinicId);
                } elseif (!check_role('super_admin')) {
                    $query->where('invoices.clinic_id', auth()->user()->clinic_id);
                }
            })
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get();

        return $payments->map(function ($payment) {
            return [
                'payment_method' => $this->getPaymentMethodLabel($payment->payment_method),
                'transaction_count' => $payment->transaction_count ?? 0,
                'total_amount' => $payment->total_amount ?? 0,
            ];
        });
    }

    protected function getPaymentMethodLabel(string $method): string
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

    public function headings(): array
    {
        return [
            'Payment Method',
            'Transaction Count',
            'Total Collected',
        ];
    }

    public function title(): string
    {
        return 'Collection Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => '1F2937'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E7FF'], // Light indigo matching website theme
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Set auto-filter on header row
                $sheet->setAutoFilter("A1:{$highestColumn}1");

                // Apply transaction count format to column B, and currency to column C
                $numberFormat = '#,##0';
                $currencyFormat = '#,##0.00';
                
                $sheet->getStyle("B2:B{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode($numberFormat);

                $sheet->getStyle("C2:C{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode($currencyFormat);

                // Apply borders to all data
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('D1D5DB');

                // Header row height
                $sheet->getRowDimension(1)->setRowHeight(28);

                // Add totals row
                $totalsRow = $highestRow + 1;
                $dataStartRow = 2;

                $sheet->setCellValue("A{$totalsRow}", 'TOTAL');
                $sheet->setCellValue("B{$totalsRow}", "=SUM(B{$dataStartRow}:B{$highestRow})");
                $sheet->setCellValue("C{$totalsRow}", "=SUM(C{$dataStartRow}:C{$highestRow})");

                // Style totals row
                $sheet->getStyle("A{$totalsRow}:{$highestColumn}{$totalsRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => '1F2937'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEF3C7'], // Light amber for totals
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                        'top' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['rgb' => '374151'],
                        ],
                    ],
                ]);

                // Apply number formatting to totals row
                $sheet->getStyle("B{$totalsRow}")
                    ->getNumberFormat()
                    ->setFormatCode($numberFormat);

                $sheet->getStyle("C{$totalsRow}")
                    ->getNumberFormat()
                    ->setFormatCode($currencyFormat);

                // Freeze header row
                $sheet->freezePane('A2');

                // Set minimum column widths
                $minWidths = [
                    'A' => 25,
                    'B' => 20,
                    'C' => 20,
                ];
                foreach ($minWidths as $col => $width) {
                    $currentWidth = $sheet->getColumnDimension($col)->getWidth();
                    if ($currentWidth < $width) {
                        $sheet->getColumnDimension($col)->setWidth($width);
                    }
                }
            },
        ];
    }
}
