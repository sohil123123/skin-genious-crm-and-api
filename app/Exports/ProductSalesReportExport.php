<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Database\Eloquent\Builder;

class ProductSalesReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected ?string $startDate;
    protected ?string $endDate;

    public function __construct(?string $startDate, ?string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        $products = Product::query()
            ->withSum(['invoiceItems' => function (Builder $query) {
                $query->whereHas('invoice', function (Builder $q) {
                    if ($this->startDate) {
                        $q->whereDate('invoice_date', '>=', $this->startDate);
                    }
                    if ($this->endDate) {
                        $q->whereDate('invoice_date', '<=', $this->endDate);
                    }
                });
            }], 'quantity')
            ->withSum(['invoiceItems' => function (Builder $query) {
                $query->whereHas('invoice', function (Builder $q) {
                    if ($this->startDate) {
                        $q->whereDate('invoice_date', '>=', $this->startDate);
                    }
                    if ($this->endDate) {
                        $q->whereDate('invoice_date', '<=', $this->endDate);
                    }
                });
            }], 'line_total')
            ->having('invoice_items_sum_quantity', '>', 0)
            ->orderByDesc('invoice_items_sum_line_total')
            ->get();

        return $products->map(function ($product) {
            return [
                'name' => $product->name,
                'quantity_sold' => $product->invoice_items_sum_quantity ?? 0,
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
                    'startColor' => ['rgb' => 'E0E7FF'],
                ],
            ],
        ];
    }
}
