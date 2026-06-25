<?php

namespace App\Exports;

use App\Models\Product;
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

class ProductSalesReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
    protected ?string $startDate;
    protected ?string $endDate;
    protected ?string $productType;

    public function __construct(?string $startDate, ?string $endDate, ?string $productType = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->productType = $productType;
    }

    public function collection(): Collection
    {
        $products = Product::query()
            ->when($this->productType, fn(Builder $q) => $q->where('type', $this->productType))
            ->withSum([
                'invoiceItems' => function (Builder $query) {
                    $query->whereHas('invoice', function (Builder $q) {
                        $q->where('status', '!=', 'cancelled');
                        if ($this->startDate) {
                            $q->whereDate('invoice_date', '>=', $this->startDate);
                        }
                        if ($this->endDate) {
                            $q->whereDate('invoice_date', '<=', $this->endDate);
                        }
                    });
                }
            ], 'quantity')
            ->withSum([
                'invoiceItems' => function (Builder $query) {
                    $query->whereHas('invoice', function (Builder $q) {
                        $q->where('status', '!=', 'cancelled');
                        if ($this->startDate) {
                            $q->whereDate('invoice_date', '>=', $this->startDate);
                        }
                        if ($this->endDate) {
                            $q->whereDate('invoice_date', '<=', $this->endDate);
                        }
                    });
                }
            ], 'line_total')
            ->having('invoice_items_sum_line_total', '>', 0)
            ->orderByDesc('invoice_items_sum_line_total')
            ->get();

        return $products->map(function ($product) {
            return [
                'name' => $product->name,
                'quantity_sold' => $product->type === 'product' ? ($product->invoice_items_sum_quantity ?? 0) : 0,
                'total_revenue' => $product->invoice_items_sum_line_total ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Product Name',
            'Quantity Sold',
            'Total Revenue',
        ];
    }

    public function title(): string
    {
        return 'Product Sales Report';
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

                // Apply quantity format to column B, and currency to column C
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
                    'A' => 35,
                    'B' => 18,
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
