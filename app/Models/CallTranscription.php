<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\TranscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The text of one recorded conversation, from one transcription attempt.
 *
 * Several may exist per call — a re-run with a better model, or a Gujarati call
 * transcribed again with the language pinned. Exactly one carries is_current,
 * and that is the one every CRM screen reads.
 */
class CallTranscription extends Model
{
    protected $fillable = [
        'call_id',
        'call_recording_id',
        'provider',
        'model',
        'version',
        'language',
        'language_code',
        'transcript',
        'transcript_json',
        'confidence',
        'duration_seconds',
        'word_count',
        'status',
        'error_message',
        'is_current',
        'started_at',
        'completed_at',
        'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'status' => TranscriptionStatus::class,
            'transcript_json' => 'array',
            'is_current' => 'boolean',
            'confidence' => 'float',
            'duration_seconds' => 'integer',
            'word_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(CallRecording::class, 'call_recording_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(CallTranscriptSegment::class)->orderBy('sequence');
    }

    // ──────────────── Scopes ────────────────

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TranscriptionStatus::Completed->value);
    }

    public function scopeDuePurge(Builder $query): Builder
    {
        return $query->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->whereNotNull('transcript');
    }

    // ──────────────── State ────────────────

    /**
     * Make this the transcript the CRM shows, demoting any previous one.
     *
     * Done as a single update rather than a loop so two transcriptions
     * finishing at once cannot both end up current.
     */
    public function makeCurrent(): void
    {
        static::query()
            ->where('call_id', $this->call_id)
            ->whereKeyNot($this->getKey())
            ->update(['is_current' => false]);

        $this->forceFill(['is_current' => true])->save();
    }

    public function hasText(): bool
    {
        return filled($this->transcript);
    }
}
