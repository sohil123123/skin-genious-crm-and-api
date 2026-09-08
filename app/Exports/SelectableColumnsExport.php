<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base Excel export where the user picks which columns to include.
 *
 * Subclasses declare every available column once in columnDefinitions(); the
 * export then renders only the selected ones, keeping the styling used by the
 * GST report (styled header, autofilter, borders, frozen pane, totals row).
 *
 * A column definition is keyed by column name and accepts:
 *   label  (string)   Heading text. Required.
 *   value  (callable) Receives the record, returns the cell value. Required.
 *   type   (string)   text|number|currency|date — drives number formatting.
 *   sum    (bool)     Include a SUM() for this column in the totals row.
 *   width  (int)      Minimum column width.
 */
abstract class SelectableColumnsExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
    protected const CURRENCY_FORMAT = '#,##0.00';

    protected const NUMBER_FORMAT = '#,##0';

    /** @var array<string, array> Definitions of the columns actually being exported, in declaration order. */
    protected array $columns;

    public function __construct(
        protected Builder $query,
        array $selectedColumns = [],
        protected ?string $reportTitle = null,
    ) {
        $this->columns = static::resolveColumns($selectedColumns);
    }

    /**
     * Every column this export can produce, keyed by column name.
     *
     * @return array<string, array>
     */
    abstract public static function columnDefinitions(): array;

    /**
     * Relations eager loaded before building rows.
     *
     * @return array<int, string>
     */
    public static function relationsToLoad(): array
    {
        return [];
    }

    /**
     * Column name => label, for building a column picker.
     *
     * @return array<string, string>
     */
    public static function columnOptions(): array
    {
        return array_map(fn (array $definition): string => $definition['label'], static::columnDefinitions());
    }

    /**
     * Columns ticked by default — everything.
     *
     * @return array<int, string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(static::columnDefinitions());
    }

    /**
     * Narrow the definitions down to the selected columns, preserving declaration order.
     *
     * @return array<string, array>
     */
    protected static function resolveColumns(array $selectedColumns): array
    {
        $definitions = static::columnDefinitions();

        $selected = array_values(array_intersect(array_keys($definitions), $selectedColumns));

        if ($selected === []) {
            return $definitions;
        }

        return array_intersect_key($definitions, array_flip($selected));
    }

    public function collection(): Collection
    {
        $query = $this->query->clone();

        if ($relations = static::relationsToLoad()) {
            $query->with($relations);
        }

        $rows = collect();

        $query->chunk(500, function (Collection $records) use ($rows): void {
            foreach ($records as $record) {
                $row = [];

                foreach ($this->columns as $key => $definition) {
                    $row[$key] = ($definition['value'])($record);
                }

                $rows->push($row);
            }
        });

        return $rows;
    }

    public function headings(): array
    {
        return array_values(array_map(fn (array $definition): string => $definition['label'], $this->columns));
    }

    public function title(): string
    {
        return $this->reportTitle ?: 'Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->setAutoFilter("A1:{$highestColumn}1");

                $hasData = $highestRow > 1;

                // Number formats, minimum widths and the letter of each column.
                $sumColumns = [];
                $index = 1;

                foreach ($this->columns as $definition) {
                    $letter = Coordinate::stringFromColumnIndex($index);
                    $type = $definition['type'] ?? 'text';

                    $format = match ($type) {
                        'currency' => self::CURRENCY_FORMAT,
                        'number' => self::NUMBER_FORMAT,
                        default => null,
                    };

                    if ($format && $hasData) {
                        $sheet->getStyle("{$letter}2:{$letter}{$highestRow}")
                            ->getNumberFormat()
                            ->setFormatCode($format);
                    }

                    if ($definition['sum'] ?? false) {
                        $sumColumns[$letter] = $format ?? self::CURRENCY_FORMAT;
                    }

                    if ($width = $definition['width'] ?? null) {
                        $dimension = $sheet->getColumnDimension($letter);
                        if ($dimension->getWidth() < $width) {
                            $dimension->setWidth($width);
                        }
                    }

                    $index++;
                }

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('D1D5DB');

                $sheet->getRowDimension(1)->setRowHeight(28);

                if ($hasData && $sumColumns !== []) {
                    $totalsRow = $highestRow + 1;

                    $sheet->setCellValue("A{$totalsRow}", 'TOTAL');

                    foreach ($sumColumns as $letter => $format) {
                        if ($letter !== 'A') {
                            $sheet->setCellValue("{$letter}{$totalsRow}", "=SUM({$letter}2:{$letter}{$highestRow})");
                        }

                        $sheet->getStyle("{$letter}{$totalsRow}")
                            ->getNumberFormat()
                            ->setFormatCode($format);
                    }

                    $sheet->getStyle("A{$totalsRow}:{$highestColumn}{$totalsRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'color' => ['rgb' => '1F2937'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEF3C7'],
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
                }

                $sheet->freezePane('A2');
            },
        ];
    }
}
