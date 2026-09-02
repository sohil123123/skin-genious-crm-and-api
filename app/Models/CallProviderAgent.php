<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A provider's employee identity, mapped to a CRM user.
 *
 * Exists because the provider's idea of an agent is a phone number, and a phone
 * number is a poor identity: staff swap handsets, clinics share a reception
 * phone, and personal SIMs are rarely the number in users.mobile. Without an
 * explicit mapping a real share of calls would be attributed to nobody, and the
 * agent performance figures built on that would be quietly wrong.
 */
class CallProviderAgent extends Model
{
    use LogsActivity;

    protected $fillable = [
        'provider',
        'provider_employee_id',
        'provider_employee_code',
        'provider_employee_number',
        'provider_employee_key',
        'provider_employee_name',
        'user_id',
        'clinic_id',
        'active',
        'auto_discovered',
        'last_seen_at',
        'call_count',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CallProvider::class,
            'active' => 'boolean',
            'auto_discovered' => 'boolean',
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
            'call_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['provider', 'provider_employee_code', 'provider_employee_name', 'user_id', 'clinic_id', 'active'])
            ->logOnlyDirty()
            ->useLogName('call_provider_agent');
    }

    // ──────────────── Relationships ────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'call_provider_agent_id');
    }

    // ──────────────── Scopes ────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOfProvider(Builder $query, CallProvider|string $provider): Builder
    {
        return $query->where('provider', $provider instanceof CallProvider ? $provider->value : $provider);
    }

    /**
     * Mappings the CRM created itself and nobody has completed.
     *
     * These are the rows worth surfacing: each one is calls being recorded
     * against an agent the CRM cannot name.
     */
    public function scopeNeedsMapping(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    // ──────────────── Lookups ────────────────

    /**
     * Find the mapping for a provider agent, registering it if it is new.
     *
     * An unrecognised agent number creates a row with no user rather than being
     * dropped. That turns "some calls have no agent" from an invisible data gap
     * into a short list an administrator can work through, and the calls it
     * explains are re-attributed as soon as the row is completed.
     */
    public static function resolve(
        CallProvider|string $provider,
        ?string $phoneKey,
        ?string $employeeCode = null,
        ?string $employeeName = null,
        ?string $rawNumber = null,
        array $metadata = [],
    ): ?self {
        $providerValue = $provider instanceof CallProvider ? $provider->value : $provider;

        if (blank($phoneKey) && blank($employeeCode)) {
            return null;
        }

        $agent = static::query()
            ->where('provider', $providerValue)
            ->where(function (Builder $query) use ($phoneKey, $employeeCode): void {
                if (filled($phoneKey)) {
                    $query->orWhere('provider_employee_key', $phoneKey);
                }

                if (filled($employeeCode)) {
                    $query->orWhere('provider_employee_code', $employeeCode);
                }
            })
            ->first();

        if ($agent === null) {
            $agent = static::create([
                'provider' => $providerValue,
                'provider_employee_key' => $phoneKey,
                'provider_employee_code' => $employeeCode,
                'provider_employee_number' => $rawNumber,
                'provider_employee_name' => $employeeName,
                'active' => true,
                'auto_discovered' => true,
                'metadata' => $metadata ?: null,
                'last_seen_at' => now(),
                'call_count' => 0,
            ]);

            return $agent;
        }

        // Fill in identifying details the mapping was missing, without ever
        // overwriting what a human typed.
        $updates = [];

        if (blank($agent->provider_employee_key) && filled($phoneKey)) {
            $updates['provider_employee_key'] = $phoneKey;
        }

        if (blank($agent->provider_employee_code) && filled($employeeCode)) {
            $updates['provider_employee_code'] = $employeeCode;
        }

        if (blank($agent->provider_employee_number) && filled($rawNumber)) {
            $updates['provider_employee_number'] = $rawNumber;
        }

        if (blank($agent->provider_employee_name) && filled($employeeName)) {
            $updates['provider_employee_name'] = $employeeName;
        }

        if ($updates !== []) {
            $agent->forceFill($updates)->save();
        }

        return $agent;
    }

    /**
     * Note that this identity appeared on another call.
     */
    public function touchUsage(): void
    {
        $this->forceFill([
            'last_seen_at' => now(),
            'call_count' => $this->call_count + 1,
        ])->saveQuietly();
    }

    public function displayName(): string
    {
        return $this->provider_employee_name
            ?: $this->provider_employee_code
            ?: $this->provider_employee_number
            ?: 'Agent #' . $this->getKey();
    }
}
