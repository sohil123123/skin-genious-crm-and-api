<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallSignalKey;
use App\Enums\Call\CallSignalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a call told the CRM about a patient or a lead.
 *
 * Written by the analyser, read by the Next Best Action engines, and never
 * updated: a signal is a statement about a moment, and the moment does not
 * change. What changes is how much that moment is worth today, which the
 * engines decide from occurred_at rather than by editing the row.
 *
 * @property-read CallSignalKey $signal_key
 */
class CallInsightSignal extends Model
{
    protected $fillable = [
        'call_analysis_id',
        'call_id',
        'clinic_id',
        'customer_user_id',
        'lead_id',
        'signal_type',
        'signal_key',
        'value',
        'confidence',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'signal_type' => CallSignalType::class,
            'signal_key' => CallSignalKey::class,
            'confidence' => 'float',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CallAnalysis::class, 'call_analysis_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // ──────────────── Scopes ────────────────

    /**
     * Signals recent enough to still mean something.
     *
     * The engines call this before scoring. A price objection from six months
     * ago is history, not a reason to send someone a payment plan today, and
     * the window is where that judgement lives.
     */
    public function scopeRecent(Builder $query, int $days): Builder
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    /**
     * Signals the engine is allowed to act on.
     *
     * A model that is unsure is worse than silent here, because the output is
     * an instruction to ring a patient. Rows below the threshold are kept —
     * they are still evidence, and still shown on the call — but they do not
     * move a score.
     */
    public function scopeConfident(Builder $query, float $minimum): Builder
    {
        return $query->where(fn (Builder $inner): Builder => $inner
            ->whereNull('confidence')
            ->orWhere('confidence', '>=', $minimum));
    }

    public function scopeForPatient(Builder $query, int $userId): Builder
    {
        return $query->where('customer_user_id', $userId);
    }

    public function scopeForLead(Builder $query, int $leadId): Builder
    {
        return $query->where('lead_id', $leadId);
    }

    /**
     * How much weight this signal still carries, given its age.
     *
     * Linear decay to zero across the window, floored at nothing rather than
     * going negative. Deliberately simple: the engines already multiply five
     * factors together, and a second exponential in the middle of that would be
     * impossible to reason about when a score looks wrong.
     */
    public function weight(int $windowDays): float
    {
        if ($this->occurred_at === null || $windowDays < 1) {
            return 0.0;
        }

        $ageInDays = max(0, $this->occurred_at->diffInDays(now()));
        $freshness = max(0.0, 1.0 - ($ageInDays / $windowDays));

        // A signal with no stated confidence is treated as fully confident: the
        // analyser only omits it when the provider returned nothing to report,
        // and discarding those would throw away most of the older analyses.
        return $freshness * ($this->confidence ?? 1.0);
    }
}
