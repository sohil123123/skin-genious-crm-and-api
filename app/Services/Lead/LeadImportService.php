<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\DTOs\Lead\ColumnMappingDto;
use App\DTOs\Lead\CsvAnalysisResult;
use App\DTOs\Lead\ImportProgressDto;
use App\DTOs\Lead\ImportRowResult;
use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\DuplicateStrategy;
use App\Enums\LeadImportStatus;
use App\Jobs\Lead\ImportLeadChunkJob;
use App\Jobs\Lead\ProcessLeadImportJob;
use App\Models\LeadImport;
use App\Models\LeadImportFailure;
use App\Models\LeadImportLog;
use App\Models\User;
// Notification actions are built from the same unified Action class as every
// other action in the panel; Filament\Notifications\Actions\Action no longer
// exists, and referencing it silently cost the uploader their notification.
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Coordinates the lifecycle of an import: store, analyse, map, dispatch,
 * finalise, retry.
 *
 * Everything stateful about an import lives on the LeadImport row rather than
 * in the session, so the wizard, the queue workers and the history screen are
 * all reading the same source of truth and an interrupted import can be picked
 * up again.
 */
class LeadImportService
{
    public function __construct(
        protected CsvReaderService $reader,
        protected CsvAnalysisService $analyzer,
        protected ColumnAutoMapperService $autoMapper,
        protected MappingTemplateService $templates,
        protected FailedRowExportService $failedExporter,
    ) {}

    // ────────────────────────────────────────────────────────────────
    // Creation
    // ────────────────────────────────────────────────────────────────

    /**
     * Register an already-stored upload and detect its format.
     *
     * @param  string  $storedPath  Path on the configured disk.
     */
    public function createFromUpload(
        string $storedPath,
        string $originalFilename,
        int $clinicId,
        ?int $userId = null,
    ): LeadImport {
        $disk = (string) config('leads.storage.disk', 'local');
        $absolutePath = Storage::disk($disk)->path($storedPath);

        $format = $this->reader->inspect($absolutePath);

        $import = LeadImport::create([
            'clinic_id' => $clinicId,
            'uploaded_by' => $userId ?? auth()->id(),
            'original_filename' => $originalFilename,
            'label' => $this->labelFromFilename($originalFilename),
            'stored_path' => $storedPath,
            'disk' => $disk,
            'file_size' => Storage::disk($disk)->size($storedPath),
            'file_hash' => hash_file('sha256', $absolutePath),
            'encoding' => $format['encoding'],
            'delimiter' => $format['delimiter'],
            'enclosure' => $format['enclosure'],
            'has_bom' => $format['has_bom'],
            'status' => LeadImportStatus::Analyzing->value,
            'detected_headers' => $format['headers'],
            'settings' => ImportSettingsDto::defaults(),
            'duplicate_strategy' => (string) config('leads.duplicates.default_strategy', 'skip'),
            'duplicate_match_fields' => (array) config('leads.duplicates.default_match_fields', ['fb_lead_id']),
        ]);

        $import->log(
            LeadImportLog::EVENT_UPLOADED,
            sprintf(
                'Uploaded "%s" (%s, %s-delimited, %d columns).',
                $originalFilename,
                $format['encoding'],
                $format['delimiter'] === "\t" ? 'tab' : $format['delimiter'],
                count($format['headers'])
            ),
            ['encoding' => $format['encoding'], 'delimiter' => $format['delimiter'], 'headers' => $format['headers']],
        );

        return $import;
    }

    /**
     * Look for an earlier import of byte-identical content.
     *
     * Meta exports overlap heavily — each one covers a date range that includes
     * everything before it — so re-uploading the exact same file is a common
     * mistake worth warning about.
     */
    public function findPreviousUploadOfSameFile(string $absolutePath, int $clinicId): ?LeadImport
    {
        return LeadImport::query()
            ->where('clinic_id', $clinicId)
            ->where('file_hash', hash_file('sha256', $absolutePath))
            ->latest('id')
            ->first();
    }

    // ────────────────────────────────────────────────────────────────
    // Analysis and mapping
    // ────────────────────────────────────────────────────────────────

