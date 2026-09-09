<?php

declare(strict_types=1);

namespace App\Services\Call\Providers\Exotel;

use App\DTOs\Call\NormalizedCall;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallSyncStatus;
use App\Models\Call;
use App\Models\CallSyncRun;
use App\Models\Setting;
use App\Services\Call\Exceptions\CallProviderException;
use App\Services\Call\CallIngestionService;
use App\Services\Call\Contracts\CallProviderInterface;
use App\Services\Call\Contracts\SyncsCallsInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Exotel adapter: incoming calls, delivered by the Passthru applet.
 *
 * Exotel signs nothing. Its Passthru applet is a plain GET to whatever URL is
 * configured in the flow, which means the endpoint's only protection is a
 * secret embedded in that URL. That is a weaker guarantee than an HMAC and it
 * is worth being explicit about: the secret is visible to anyone who can read
 * the Exotel flow configuration, and it travels in a query string, so it is
 * treated as a shared password — required, redacted before storage, and
 * comparable only in constant time.
 */
class ExotelProvider implements CallProviderInterface, SyncsCallsInterface
{
    public function __construct(
        protected ExotelClient $client,
        protected ExotelCallMapper $mapper,
        protected CallIngestionService $ingestion,
    ) {}

    public function provider(): CallProvider
    {
        return CallProvider::Exotel;
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('exotel_enabled', config('calls.exotel.enabled', false));
    }

    /**
     * Pulling additionally needs API credentials to pull with.
     *
     * Independent of isEnabled() on purpose: Exotel's Passthru works without
     * the API key and token, so an installation can be receiving calls all day
     * and still have nothing to poll with.
     */
    public function isSyncEnabled(): bool
    {
        return $this->isEnabled()
            && $this->client->isConfigured()
            && (bool) Setting::getValue('exotel_sync_enabled', config('calls.exotel.sync.enabled', false));
    }

    /**
     * Check the shared secret carried by the request.
     *
     * The comparison is constant-time so that a wrong secret cannot be
     * recovered a character at a time by measuring how long the rejection took.
     *
     * Verification can be switched off for local replay of captured payloads,
     * but an *enabled* check with no secret configured rejects everything —
     * failing closed, because the alternative is an endpoint that writes
     * conversations into patient files on the word of anyone who found the URL.
     */
    public function validateWebhook(Request $request): bool
    {
        $required = (bool) config('calls.exotel.webhook.require_secret', true);
        $expected = Setting::getConfigured('exotel_webhook_secret', config('calls.exotel.webhook.secret'));

        if (! $required) {
            return true;
        }

        if (blank($expected)) {
            Log::channel('calls')->error('Exotel webhook rejected: no webhook secret is configured.');

            return false;
        }

        $queryKey = (string) config('calls.exotel.webhook.secret_query_key', 'token');

        $supplied = $request->header((string) config('calls.exotel.webhook.secret_header', 'X-Exotel-Webhook-Secret'))
            ?? $request->query($queryKey)
            ?? $request->input($queryKey);

        if (blank($supplied)) {
            return false;
        }

        return hash_equals((string) $expected, (string) $supplied);
    }

