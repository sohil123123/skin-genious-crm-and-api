<?php

declare(strict_types=1);

namespace App\DTOs\Lead;

use App\Enums\LeadImportStatus;
use App\Models\LeadImport;

/**
 * A snapshot of an in-flight import, shaped for the progress panel.
 */
final readonly class ImportProgressDto
{
    public function __construct(
        public LeadImportStatus $status,
        public int $totalRows,
        public int $processedRows,
        public int $importedRows,
        public int $updatedRows,
        public int $skippedRows,
        public int $failedRows,
        public float $percentage,
        public ?int $etaSeconds = null,
        public ?string $etaForHumans = null,
        public ?int $elapsedSeconds = null,
    ) {}

    public static function fromImport(LeadImport $import): self
    {
        $elapsed = $import->started_at !== null
            ? (int) ($import->finished_at ?? now())->diffInSeconds($import->started_at, absolute: true)
            : null;

        return new self(
            status: $import->status,
            totalRows: $import->total_rows,
            processedRows: $import->processed_rows,
            importedRows: $import->imported_rows,
            updatedRows: $import->updated_rows,
            skippedRows: $import->skipped_rows,
            failedRows: $import->failed_rows,
            percentage: $import->progress_percentage,
            etaSeconds: $import->eta_seconds,
            etaForHumans: $import->eta_for_humans,
            elapsedSeconds: $elapsed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'status_color' => $this->status->getColor(),
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'imported_rows' => $this->importedRows,
            'updated_rows' => $this->updatedRows,
            'skipped_rows' => $this->skippedRows,
            'failed_rows' => $this->failedRows,
            'percentage' => $this->percentage,
            'eta_seconds' => $this->etaSeconds,
            'eta_for_humans' => $this->etaForHumans,
            'elapsed_seconds' => $this->elapsedSeconds,
        ];
    }
}
