<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\DTOs\Call\NormalizedCall;
use App\DTOs\Call\NormalizedRecording;
use App\Enums\Call\CallDirection;
use App\Enums\Call\CallMatchingMethod;
use App\Enums\Call\CallMatchingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallStatus;
use App\Enums\Call\RecordingDownloadStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Events\Call\CallAnnounced;
use App\Jobs\Call\DownloadCallRecordingJob;
use App\Models\Call;
use App\Models\CallRecording;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The single door through which every call enters the CRM.
 *
 * Both providers, both paths — live webhook and overnight API sync — write
 * through here, and that is the entire reason duplicates cannot happen: there
 * is one place that decides whether a provider call id is new, and it decides
 * it inside a transaction with a unique constraint behind it.
 *
 * The harder problem this class solves is not insertion but *update*. A call
 * arrives in pieces: ringing, then answered, then completed with a duration,
 * then a recording URL some minutes later. Those pieces can arrive out of
 * order, because a provider retrying a slow "ringing" delivery will happily
 * deliver it after "completed" has already succeeded. A naive upsert would let
 * that retry roll a finished call back to ringing and wipe its duration.
 *
 * So the merge is not last-write-wins. It is: a later event may add what is
 * missing and may advance the call's state, but it may never regress it, and it
 * may never blank a value it says nothing about.
 */
class CallIngestionService
{
    public function __construct(
        protected CallParticipantResolver $participants,
        protected PhoneNumberNormalizer $phone,
    ) {}

    /**
     * Create or update the one call record for a normalised provider event.
     *
     * @param  array<string, mixed>  $context  extra columns the caller controls, e.g. last_source
     */
    public function ingest(NormalizedCall $normalized, array $context = []): ?Call
    {
        if (! $normalized->isIdentifiable()) {
            Log::channel('calls')->warning('Refusing a call event with no provider call id.', [
                'provider' => $normalized->provider->value,
            ]);

            return null;
        }

        $wasCreated = false;

        $call = DB::transaction(function () use ($normalized, $context, &$wasCreated): Call {
            $call = $this->lockExisting($normalized);

            if ($call === null) {
                $call = $this->createCall($normalized, $context);
                $wasCreated = true;
            } else {
                $this->mergeInto($call, $normalized, $context);
            }

            $this->participants->resolve($call, $normalized);

            $call->save();

            $this->attachRecordings($call, $normalized->recordings);

            return $call;
        });

        // Dispatched after the transaction commits. A job that started while
        // the transaction was still open could read a call that does not exist
        // yet, which on a fast queue worker is not a theoretical race.
        $this->queueRecordingDownloads($call);

        $this->announceCall($call, $wasCreated);

        return $call;
    }

