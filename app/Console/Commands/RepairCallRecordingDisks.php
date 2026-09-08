<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CallRecording;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Repoints recordings whose disk no longer exists in filesystems.php.
 *
 * Renaming a disk is a one-line config change with no obvious consequence, and
 * it silently orphans every row already written against the old name:
 * Storage::disk() throws on a name it does not know, so every player 500s. That
 * is exactly what `call_recording` becoming `call_recordings` did here.
 *
 * CallRecording::diskName() falls back at read time so nothing stays broken,
 * but a fallback is not a fix — it hides the stale data and keeps paying for it
 * on every request. This makes the rows correct.
 *
 * Only ever rewrites a row when the audio is genuinely readable at the same
 * path on the target disk. A row pointing at a file that is not there is a
 * different problem, and quietly relabelling it would bury that.
 */
class RepairCallRecordingDisks extends Command
{
    protected $signature = 'calls:repair-recording-disks
        {--disk= : Target disk. Defaults to the configured recording disk.}
        {--dry-run : Report what would change without writing anything.}';

    protected $description = 'Repoint call recordings whose storage disk was renamed or removed';

    public function handle(): int
    {
        $target = (string) ($this->option('disk') ?: Setting::getConfigured(
            'call_recording_disk',
            config('calls.recording.disk', 'local'),
        ));

        $disks = array_keys((array) config('filesystems.disks', []));

        if (! in_array($target, $disks, true)) {
            $this->error(sprintf('Disk "%s" is not configured. Known disks: %s', $target, implode(', ', $disks)));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run - nothing will be written.');
        }

        // Only rows whose disk is genuinely unknown. A row on a valid but
        // different disk was put there deliberately.
        $stale = CallRecording::query()
            ->whereNotNull('storage_disk')
            ->whereNotIn('storage_disk', $disks)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No recordings point at a missing disk.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Found %d recording(s) on a disk that no longer exists.', $stale->count()));

        $repaired = 0;
        $missing = 0;

        foreach ($stale as $recording) {
            $found = filled($recording->storage_path)
                && Storage::disk($target)->exists($recording->storage_path);

            if (! $found) {
                $missing++;

                $this->warn(sprintf(
                    '  #%d: no file at "%s" on "%s" - left alone.',
                    $recording->getKey(),
                    $recording->storage_path ?: '(no path)',
                    $target,
                ));

                continue;
            }

            if (! $dryRun) {
                $recording->forceFill(['storage_disk' => $target])->save();
            }

            $repaired++;

            $this->line(sprintf('  #%d: %s -> %s', $recording->getKey(), $recording->storage_disk, $target));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d recording(s).%s',
            $dryRun ? 'Would repair' : 'Repaired',
            $repaired,
            $missing > 0 ? sprintf(' %d had no file on "%s" and were left alone.', $missing, $target) : '',
        ));

        if ($missing > 0) {
            $this->comment('Those need the audio re-downloading: php artisan calls:retry --recordings');
        }

        return self::SUCCESS;
    }
}
