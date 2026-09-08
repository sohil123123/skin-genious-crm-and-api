<?php

declare(strict_types=1);

namespace App\Services\Call\Contracts;

use App\DTOs\Call\NormalizedCall;
use App\Enums\Call\CallProvider;
use Illuminate\Http\Request;

/**
 * What the unified call layer needs from a telephony provider.
 *
 * Kept small on purpose. Everything a provider does that the CRM genuinely
 * depends on is here; everything else — how it paginates, what its errors look
 * like, whether it signs its webhooks — stays inside the implementation.
 *
 * Note what is absent: there is no method for fetching a recording's bytes, and
 * none for transcription. Downloading is identical for every provider once a
 * URL exists, and transcription has nothing to do with telephony at all. Adding
 * them here would drag provider adapters into work they have no opinion about.
 */
interface CallProviderInterface
{
    public function provider(): CallProvider;

    /**
     * Whether this integration is switched on and has the credentials it needs.
     */
    public function isEnabled(): bool;

    /**
     * Prove an inbound webhook actually came from this provider.
     *
     * Returning false must cause the request to be rejected before anything is
     * written, because a webhook endpoint that trusts its caller is a way for
     * anyone to inject conversations into a patient's medical file.
     */
    public function validateWebhook(Request $request): bool;

    /**
     * The identity of the event this request represents.
     *
     * Must be deterministic: the same delivery replayed must produce the same
     * key, or idempotency does not hold. Returns null when the payload carries
     * nothing stable enough to key on, which the caller treats as unprocessable
     * rather than as new.
     *
     * @param  array<string, mixed>  $payload
     */
    public function eventKey(array $payload): ?string;

    /**
     * The provider's identifier for the call a payload refers to.
     *
     * @param  array<string, mixed>  $payload
     */
    public function callId(array $payload): ?string;

    /**
     * The provider's name for this kind of event, where it has one.
     *
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): ?string;

    /**
     * Translate one provider payload into the CRM's vocabulary.
     *
     * Must not touch the database, must not throw on unfamiliar fields, and
     * must put anything it does not recognise into providerData rather than
     * discarding it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): ?NormalizedCall;
}
