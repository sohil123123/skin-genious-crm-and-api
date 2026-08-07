<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateStrategy;
use App\Enums\LeadImportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One uploaded lead file and everything known about how it was processed.
 */
class LeadImport extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'uploaded_by',
        'lead_mapping_template_id',
        'original_filename',
        'label',
        'stored_path',
        'disk',
        'file_size',
        'file_hash',
        'encoding',
        'delimiter',
        'enclosure',
        'has_bom',
        'status',
        'detected_headers',
        'analysis',
        'column_mapping',
        'settings',
        'duplicate_strategy',
        'duplicate_match_fields',
        'batch_id',
        'total_rows',
        'processed_rows',
        'imported_rows',
        'updated_rows',
        'skipped_rows',
        'failed_rows',
        'started_at',
        'finished_at',
        'duration_seconds',
        'failed_export_path',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadImportStatus::class,
            'duplicate_strategy' => DuplicateStrategy::class,
            'detected_headers' => 'array',
            'analysis' => 'array',
            'column_mapping' => 'array',
            'settings' => 'array',
            'duplicate_match_fields' => 'array',
            'has_bom' => 'boolean',
            'file_size' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'imported_rows' => 'integer',
            'updated_rows' => 'integer',
            'skipped_rows' => 'integer',
            'failed_rows' => 'integer',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'column_mapping', 'duplicate_strategy', 'settings'])
            ->logOnlyDirty()
            ->useLogName('lead_import');
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LeadMappingTemplate::class, 'lead_mapping_template_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function failures(): HasMany
    {
        return $this->hasMany(LeadImportFailure::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LeadImportLog::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        return $query->where('clinic_id', auth()->user()->clinic_id);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LeadImportStatus::Queued->value,
            LeadImportStatus::Processing->value,
            LeadImportStatus::Analyzing->value,
        ]);
    }

    // ──────────────── Progress ────────────────

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_rows === 0) {
            return $this->status->isFinished() ? 100.0 : 0.0;
        }

        return round(min($this->processed_rows / $this->total_rows, 1) * 100, 1);
    }

    /**
     * Estimated seconds remaining, based on the rate achieved so far.
     *
     * Returns null before there is enough throughput to extrapolate from,
     * which is better than showing a wildly wrong number for the first second.
     */
    public function getEtaSecondsAttribute(): ?int
    {
        if (! $this->status->isRunning() || $this->started_at === null) {
            return null;
        }

        $processed = $this->processed_rows;
        $remaining = max($this->total_rows - $processed, 0);

        if ($processed < 1 || $remaining === 0) {
            return null;
        }

        $elapsed = max(now()->diffInSeconds($this->started_at, absolute: true), 1);
        $rate = $processed / $elapsed;

        if ($rate <= 0) {
            return null;
        }

        return (int) ceil($remaining / $rate);
    }

    public function getEtaForHumansAttribute(): ?string
    {
        $seconds = $this->eta_seconds;

        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        return now()->addSeconds($seconds)->diffForHumans(now(), syntax: true, short: true, parts: 2);
    }

    public function getDurationForHumansAttribute(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        if ($this->duration_seconds < 60) {
            return $this->duration_seconds . 's';
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return $seconds === 0 ? "{$minutes}m" : "{$minutes}m {$seconds}s";
    }

    /**
     * Rows that produced a lead, either newly created or updated.
     */
    public function getSuccessfulRowsAttribute(): int
    {
        return $this->imported_rows + $this->updated_rows;
    }

    // ──────────────── State helpers ────────────────

    public function hasFile(): bool
    {
        return $this->stored_path !== null
            && Storage::disk($this->disk)->exists($this->stored_path);
    }

    public function hasFailedExport(): bool
    {
        return $this->failed_export_path !== null
            && Storage::disk($this->disk)->exists($this->failed_export_path);
    }

    public function canRetryFailures(): bool
    {
        return $this->status->isFinished()
            && $this->failures()->unresolved()->exists();
    }

    /**
     * Record a lifecycle event against this import.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, string $message, array $context = [], string $level = LeadImportLog::LEVEL_INFO): LeadImportLog
    {
        return $this->logs()->create([
            'user_id' => auth()->id(),
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * Reset counters so a retry does not double-count the original run.
     */
    public function resetCounters(): void
    {
        $this->forceFill([
            'processed_rows' => 0,
            'imported_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ])->save();
    }

    /**
     * The settings array with defaults applied for any key not stored.
     *
     * @return array<string, mixed>
     */
    public function settingsWithDefaults(): array
    {
        return array_merge(\App\DTOs\Lead\ImportSettingsDto::defaults(), $this->settings ?? []);
    }
}
