<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Jobs\Call\TranscribeCallRecordingJob;
use App\Models\CallRecording;
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;
use Illuminate\Console\Command;

/**
 * Queues stored recordings for transcription.
 *
 * Exists because of an ordering problem that is invisible until it bites. While
 * transcription is switched off, every recording that arrives is settled as
 * NotAvailable — correctly, since there is no transcriber to run and leaving
 * them Pending would retry them forever.
 *
 * But NotAvailable is a terminal state, so switching transcription on later
 * changes nothing for the calls already in the system: the retry sweep
 * deliberately skips settled rows, and nothing else ever revisits them. Without
 * this command, turning the feature on would only ever apply to calls that
 * arrive after the switch, and a clinic's existing recordings would stay
 * permanently untranscribed with nothing to indicate why.
 *
 * Deliberately explicit rather than automatic: transcription costs money per
 * minute of audio, and quietly transcribing a year of back-catalogue the moment
 * someone flips a toggle is not a decision this application should make.
 */
class TranscribeCallRecordings extends Command
{
    protected $signature = 'calls:transcribe
        {--all : Include recordings already settled as not available or completed.}
        {--redo : Re-transcribe recordings that already have a transcript.}
        {--limit=100 : Maximum recordings to queue.}
        {--dry-run : Report what would be queued without queueing anything.}';

    protected $description = 'Queue stored call recordings for transcription';

    public function handle(CallTranscriptionServiceInterface $transcriber): int
    {
        if (! $transcriber->isEnabled()) {
            $this->error('Transcription is not enabled.');
            $this->line('  Open Calls → Call Settings and set a transcription provider,');
            $this->line('  paste an API key, and switch "Transcribe recordings" on.');

            return self::FAILURE;
        }

        $this->info(sprintf('Transcribing with: %s', $transcriber->name()));

        $states = [TranscriptionStatus::Pending->value, TranscriptionStatus::Failed->value];

        if ($this->option('all')) {
            // The whole point of the flag: NotAvailable is where everything
            // lands while the feature is off.
            $states[] = TranscriptionStatus::NotAvailable->value;
        }

        if ($this->option('redo')) {
            $states[] = TranscriptionStatus::Completed->value;
        }

        $recordings = CallRecording::query()
            ->where('storage_status', RecordingStorageStatus::Stored->value)
            ->whereIn('transcription_status', $states)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($recordings->isEmpty()) {
            $this->info('Nothing to transcribe.');

            if (! $this->option('all')) {
                $this->comment('Recordings settled while transcription was off are skipped. Use --all to include them.');
            }

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $queued = 0;
        $skipped = 0;

        $minimum = (int) config('calls.transcription.min_duration_seconds', 5);

        foreach ($recordings as $recording) {
            // Checked here as well as in the job so the count reported is the
            // work actually queued, not the rows looked at.
            if (! $recording->fileExists()) {
                $skipped++;
                $this->warn(sprintf('  #%d: the stored audio is missing.', $recording->getKey()));

                continue;
            }

            if ($recording->duration_seconds !== null && $recording->duration_seconds < $minimum) {
                $skipped++;
                $this->line(sprintf(
                    '  #%d: %ds is under the %ds minimum — a ring-out, not a conversation.',
                    $recording->getKey(),
                    $recording->duration_seconds,
                    $minimum,
                ));

                continue;
            }

            if (! $dryRun) {
                // Back to Pending: the job returns immediately on a settled
                // status, so a re-run would otherwise be a no-op.
                $recording->forceFill([
                    'transcription_status' => TranscriptionStatus::Pending,
                    'error_message' => null,
                ])->save();

                TranscribeCallRecordingJob::dispatch($recording->getKey());
            }

            $queued++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d recording(s).%s',
            $dryRun ? 'Would queue' : 'Queued',
            $queued,
            $skipped > 0 ? sprintf(' Skipped %d.', $skipped) : '',
        ));

        if ($queued > 0 && ! $dryRun) {
            $this->comment('A queue worker must be running on the call-recordings queue for these to run.');
        }

        return self::SUCCESS;
    }
}
