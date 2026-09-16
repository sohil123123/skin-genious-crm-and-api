<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Clinic;
use App\Models\User;
use App\Models\Appointment;
use App\Models\UserPackage;
use App\Models\Assessment;

use Carbon\Carbon;

class AiActionLog extends Model
{
    protected $fillable = [
        'clinic_id',
        'user_id',
        'action_category',
        'action_trigger',
        'priority_score',
        'recommended_channel',
        'recommended_time',
        'reason',
        'suggested_message',
        'goal',
        'slots_to_offer',
        'avoid_notes',
        'assigned_to',
        'related_call_id',
        'call_signals',
        'related_appointment_id',
        'related_package_id',
        'related_assessment_id',
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
        'slots_to_offer' => 'array',
        'expires_at' => 'datetime',
        'outcome_at' => 'datetime',
        'generated_date' => 'date',
        'is_active' => 'boolean',
        'priority_score' => 'integer',
    ];

    // ──────────────── Constants ────────────────

    /** Broad action categories */
    public const CATEGORY_RESCUE = 'rescue';
    public const CATEGORY_CONVERSION = 'conversion';
    public const CATEGORY_RETENTION = 'retention';
    public const CATEGORY_CAPACITY = 'capacity';

    /** Available staff outcomes */
    public const OUTCOME_CALLED = 'called';
    public const OUTCOME_NO_ANSWER = 'no_answer';
    public const OUTCOME_WHATSAPP_SENT = 'whatsapp_sent';
    public const OUTCOME_BOOKED = 'booked';
    public const OUTCOME_NOT_INTERESTED = 'not_interested';
    public const OUTCOME_CALL_LATER = 'call_later';
    public const OUTCOME_WRONG_RECOMMENDATION = 'wrong_recommendation';
    public const OUTCOME_DO_NOT_CONTACT = 'do_not_contact';
    public const OUTCOME_OTHER = 'other';

    /**
     * All valid staff outcome options for UI rendering.
     */
    public static function outcomeOptions(): array
    {
        return [
            self::OUTCOME_CALLED => 'Called',
            self::OUTCOME_NO_ANSWER => 'No Answer',
            self::OUTCOME_WHATSAPP_SENT => 'WhatsApp Sent',
            self::OUTCOME_BOOKED => 'Booked',
            self::OUTCOME_NOT_INTERESTED => 'Not Interested',
            self::OUTCOME_CALL_LATER => 'Call Later',
            self::OUTCOME_WRONG_RECOMMENDATION => 'Wrong Recommendation',
            self::OUTCOME_DO_NOT_CONTACT => 'Do Not Contact',
            self::OUTCOME_OTHER => 'Other',
        ];
    }

    /**
     * Category display labels.
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_RESCUE => 'Rescue',
            self::CATEGORY_CONVERSION => 'Conversion',
            self::CATEGORY_RETENTION => 'Retention',
            self::CATEGORY_CAPACITY => 'Capacity',
        ];
    }

    /**
     * Category badge colors (Filament-compatible).
     */
    public static function categoryColors(): array
    {
        return [
            self::CATEGORY_RESCUE => 'danger',
            self::CATEGORY_CONVERSION => 'warning',
            self::CATEGORY_RETENTION => 'info',
            self::CATEGORY_CAPACITY => 'success',
        ];
    }

    /**
     * Get the Filament-style color for the priority score.
     */
    public function getPriorityColorAttribute(): string
    {
        if ($this->priority_score >= 80) {
            return 'danger';
        }

        if ($this->priority_score >= 60) {
            return 'warning';
        }

        if ($this->priority_score >= 40) {
            return 'info';
        }

        return 'gray';
    }

    /**
     * Get a human-readable label for the action trigger.
     */
    public function getTriggerLabelAttribute(): string
    {
        $labels = [
            'cancelled_not_rebooked' => 'Cancelled — Not Rebooked',
            'no_show_not_rebooked' => 'No-Show — Not Rebooked',
            'same_day_slot_fill' => 'Same-Day Slot Fill',
            'scan_no_treatment' => 'Scan Done — No Treatment',
            'consultation_no_treatment' => 'Consult Done — No Treatment',
            'package_overdue' => 'Package Session Overdue',
            'treatment_plan_dropoff' => 'Treatment Plan Drop-Off',
            'package_nearing_exhaustion' => 'Package Nearing Exhaustion',
            'tomorrows_risk_list' => 'Tomorrow — At-Risk Appointment',
            'maintenance_due' => 'Maintenance Now Due',
        ];

        return $labels[$this->action_trigger] ?? ucwords(str_replace('_', ' ', $this->action_trigger));
    }

    /**
     * Get the category badge label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::categoryLabels()[$this->action_category] ?? ucfirst($this->action_category);
    }

    /**
     * Get the category badge color.
     */
    public function getCategoryColorAttribute(): string
    {
        return self::categoryColors()[$this->action_category] ?? 'gray';
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'related_appointment_id');
    }

    public function relatedPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'related_package_id');
    }

    public function relatedAssessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'related_assessment_id');
    }

    public function outcomeAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'outcome_appointment_id');
    }
}
