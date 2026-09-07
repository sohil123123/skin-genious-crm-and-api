<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\CallAnalysisStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Jobs\Call\AnalyzeCallJob;
use App\Models\Call;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use Illuminate\Console\Command;

/**
 * Queues transcribed calls for AI analysis.
 *
 * The mirror of calls:transcribe, and it exists for the same ordering problem.
 * While analysis is off, every call is settled as NotAvailable — correctly,
 * since there is no analyser to run. But NotAvailable is terminal, so switching
 * it on later changes nothing for the calls already in the system: nothing
 * revisits a settled row.
 *
 * Explicit rather than automatic, because analysis costs a model call per
 * transcript and quietly working through a year of back-catalogue the moment
 * someone flips a toggle is not a decision this application should make.
 */
class AnalyseCalls extends Command
{
    protected $signature = 'calls:analyse
        {--all : Include calls already settled as not available.}
        {--redo : Re-analyse calls that already have an analysis.}
        {--limit=100 : Maximum calls to queue.}
        {--dry-run : Report what would be queued without queueing anything.}';

    protected $description = 'Queue transcribed calls for AI analysis';

    public function handle(CallAnalysisServiceInterface $analyser): int
    {
        if (! $analyser->isEnabled()) {
            $this->error('Call analysis is not enabled.');
            $this->line('  Open Calls → Call Settings and set an analysis provider,');
            $this->line('  then switch "Analyse transcripts" on.');

            return self::FAILURE;
        }

        $this->info(sprintf('Analysing with: %s (%s)', $analyser->name(), $analyser->version()));

        $states = [CallAnalysisStatus::Pending->value, CallAnalysisStatus::Failed->value];

        if ($this->option('all')) {
            $states[] = CallAnalysisStatus::NotAvailable->value;
        }

        if ($this->option('redo')) {
            $states[] = CallAnalysisStatus::Completed->value;
        }

        // Only calls that actually have something to read.
        $calls = Call::query()
            ->where('transcription_status', TranscriptionStatus::Completed->value)
            ->whereIn('analysis_status', $states)
            ->whereHas('currentTranscription', fn ($query) => $query->whereNotNull('transcript'))
            ->limit((int) $this->option('limit'))
            ->get();

        if ($calls->isEmpty()) {
            $this->info('Nothing to analyse.');

            if (! $this->option('all')) {
                $this->comment('Calls settled while analysis was off are skipped. Use --all to include them.');
            }

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($calls as $call) {
            if (! $dryRun) {
                // Back to Pending: the job returns immediately on a settled
                // status, so a re-run would otherwise be a no-op.
                $call->forceFill([
                    'analysis_status' => CallAnalysisStatus::Pending,
                    'last_error' => null,
                ])->saveQuietly();

                AnalyzeCallJob::dispatch($call->getKey());
            }

            $this->line(sprintf('  #%d %s', $call->getKey(), $call->customer_name));
        }

        $this->newLine();
        $this->info(sprintf('%s %d call(s).', $dryRun ? 'Would queue' : 'Queued', $calls->count()));

        if (! $dryRun) {
            $this->comment('A queue worker must be running on the call-recordings queue for these to run.');
        }

        return self::SUCCESS;
    }
}
