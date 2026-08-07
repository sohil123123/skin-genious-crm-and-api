<?php

declare(strict_types=1);

namespace App\DTOs\Lead;

/**
 * Everything the preview screen needs to describe a file before importing it.
 *
 * Cached onto lead_imports.analysis so re-opening the wizard, or a queue worker
 * picking the import up later, never has to re-scan the file.
 */
final readonly class CsvAnalysisResult
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, string>>  $sampleRows
     * @param  array<string, string>  $detectedTypes  header => LeadFieldType value
     * @param  array<string, array<int, string>>  $distinctValues  header => distinct answers, capped
     * @param  array<string, int>  $blankCounts  header => number of blank cells
     * @param  array<int, string>  $duplicateHeaders
     * @param  array<int, string>  $missingRequiredFields
     */
    public function __construct(
        public array $headers = [],
        public array $sampleRows = [],
        public array $detectedTypes = [],
        public array $distinctValues = [],
        public array $blankCounts = [],
        public array $duplicateHeaders = [],
        public array $missingRequiredFields = [],
        public int $totalRows = 0,
        public int $blankRows = 0,
        public int $duplicateRows = 0,
        public int $invalidPhoneRows = 0,
        public int $needsReviewPhoneRows = 0,
        public string $encoding = 'UTF-8',
        public string $delimiter = ',',
        public bool $hasBom = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            headers: (array) ($data['headers'] ?? []),
            sampleRows: (array) ($data['sample_rows'] ?? []),
            detectedTypes: (array) ($data['detected_types'] ?? []),
            distinctValues: (array) ($data['distinct_values'] ?? []),
            blankCounts: (array) ($data['blank_counts'] ?? []),
            duplicateHeaders: (array) ($data['duplicate_headers'] ?? []),
            missingRequiredFields: (array) ($data['missing_required_fields'] ?? []),
            totalRows: (int) ($data['total_rows'] ?? 0),
            blankRows: (int) ($data['blank_rows'] ?? 0),
            duplicateRows: (int) ($data['duplicate_rows'] ?? 0),
            invalidPhoneRows: (int) ($data['invalid_phone_rows'] ?? 0),
            needsReviewPhoneRows: (int) ($data['needs_review_phone_rows'] ?? 0),
            encoding: (string) ($data['encoding'] ?? 'UTF-8'),
            delimiter: (string) ($data['delimiter'] ?? ','),
            hasBom: (bool) ($data['has_bom'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'headers' => $this->headers,
            'sample_rows' => $this->sampleRows,
            'detected_types' => $this->detectedTypes,
            'distinct_values' => $this->distinctValues,
            'blank_counts' => $this->blankCounts,
            'duplicate_headers' => $this->duplicateHeaders,
            'missing_required_fields' => $this->missingRequiredFields,
            'total_rows' => $this->totalRows,
            'blank_rows' => $this->blankRows,
            'duplicate_rows' => $this->duplicateRows,
            'invalid_phone_rows' => $this->invalidPhoneRows,
            'needs_review_phone_rows' => $this->needsReviewPhoneRows,
            'encoding' => $this->encoding,
            'delimiter' => $this->delimiter,
            'has_bom' => $this->hasBom,
        ];
    }

    /**
     * Rows that stand a chance of importing cleanly.
     */
    public function importableRows(): int
    {
        return max($this->totalRows - $this->blankRows - $this->invalidPhoneRows, 0);
    }

    /**
     * Whether anything about the file should stop the user pressing Import.
     */
    public function hasBlockingIssues(): bool
    {
        return $this->totalRows === 0 || $this->missingRequiredFields !== [];
    }

    /**
     * The delimiter rendered for a human, since a tab is invisible.
     */
    public function delimiterLabel(): string
    {
        return match ($this->delimiter) {
            "\t" => 'Tab',
            ',' => 'Comma',
            ';' => 'Semicolon',
            '|' => 'Pipe',
            default => $this->delimiter,
        };
    }
}
