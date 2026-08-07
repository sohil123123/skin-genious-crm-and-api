<?php

declare(strict_types=1);

namespace App\Jobs\Lead;

use App\Models\LeadImport;
use App\Models\LeadImportLog;
use App\Services\Lead\FailedRowExportService;
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
 * Replays the rows that previously failed.
 *
 * Rows are replayed from the stored raw_row rather than from the file, so a
 * retry still works after the original upload has been pruned, and so a row
 * that was fixed by editing the underlying data (adding the missing patient,
 * correcting a phone number) succeeds without re-uploading anything.
 */
class RetryFailedLeadRowsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

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
        LeadRowImporterService $rowImporter,
        FailedRowExportService $failedExporter,
    ): void {
        $import = LeadImport::find($this->importId);

        if ($import === null) {
            return;
        }

        try {
            $rowImporter->prepare($import);

            $retried = 0;
            $recovered = 0;

            $import->failures()
                ->unresolved()
                ->orderBy('row_number')
                ->chunkById(200, function ($failures) use ($import, $rowImporter, &$retried, &$recovered): void {
                    foreach ($failures as $failure) {
                        $retried++;

                        $result = $rowImporter->import($failure->raw_row ?? [], $failure->row_number);

                        $failure->forceFill(['retried_at' => now()])->save();

                        if ($result->isFailure()) {
                            // Still broken: refresh the reason so the user sees
                            // the current problem rather than the original one.
                            $failure->forceFill([
                                'reason_code' => $result->reasonCode?->value ?? 'validation',
                                'reason' => (string) $result->reason,
                                'errors' => $result->errors === [] ? null : $result->errors,
                            ])->save();

                            continue;
                        }

                        $failure->markResolved();
                        $recovered++;

                        // The original run already counted this row as failed,
                        // so the tallies are corrected rather than re-added.
                        $import->newQuery()->whereKey($import->getKey())->decrement('failed_rows');
                        $import->newQuery()->whereKey($import->getKey())->increment($result->counterColumn());
                    }
                });

            $rowImporter->flushFieldUsage();

            // The download should reflect what is still outstanding, not what
            // failed the first time round.
            $import->forceFill([
                'failed_export_path' => $failedExporter->generate($import->refresh()),
            ])->save();

            $import->log(
                LeadImportLog::EVENT_RETRIED,
                sprintf('%d failed rows retried, %d recovered.', $retried, $recovered),
                ['retried' => $retried, 'recovered' => $recovered],
            );
        } catch (Throwable $exception) {
            Log::channel('lead_imports')->error('Retrying failed rows did not complete.', [
                'import_id' => $this->importId,
                'exception' => $exception->getMessage(),
            ]);

            $import->log(
                LeadImportLog::EVENT_FAILED,
                'Retrying failed rows did not complete: ' . $exception->getMessage(),
                [],
                LeadImportLog::LEVEL_ERROR,
            );
        }
    }
}
