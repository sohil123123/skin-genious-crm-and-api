<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One provider synchronisation run.
 *
 * The record that turns "the integration stopped working" from something
 * noticed weeks later into something visible on a screen: a last successful
 * sync that is three days old says more than any absence of errors.
 */
class CallSyncRun extends Model
{
    protected $fillable = [
        'provider',
        'trigger',
        'triggered_by',
        'status',
        'window_from',
        'window_to',
        'cursor_to',
        'pages_fetched',
        'records_received',
        'calls_created',
        'calls_updated',
        'calls_skipped',
        'calls_failed',
        'recordings_queued',
        'rate_limit_waits',
        'last_http_status',
        'last_error',
        'parameters',
        'metrics',
        'started_at',
        'finished_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CallProvider::class,
            'status' => CallSyncStatus::class,
            'parameters' => 'array',
            'metrics' => 'array',
            'window_from' => 'datetime',
            'window_to' => 'datetime',
            'cursor_to' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pages_fetched' => 'integer',
            'records_received' => 'integer',
            'calls_created' => 'integer',
            'calls_updated' => 'integer',
            'calls_skipped' => 'integer',
            'calls_failed' => 'integer',
            'recordings_queued' => 'integer',
            'rate_limit_waits' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    // ──────────────── Scopes ────────────────

    public function scopeOfProvider(Builder $query, CallProvider|string $provider): Builder
    {
        return $query->where('provider', $provider instanceof CallProvider ? $provider->value : $provider);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CallSyncStatus::Completed->value,
            CallSyncStatus::Partial->value,
        ]);
    }

    // ──────────────── Cursor ────────────────

    /**
     * Where the next incremental run should start from.
     *
     * Only completed runs advance the cursor. A partial run must repeat its own
     * window: it stopped somewhere in the middle, and skipping past the calls
     * it never reached would lose them silently, which is the one failure mode
     * an incremental sync must not have.
     */
    public static function lastCursor(CallProvider|string $provider): ?Carbon
    {
        $run = static::query()
            ->ofProvider($provider)
            ->where('status', CallSyncStatus::Completed->value)
            ->whereNotNull('cursor_to')
            ->latest('cursor_to')
            ->first();

        return $run?->cursor_to;
    }

    public static function lastSuccessful(CallProvider|string $provider): ?self
    {
        return static::query()
            ->ofProvider($provider)
            ->successful()
            ->latest('finished_at')
            ->first();
    }

    public static function lastFailed(CallProvider|string $provider): ?self
    {
        return static::query()
            ->ofProvider($provider)
            ->where('status', CallSyncStatus::Failed->value)
            ->latest('finished_at')
            ->first();
    }

    /**
     * Whether a run of this provider is already in flight.
     *
     * Used to stop a manual "Sync Now" racing the scheduled run and doubling
     * the request rate against a provider that allows one call every two
     * seconds.
     */
    public static function isRunning(CallProvider|string $provider): bool
    {
        return static::query()
            ->ofProvider($provider)
            ->where('status', CallSyncStatus::Running->value)
            // A run whose worker died would otherwise block every later run
            // forever, so anything older than an hour is treated as abandoned.
            ->where('started_at', '>=', now()->subHour())
            ->exists();
    }

    public function finish(CallSyncStatus $status, ?string $error = null): void
    {
        $this->forceFill([
            'status' => $status,
            'last_error' => $error !== null ? mb_substr($error, 0, 1000) : $this->last_error,
            'finished_at' => now(),
            'duration_seconds' => $this->started_at !== null
                ? max(0, now()->diffInSeconds($this->started_at, absolute: true))
                : null,
        ])->save();
    }

    public function totalWritten(): int
    {
        return $this->calls_created + $this->calls_updated;
    }
}
