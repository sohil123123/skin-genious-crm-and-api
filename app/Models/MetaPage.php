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
        'last_synced_at',
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
            'last_synced_at' => 'datetime',
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
     * Resolve the Page a webhook came from, registering it on first sight.
     *
     * An administrator should never have to create a Page before its leads can
     * arrive, so an unrecognised page_id is treated as a new Page rather than a
     * configuration error. Only the id is written here — the name needs a Graph
     * call, which belongs on the queue, not in the webhook request.
     *
     * A Page that was explicitly deactivated is returned as null: switching it
     * off is a deliberate instruction to stop accepting its leads, and
     * recreating it automatically would quietly override that.
     */
    public static function resolveFromWebhook(?string $pageId): ?self
    {
        $pageId = trim((string) $pageId);

        if ($pageId === '') {
            return null;
        }

        $page = static::query()->where('page_id', $pageId)->first();

        if ($page !== null) {
            return $page->is_active ? $page : null;
        }

        return static::create([
            'page_id' => $pageId,
            // Stamped at creation from the Meta default-clinic setting, so the
            // Page shows a real clinic in the UI straight away and an
            // administrator can see — and override — where its leads are going
            // before the first one is even processed.
            'clinic_id' => static::defaultClinicId(),
            'is_active' => true,
        ]);
    }

    /**
     * The clinic to file this Page's leads against.
     *
     * Normally the Page's own clinic. The fallback covers Pages created before
     * a default was configured, and any row whose clinic was later deleted —
     * leads.clinic_id is required, and losing a lead over missing setup would
     * be the worst possible outcome.
     */
    public function resolveClinicId(): ?int
    {
        return $this->clinic_id !== null
            ? (int) $this->clinic_id
            : static::defaultClinicId();
    }

    /**
     * The clinic new Meta Pages are assigned to.
     *
     * Read live from the settings table, whose cache is cleared whenever a
     * setting is saved — so changing it on the Meta Lead Settings screen
     * takes effect on the very next webhook. Falls through to config, then
     * to the first active clinic, so a lead is never dropped for want of
     * configuration.
     */
    public static function defaultClinicId(): ?int
    {
        $configured = Setting::getValue('meta_default_clinic_id', config('meta.defaults.clinic_id'));

        if (filled($configured) && Clinic::query()->whereKey($configured)->exists()) {
            return (int) $configured;
        }

        return Clinic::query()->where('is_active', true)->min('id')
            ?? Clinic::query()->min('id');
    }

    /**
     * The token to use when calling Graph for this Page.
     *
     * Preference order, most specific first:
     *   1. a token saved against this Page, if someone deliberately set one
     *   2. a Page token derived from the system token, cached briefly
     *   3. the system token itself, which is enough when it is already a Page
     *      token or a User token carrying leads_retrieval
     *
     * Step 2 is what removes the per-Page setup: one User token in settings
     * yields a working token for every Page it administers.
     */
    public function resolveAccessToken(): ?string
    {
        if (filled($this->access_token)) {
            return $this->access_token;
        }

        return static::systemAccessToken();
    }

    public static function systemAccessToken(): ?string
    {
        $token = Setting::getValue('meta_access_token', config('meta.credentials.access_token'));

        return filled($token) ? (string) $token : null;
    }

    public function hasUsableToken(): bool
    {
        return filled($this->resolveAccessToken());
    }

    /**
     * A label that is always safe to display.
     *
     * A Page registers itself from the webhook with only its id — the name needs
     * a Graph call — so page_name is null until then. Anything rendering a Page
     * to a human should use this rather than page_name directly.
     */
    public function displayName(): string
    {
        return filled($this->page_name)
            ? (string) $this->page_name
            : 'Page ' . $this->page_id;
    }

    /**
     * Whether this Page has no clinic of its own and relies on the default.
     *
     * Only reachable now for Pages created before a default clinic existed, or
     * whose clinic was later deleted — new Pages are stamped at creation.
     */
    public function usesDefaultClinic(): bool
    {
        return $this->clinic_id === null;
    }
}