    public function analyze(LeadImport $import): CsvAnalysisResult
    {
        $analysis = $this->analyzer->analyze($this->absolutePath($import));

        $import->forceFill([
            'analysis' => $analysis->toArray(),
            'detected_headers' => $analysis->headers,
            'total_rows' => $analysis->totalRows,
            'encoding' => $analysis->encoding,
            'delimiter' => $analysis->delimiter,
            'has_bom' => $analysis->hasBom,
            'status' => LeadImportStatus::Mapping->value,
        ])->save();

        $import->log(
            LeadImportLog::EVENT_ANALYZED,
            sprintf(
                '%d rows analysed: %d blank, %d in-file duplicates, %d invalid phone numbers.',
                $analysis->totalRows,
                $analysis->blankRows,
                $analysis->duplicateRows,
                $analysis->invalidPhoneRows
            ),
            $analysis->toArray(),
        );

        return $analysis;
    }

    public function cachedAnalysis(LeadImport $import): CsvAnalysisResult
    {
        return $import->analysis
            ? CsvAnalysisResult::fromArray($import->analysis)
            : $this->analyze($import);
    }

    /**
     * Propose a mapping, preferring a saved template over fresh detection.
     *
     * @return array{mapping: array<string, ColumnMappingDto>, template: ?\App\Models\LeadMappingTemplate}
     */
    public function proposeMapping(LeadImport $import): array
    {
        $analysis = $this->cachedAnalysis($import);
        $headers = $analysis->headers;

        $auto = $this->autoMapper->map($headers, $import->clinic_id, $analysis->distinctValues);
        $template = $this->templates->suggestFor($headers, $import->clinic_id);

        if ($template !== null) {
            // Saved decisions win, but any column the template does not know
            // about still gets an automatic proposal rather than being dropped.
            $auto = array_merge($auto, $this->templates->applyTo($template, $headers));
        }

        return ['mapping' => $auto, 'template' => $template];
    }

    /**
     * Store the confirmed mapping and settings, readying the import to run.
     *
     * @param  array<string, ColumnMappingDto>  $mapping
     */
    public function confirmMapping(
        LeadImport $import,
        array $mapping,
        ImportSettingsDto $settings,
        DuplicateStrategy $strategy,
        array $matchFields,
    ): LeadImport {
        $serialized = $this->templates->serializeMapping($mapping);

        // The wizard persists on every step transition and again when the
        // preview is built, so an unchanged configuration would write an
        // identical log line five times and bury the entries that matter.
        //
        // The comparison is canonical rather than a strict one: a round trip
        // through the database reorders JSON object keys and returns a stored
        // 100.0 as an integer, neither of which is a change the user made.
        $unchanged = $this->configurationFingerprint(
            $import->column_mapping ?? [],
            $import->settings ?? [],
            $import->duplicate_strategy?->value,
            $import->duplicate_match_fields ?? [],
        ) === $this->configurationFingerprint(
            $serialized,
            $settings->toArray(),
            $strategy->value,
            array_values($matchFields),
        );

        $import->forceFill([
            'column_mapping' => $serialized,
            'settings' => $settings->toArray(),
            'duplicate_strategy' => $strategy->value,
            'duplicate_match_fields' => array_values($matchFields),
            'status' => LeadImportStatus::Ready->value,
        ])->save();

        if ($unchanged) {
            return $import;
        }

        $mapped = count(array_filter($mapping, fn (ColumnMappingDto $dto): bool => ! $dto->isIgnored()));

        $import->log(
            LeadImportLog::EVENT_MAPPED,
            sprintf('Mapping confirmed: %d of %d columns will be imported.', $mapped, count($mapping)),
            ['mapping' => $this->templates->serializeMapping($mapping), 'strategy' => $strategy->value],
        );

        return $import;
    }

    // ────────────────────────────────────────────────────────────────
    // Execution
    // ────────────────────────────────────────────────────────────────

    /**
     * Build an order-independent fingerprint of an import's configuration.
     *
     * Keys are sorted recursively and the result is JSON encoded, which also
     * normalises numeric types, so two configurations that differ only by how
     * the database happened to serialise them compare equal.
     *
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $matchFields
     */
    protected function configurationFingerprint(array $mapping, array $settings, ?string $strategy, array $matchFields): string
    {
        $sortRecursive = function (array $data) use (&$sortRecursive): array {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $sortRecursive($value);
                }
            }

