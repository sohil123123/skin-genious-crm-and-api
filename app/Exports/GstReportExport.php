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

        return $invoices->flatMap(function ($invoice) use ($companyState) {
            $clientName = $invoice->client
                ? trim($invoice->client->first_name . ' ' . ($invoice->client->last_name ?? ''))
                : 'N/A';

            $clinicState = $invoice->clinic->state ?? $companyState;
            $clientState = $invoice->client->state ?? null;

            // Determine GST type: same state = SGST+CGST, different state = IGST
            $isSameState = empty($clientState) || strtoupper($clinicState) === strtoupper($clientState);

            $groups = $invoice->items->groupBy(function ($item) {
                return $item->hsn_sac_code;
            });

            $rows = [];
            $isFirstRow = true;

            foreach ($groups as $hsnCode => $items) {
                // Get the most common GST percentage among items in this group
                $rates = $items->groupBy('gst_percentage');
                $predominantRate = $rates->sortByDesc(function ($gItems) {
                    return $gItems->sum('line_total');
                })->keys()->first();
                $gstRateStr = rtrim(rtrim(number_format((float) $predominantRate, 2), '0'), '.') . '%';

                $numberOfItems = $items->sum('quantity');
                $taxableAmount = (float) $items->sum('taxable_value');
                $gstAmount = (float) $items->sum('gst_amount');
                $rowTotal = (float) $items->sum('line_total');

                $sgst = $isSameState ? round($gstAmount / 2, 2) : 0;
                $cgst = $isSameState ? round($gstAmount / 2, 2) : 0;
                $igst = $isSameState ? 0 : round($gstAmount, 2);

                $rows[] = [
                    'customer_name' => $isFirstRow ? $clientName : '',
                    'state' => $isFirstRow ? ($clientState ?: $clinicState) : '',
                    'invoice_number' => $isFirstRow ? $invoice->invoice_number : '',
                    'invoice_date' => $isFirstRow ? Carbon::parse($invoice->invoice_date)->format('d-m-Y') : '',
                    'hsn_code' => $hsnCode,
                    'number_of_items' => $numberOfItems,
                    'taxable_amount' => round($taxableAmount, 2),
                    'sgst' => $sgst,
                    'cgst' => $cgst,
                    'igst' => $igst,
                    'gst_rate' => $gstRateStr,
                    'invoice_total' => round($rowTotal, 2),
                ];

                $isFirstRow = false;
            }

            // Fallback if no items found
            if ($groups->isEmpty()) {
                $gstTotal = (float) $invoice->gst_total;
                $sgst = $isSameState ? round($gstTotal / 2, 2) : 0;
                $cgst = $isSameState ? round($gstTotal / 2, 2) : 0;
                $igst = $isSameState ? 0 : round($gstTotal, 2);

                $rows[] = [
                    'customer_name' => $clientName,
                    'state' => $clientState ?: $clinicState,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => Carbon::parse($invoice->invoice_date)->format('d-m-Y'),
                    'hsn_code' => '',
                    'number_of_items' => 0,
                    'taxable_amount' => round((float) $invoice->taxable_value, 2),
                    'sgst' => $sgst,
                    'cgst' => $cgst,
                    'igst' => $igst,
                    'gst_rate' => '0%',
                    'invoice_total' => round((float) $invoice->grand_total, 2),
                ];
            }

            return $rows;
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
            'HSN CODE',
            'Number of ITEMS',
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

                // Apply currency number format to amount columns (G, H, I, J, L)
                $currencyFormat = '#,##0.00';
                $amountColumns = ['G', 'H', 'I', 'J', 'L'];
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
                $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStartRow}:G{$highestRow})");
                $sheet->setCellValue("H{$totalsRow}", "=SUM(H{$dataStartRow}:H{$highestRow})");
                $sheet->setCellValue("I{$totalsRow}", "=SUM(I{$dataStartRow}:I{$highestRow})");
                $sheet->setCellValue("J{$totalsRow}", "=SUM(J{$dataStartRow}:J{$highestRow})");
                $sheet->setCellValue("L{$totalsRow}", "=SUM(L{$dataStartRow}:L{$highestRow})");

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
                    'E' => 12, 'F' => 15, 'G' => 16, 'H' => 14,
                    'I' => 14, 'J' => 14, 'K' => 10, 'L' => 16,
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
