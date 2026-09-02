<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\Enums\Call\CallEventProcessingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Jobs\Call\ProcessCallWebhookEventJob;
use App\Models\CallProviderPayload;
use App\Models\CallWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Everything a webhook controller does, minus the provider-specific parts.
 *
 * Both webhook endpoints do exactly the same four things in exactly the same
 * order — archive the payload, stake an idempotency claim, dispatch, answer —
 * and the order is what makes the guarantees hold, so it lives in one place
 * rather than being reimplemented per provider and drifting.
 *
 * The order matters more than it looks:
 *
 *  1. Archive first. If anything after this fails, the payload survives and the
 *     event can be replayed. Nothing is ever lost to a bug downstream.
 *  2. Claim second, by insert. Two concurrent retries race here, and the unique
 *     index picks a winner. Checking-then-inserting would let both through.
 *  3. Dispatch third — never process inline. Both providers treat a slow
 *     response as a failed delivery and retry, so doing real work here is how
 *     duplicate storms begin.
 *  4. Answer 2xx for anything that is not an authentication failure, including
 *     payloads the CRM cannot use. A 500 makes the provider redeliver for
 *     hours, and a malformed payload will not become valid on the fifth
 *     attempt — it needs a person, so it is recorded rather than retried.
 */
class CallWebhookService
{
    public function __construct(
        protected CallProviderManager $providers,
    ) {}

    /**
     * Take delivery of one webhook.
     *
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, duplicate: bool, event: ?CallWebhookEvent}
     */
    public function receive(CallProvider $provider, Request $request, array $payload): array
    {
        $adapter = $this->providers->get($provider);

        $eventKey = $adapter->eventKey($payload);
        $providerCallId = $adapter->callId($payload);

        // ─── 1. Archive, before anything can go wrong ────────────────────
        $archived = $this->storePayload($provider, $providerCallId, [
            'provider' => $provider->value,
            'source' => CallSource::Webhook->value,
            'event_type' => $adapter->eventType($payload),
            'provider_call_id' => $providerCallId,
            'request_id' => $request->header('X-Request-Id') ?: (string) Str::uuid(),
            'payload' => CallProviderPayload::redactPayload($payload),
            'headers' => CallProviderPayload::redactHeaders($request->headers->all()),
            'ip_address' => $request->ip(),
            'http_method' => $request->method(),
            'received_at' => now(),
            'processing_status' => CallEventProcessingStatus::Pending->value,
        ]);

        if (blank($eventKey)) {
            // Nothing stable to key on, so this delivery cannot be deduplicated
            // and cannot be updated by whatever follows it. Recorded and
            // stopped — but answered 2xx, because redelivering it will not
            // supply the identifier it never had.
            $archived->forceFill([
                'processing_status' => CallEventProcessingStatus::Ignored,
                'processed_at' => now(),
                'processing_error' => 'The payload carried no identifiable call reference.',
            ])->save();

            Log::channel('calls')->warning('Call webhook carried no usable call identifier.', [
                'provider' => $provider->value,
                'payload_id' => $archived->getKey(),
            ]);

            return ['accepted' => false, 'duplicate' => false, 'event' => null];
        }

        // ─── 2. Claim, by insert ─────────────────────────────────────────
        [$event, $isOwner] = CallWebhookEvent::claim($provider, $eventKey, [
            'event_id' => $providerCallId,
            'provider_call_id' => $providerCallId,
            'event_type' => $adapter->eventType($payload),
            'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
        ]);

        $archived->forceFill(['call_webhook_event_id' => $event->getKey()])->save();

        if (! $isOwner) {
            $archived->forceFill([
                'processing_status' => CallEventProcessingStatus::Duplicate,
                'processed_at' => now(),
            ])->save();

            Log::channel('calls')->info('Call webhook redelivered; already recorded.', [
                'provider' => $provider->value,
                'event_key' => $eventKey,
                'duplicates' => $event->duplicate_count,
            ]);

            return ['accepted' => true, 'duplicate' => true, 'event' => $event];
        }

        // ─── 3. Dispatch, never process inline ───────────────────────────
        ProcessCallWebhookEventJob::dispatch($event->getKey(), $archived->getKey());

        return ['accepted' => true, 'duplicate' => false, 'event' => $event];
    }

    /**
     * Archive a record pulled from an API rather than pushed by a webhook.
     *
     * Sync records get the same treatment as webhooks so that "what did the
     * provider actually send" is answerable regardless of which path a call
     * came in by. Without this, a sync-only call would have normalised columns
     * and nothing to check them against.
     *
     * @param  array<string, mixed>  $payload
     */
    public function archiveSyncPayload(
        CallProvider $provider,
        array $payload,
        ?string $providerCallId = null,
        ?int $callId = null,
    ): CallProviderPayload {
        return $this->storePayload($provider, $providerCallId, array_filter([
            // Filtered so a sync that does not yet know the call keeps whatever
            // link an earlier delivery established, instead of clearing it.
            'call_id' => $callId,
            'provider' => $provider->value,
            'source' => CallSource::ApiSync->value,
            'provider_call_id' => $providerCallId,
            'payload' => $payload,
            'received_at' => now(),
            'processed_at' => now(),
            'processing_status' => CallEventProcessingStatus::Processed->value,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Archive one delivery, one row per call.
     *
     * Providers do not send a call once. Callyzer sends it at hangup and again
     * when the recording finishes uploading; Exotel redelivers whenever it does
     * not see a prompt 2xx. Keeping a row per delivery meant several rows per
     * call, all but the last of them superseded, growing without bound on the
     * busiest tables in the system.
     *
     * So the latest delivery replaces the one before it. The archive answers
     * "what did the provider last send for this call", which is the question it
     * is actually asked - and it stays one row deep per call.
     *
     * Deliberately a replacement rather than a merge: a row built by combining
     * two deliveries would be a payload the provider never sent, which is
     * exactly what an archive must not contain.
     *
     * A payload with no readable call id keeps its own row every time. Those
     * are the unparseable ones, each is its own puzzle, and collapsing them
     * would throw away the only evidence of what went wrong.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function storePayload(
        CallProvider $provider,
        ?string $providerCallId,
        array $attributes,
    ): CallProviderPayload {
        if (blank($providerCallId)) {
            return CallProviderPayload::create($attributes);
        }

        return CallProviderPayload::updateOrCreate(
            ['provider' => $provider->value, 'provider_call_id' => $providerCallId],
            $attributes,
        );
    }
}
