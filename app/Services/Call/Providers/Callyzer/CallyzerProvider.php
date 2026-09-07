<?php

declare(strict_types=1);

namespace App\Services\Call\Providers\Callyzer;

use App\DTOs\Call\NormalizedCall;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallSyncStatus;
use App\Models\Call;
use App\Models\CallSyncRun;
use App\Models\Setting;
use App\Services\Call\CallIngestionService;
use App\Services\Call\Contracts\CallProviderInterface;
use App\Services\Call\Contracts\SyncsCallsInterface;
use App\Services\Call\Exceptions\CallProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The Callyzer adapter: outgoing calls, by webhook and by API sync.
 *
 * Both paths deliberately converge on the same mapper and the same ingestion
 * service, which is what makes the overlap between them harmless. A call that
 * arrives live by webhook and again in the nightly sync is the normal case, not
 * an edge case, and it must update one row rather than create two.
 */
class CallyzerProvider implements CallProviderInterface, SyncsCallsInterface
{
    public function __construct(
        protected CallyzerClient $client,
        protected CallyzerCallMapper $mapper,
        protected CallIngestionService $ingestion,
    ) {}

    public function provider(): CallProvider
    {
        return CallProvider::Callyzer;
    }

    /**
     * Whether Callyzer calls are accepted at all.
     *
     * Deliberately does not require an API token. Receiving webhooks and
     * polling the API are independent capabilities: an installation can
     * legitimately run on webhooks alone, and demanding a pull token before
     * accepting a push would silently reject every call it sends.
     */
    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('callyzer_enabled', config('calls.callyzer.enabled', false));
    }

    /**
     * Polling additionally needs credentials to poll with.
     */
    public function isSyncEnabled(): bool
    {
        return $this->isEnabled()
            && $this->client->isConfigured()
            && (bool) Setting::getValue('callyzer_sync_enabled', config('calls.callyzer.sync.enabled', false));
    }

    /**
     * Prove a webhook came from Callyzer.
     *
     * Callyzer authenticates its webhooks with a shared secret rather than a
     * signature, so both the header and the query string are accepted — the
     * dashboard has offered each at different times.
     *
     * An unconfigured secret rejects everything. Waving requests through would
     * let anyone who guessed the URL write calls into a patient's file, and a
     * webhook endpoint that trusts its caller is not a webhook endpoint, it is
     * an open door.
     */
    public function validateWebhook(Request $request): bool
    {
        $expected = Setting::getConfigured('callyzer_webhook_secret', config('calls.callyzer.webhook_secret'));

        if (blank($expected)) {
            Log::channel('calls')->error('Callyzer webhook rejected: no webhook secret is configured.');

            return false;
        }

        $supplied = $request->header('X-Callyzer-Secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->query('secret')
            ?? $request->input('secret');

        if (blank($supplied)) {
            return false;
        }

        return hash_equals((string) $expected, (string) $supplied);
    }

    /**
     * The identity of one Callyzer event.
     *
     * Built from the call id plus the fields that change as a call is edited in
     * the Callyzer app, so that a genuine update — an agent adding a note or
     * setting a reminder hours later — produces a new key and is processed,
     * while a plain redelivery of the same state does not.
     *
     * @param  array<string, mixed>  $payload
     */
    public function eventKey(array $payload): ?string
    {
        $callId = $this->callId($payload);

        if (blank($callId)) {
            return null;
        }

        $modified = $payload['modified_at'] ?? $payload['modifiedAt'] ?? null;

        // synced_at is deliberately NOT accepted as a modification stamp.
        //
        // It is the time Callyzer ran the batch, identical for every call in
        // one delivery and unchanged when the call itself changes. It used to
        // stand in for modified_at, and the effect was silent data loss:
        // Callyzer posts a call the moment it ends, then posts it again minutes
        // later once the recording has uploaded. Both carry the same synced_at
        // and no modified_at, so the second delivery - the only one holding
        // call_recording_url - collapsed into the first as a duplicate and was
        // dropped. The call was stored, the audio never was, and nothing
        // anywhere reported a failure.
        //
        // So the discriminator is now the content itself. A fingerprint answers
        // the only question idempotency actually asks - "have I already seen
        // exactly this?" - where a timestamp merely guesses at it.
        $material = $payload;

        // Excluded from the fingerprint for the same reason it is not trusted
        // above: it changes on every sync run without the call changing, and
        // hashing it would make each run look like new work.
        unset($material['synced_at'], $material['syncedAt']);

        // Key order is not meaningful in JSON and has differed between Callyzer
        // versions; without this, a reordered redelivery would look new.
        ksort($material);

        $fingerprint = substr(hash('sha256', json_encode($material) ?: ''), 0, 16);

        // modified_at is kept in front when present. It carries no weight the
        // fingerprint lacks, but it makes an event key legible in the database
        // when someone is working out why a call was or was not reprocessed.
        $discriminator = filled($modified)
            ? $modified . ':' . $fingerprint
            : $fingerprint;

        return sprintf('callyzer:%s:%s', $callId, $discriminator);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function callId(array $payload): ?string
    {
        foreach (['id', 'call_id', 'callId', '_id'] as $key) {
            if (filled($payload[$key] ?? null)) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): ?string
    {
        $type = $payload['call_type'] ?? $payload['callType'] ?? $payload['event'] ?? null;

        return filled($type) ? (string) $type : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): ?NormalizedCall
    {
        return $this->mapper->map($payload, CallSource::Webhook);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalizeFromSync(array $payload): ?NormalizedCall
    {
        return $this->mapper->map($payload, CallSource::ApiSync);
    }

    // ──────────────── API synchronisation ────────────────

    /**
     * Pull a window of call history and write it through the ingestion service.
     *
     * The loop stops on a rate limit rather than fighting it, and records the
     * run as Partial. That matters for correctness, not just tidiness: a partial
     * run does not advance the sync cursor, so the next run repeats the same
     * window and picks up whatever this one never reached.
     *
     * @param  array<string, mixed>  $filters
     */
    public function syncCalls(CallSyncRun $run, Carbon $from, Carbon $to, array $filters = []): CallSyncRun
    {
        $maxPages = (int) config('calls.callyzer.sync.max_pages', 200);
        $page = 1;
        $status = CallSyncStatus::Completed;
        $error = null;

        try {
            do {
                $result = $this->client->callHistory($from, $to, $page, $filters);

                $run->pages_fetched = $page;
                $run->records_received += count($result['records']);
                $run->last_http_status = $result['status'];

                foreach ($result['records'] as $record) {
                    $this->ingestRecord($run, $record);
                }

                $run->save();

                $page++;
            } while ($result['has_more'] && $page <= $maxPages);

            if ($page > $maxPages && $result['has_more']) {
                // Stopping at the page cap is not success. Saying so is what
                // stops the cursor moving past calls that were never fetched.
                $status = CallSyncStatus::Partial;
                $error = sprintf('Stopped at the %d page limit; more calls remain in this window.', $maxPages);
            }
        } catch (CallProviderException $exception) {
            $status = $exception->httpStatus === 429 ? CallSyncStatus::Partial : CallSyncStatus::Failed;
            $error = $exception->getMessage();

            if ($exception->httpStatus === 429) {
                $run->rate_limit_waits++;
            }

            $run->last_http_status = $exception->httpStatus;

            Log::channel('calls')->error('Callyzer sync stopped.', [
                'run_id' => $run->getKey(),
                'reason' => $exception->getMessage(),
                'retryable' => $exception->retryable,
            ]);
        }

        // Only a clean run moves the cursor forward.
        if ($status === CallSyncStatus::Completed) {
            $run->cursor_to = $to;
        }

        $run->save();
        $run->finish($status, $error);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function ingestRecord(CallSyncRun $run, array $record): void
    {
        try {
            $normalized = $this->normalizeFromSync($record);

            if ($normalized === null) {
                $run->calls_skipped++;

                return;
            }

            $existing = Call::query()
                ->where('provider', CallProvider::Callyzer->value)
                ->where('provider_call_id', $normalized->providerCallId)
                ->first();

            $recordingsBefore = $existing?->recording_count ?? 0;

            $call = $this->ingestion->ingest($normalized, ['last_source' => CallSource::ApiSync->value]);

            if ($call === null) {
                $run->calls_failed++;

                return;
            }

            $existing === null ? $run->calls_created++ : $run->calls_updated++;

            if ($call->recording_count > $recordingsBefore) {
                $run->recordings_queued += $call->recording_count - $recordingsBefore;
            }
        } catch (\Throwable $exception) {
            // One malformed record must not abandon the rest of the page. The
            // raw record is already archived by the sync service, so nothing is
            // lost by moving on.
            $run->calls_failed++;
            $run->last_error = mb_substr($exception->getMessage(), 0, 1000);

            Log::channel('calls')->error('Callyzer record could not be ingested.', [
                'run_id' => $run->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Refresh specific calls, most often to collect a recording URL that was
     * not published when the call first arrived.
     *
     * @param  array<int, string>  $providerCallIds
     */
    public function refreshCalls(array $providerCallIds): int
    {
        $result = $this->client->callHistoryByIds($providerCallIds);
        $updated = 0;

        foreach ($result['records'] as $record) {
            $normalized = $this->normalizeFromSync($record);

            if ($normalized !== null && $this->ingestion->ingest($normalized) !== null) {
                $updated++;
            }
        }

        return $updated;
    }
}
