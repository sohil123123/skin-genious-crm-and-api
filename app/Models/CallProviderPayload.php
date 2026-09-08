<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallEventProcessingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provider payload, exactly as it arrived.
 *
 * Treated as append-only. Nothing in this application edits the payload column
 * after insert, and nothing should: the whole value of this table is that it is
 * the one place a normalised field can be checked against what was actually
 * sent.
 *
 * Only the processing outcome columns are ever written after creation.
 */
class CallProviderPayload extends Model
{
    protected $fillable = [
        'call_id',
        'call_webhook_event_id',
        'provider',
        'source',
        'event_type',
        'provider_call_id',
        'request_id',
        'payload',
        'headers',
        'ip_address',
        'http_method',
        'received_at',
        'processed_at',
        'processing_status',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CallProvider::class,
            'source' => CallSource::class,
            'processing_status' => CallEventProcessingStatus::class,
            'payload' => 'array',
            'headers' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Header names that must never be written to the database.
     *
     * The point of storing headers is diagnosing delivery, and none of that
     * needs the credential the request carried. Keeping one here would put a
     * live secret in every database backup.
     */
    protected const REDACTED_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-exotel-webhook-secret',
        'x-callyzer-signature',
        'x-hub-signature',
        'x-hub-signature-256',
    ];

    // ──────────────── Relationships ────────────────

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(CallWebhookEvent::class, 'call_webhook_event_id');
    }

    // ──────────────── Scopes ────────────────

    public function scopeOfProvider(Builder $query, CallProvider|string $provider): Builder
    {
        return $query->where('provider', $provider instanceof CallProvider ? $provider->value : $provider);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('processing_status', CallEventProcessingStatus::Failed->value);
    }

    // ──────────────── Helpers ────────────────

    /**
     * Strip credentials out of a header bag before it is stored.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function redactHeaders(array $headers): array
    {
        $safe = [];

        foreach ($headers as $name => $value) {
            $safe[$name] = in_array(strtolower((string) $name), self::REDACTED_HEADERS, true)
                ? '[redacted]'
                : $value;
        }

        return $safe;
    }

    /**
     * Remove secret-bearing query parameters from a payload before storing it.
     *
     * Exotel's Passthru applet is a GET, so the shared secret protecting the
     * endpoint travels in the query string and would otherwise be archived in
     * full alongside every single call.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function redactPayload(array $payload): array
    {
        $secretKeys = array_filter([
            (string) config('calls.exotel.webhook.secret_query_key', 'token'),
            'secret',
            'api_key',
            'apikey',
            'auth_token',
            'password',
        ]);

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), array_map('strtolower', $secretKeys), true)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }

    public function markProcessed(?Call $call = null): void
    {
        $this->forceFill([
            'call_id' => $call?->getKey() ?? $this->call_id,
            'processing_status' => CallEventProcessingStatus::Processed,
            'processed_at' => now(),
            'processing_error' => null,
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'processing_status' => CallEventProcessingStatus::Failed,
            'processed_at' => now(),
            'processing_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
