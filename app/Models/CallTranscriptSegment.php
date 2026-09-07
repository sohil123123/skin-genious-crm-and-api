<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\TranscriptSpeakerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attributed, timed piece of a transcript.
 */
class CallTranscriptSegment extends Model
{
    protected $fillable = [
        'call_transcription_id',
        'call_id',
        'sequence',
        'speaker',
        'speaker_type',
        'start_seconds',
        'end_seconds',
        'text',
        'confidence',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'speaker_type' => TranscriptSpeakerType::class,
            'metadata' => 'array',
            'sequence' => 'integer',
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'confidence' => 'float',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function transcription(): BelongsTo
    {
        return $this->belongsTo(CallTranscription::class, 'call_transcription_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeSpokenBy(Builder $query, TranscriptSpeakerType|string $type): Builder
    {
        return $query->where('speaker_type', $type instanceof TranscriptSpeakerType ? $type->value : $type);
    }

    // ──────────────── Presentation ────────────────

    /**
     * Start offset as "1:24", for a transcript that can be clicked to seek.
     */
    public function getTimestampLabelAttribute(): ?string
    {
        if ($this->start_seconds === null) {
            return null;
        }

        $seconds = (int) round($this->start_seconds);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
