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

class ProductPurchaseReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
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
        $products = Product::query()
            ->whereIn('type', ['product', 'iv_product'])
            ->withSum([
                'purchaseItems as total_purchased_qty' => function (Builder $query) {
                    $query->whereHas('purchase', function ($q) {
                        if ($this->startDate) {
                            $q->whereDate('purchase_date', '>=', $this->startDate);
                        }
                        if ($this->endDate) {
                            $q->whereDate('purchase_date', '<=', $this->endDate);
                        }
                        if ($this->clinicId) {
                            $q->where('clinic_id', $this->clinicId);
                        } elseif (!check_role('super_admin')) {
                            $q->where('clinic_id', auth()->user()->clinic_id);
                        }
                    });
                }
            ], 'quantity')
            ->withSum([
                'purchaseItems as actual_cost' => function (Builder $query) {
                    $query->whereHas('purchase', function ($q) {
                        if ($this->startDate) {
                            $q->whereDate('purchase_date', '>=', $this->startDate);
                        }
                        if ($this->endDate) {
                            $q->whereDate('purchase_date', '<=', $this->endDate);
                        }
                        if ($this->clinicId) {
                            $q->where('clinic_id', $this->clinicId);
                        } elseif (!check_role('super_admin')) {
                            $q->where('clinic_id', auth()->user()->clinic_id);
                        }
                    });
                }
            ], 'total')
            ->withSum([
                'clinicInventories as current_stock' => function (Builder $query) {
                    if ($this->clinicId) {
                        $query->where('clinic_id', $this->clinicId);
                    } elseif (!check_role('super_admin')) {
                        $query->where('clinic_id', auth()->user()->clinic_id);
                    }
                }
            ], 'stock_quantity')
            ->having('total_purchased_qty', '>', 0)
            ->orderByDesc('total_purchased_qty')
            ->get();

        return $products->map(function ($product) {
            return [
                'name' => $product->name . " (" . str_replace('_', ' ', $product->type) . ")",
                'quantity_purchased' => $product->total_purchased_qty ?? 0,
                'actual_cost' => $product->actual_cost ?? 0,
                'current_stock' => $product->current_stock ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Product Name',
            'Quantity Purchased',
            'Total Cost',
            'Current Stock',
        ];
    }

    public function title(): string
    {
        return 'Product Purchase Report';
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