    /**
     * Tell the clinic's screens about a call that just happened.
     *
     * Both directions, and both providers. Exotel announces a call while it is
     * still ringing, which is the classic screen pop. Callyzer physically
     * cannot: its log leaves the agent's handset once the conversation is over,
     * so the card reports a call that just finished and offers somewhere to
     * write down what came of it. The event carries is_live so the card can say
     * which of the two it is rather than implying the phone is still ringing.
     *
     * The guards that remain are the ones that keep it honest:
     *
     *  - Only on creation, so the later "answered" and "completed" deliveries
     *    for the same call do not pop a second and third time.
     *  - Only with a known direction. "Unknown" has no sentence to put on a
     *    card, and a card that cannot say what happened is just noise.
     *  - Only if it started in the last few minutes. This is what stops a
     *    backfill filling the clinic's screens with popups for calls from last
     *    March, and it is doing more work now than it used to: it is the only
     *    thing standing between an hourly Callyzer sync and a hundred cards.
     *
     * Failure here is swallowed on purpose. The popup is a convenience; the
     * call record is the product, and a Reverb outage must not fail ingestion
     * and send the job into a retry loop.
     */
    protected function announceCall(Call $call, bool $wasCreated): void
    {
        if (! $wasCreated || $call->clinic_id === null) {
            return;
        }

        if (! in_array($call->direction, [CallDirection::Incoming, CallDirection::Outgoing], true)) {
            return;
        }

        // Wide enough to absorb clock skew between the provider and this server
        // — a few minutes out is common and must not silently stop every popup
        // — and wide enough for Callyzer, which delivers a minute or two after
        // the handset hangs up. Nowhere near wide enough to admit a backfill.
        $window = (int) config('calls.popup.recent_minutes', 5);

        if ($call->started_at !== null && $call->started_at->lt(now()->subMinutes($window))) {
            return;
        }

        try {
            CallAnnounced::dispatch($call);
        } catch (\Throwable $exception) {
            Log::channel('calls')->warning('Could not announce a call to the clinic screens.', [
                'call_uuid' => $call->uuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Find the existing row for this provider call, locked for update.
     *
     * The lock is what serialises two simultaneous events for the same call —
     * a webhook and a sync landing together, which happens routinely — so their
     * merges apply one after the other rather than one overwriting the other.
     */
    protected function lockExisting(NormalizedCall $normalized): ?Call
    {
        return Call::query()
            ->where('provider', $normalized->provider->value)
            ->where('provider_call_id', $normalized->providerCallId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function createCall(NormalizedCall $normalized, array $context): Call
    {
        $attributes = $normalized->toAttributes();

        $call = new Call();
        $call->forceFill(array_merge($attributes, [
            'uuid' => (string) Str::uuid(),
            'source' => $normalized->source->value,
            'last_source' => $normalized->source->value,
            'provider_data' => $normalized->providerData ?: null,
            'first_seen_at' => now(),
            'last_event_at' => now(),
            'event_count' => 1,
            'direction' => ($normalized->direction ?? CallDirection::Unknown)->value,
            'call_status' => ($normalized->status ?? CallStatus::Unknown)->value,
            'is_connected' => $normalized->status?->isConnected() ?? false,
            // Stated rather than left to the column defaults, so the model this
            // method returns matches what is in the database. Otherwise a
            // caller reading $call->has_recording straight after ingestion gets
            // null until something refreshes the row.
            'has_recording' => false,
            'recording_count' => 0,
            'follow_up_required' => false,
        ], $context));

        $this->applyDerivedTiming($call);

        return $call;
    }

    /**
     * Fold a later event into an existing call.
     *
     * @param  array<string, mixed>  $context
     */
    protected function mergeInto(Call $call, NormalizedCall $normalized, array $context): void
    {
        $incoming = $normalized->toAttributes();

        // Columns the CRM owns. A provider re-sync must never touch them —
        // this is what stops a Callyzer sync overwriting an outcome a staff
        // member recorded here yesterday.
        unset(
            $incoming['crm_note'],
            $incoming['crm_outcome'],
            $incoming['uuid'],
            $incoming['source'],
        );

        foreach ($incoming as $column => $value) {
            if ($this->shouldApply($call, $column, $value)) {
                $call->{$column} = $value;
            }
        }

        // Provider extras accumulate rather than replace: a "completed" event
        // carrying five fields must not erase the twelve the "ringing" event
        // brought.
        if ($normalized->providerData !== []) {
            $call->provider_data = array_merge(
                (array) ($call->provider_data ?? []),
                $normalized->providerData,
            );
        }

        $call->forceFill(array_merge([
            'last_source' => $normalized->source->value,
            'last_event_at' => now(),
            'event_count' => $call->event_count + 1,
        ], $context));

        $this->applyDerivedTiming($call);
    }

    /**
     * Decide whether one incoming value may replace what is already stored.
     *
     * The rules, in order:
     *
     *  - Anything the call does not have yet is always accepted.
     *  - Status may advance but not regress. A finished call stays finished
     *    even when a stale "ringing" retry arrives afterwards.
     *  - Direction may be corrected away from Unknown but not back to it.
     *  - Durations only grow. Providers report 0 on interim events and the real
     *    figure at the end, so accepting a smaller number would throw away the
     *    only value that was ever true.
     */
    protected function shouldApply(Call $call, string $column, mixed $value): bool
    {
        $current = $call->{$column};

        if ($current === null || $current === '') {
            return true;
        }

        return match ($column) {
            'call_status' => $this->statusMayAdvance($call, $value),
            // Only ever set, never cleared. "These two people spoke" is a fact
            // a later event cannot un-observe, and a stale retry saying
            // otherwise is simply older news.
            'is_connected' => $value === true,
            'direction' => $value !== CallDirection::Unknown->value,
            'duration_seconds',
            'talk_duration_seconds',
            'ring_duration_seconds',
            'hold_duration_seconds',
            'wait_duration_seconds' => (int) $value > (int) $current,
            // started_at is set once. The first event that named a start time
            // was closest to the call actually starting; a later event
            // recalculating it from its own clock is less trustworthy.
            'started_at' => false,
            default => true,
        };
    }

    /**
     * Whether the call's status may move to the incoming one.
     *
     * A terminal status is final. The only thing that reaches a completed call
     * afterwards is a stale retry or a re-sync of a record the provider already
     * closed, and neither should reopen it.
     */
    protected function statusMayAdvance(Call $call, mixed $incoming): bool
    {
        $current = $call->call_status;

        if (! $current instanceof CallStatus) {
            return true;
        }

        if ($current->isInFlight()) {
            return true;
        }

        // Already terminal. Accept only a same-or-better terminal state, which
        // in practice means letting "no_answer" be corrected to "completed" by
        // a fuller record, and never the reverse.
        $incomingStatus = $incoming instanceof CallStatus
            ? $incoming
            : CallStatus::tryFrom((string) $incoming);

        if ($incomingStatus === null) {
            return false;
        }

        return $incomingStatus->isConnected() && ! $current->isConnected();
    }

    /**
     * Fill in the timings the provider did not supply but the data implies.
     *
     * Strictly limited to arithmetic on values the provider actually sent. A
     * duration inferred from two provider timestamps is a fact; a talk time
     * guessed from a total duration is a fabrication, and reports built on
     * fabricated numbers are worse than reports with gaps in them.
     */
    protected function applyDerivedTiming(Call $call): void
    {
        if ($call->duration_seconds === null && $call->started_at && $call->ended_at) {
            $seconds = $call->ended_at->diffInSeconds($call->started_at, absolute: true);
            $call->duration_seconds = (int) $seconds;
        }

        if ($call->talk_duration_seconds === null && $call->answered_at && $call->ended_at) {
            $call->talk_duration_seconds = (int) $call->ended_at->diffInSeconds($call->answered_at, absolute: true);
        }

        if ($call->ring_duration_seconds === null && $call->started_at && $call->answered_at) {
            $call->ring_duration_seconds = (int) $call->answered_at->diffInSeconds($call->started_at, absolute: true);
        }

        // A call with talk time was answered, whatever the status word said.
        // Callyzer in particular reports a duration on calls it labels in ways
        // that do not map cleanly onto "answered".
        if (! $call->is_connected && (int) $call->talk_duration_seconds > 0) {
            $call->is_connected = true;
        }
    }

    // ──────────────── Recordings ────────────────

    /**
     * Attach any recordings this event announced.
     *
     * Nothing is downloaded here. A webhook must answer in milliseconds or the
     * provider treats it as failed and retries — and a retry storm caused by
     * slow webhook responses is exactly how duplicate calls get created.
     *
     * @param  array<int, NormalizedRecording>  $recordings
     */
    protected function attachRecordings(Call $call, array $recordings): void
    {
        if ($recordings === []) {
            return;
        }

        $changed = false;

        foreach ($recordings as $recording) {
            if (! $recording instanceof NormalizedRecording || blank($recording->sourceUrl)) {
                continue;
            }

            $existing = $call->recordings()
                ->where('source_url_hash', $recording->urlHash())
                ->first();

            if ($existing !== null) {
                // The same audio announced again. Fill in anything the earlier
                // announcement lacked, but never reset a download in progress.
                $updates = array_filter([
                    'provider_recording_id' => $existing->provider_recording_id ?? $recording->providerRecordingId,
                    'duration_seconds' => $existing->duration_seconds ?? $recording->durationSeconds,
                ], static fn (mixed $value): bool => $value !== null);

                if ($updates !== []) {
                    $existing->forceFill($updates)->save();
                }

                continue;
            }

            $call->recordings()->create(array_merge($recording->toArray(), [
                'provider' => $call->provider->value,
                'download_status' => RecordingDownloadStatus::Pending->value,
                'storage_status' => RecordingStorageStatus::RemoteOnly->value,
                'transcription_status' => TranscriptionStatus::Pending->value,
                'purge_after' => $this->recordingPurgeDate(),
            ]));

            $changed = true;
        }

        if ($changed) {
            $call->refreshPipelineFlags();
        }
    }

    /**
     * Queue a download for every recording that still has no local copy.
     */
    protected function queueRecordingDownloads(Call $call): void
    {
        if (! (bool) config('calls.recording.enabled', true)) {
            return;
        }

        $call->recordings()
            ->where('download_status', RecordingDownloadStatus::Pending->value)
            ->get()
            ->each(fn (CallRecording $recording) => DownloadCallRecordingJob::dispatch($recording->getKey()));
    }

    /**
     * When this recording's audio becomes eligible for deletion.
     *
     * Null unless a retention period is configured, because deleting a patient
     * conversation must be something somebody chose, not a default.
     */
    protected function recordingPurgeDate(): ?Carbon
    {
        $days = config('calls.retention.recording_days');

        return filled($days) ? now()->addDays((int) $days) : null;
    }

    // ──────────────── Re-matching ────────────────

    /**
     * Re-run customer matching over calls that were never attributed.
     *
     * Worth doing because the CRM changes underneath the calls: a caller who
     * was a stranger on Monday is a patient by Friday, and their earlier call
     * should appear in the history that gets built for them. Manual matches are
     * skipped — the resolver refuses them — so nothing a person decided is
     * disturbed.
     */
    public function rematchUnmatched(?string $phoneKey = null, int $limit = 500): int
    {
        $matched = 0;

        Call::query()
            ->needsMatching()
            ->when(filled($phoneKey), fn (Builder $query): Builder => $query->where('client_phone_key', $phoneKey))
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->each(function (Call $call) use (&$matched): void {
                $before = $call->customer_user_id ?? $call->lead_id;

                $this->participants->resolveCustomer($call);
                $this->participants->resolveClinic($call);

                if ($call->isDirty()) {
                    $call->save();
                }

                if ($before === null && $call->isMatched()) {
                    $matched++;
                }
            });

        return $matched;
    }

    /**
     * Attach a call to a patient or lead because a person said so.
     *
     * Recorded as ManuallyMatched, which permanently exempts the call from
     * automatic matching. That is the point: someone looked at an ambiguous
     * number and resolved it, and no later sync may quietly disagree.
     */
    public function matchManually(Call $call, ?int $userId, ?int $leadId, ?int $decidedBy = null): Call
    {
        $call->forceFill([
            'customer_user_id' => $userId,
            'lead_id' => $leadId,
            'matching_status' => CallMatchingStatus::ManuallyMatched,
            'matching_method' => CallMatchingMethod::Manual,
            'matched_at' => now(),
            'matched_by' => $decidedBy ?? auth()->id(),
            'match_candidates' => null,
        ]);

        $this->participants->resolveClinic($call);

        $call->save();

        return $call;
    }

    /**
     * Whether a provider is configured to be accepted at all.
     */
    public function providerEnabled(CallProvider $provider): bool
    {
        return (bool) config('calls.' . $provider->value . '.enabled', false);
    }

    /**
     * Record a manually entered call, for conversations that happened off-system.
     */
    public function recordManualCall(array $attributes): Call
    {
        $client = $this->phone->normalize($attributes['client_phone'] ?? null);

        $call = new Call();
        $call->forceFill(array_merge($attributes, [
            'uuid' => (string) Str::uuid(),
            'provider' => CallProvider::Manual->value,
            'provider_call_id' => 'manual-' . Str::uuid(),
            'source' => CallSource::Manual->value,
            'last_source' => CallSource::Manual->value,
            'client_phone' => $client->original,
            'client_phone_normalized' => $client->normalized,
            'client_phone_key' => $client->key,
            'first_seen_at' => now(),
            'last_event_at' => now(),
            'event_count' => 1,
        ]));

        $this->participants->resolve($call);

        $call->save();

        return $call;
    }
}
