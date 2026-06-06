<?php

namespace App\Exports;

use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Carbon;

class HsnGstSummaryExport implements WithMultipleSheets
{
    protected string $startDate;
    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function sheets(): array
    {
        return [
            new HsnSummarySheet($this->startDate, $this->endDate),
            new GstRateSummarySheet($this->startDate, $this->endDate),
        ];
    }
}

class HsnSummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    protected string $startDate;
    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        $items = InvoiceItem::whereHas('invoice', function ($query) {
                $query->whereDate('invoice_date', '>=', $this->startDate)
                      ->whereDate('invoice_date', '<=', $this->endDate)
                      ->whereNotIn('status', ['draft', 'cancelled'])
                      ->where('grand_total', '>', 0);
            })
            ->selectRaw('hsn_sac_code, SUM(taxable_value) as total_taxable, SUM(gst_amount) as total_gst')
            ->groupBy('hsn_sac_code')
            ->get();

        return $items->map(function ($item) {
            return [
                'hsn_sac_code' => $item->hsn_sac_code ?? 'N/A',
                'taxable_value' => round((float) $item->total_taxable, 2),
                'gst_amount' => round((float) $item->total_gst, 2),
            ];
        });
    }

    public function headings(): array
    {
        return ['HSN/SAC', 'Taxable Value', 'GST Amount'];
    }

    public function title(): string
    {
        return 'HSN-SAC Summary';
    }

    public function styles(Worksheet $sheet)
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
}

class GstRateSummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    protected string $startDate;
    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        $items = InvoiceItem::whereHas('invoice', function ($query) {
                $query->whereDate('invoice_date', '>=', $this->startDate)
                      ->whereDate('invoice_date', '<=', $this->endDate)
                      ->whereNotIn('status', ['draft', 'cancelled'])
                      ->where('grand_total', '>', 0);
            })
            ->selectRaw('gst_percentage, SUM(taxable_value) as total_taxable, SUM(gst_amount) as total_gst')
            ->groupBy('gst_percentage')
            ->get();

        return $items->map(function ($item) {
            return [
                'gst_percentage' => floatval($item->gst_percentage) . '%',
                'taxable_value' => round((float) $item->total_taxable, 2),
                'gst_amount' => round((float) $item->total_gst, 2),
            ];
        });
    }

    public function headings(): array
    {
        return ['GST %', 'Taxable Value', 'GST Amount'];
    }

    public function title(): string
    {
        return 'GST Rate Summary';
    }

    public function styles(Worksheet $sheet)
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
}
