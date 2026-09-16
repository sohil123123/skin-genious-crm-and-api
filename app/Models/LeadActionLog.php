<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Next Best Action targeting an imported Meta lead.
 *
 * Mirrors AiActionLog's public surface — same category constants, same outcome
 * options, same accessor names — so one shared card view renders either model
 * and staff see one consistent interface across both queues.
 */
class LeadActionLog extends Model
{
    protected $fillable = [
        'clinic_id',
        'lead_id',
        'matched_user_id',
        'action_category',
        'action_trigger',
        'priority_score',
        'recommended_channel',
        'recommended_time',
        'reason',
        'suggested_message',
        'goal',
        'avoid_notes',
        'assigned_to',
        'related_call_id',
        'call_signals',
        'expires_at',
        'staff_outcome',
        'outcome_notes',
        'outcome_at',
        'outcome_appointment_id',
        'generated_date',
        'is_active',
    ];

    protected $casts = [
        'call_signals' => 'array',
        'expires_at' => 'datetime',
        'outcome_at' => 'datetime',
        'generated_date' => 'date',
        'is_active' => 'boolean',
        'priority_score' => 'integer',
    ];

    // ──────────────── Constants ────────────────

    /** Shared with AiActionLog so both queues colour and label alike. */
    public const CATEGORY_CONVERSION = 'conversion';
    public const CATEGORY_RETENTION = 'retention';

    /** Lead-specific triggers. */
    public const TRIGGER_HOT_INTENT = 'lead_hot_intent';
    public const TRIGGER_NEVER_CONTACTED = 'lead_never_contacted';
    public const TRIGGER_EXISTING_PATIENT = 'lead_existing_patient';
    public const TRIGGER_STALLED = 'lead_stalled';

    /**
     * Something the lead asked for on a call and has not received.
     *
     * The only lead trigger that comes from a conversation rather than from
     * dates and form fields, which is why it outranks them: a form says what
     * somebody wanted when they filled it in, a call is them saying it now.
     */
    public const TRIGGER_CALL_COMMITMENT = 'lead_call_commitment';

    /**
     * Outcome options, identical to the patient queue so staff do not have to
     * learn a second vocabulary.
     *
     * @return array<string, string>
     */
    public static function outcomeOptions(): array
    {
        return AiActionLog::outcomeOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_CONVERSION => 'Conversion',
            self::CATEGORY_RETENTION => 'Retention',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryColors(): array
    {
        return [
            self::CATEGORY_CONVERSION => 'warning',
            self::CATEGORY_RETENTION => 'info',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return [
            self::TRIGGER_HOT_INTENT => 'Wants to Visit Now',
            self::TRIGGER_NEVER_CONTACTED => 'New — Never Contacted',
            self::TRIGGER_EXISTING_PATIENT => 'Existing Patient Enquired',
            self::TRIGGER_STALLED => 'Contacted — No Booking',
        ];
    }

    // ──────────────── Accessors ────────────────

    public function getPriorityColorAttribute(): string
    {
        return match (true) {
            $this->priority_score >= 80 => 'danger',
            $this->priority_score >= 60 => 'warning',
            $this->priority_score >= 40 => 'info',
            default => 'gray',
        };
    }

    public function getTriggerLabelAttribute(): string
    {
        return self::triggerLabels()[$this->action_trigger]
            ?? ucwords(str_replace('_', ' ', (string) $this->action_trigger));
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categoryLabels()[$this->action_category] ?? ucfirst((string) $this->action_category);
    }

    public function getCategoryColorAttribute(): string
    {
        return self::categoryColors()[$this->action_category] ?? 'gray';
    }

    /**
     * The shared action card reads `client` for the person's name.
     *
     * Exposing the lead under that name lets one partial serve both queues
     * without a per-model branch for something as basic as a display name.
     */
    public function getClientAttribute(): ?Lead
    {
        return $this->lead;
    }

    // ──────────────── Scopes ────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForToday(Builder $query): Builder
    {
        return $query->where('generated_date', Carbon::today());
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('generated_date', $date);
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('staff_outcome');
    }

    public function scopeWithOutcome(Builder $query): Builder
    {
        return $query->whereNotNull('staff_outcome');
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('action_category', $category);
    }

    public function scopeHighPriority(Builder $query, int $threshold = 70): Builder
    {
        return $query->where('priority_score', '>=', $threshold);
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * The call that raised this action, where one did.
     *
     * Loaded with its current analysis because the card shows what the model
     * made of the conversation, not just that a call happened: a staff member
     * about to ring somebody back needs the summary and the objection, and
     * sending them to the call page to read it is a page load in the middle of
     * a queue they are working through.
     */
    public function relatedCall(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Call::class, 'related_call_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function outcomeAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'outcome_appointment_id');
    }
}
