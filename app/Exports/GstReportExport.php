<?php

namespace App\Exports;

use App\Models\Invoice;
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
use Illuminate\Support\Carbon;

class GstReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
    protected string $startDate;
    protected string $endDate;
    protected string $reportTitle;
    protected ?int $clinicId;

    public function __construct(string $startDate, string $endDate, string $reportTitle, ?int $clinicId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->reportTitle = $reportTitle;
        $this->clinicId = $clinicId;
    }

    public function collection(): Collection
    {
        $companyState = config('project.company_state_code', 'MH');

        $query = Invoice::query()
            ->with(['client', 'clinic', 'items'])
            ->whereDate('invoice_date', '>=', $this->startDate)
            ->whereDate('invoice_date', '<=', $this->endDate)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('grand_total', '>', 0);

        if ($this->clinicId) {
            $query->where('clinic_id', $this->clinicId);
        }

        $invoices = $query->orderBy('invoice_date', 'asc')
            ->orderBy('invoice_number', 'asc')
            ->get();

        return $invoices->map(function ($invoice) use ($companyState) {
            $clientName = $invoice->client
                ? trim($invoice->client->first_name . ' ' . ($invoice->client->last_name ?? ''))
                : 'N/A';

            $clinicState = $invoice->clinic->state ?? $companyState;
            $clientState = $invoice->client->state ?? null;

            // Determine GST type: same state = SGST+CGST, different state = IGST
            $isSameState = empty($clientState) || strtoupper($clinicState) === strtoupper($clientState);

            $gstTotal = (float) $invoice->gst_total;
            $sgst = $isSameState ? round($gstTotal / 2, 2) : 0;
            $cgst = $isSameState ? round($gstTotal / 2, 2) : 0;
            $igst = $isSameState ? 0 : round($gstTotal, 2);

            // Determine predominant GST rate from invoice items
            $gstRate = $this->getGstRate($invoice);

            return [
                'customer_name' => $clientName,
                'state' => $clientState ?: $clinicState,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => Carbon::parse($invoice->invoice_date)->format('d-m-Y'),
                'taxable_amount' => round((float) $invoice->taxable_value, 2),
                'sgst' => $sgst,
                'cgst' => $cgst,
                'igst' => $igst,
                'gst_rate' => $gstRate,
                'invoice_total' => round((float) $invoice->grand_total, 2),
            ];
        });
    }

    /**
     * Get the predominant GST rate from invoice items.
     */
    protected function getGstRate(Invoice $invoice): string
    {
        if ($invoice->items->isEmpty()) {
            return '0%';
        }

        // Get the most common GST percentage among items
        $rates = $invoice->items->groupBy('gst_percentage');
        $predominantRate = $rates->sortByDesc(function ($items) {
            return $items->sum('line_total');
        })->keys()->first();

        return rtrim(rtrim(number_format((float) $predominantRate, 2), '0'), '.') . '%';
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'State',
            'Invoice Number',
            'Invoice Date',
            'Taxable Amount',
            'SGST Tax',
            'CGST Tax',
            'IGST Tax',
            'GST Rate',
            'Invoice Total',
        ];
    }

    public function title(): string
    {
        return 'GST Report';
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

                // Apply currency number format to amount columns (E, F, G, H, J)
                $currencyFormat = '#,##0.00';
                $amountColumns = ['E', 'F', 'G', 'H', 'J'];
                foreach ($amountColumns as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode($currencyFormat);
                }

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
                $sheet->setCellValue("E{$totalsRow}", "=SUM(E{$dataStartRow}:E{$highestRow})");
                $sheet->setCellValue("F{$totalsRow}", "=SUM(F{$dataStartRow}:F{$highestRow})");
                $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStartRow}:G{$highestRow})");
                $sheet->setCellValue("H{$totalsRow}", "=SUM(H{$dataStartRow}:H{$highestRow})");
                $sheet->setCellValue("J{$totalsRow}", "=SUM(J{$dataStartRow}:J{$highestRow})");

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

                // Apply currency format to totals row amount columns
                foreach ($amountColumns as $col) {
                    $sheet->getStyle("{$col}{$totalsRow}")
                        ->getNumberFormat()
                        ->setFormatCode($currencyFormat);
                }

                // Freeze header row
                $sheet->freezePane('A2');

                // Set minimum column widths
                $minWidths = [
                    'A' => 22, 'B' => 8, 'C' => 18, 'D' => 14,
                    'E' => 16, 'F' => 14, 'G' => 14, 'H' => 14,
                    'I' => 10, 'J' => 16,
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
