<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Models\LeadImport;
use App\Models\LeadImportFailure;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use SplTempFileObject;

/**
 * Produces the downloadable "failed rows" file.
 *
 * The output is a repair sheet, not a log: it carries the original columns
 * exactly as they arrived plus three diagnostic columns, so the user can fix
 * the offending cells and hand the same file straight back to the importer.
 */
class FailedRowExportService
{
    public const COLUMN_ROW = '_row_number';
    public const COLUMN_REASON = '_failure_reason';
    public const COLUMN_ERRORS = '_field_errors';

    public function __construct(
        protected CsvReaderService $reader,
    ) {}

    /**
     * Write the failed rows for an import and return the stored path.
     *
     * Returns null when there is nothing to export, so callers can skip
     * offering a download rather than serving an empty file.
     */
    public function generate(LeadImport $import): ?string
    {
        if (! $import->failures()->exists()) {
            return null;
        }

        $headers = $import->detected_headers ?: $this->inferHeaders($import);

        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');

        $writer->insertOne(array_merge($headers, [
            self::COLUMN_ROW,
            self::COLUMN_REASON,
            self::COLUMN_ERRORS,
        ]));

        $import->failures()
            ->orderBy('row_number')
            ->chunkById(500, function ($failures) use ($writer, $headers): void {
                foreach ($failures as $failure) {
                    $writer->insertOne($this->buildRow($failure, $headers));
                }
            });

        $path = sprintf(
            '%s/%d_failed_%s.csv',
            trim((string) config('leads.storage.failed_directory', 'lead-imports/failed'), '/'),
            $import->getKey(),
            now()->format('Ymd_His')
        );

        // Excel assumes the system codepage unless a UTF-8 mark is present, and
        // these files contain rupee signs and curly apostrophes that would
        // otherwise render as mojibake for the person trying to fix them.
        Storage::disk($import->disk)->put($path, "\xEF\xBB\xBF" . $writer->toString());

        return $path;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    protected function buildRow(LeadImportFailure $failure, array $headers): array
    {
        $raw = $failure->raw_row ?? [];
        $row = [];

        foreach ($headers as $header) {
            // Escaping happens on the way out rather than on the way in, so a
            // value that was only ever data cannot become a live formula when
            // the repair sheet is opened in Excel.
            $row[] = $this->reader->escapeForExport((string) ($raw[$header] ?? ''));
        }

        $row[] = (string) $failure->row_number;
        $row[] = $this->reader->escapeForExport($failure->reason_code->getLabel() . ': ' . $failure->reason);
        $row[] = $this->reader->escapeForExport($failure->error_summary);

        return $row;
    }

    /**
     * Recover the header list from a stored failure when the import record has
     * lost it.
     *
     * @return array<int, string>
     */
    protected function inferHeaders(LeadImport $import): array
    {
        $first = $import->failures()->orderBy('row_number')->first();

        return $first !== null && is_array($first->raw_row)
            ? array_keys($first->raw_row)
            : [];
    }
}
