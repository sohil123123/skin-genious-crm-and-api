<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Throwable;

/**
 * Sweep abandoned browser uploads out of Livewire's temporary directory.
 *
 * The import wizard deletes each temporary file as soon as it has copied the
 * export into permanent storage, so nothing should normally be left here. This
 * catches what that path cannot: uploads where the person chose a file and then
 * closed the tab, and files whose deletion failed.
 *
 * Livewire has its own sweep, but it only runs when someone happens to upload
 * and only removes files over a day old, which on a quiet system means never.
 */
class CleanupLeadUploadTempFiles extends Command
{
    protected $signature = 'leads:cleanup-temp-uploads
                            {--hours=6 : Delete temporary uploads older than this}
                            {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Delete abandoned Livewire temporary uploads';

    public function handle(): int
    {
        // S3 expires these with a bucket lifecycle rule instead, and listing a
        // remote bucket file by file is the wrong tool for the job.
        if (FileUploadConfiguration::isUsingS3()) {
            $this->info('Temporary uploads are on S3; use a bucket lifecycle rule instead.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(FileUploadConfiguration::disk());
        $directory = FileUploadConfiguration::path();

        if (! $disk->exists($directory)) {
            $this->info('No temporary upload directory to clean.');

            return self::SUCCESS;
        }

        $hours = max((int) $this->option('hours'), 1);
        $cutoff = now()->subHours($hours)->timestamp;
        $isDryRun = (bool) $this->option('dry-run');

        $deleted = 0;
        $bytes = 0;
        $failed = 0;

        foreach ($disk->allFiles($directory) as $file) {
            try {
                // Another process may have removed the file between listing it
                // and reading its timestamp.
                if (! $disk->exists($file) || $disk->lastModified($file) > $cutoff) {
                    continue;
                }

                $size = $disk->size($file);

                if (! $isDryRun && ! $disk->delete($file)) {
                    $failed++;

                    continue;
                }

                $deleted++;
                $bytes += $size;

                $this->line(($isDryRun ? 'Would delete: ' : 'Deleted: ') . $file);
            } catch (Throwable $exception) {
                $failed++;

                $this->warn("Could not remove {$file}: {$exception->getMessage()}");
            }
        }

        $this->info(sprintf(
            '%s %d file(s), %s.',
            $isDryRun ? 'Would remove' : 'Removed',
            $deleted,
            $this->formatBytes($bytes),
        ));

        if ($failed > 0) {
            $this->warn("{$failed} file(s) could not be removed.");
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 1) . ' KB',
            default => $bytes . ' B',
        };
    }
}
