<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetaSyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Meta lead's journey from webhook to CRM lead.
 *
 * Deliberately narrow: application errors still go to the log viewer through
 * the meta_leads channel. What this adds is per-lead state the log files cannot
 * answer — which leads failed, how many times, and what payload to replay —
 * plus the unique leadgen_id that makes a redelivered webhook a no-op.
 */
class MetaLeadSyncLog extends Model
{
    protected $fillable = [
        'leadgen_id',
        'meta_page_id',
        'lead_id',
        'status',
        'attempts',
        'error_message',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MetaSyncStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeOfStatus(Builder $query, MetaSyncStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof MetaSyncStatus ? $status->value : $status);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', MetaSyncStatus::Failed->value);
    }

    /**
     * Sync logs for the current user's clinic, resolved through the Page.
     *
     * Records whose Page is unknown have no clinic to belong to, so only a
     * super admin sees them — they are a configuration problem rather than
     * anyone's lead.
     */
    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        return $query->whereHas(
            'metaPage',
            fn (Builder $page): Builder => $page->where('clinic_id', auth()->user()->clinic_id)
        );
    }

    // ──────────────── State transitions ────────────────

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => MetaSyncStatus::Processing,
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markSuccess(Lead $lead): void
    {
        $this->forceFill([
            'status' => MetaSyncStatus::Success,
            'lead_id' => $lead->getKey(),
            'error_message' => null,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * The lead was already in the CRM, so nothing was written.
     */
    public function markSkipped(?Lead $lead, string $reason): void
    {
        $this->forceFill([
            'status' => MetaSyncStatus::Skipped,
            'lead_id' => $lead?->getKey() ?? $this->lead_id,
            'error_message' => $reason,
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => MetaSyncStatus::Failed,
            // Column is TEXT, but a Graph API error body can be far longer than
            // anything useful to read on screen.
            'error_message' => mb_substr($message, 0, 1000),
            'processed_at' => now(),
        ])->save();
    }

    /**
     * Whether processing this record again would be pointless.
     */
    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }
}
