<?php

declare(strict_types=1);

namespace App\Jobs\Lead;

use App\Enums\LeadImportStatus;
use App\Models\LeadImport;
use App\Models\LeadImportLog;
use App\Services\Lead\CsvReaderService;
use App\Services\Lead\LeadImportService;
use App\Services\Lead\LeadRowImporterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coordinates one import.
 *
 * Small files are imported inline here. Large ones are fanned out into chunk
 * jobs. The threshold exists because batching is not free — it writes a batch
 * row, a job row per chunk and a completion callback — and the everyday case
 * for this clinic is a fifty-row export that should simply finish instantly.
 */
class ProcessLeadImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public int $importId,
    ) {
        $this->onQueue((string) config('leads.import.queue', 'lead-imports'));

        if ($connection = config('leads.import.connection')) {
            $this->onConnection($connection);
        }
    }

    public function handle(
        LeadImportService $importService,
        CsvReaderService $reader,
        LeadRowImporterService $rowImporter,
    ): void {
        $import = LeadImport::find($this->importId);

        if ($import === null) {
            Log::channel('lead_imports')->warning('Import record no longer exists.', ['import_id' => $this->importId]);

            return;
        }

        if ($import->status === LeadImportStatus::Cancelled) {
            return;
        }

        try {
            if (! $import->hasFile()) {
                $importService->markFailed($import, 'The uploaded file is no longer available on disk.');

                return;
            }

            $absolutePath = $importService->absolutePath($import);

            // The row count from the analysis pass can be stale if the file was
            // replaced, and every offset the chunk jobs use depends on it, so it
            // is re-established here before anything is dispatched.
            $totalRows = $reader->countRows($absolutePath, $import->encoding, $import->delimiter);

            $import->forceFill([
                'status' => LeadImportStatus::Processing->value,
                'total_rows' => $totalRows,
                'started_at' => now(),
            ])->save();

            if ($totalRows === 0) {
                $importService->finalize($import);

                return;
            }

            $syncThreshold = (int) config('leads.import.sync_threshold', 2000);

            if ($totalRows <= $syncThreshold) {
                $this->importInline($import, $importService, $reader, $rowImporter, $absolutePath);
                $importService->finalize($import);

                return;
            }

            $import->log(
                LeadImportLog::EVENT_QUEUED,
                sprintf(
                    '%d rows exceed the inline threshold of %d; splitting into chunks of %d.',
                    $totalRows,
                    $syncThreshold,
                    (int) config('leads.import.chunk_size', 500)
                ),
            );

            $importService->dispatchChunks($import);
        } catch (Throwable $exception) {
            Log::channel('lead_imports')->error('Lead import coordinator failed.', [
                'import_id' => $this->importId,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $importService->markFailed($import, $exception->getMessage());
        }
    }

    /**
     * Import every row in this job, for files small enough not to warrant a batch.
     */
    protected function importInline(
        LeadImport $import,
        LeadImportService $importService,
        CsvReaderService $reader,
        LeadRowImporterService $rowImporter,
        string $absolutePath,
    ): void {
        $rowImporter->prepare($import);

        foreach ($reader->records($absolutePath, $import->encoding, $import->delimiter) as $rowNumber => $row) {
            $result = $rowImporter->import($row, $rowNumber);
            $importService->recordRowResult($import, $result, $row);
        }

        $rowImporter->flushFieldUsage();
    }

    public function failed(Throwable $exception): void
    {
        $import = LeadImport::find($this->importId);

        if ($import !== null) {
            app(LeadImportService::class)->markFailed($import, $exception->getMessage());
        }
    }
}