    /**
     * The identity of one Exotel delivery.
     *
     * A CallSid alone would be wrong: the same call legitimately produces
     * several deliveries as it progresses, and keying on the SID would let the
     * first one through and silently discard the "completed" event carrying the
     * duration and the recording.
     *
     * So the key is the SID plus what this particular delivery knew — its
     * status, and whether it carried a recording. A retry of the same stage
     * produces the same key and is ignored; a genuine progression produces a
     * new one and is processed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function eventKey(array $payload): ?string
    {
        $callSid = $this->callId($payload);

        if (blank($callSid)) {
            return null;
        }

        $stage = implode('|', array_filter([
            $payload['EventType'] ?? $payload['eventType'] ?? null,
            $payload['CallStatus'] ?? $payload['Status'] ?? $payload['status'] ?? null,
            $payload['DialCallStatus'] ?? null,
            $payload['CallType'] ?? null,
            // A later delivery differing only by having the recording ready is
            // a real progression and must not be mistaken for a retry.
            filled($payload['RecordingUrl'] ?? null) ? 'rec' : '',
        ], static fn (mixed $value): bool => filled($value)));

        if ($stage === '') {
            $stage = substr(hash('sha256', json_encode($payload) ?: ''), 0, 16);
        }

        return sprintf('exotel:%s:%s', $callSid, substr(hash('sha256', $stage), 0, 24));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function callId(array $payload): ?string
    {
        foreach (['CallSid', 'callSid', 'Sid', 'sid'] as $key) {
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
        $type = $payload['EventType']
            ?? $payload['CallType']
            ?? $payload['CallStatus']
            ?? $payload['Status']
            ?? null;

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
     * Ask Exotel for a call's current state and fold it in.
     *
     * The reason this exists: Exotel finalises a recording a short while after
     * the call ends, so a flow that hangs up promptly delivers its last
     * Passthru with no RecordingUrl at all. Without a pull, those calls would
     * permanently have no audio despite one existing.
     */
    public function refreshCall(Call $call): bool
    {
        // Said out loud rather than returned as false.
        //
        // Pulling a call needs API credentials, which are a separate setting
        // from the webhook — a clinic can receive calls all day without ever
        // filling them in. Returning false here made "Refresh from provider"
        // answer "the provider returned nothing new", which is a report about
        // Exotel's data for a request that was never sent.
        if (! $this->client->isConfigured()) {
            throw CallProviderException::permanent(
                'Exotel API credentials are not set. Add the Account SID, API key and API token '
                . 'under Calls → Call Settings → Exotel. The webhook works without them; pulling a '
                . 'call after the fact does not.'
            );
        }

        if (blank($call->provider_call_id)) {
            return false;
        }

        $record = $this->client->getCall($call->provider_call_id);

        if ($record === null) {
            return false;
        }

        $normalized = $this->mapper->map($record, CallSource::ApiSync);

        if ($normalized === null) {
            return false;
        }

        return $this->ingestion->ingest($normalized, ['last_source' => CallSource::ApiSync->value]) !== null;
    }

    /**
     * Pull the calls Exotel created inside a window.
     *
     * A backstop rather than the main path. Exotel pushes every call as it
     * happens; this catches the ones whose Passthru never arrived — a flow
     * edited mid-call, an outage at this end — and picks up recordings that
     * were still being finalised when the last webhook fired.
     *
     * Both paths converge on the same mapper and the same ingestion service, so
     * a call that arrived live and again here updates one row rather than
     * creating two.
     *
     * @param  array<string, mixed>  $filters
     */
    public function syncCalls(CallSyncRun $run, Carbon $from, Carbon $to, array $filters = []): CallSyncRun
    {
        $maxPages = (int) config('calls.exotel.sync.max_pages', 200);
        // Exotel's Page parameter is zero-based, unlike Callyzer's.
        $page = 0;
        $status = CallSyncStatus::Completed;
        $error = null;
        $result = ['has_more' => false];

        try {
            do {
                $result = $this->client->calls($from, $to, $page, $filters);

                $run->pages_fetched = $page + 1;
                $run->records_received += count($result['records']);
                $run->last_http_status = $result['status'];

                foreach ($result['records'] as $record) {
                    $this->ingestRecord($run, $record);
                }

                $run->save();

                $page++;
            } while ($result['has_more'] && $page < $maxPages);

            if ($page >= $maxPages && $result['has_more']) {
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

            Log::channel('calls')->error('Exotel sync stopped.', [
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
            $normalized = $this->mapper->map($record, CallSource::ApiSync);

            if ($normalized === null) {
                $run->calls_skipped++;

                return;
            }

            $existing = Call::query()
                ->where('provider', CallProvider::Exotel->value)
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
        } catch (Throwable $exception) {
            // One malformed record must not abandon the rest of the page.
            $run->calls_failed++;
            $run->last_error = mb_substr($exception->getMessage(), 0, 1000);

            Log::channel('calls')->error('Exotel record could not be ingested.', [
                'run_id' => $run->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
