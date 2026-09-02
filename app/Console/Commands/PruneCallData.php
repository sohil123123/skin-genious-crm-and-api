<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\CallEventProcessingStatus;
use App\Models\CallProviderPayload;
use App\Models\CallRecording;
use App\Models\CallTranscription;
use App\Models\CallWebhookEvent;
use App\Services\Call\CallRecordingService;
use Illuminate\Console\Command;

/**
 * Applies the retention policy to call data.
 *
 * Does nothing at all unless a retention period is configured. That is the
 * deliberate default: deleting a patient conversation must be a decision
 * somebody made on purpose, not something that happens because a command got
 * scheduled.
 *
 * Recordings and transcripts lose their content but keep their row, so the call
 * history stays honest — "this call was recorded and the audio was deleted
 * under policy" is a different and more useful statement than silence.
 */
class PruneCallData extends Command
{
    protected $signature = 'calls:prune
        {--dry-run : Report what would be deleted without deleting anything.}';

    protected $description = 'Delete call recordings, transcripts and payloads past their configured retention period';

    public function handle(CallRecordingService $recordings): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run — nothing will be deleted.');
        }

        $configured = collect([
            'recording_days' => config('calls.retention.recording_days'),
            'transcript_days' => config('calls.retention.transcript_days'),
            'payload_days' => config('calls.retention.payload_days'),
            'webhook_event_days' => config('calls.retention.webhook_event_days'),
        ])->filter(fn ($value): bool => filled($value));

        if ($configured->isEmpty()) {
            $this->info('No retention periods are configured. Nothing is ever deleted by default.');

            return self::SUCCESS;
        }

        $this->pruneRecordings($recordings, $dryRun);
        $this->pruneTranscripts($dryRun);
        $this->prunePayloads($dryRun);
        $this->pruneWebhookEvents($dryRun);

        return self::SUCCESS;
    }

    /**
     * Delete the audio, keep the record that it existed.
     */
    protected function pruneRecordings(CallRecordingService $service, bool $dryRun): void
    {
        if (blank(config('calls.retention.recording_days'))) {
            return;
        }

        $due = CallRecording::query()->duePurge()->limit(1000)->get();

        if ($dryRun) {
            $this->line(sprintf('Recordings: %d would be purged.', $due->count()));

            return;
        }

        $due->each(fn (CallRecording $recording) => $service->purge($recording));

        $this->info(sprintf('Recordings: purged %d.', $due->count()));
    }

    /**
     * Blank the transcript text, keep the row and its metadata.
     */
    protected function pruneTranscripts(bool $dryRun): void
    {
        if (blank(config('calls.retention.transcript_days'))) {
            return;
        }

        $query = CallTranscription::query()->duePurge();

        if ($dryRun) {
            $this->line(sprintf('Transcripts: %d would be cleared.', $query->count()));

            return;
        }

        $cleared = $query->limit(1000)->update([
            'transcript' => null,
            'transcript_json' => null,
        ]);

        $this->info(sprintf('Transcripts: cleared %d.', $cleared));
    }

    /**
     * Raw payloads are deleted outright rather than blanked.
     *
     * Unlike a recording, a payload with its body removed is worth nothing —
     * the whole point of the row is the bytes it holds.
     */
    protected function prunePayloads(bool $dryRun): void
    {
        $days = config('calls.retention.payload_days');

        if (blank($days)) {
            return;
        }

        $query = CallProviderPayload::query()->where('received_at', '<', now()->subDays((int) $days));

        if ($dryRun) {
            $this->line(sprintf('Provider payloads: %d would be deleted.', $query->count()));

            return;
        }

        $deleted = $query->limit(5000)->delete();

        $this->info(sprintf('Provider payloads: deleted %d.', $deleted));
    }

    /**
     * Old idempotency records.
     *
     * Safe to remove once a provider could no longer plausibly redeliver: the
     * row only exists to recognise a retry, and no provider retries a
     * months-old event.
     */
    protected function pruneWebhookEvents(bool $dryRun): void
    {
        $days = config('calls.retention.webhook_event_days');

        if (blank($days)) {
            return;
        }

        $query = CallWebhookEvent::query()
            ->where('received_at', '<', now()->subDays((int) $days))
            // A failed event is unfinished business. Deleting it would remove
            // the only sign that a call is missing.
            ->whereNot('processing_status', CallEventProcessingStatus::Failed->value);

        if ($dryRun) {
            $this->line(sprintf('Webhook events: %d would be deleted.', $query->count()));

            return;
        }

        $deleted = $query->limit(5000)->delete();

        $this->info(sprintf('Webhook events: deleted %d.', $deleted));
    }
}
