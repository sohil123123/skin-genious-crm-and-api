<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallAnalysisStatus;
use App\Enums\Call\CallSentiment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What AI concluded about one call, at one prompt version.
 *
 * Versioned rather than overwritten so a new prompt can be compared against the
 * old one before it is trusted. is_current marks the interpretation the CRM
 * shows; the rest are kept for exactly that comparison.
 */
class CallAnalysis extends Model
{
    protected $table = 'call_analyses';

    protected $fillable = [
        'call_id',
        'call_transcription_id',
        'analysis_version',
        'provider',
        'model',
        'status',
        'is_current',
        'summary',
        'customer_intent',
        'call_reason',
        'outcome',
        'sentiment',
        'sentiment_score',
        'urgency',
        'lead_temperature',
        'purchase_intent',
        'objection',
        'product_interest',
        'treatment_interest',
        'price_discussed',
        'appointment_discussed',
        'appointment_booked',
        'follow_up_required',
        'follow_up_reason',
        'next_best_action',
        'next_best_action_priority',
        'ai_confidence',
        'structured_result',
        'error_message',
        'input_tokens',
        'output_tokens',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallAnalysisStatus::class,
            'sentiment' => CallSentiment::class,
            'structured_result' => 'array',
            'is_current' => 'boolean',
            'price_discussed' => 'boolean',
            'appointment_discussed' => 'boolean',
            'appointment_booked' => 'boolean',
            'follow_up_required' => 'boolean',
            'sentiment_score' => 'float',
            'ai_confidence' => 'float',
            'purchase_intent' => 'integer',
            'next_best_action_priority' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ──────────────── Relationships ────────────────

    /**
     * The structured signals this analysis produced.
     *
     * The summary is for a person; these are for the action engines. Both come
     * from the same model response, and both are kept — a signal without the
     * sentence it came from is unarguable when somebody disputes it.
     */
    public function signals(): HasMany
    {
        return $this->hasMany(CallInsightSignal::class, 'call_analysis_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function transcription(): BelongsTo
    {
        return $this->belongsTo(CallTranscription::class, 'call_transcription_id');
    }

    // ──────────────── Scopes ────────────────

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', CallAnalysisStatus::Completed->value);
    }

    public function scopeOfVersion(Builder $query, string $version): Builder
    {
        return $query->where('analysis_version', $version);
    }

    // ──────────────── State ────────────────

    public function makeCurrent(): void
    {
        static::query()
            ->where('call_id', $this->call_id)
            ->whereKeyNot($this->getKey())
            ->update(['is_current' => false]);

        $this->forceFill(['is_current' => true])->save();
    }
}
