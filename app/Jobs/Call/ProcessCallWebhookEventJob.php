<?php

declare(strict_types=1);

namespace App\Jobs\Call;

use App\Models\CallProviderPayload;
use App\Models\CallWebhookEvent;
use App\Services\Call\CallIngestionService;
use App\Services\Call\CallProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns one archived webhook payload into a call, off the request cycle.
 *
 * ShouldBeUnique is the second line of idempotency defence, behind the unique
 * index on call_webhook_events. The index stops two deliveries becoming two
 * events; this stops one event being processed twice when a retry slips in
 * while the first attempt is still queued.
 */
class ProcessCallWebhookEventJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public int $eventId,
        public ?int $payloadId = null,
    ) {
        $this->tries = (int) config('calls.queue.tries', 5);
        $this->timeout = (int) config('calls.queue.timeout', 180);

        $this->onQueue((string) config('calls.queue.webhooks', 'calls'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'call-event-' . $this->eventId;
    }

    /**
     * How long the uniqueness lock survives a worker dying mid-job.
     */
    public function uniqueFor(): int
    {
        return (int) config('calls.queue.timeout', 180) * 2;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('calls.queue.backoff', [30, 120, 300, 900, 1800]);
    }

    public function handle(CallProviderManager $providers, CallIngestionService $ingestion): void
    {
        $event = CallWebhookEvent::find($this->eventId);

        if ($event === null) {
            return;
        }

        // Covers a redelivery that got past the unique dispatch lock — one
        // queued after the first attempt had already finished.
        if ($event->isSettled()) {
            return;
        }

        $payload = $this->payloadId !== null
            ? CallProviderPayload::find($this->payloadId)
            : $event->payloads()->latest('received_at')->first();

        if ($payload === null) {
            $event->markFailed('The archived payload for this event no longer exists.');

            return;
        }

        $event->markProcessing();

        try {
            $adapter = $providers->get($event->provider);
            $normalized = $adapter->normalize((array) $payload->payload);

            if ($normalized === null || ! $normalized->isIdentifiable()) {
                // Understood and deliberately not turned into a call. Ignored
                // rather than failed, because retrying will not make an
                // unusable payload usable.
                $event->markIgnored('The payload could not be normalised into a call.');
                $payload->markFailed('The payload could not be normalised into a call.');

                return;
            }

            $call = $ingestion->ingest($normalized, [
                'last_processing_status' => 'processed',
                'last_error' => null,
            ]);

            if ($call === null) {
                $event->markFailed('The call could not be written.');
                $payload->markFailed('The call could not be written.');

                return;
            }

            $event->markProcessed($call);
            $payload->markProcessed($call);
        } catch (Throwable $exception) {
            $event->markFailed($exception->getMessage());
            $payload->markFailed($exception->getMessage());

            Log::channel('calls')->error('Call webhook event processing failed.', [
                'event_id' => $event->getKey(),
                'provider' => $event->provider->value,
                'error' => $exception->getMessage(),
            ]);

            // Rethrown so the queue applies its backoff and, eventually, gives
            // up loudly rather than silently.
            throw $exception;
        }
    }

    /**
     * Record the final failure once the retries are exhausted.
     *
     * handle() already writes a message per attempt; this exists so a job that
     * dies for a reason the handler never saw — a timeout, a worker restart —
     * does not leave the event stuck on "processing" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $event = CallWebhookEvent::find($this->eventId);

        if ($event === null || $event->isSettled()) {
            return;
        }

        $event->markFailed($exception?->getMessage() ?? 'The job failed without reporting a reason.');
    }
}
