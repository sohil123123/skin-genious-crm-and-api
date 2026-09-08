<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallEventProcessingStatus;
use App\Enums\Call\CallProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

/**
 * One distinct provider event. The row that makes redelivery harmless.
 *
 * The guarantee comes from the unique index on (provider, event_key) plus the
 * ordering in claim(): insert first, work second. Checking for an existing row
 * and then inserting leaves a window in which two concurrent retries both pass
 * the check and both create a call — which is precisely the failure this table
 * exists to prevent, and precisely the one that only shows up under load.
 */
class CallWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_key',
        'event_id',
        'provider_call_id',
        'event_type',
        'payload_hash',
        'call_id',
        'received_at',
        'processed_at',
        'processing_status',
        'attempts',
        'error_message',
        'duplicate_count',
        'last_duplicate_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CallProvider::class,
            'processing_status' => CallEventProcessingStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_duplicate_at' => 'datetime',
            'attempts' => 'integer',
            'duplicate_count' => 'integer',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function payloads(): HasMany
    {
        return $this->hasMany(CallProviderPayload::class, 'call_webhook_event_id');
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

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereIn('processing_status', [
            CallEventProcessingStatus::Pending->value,
            CallEventProcessingStatus::Processing->value,
        ]);
    }

    // ──────────────── Idempotency ────────────────

    /**
     * Stake a claim on an event, or discover that someone already has.
     *
     * Returns the event and whether this caller is the one that must process
     * it. The insert is attempted unconditionally and a unique-constraint
     * violation is treated as the answer rather than as an error — that is what
     * closes the race between two simultaneous retries.
     *
     * A duplicate is counted rather than logged as a fault: both providers
     * retry by design, and treating normal behaviour as an error would make the
     * health screen permanently red and therefore permanently ignored.
     *
     * @return array{0: self, 1: bool} the event, and true if the caller owns it
     */
    public static function claim(
        CallProvider|string $provider,
        string $eventKey,
        array $attributes = [],
    ): array {
        $provider = $provider instanceof CallProvider ? $provider->value : $provider;

        try {
            $event = static::create(array_merge([
                'provider' => $provider,
                'event_key' => $eventKey,
                'received_at' => now(),
                'processing_status' => CallEventProcessingStatus::Pending,
            ], $attributes));

            return [$event, true];
        } catch (QueryException $exception) {
            if (! static::isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        $event = static::query()
            ->where('provider', $provider)
            ->where('event_key', $eventKey)
            ->firstOrFail();

        $event->recordDuplicate();

        // A redelivery of an event that previously failed is a second chance,
        // not noise: the original failure may have been a transient database
        // error, and refusing to reprocess would lose the call for good.
        $reprocess = $event->processing_status === CallEventProcessingStatus::Failed;

        return [$event, $reprocess];
    }

    protected static function isUniqueViolation(QueryException $exception): bool
    {
        // 23000 covers MySQL's duplicate-key SQLSTATE; SQLite reports 23000 too
        // and is what the test suite runs on.
        return $exception->getCode() === '23000'
            || str_contains(strtolower($exception->getMessage()), 'unique');
    }

    public function recordDuplicate(): void
    {
        $this->forceFill([
            'duplicate_count' => $this->duplicate_count + 1,
            'last_duplicate_at' => now(),
        ])->save();
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'processing_status' => CallEventProcessingStatus::Processing,
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markProcessed(?Call $call = null): void
    {
        $this->forceFill([
            'call_id' => $call?->getKey() ?? $this->call_id,
            'processing_status' => CallEventProcessingStatus::Processed,
            'processed_at' => now(),
            'error_message' => null,
        ])->save();
    }

    /**
     * The event was understood and deliberately not turned into a call.
     */
    public function markIgnored(string $reason): void
    {
        $this->forceFill([
            'processing_status' => CallEventProcessingStatus::Ignored,
            'processed_at' => now(),
            'error_message' => mb_substr($reason, 0, 1000),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'processing_status' => CallEventProcessingStatus::Failed,
            'processed_at' => now(),
            'error_message' => mb_substr($message, 0, 1000),
        ])->save();
    }

    public function isSettled(): bool
    {
        return $this->processing_status->isSettled();
    }
}