            ksort($data);

            return $data;
        };

        return md5((string) json_encode([
            $sortRecursive($mapping),
            $sortRecursive($settings),
            $strategy,
            $matchFields,
        ]));
    }

    public function dispatchImport(LeadImport $import): LeadImport
    {
        $import->resetCounters();
        $import->forceFill([
            'status' => LeadImportStatus::Queued->value,
            'error_message' => null,
            'failed_export_path' => null,
        ])->save();

        $import->log(LeadImportLog::EVENT_QUEUED, sprintf('Queued for import (%d rows).', $import->total_rows));

        ProcessLeadImportJob::dispatch($import->getKey());

        return $import;
    }

    /**
     * Fan a large file out into chunk jobs.
     *
     * Each chunk seeks to its own offset rather than being handed its rows, so
     * the payload stays tiny and memory stays flat no matter how large the file
     * is. The batch's finally callback is what marks the import complete,
     * because it runs whether the chunks succeeded or not.
     */
    public function dispatchChunks(LeadImport $import): Batch
    {
        $chunkSize = max((int) config('leads.import.chunk_size', 500), 1);
        $jobs = [];

        for ($offset = 0; $offset < $import->total_rows; $offset += $chunkSize) {
            $jobs[] = new ImportLeadChunkJob($import->getKey(), $offset, $chunkSize);
        }

        $importId = $import->getKey();

        $batch = Bus::batch($jobs)
            ->name('lead-import-' . $importId)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($importId): void {
                $import = LeadImport::find($importId);

                if ($import !== null) {
                    app(self::class)->finalize($import, $batch->failedJobs > 0);
                }
            })
            ->onQueue((string) config('leads.import.queue', 'lead-imports'))
            ->dispatch();

        $import->forceFill(['batch_id' => $batch->id])->save();

        return $batch;
    }

    /**
     * Record the outcome of a single row against the import.
     *
     * Counters are incremented atomically rather than read-modify-written so
     * parallel chunk jobs cannot lose each other's updates.
     *
     * @param  array<string, string>  $rawRow
     */
    public function recordRowResult(LeadImport $import, ImportRowResult $result, array $rawRow): void
    {
        if ($result->isFailure()) {
            LeadImportFailure::create([
                'lead_import_id' => $import->getKey(),
                'row_number' => $result->rowNumber,
                'reason_code' => $result->reasonCode?->value ?? 'validation',
                'reason' => (string) $result->reason,
                'errors' => $result->errors === [] ? null : $result->errors,
                'raw_row' => $rawRow,
            ]);
        }

        $import->newQuery()->whereKey($import->getKey())->increment($result->counterColumn());
        $import->newQuery()->whereKey($import->getKey())->increment('processed_rows');
    }

    /**
     * Close out an import: duration, final status, failed export, notification.
     */
    public function finalize(LeadImport $import, bool $hadJobFailures = false): void
    {
        $import->refresh();

        if ($import->status === LeadImportStatus::Cancelled) {
            return;
        }

        $failedExportPath = null;

        try {
            $failedExportPath = $this->failedExporter->generate($import);
        } catch (Throwable $exception) {
            Log::channel('lead_imports')->error('Failed to build the failed-rows export.', [
                'import_id' => $import->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        $finishedAt = now();
        $duration = $import->started_at !== null
            ? (int) $finishedAt->diffInSeconds($import->started_at, absolute: true)
            : null;

        $status = match (true) {
            $hadJobFailures && $import->successful_rows === 0 => LeadImportStatus::Failed,
            $import->failed_rows > 0 || $hadJobFailures => LeadImportStatus::CompletedWithErrors,
            default => LeadImportStatus::Completed,
        };

        $import->forceFill([
            'status' => $status->value,
            'finished_at' => $finishedAt,
            'duration_seconds' => $duration,
            'failed_export_path' => $failedExportPath,
        ])->save();

        $summary = sprintf(
            '%d imported, %d updated, %d skipped, %d failed of %d rows.',
            $import->imported_rows,
            $import->updated_rows,
            $import->skipped_rows,
            $import->failed_rows,
            $import->total_rows
        );

        $import->log(
            $status === LeadImportStatus::Failed ? LeadImportLog::EVENT_FAILED : LeadImportLog::EVENT_COMPLETED,
            $summary,
            ['duration_seconds' => $duration],
            $status === LeadImportStatus::Completed ? LeadImportLog::LEVEL_INFO : LeadImportLog::LEVEL_WARNING,
        );

        Log::channel('lead_imports')->info('Lead import finished.', [
            'import_id' => $import->getKey(),
            'clinic_id' => $import->clinic_id,
            'status' => $status->value,
            'summary' => $summary,
        ]);

        $this->notifyUploader($import, $status, $summary);
    }

    public function markFailed(LeadImport $import, string $message): void
    {
        $import->forceFill([
            'status' => LeadImportStatus::Failed->value,
            'error_message' => Str::limit($message, 1000),
            'finished_at' => now(),
        ])->save();

        $import->log(LeadImportLog::EVENT_FAILED, $message, [], LeadImportLog::LEVEL_ERROR);

        Log::channel('lead_imports')->error('Lead import failed.', [
            'import_id' => $import->getKey(),
            'message' => $message,
        ]);

        $this->notifyUploader($import, LeadImportStatus::Failed, $message);
    }

    public function cancel(LeadImport $import): void
    {
        if (! $import->status->isCancellable()) {
            return;
        }

        if ($import->batch_id !== null) {
            Bus::findBatch($import->batch_id)?->cancel();
        }

        $import->forceFill([
            'status' => LeadImportStatus::Cancelled->value,
            'finished_at' => now(),
        ])->save();

        $import->log(LeadImportLog::EVENT_CANCELLED, 'Import cancelled by the user.', [], LeadImportLog::LEVEL_WARNING);
    }

    // ────────────────────────────────────────────────────────────────
    // Progress and cleanup
    // ────────────────────────────────────────────────────────────────

    public function progress(LeadImport $import): ImportProgressDto
    {
        return ImportProgressDto::fromImport($import);
    }

    public function absolutePath(LeadImport $import): string
    {
        return Storage::disk($import->disk)->path($import->stored_path);
    }

    /**
     * Turn "facials by name_ July_Leads_2026-07-14_2026-08-05.csv" into
     * "Facials by name July — 14 Jul 2026 to 05 Aug 2026".
     *
     * Purely cosmetic, but it makes the history table readable at a glance when
     * every filename otherwise starts to look the same.
     */
    public function labelFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/^(.*)_Leads_(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/', $base, $matches) === 1) {
            $name = trim(preg_replace('/[_\s]+/', ' ', $matches[1]) ?? $matches[1]);

            try {
                return sprintf(
                    '%s — %s to %s',
                    Str::ucfirst($name),
                    \Carbon\CarbonImmutable::parse($matches[2])->format('d M Y'),
                    \Carbon\CarbonImmutable::parse($matches[3])->format('d M Y'),
                );
            } catch (Throwable) {
                return Str::ucfirst($name);
            }
        }

        return Str::ucfirst(trim(preg_replace('/[_\s]+/', ' ', $base) ?? $base));
    }

    protected function notifyUploader(LeadImport $import, LeadImportStatus $status, string $summary): void
    {
        $recipient = $import->uploader;

        if (! $recipient instanceof User) {
            return;
        }

        $notification = Notification::make()
            ->title(match ($status) {
                LeadImportStatus::Completed => 'Lead import completed',
                LeadImportStatus::CompletedWithErrors => 'Lead import completed with errors',
                default => 'Lead import failed',
            })
            ->body($import->original_filename . ' — ' . $summary)
            ->icon($status->getIcon())
            ->color($status->getColor());

        $notification = match ($status) {
            LeadImportStatus::Completed => $notification->success(),
            LeadImportStatus::CompletedWithErrors => $notification->warning(),
            default => $notification->danger(),
        };

        try {
            $notification
                ->actions([
                    NotificationAction::make('view')
                        ->label('View import')
                        ->url(\App\Filament\Resources\LeadImports\LeadImportResource::getUrl('view', ['record' => $import->getKey()]))
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipient);
        } catch (Throwable $exception) {
            // A notification failure must never turn a successful import into a
            // failed one, so this is logged and swallowed.
            Log::channel('lead_imports')->warning('Could not notify the uploader.', [
                'import_id' => $import->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
