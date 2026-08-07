<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saved column mapping, so the same lead form never has to be mapped twice.
 *
 * Templates are matched to a new upload by a signature over the sorted header
 * list rather than by filename, because Meta names every export after the ad
 * and date range while the form's questions stay the same.
 */
class LeadMappingTemplate extends Model
{
    protected $fillable = [
        'clinic_id',
        'name',
        'description',
        'signature',
        'header_columns',
        'mapping',
        'settings',
        'duplicate_strategy',
        'duplicate_match_fields',
        'is_default',
        'created_by',
        'usage_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'header_columns' => 'array',
            'mapping' => 'array',
            'settings' => 'array',
            'duplicate_match_fields' => 'array',
            'duplicate_strategy' => DuplicateStrategy::class,
            'is_default' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(LeadImport::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        $clinicId = auth()->user()->clinic_id;

        return $query->where(fn (Builder $inner): Builder => $inner
            ->where('clinic_id', $clinicId)
            ->orWhereNull('clinic_id'));
    }

    public function scopeMatchingSignature(Builder $query, string $signature): Builder
    {
        return $query->where('signature', $signature);
    }

    // ──────────────── Helpers ────────────────

    /**
     * Fingerprint a header list so an identical form is recognised on re-upload.
     *
     * Headers are normalised and sorted first, so a column reorder or a change
     * in casing still resolves to the same template.
     *
     * @param  array<int, string>  $headers
     */
    public static function buildSignature(array $headers): string
    {
        $normalized = array_map(
            fn (string $header): string => strtolower(trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', $header)) ?? $header)),
            $headers
        );

        sort($normalized);

        return sha1(implode('|', $normalized));
    }

    public function recordUsage(): void
    {
        $this->forceFill([
            'usage_count' => $this->usage_count + 1,
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * How closely a header list matches the one this template was built from.
     *
     * Used to warn the user when a form has gained or lost a question since the
     * template was saved.
     *
     * @param  array<int, string>  $headers
     */
    public function headerOverlap(array $headers): float
    {
        $saved = $this->header_columns ?? [];

        if ($saved === []) {
            return 0.0;
        }

        $shared = count(array_intersect($saved, $headers));

        return round(($shared / count($saved)) * 100, 1);
    }
}
