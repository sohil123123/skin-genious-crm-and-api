<?php

declare(strict_types=1);

namespace App\Jobs\Lead;

use App\Enums\LeadImportStatus;
use App\Models\LeadImport;
use App\Models\LeadImportLog;
use App\Services\Lead\CsvReaderService;
use App\Services\Lead\LeadImportService;
use App\Services\Lead\LeadRowImporterService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports one window of rows from a file.
 *
 * The job carries an offset and a length, not the rows themselves. Each worker
 * opens the file, seeks to its own window and streams only that slice, so the
 * queue payload is a few bytes and peak memory is independent of file size — a
 * 100k-row import costs the same per worker as a 100-row one.
 */
class ImportLeadChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public int $importId,
        public int $offset,
        public int $length,
    ) {
        $this->timeout = (int) config('leads.import.job_timeout', 1800);
        $this->tries = (int) config('leads.import.job_tries', 3);

        $this->onQueue((string) config('leads.import.queue', 'lead-imports'));

        if ($connection = config('leads.import.connection')) {
            $this->onConnection($connection);
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('leads.import.job_backoff', [30, 120, 300]);
    }

    public function handle(
        LeadImportService $importService,
        CsvReaderService $reader,
        LeadRowImporterService $rowImporter,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = LeadImport::find($this->importId);

        if ($import === null || $import->status === LeadImportStatus::Cancelled) {
            return;
        }

        if (! $import->hasFile()) {
            throw new \RuntimeException('The uploaded file is no longer available on disk.');
        }

        $rowImporter->prepare($import);

        $processed = 0;

        foreach ($reader->records(
            $importService->absolutePath($import),
            $import->encoding,
            $import->delimiter,
            $this->offset,
            $this->length,
        ) as $rowNumber => $row) {
            if ($this->batch()?->cancelled()) {
                break;
            }

            $result = $rowImporter->import($row, $rowNumber);
            $importService->recordRowResult($import, $result, $row);
            $processed++;
        }

        $rowImporter->flushFieldUsage();

        $import->log(
            LeadImportLog::EVENT_CHUNK_COMPLETED,
            sprintf('Rows %d–%d processed (%d rows).', $this->offset + 1, $this->offset + $processed, $processed),
            ['offset' => $this->offset, 'length' => $this->length, 'processed' => $processed],
            LeadImportLog::LEVEL_DEBUG,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('lead_imports')->error('Lead import chunk failed.', [
            'import_id' => $this->importId,
            'offset' => $this->offset,
            'length' => $this->length,
            'exception' => $exception->getMessage(),
        ]);

        $import = LeadImport::find($this->importId);

        // The batch is configured to allow failures so one bad chunk cannot
        // abandon the rest of the file. The failure is recorded here instead,
        // so the import history still shows what went wrong.
        $import?->log(
            LeadImportLog::EVENT_FAILED,
            sprintf('Rows %d–%d could not be processed: %s', $this->offset + 1, $this->offset + $this->length, $exception->getMessage()),
            ['offset' => $this->offset, 'length' => $this->length],
            LeadImportLog::LEVEL_ERROR,
        );
    }
}
