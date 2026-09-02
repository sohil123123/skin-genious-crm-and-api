<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\DTOs\Lead\CsvAnalysisResult;
use App\Enums\CrmLeadField;
use App\Enums\PhoneStatus;

/**
 * Profiles a file so the preview screen can describe it before a single row is
 * written.
 *
 * The scan is a single streaming pass. It collects the sample rows the user
 * sees, the distinct values that drive select-field detection, and the counts
 * that answer the only question that matters at that point: is this file going
 * to import cleanly, and if not, what exactly is wrong with it?
 */
class CsvAnalysisService
{
    public function __construct(
        protected CsvReaderService $reader,
        protected ColumnAutoMapperService $autoMapper,
        protected PhoneNormalizerService $phoneNormalizer,
    ) {}

    public function analyze(string $absolutePath): CsvAnalysisResult
    {
        $format = $this->reader->inspect($absolutePath);
        $headers = $format['headers'];

        $previewLimit = (int) config('leads.csv.preview_rows', 20);
        $maxDistinct = (int) config('leads.csv.max_distinct_for_select', 25);

        $sampleRows = [];
        $distinct = [];
        $blankCounts = array_fill_keys($headers, 0);
        $seenIdentities = [];

        $totalRows = 0;
        $blankRows = 0;
        $duplicateRows = 0;
        $invalidPhoneRows = 0;
        $needsReviewPhoneRows = 0;

        // Which column holds the phone, so the preview can warn about numbers
        // that will fail before the user commits to the import.
        $phoneColumn = $this->locateColumn($headers, CrmLeadField::Phone);
        $leadIdColumn = $this->locateColumn($headers, CrmLeadField::FbLeadId);

        foreach ($this->reader->records($absolutePath, $format['encoding'], $format['delimiter']) as $row) {
            $totalRows++;

            if ($this->isBlankRow($row)) {
                $blankRows++;

                continue;
            }

            if (count($sampleRows) < $previewLimit) {
                $sampleRows[] = $row;
            }

            foreach ($headers as $header) {
                $value = trim((string) ($row[$header] ?? ''));

                if ($value === '') {
                    $blankCounts[$header]++;

                    continue;
                }

                // Distinct collection is capped: once a column is clearly free
                // text there is nothing to gain from tracking more of it, and
                // an unbounded set would grow with the file.
                if (! isset($distinct[$header]) || count($distinct[$header]) <= $maxDistinct) {
                    $distinct[$header][$value] = true;
                }
            }

            if ($phoneColumn !== null) {
                $result = $this->phoneNormalizer->normalize($row[$phoneColumn] ?? null);

                match ($result->status) {
                    PhoneStatus::Invalid => $invalidPhoneRows++,
                    PhoneStatus::NeedsReview => $needsReviewPhoneRows++,
                    default => null,
                };
            }

            // In-file duplicates are counted on the same key the importer will
            // later match on, so the preview number matches the outcome.
            $identity = $this->identityKey($row, $leadIdColumn, $phoneColumn);

            if ($identity !== null) {
                if (isset($seenIdentities[$identity])) {
                    $duplicateRows++;
                } else {
                    $seenIdentities[$identity] = true;
                }
            }
        }

        $distinctValues = [];

        foreach ($distinct as $header => $values) {
            $distinctValues[$header] = array_slice(array_keys($values), 0, $maxDistinct + 1);
        }

        $detectedTypes = [];

        foreach ($headers as $header) {
            $detectedTypes[$header] = $this->autoMapper->inferType($distinctValues[$header] ?? [])->value;
        }

        return new CsvAnalysisResult(
            headers: $headers,
            sampleRows: $sampleRows,
            detectedTypes: $detectedTypes,
            distinctValues: $distinctValues,
            blankCounts: $blankCounts,
            duplicateHeaders: $this->findDuplicateHeaders($headers),
            missingRequiredFields: $this->findMissingRequiredFields($headers),
            totalRows: $totalRows,
            blankRows: $blankRows,
            duplicateRows: $duplicateRows,
            invalidPhoneRows: $invalidPhoneRows,
            needsReviewPhoneRows: $needsReviewPhoneRows,
            encoding: $format['encoding'],
            delimiter: $format['delimiter'],
            hasBom: $format['has_bom'],
        );
    }

    /**
     * Find the header that auto-maps onto a given CRM field.
     *
     * @param  array<int, string>  $headers
     */
    protected function locateColumn(array $headers, CrmLeadField $field): ?string
    {
        $lookup = CrmLeadField::aliasLookup();

        foreach ($headers as $header) {
            $normalized = $this->autoMapper->normalizeHeader($header);

            if (($lookup[$normalized] ?? null) === $field) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function identityKey(array $row, ?string $leadIdColumn, ?string $phoneColumn): ?string
    {
        if ($leadIdColumn !== null && trim((string) ($row[$leadIdColumn] ?? '')) !== '') {
            return 'id:' . trim((string) $row[$leadIdColumn]);
        }

        if ($phoneColumn !== null) {
            $key = $this->phoneNormalizer->matchKey($row[$phoneColumn] ?? null);

            if ($key !== null) {
                return 'phone:' . $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Headers appearing more than once, after the reader has already made them
     * unique by suffixing. Reported so the user knows a column was renamed.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    protected function findDuplicateHeaders(array $headers): array
    {
        $normalized = array_map(fn (string $header): string => $this->autoMapper->normalizeHeader($header), $headers);
        $counts = array_count_values(array_filter($normalized, fn (string $header): bool => $header !== ''));

        return array_values(array_keys(array_filter($counts, fn (int $count): bool => $count > 1)));
    }

    /**
     * Required CRM fields with no column to fill them.
     *
     * Only the phone is required. Email deliberately is not: the lead forms in
     * this account do not collect one, so requiring it would reject every real
     * export.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    protected function findMissingRequiredFields(array $headers): array
    {
        $missing = [];

        foreach (CrmLeadField::required() as $field) {
            if ($this->locateColumn($headers, $field) === null) {
                $missing[] = $field->getLabel();
            }
        }

        return $missing;
    }
}
