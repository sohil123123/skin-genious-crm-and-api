<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A Facebook Page connected to the CRM.
 *
 * This exists because a leadgen webhook arrives with no authentication and no
 * clinic: the only identifying thing in the payload is page_id. Mapping that to
 * a clinic here — rather than assuming a single clinic — is also what makes
 * supporting a second Page later a row rather than a migration.
 */
class MetaPage extends Model
{
    use LogsActivity;

    protected $fillable = [
        'page_id',
        'page_name',
        'clinic_id',
        'access_token',
        'is_active',
        'subscribed_at',
        'last_lead_at',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest so a database dump does not hand over the
            // ability to read this Page's leads.
            'access_token' => 'encrypted',
            'is_active' => 'boolean',
            'subscribed_at' => 'datetime',
            'last_lead_at' => 'datetime',
        ];
    }

    /**
     * The token is deliberately excluded: it is never shown in a table, never
     * serialised into a response, and never written to the activity log.
     */
    protected $hidden = [
        'access_token',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['page_id', 'page_name', 'clinic_id', 'is_active', 'subscribed_at'])
            ->logOnlyDirty()
            ->useLogName('meta_page');
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(MetaLeadSyncLog::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Restrict to the authenticated user's clinic, matching the convention used
     * by Lead and the other clinic-owned models.
     */
    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        return $query->where('clinic_id', auth()->user()->clinic_id);
    }

    // ──────────────── Lookups ────────────────

    /**
     * Resolve the Page a webhook came from.
     *
     * Returns null rather than throwing for an unknown or deactivated Page: a
     * Meta app can be subscribed to Pages this CRM was never told about, and
     * that is a configuration gap to report, not an exception to retry.
     */
    public static function findActiveByPageId(?string $pageId): ?self
    {
        if ($pageId === null || $pageId === '') {
            return null;
        }

        return static::query()
            ->active()
            ->where('page_id', $pageId)
            ->first();
    }

    public function hasUsableToken(): bool
    {
        return filled($this->access_token);
    }
}
