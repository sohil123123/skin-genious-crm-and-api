<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallMatchingMethod;
use App\Enums\Call\CallMatchingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallStatus;
use App\Enums\Call\CallAnalysisStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One real-world conversation, whichever telephony system observed it.
 *
 * Everything provider-specific has already been resolved by the time a row
 * exists here, which is the point: the customer timeline, the action queues and
 * the analytics all read this model and none of them contains the word Exotel
 * or Callyzer.
 *
 * The model deliberately holds no provider logic. Mapping a Callyzer call_type
 * to a direction belongs in that provider's mapper, not in an accessor here,
 * because the moment it lives on the model every future provider has to be
 * taught about the previous ones.
 *
 * @property-read string $uuid
 */
class Call extends Model
{
    use HasAuditColumns, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid',
        'clinic_id',
        'provider',
        'provider_call_id',
        'provider_reference_id',
        'provider_parent_call_id',
        'provider_event_id',
        'source',
        'direction',
        'call_status',
        'is_connected',
        'provider_direction',
        'provider_call_status',
        'disposition',
        'hangup_cause_code',
        'customer_user_id',
        'lead_id',
        'client_name',
        'client_country_code',
        'client_phone',
        'client_phone_normalized',
        'client_phone_key',
        'agent_user_id',
        'assigned_user_id',
        'call_provider_agent_id',
        'employee_name',
        'employee_code',
        'employee_country_code',
        'employee_phone',
        'employee_phone_normalized',
        'employee_phone_key',
        'virtual_number',
        'virtual_number_normalized',
        'matching_status',
        'matching_method',
        'matched_at',
        'matched_by',
        'match_candidates',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'ring_duration_seconds',
        'talk_duration_seconds',
        'hold_duration_seconds',
        'wait_duration_seconds',
        'timezone',
        'provider_started_at_raw',
        'provider_answered_at_raw',
        'provider_ended_at_raw',
        'provider_note',
        'crm_note',
        'ai_summary',
        'provider_crm_status',
        'crm_outcome',
        'provider_lead_id',
        'provider_reminder_at',
        'follow_up_required',
        'follow_up_at',
        'follow_up_reason',
        'follow_up_completed_at',
        'has_recording',
        'recording_count',
        'recording_status',
        'transcription_status',
        'analysis_status',
        'call_method',
        'call_mode',
        'provider_data',
        'first_seen_at',
        'last_event_at',
        'event_count',
        'last_source',
        'last_processing_status',
        'last_error',
        'provider_synced_at',
        'provider_modified_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CallProvider::class,
            'source' => CallSource::class,
            'last_source' => CallSource::class,
            'direction' => CallDirection::class,
            'call_status' => CallStatus::class,
            'matching_status' => CallMatchingStatus::class,
            'matching_method' => CallMatchingMethod::class,
            'recording_status' => RecordingStorageStatus::class,
            'transcription_status' => TranscriptionStatus::class,
            'analysis_status' => CallAnalysisStatus::class,
            'is_connected' => 'boolean',
            'has_recording' => 'boolean',
            'follow_up_required' => 'boolean',
            'match_candidates' => 'array',
            'provider_data' => 'array',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'matched_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_event_at' => 'datetime',
            'provider_synced_at' => 'datetime',
            'provider_modified_at' => 'datetime',
            'provider_reminder_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'follow_up_completed_at' => 'datetime',
            'duration_seconds' => 'integer',
            'ring_duration_seconds' => 'integer',
            'talk_duration_seconds' => 'integer',
            'hold_duration_seconds' => 'integer',
            'wait_duration_seconds' => 'integer',
            'event_count' => 'integer',
            'recording_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $call): void {
            $call->uuid ??= (string) Str::uuid();
            $call->first_seen_at ??= now();
        });
    }

    /**
     * Only CRM-owned fields are logged.
     *
     * Provider columns change on every re-sync and would bury the handful of
     * changes a human actually made — which is the only thing anyone opens an
     * activity log to find. Phone numbers are excluded because the log is
     * visible more widely than the call record itself.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'customer_user_id',
                'lead_id',
                'agent_user_id',
                'assigned_user_id',
                'matching_status',
                'crm_note',
                'crm_outcome',
                'follow_up_required',
                'follow_up_at',
                'follow_up_completed_at',
            ])
            ->logOnlyDirty()
            ->useLogName('call');
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * The patient this call was with.
     *
     * Named customer rather than user because "user" in this application means
     * both patients and staff, and a call has one of each.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function providerAgent(): BelongsTo
    {
        return $this->belongsTo(CallProviderAgent::class, 'call_provider_agent_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(CallRecording::class);
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(CallTranscription::class);
    }

    /**
     * The transcript the CRM should show, of possibly several.
     */
    public function currentTranscription(): HasOne
    {
        return $this->hasOne(CallTranscription::class)->where('is_current', true);
    }

    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(CallTranscriptSegment::class)->orderBy('sequence');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(CallAnalysis::class);
    }

    public function currentAnalysis(): HasOne
    {
        return $this->hasOne(CallAnalysis::class)->where('is_current', true);
    }

    public function payloads(): HasMany
    {
        return $this->hasMany(CallProviderPayload::class)->latest('received_at');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(CallWebhookEvent::class)->latest('received_at');
    }

    // ──────────────── Scopes ────────────────

    /**
     * Restrict to the authenticated user's clinic, matching the convention used
     * by Lead and the other clinic-owned models.
     *
     * Calls with no clinic are visible only to a super admin: an unattributed
     * call is a configuration problem, not any one clinic's conversation.
     */
    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        return $query->where('clinic_id', auth()->user()->clinic_id);
    }

    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', CallDirection::Incoming->value);
    }

    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->where('direction', CallDirection::Outgoing->value);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('is_connected', true);
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->whereIn('call_status', CallStatus::unansweredValues());
    }

    public function scopeMissed(Builder $query): Builder
    {
        return $query->where('call_status', CallStatus::Missed->value);
    }

    public function scopeOfProvider(Builder $query, CallProvider|string $provider): Builder
    {
        return $query->where('provider', $provider instanceof CallProvider ? $provider->value : $provider);
    }

    /**
     * Calls whose customer could not be determined automatically.
     */
    public function scopeNeedsMatching(Builder $query): Builder
    {
        return $query->whereIn('matching_status', [
            CallMatchingStatus::Unmatched->value,
            CallMatchingStatus::Ambiguous->value,
        ]);
    }

    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->where('follow_up_required', true)->whereNull('follow_up_completed_at');
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('started_at', [$from, $to]);
    }

    /**
     * Every call with a given person, whether they are a patient or a lead.
     *
     * The phone key is included on purpose: a call that arrived before the
     * person existed in the CRM is still their call, and a timeline that
     * silently omitted it would be worse than useless.
     */
    public function scopeForCustomer(Builder $query, ?int $userId, ?int $leadId = null, ?string $phoneKey = null): Builder
    {
        return $query->where(function (Builder $inner) use ($userId, $leadId, $phoneKey): void {
            if ($userId !== null) {
                $inner->orWhere('customer_user_id', $userId);
            }

            if ($leadId !== null) {
                $inner->orWhere('lead_id', $leadId);
            }

            if (filled($phoneKey)) {
                $inner->orWhere('client_phone_key', $phoneKey);
            }
        });
    }

    // ──────────────── Presentation ────────────────

    /**
     * The best name available for whoever was on the other end.
     *
     * Falls through CRM record, then provider-supplied name, then the number
     * itself — an unmatched call still has to render as something a human can
     * recognise and act on.
     */
    public function getCustomerNameAttribute(): string
    {
        if ($this->customer !== null) {
            return $this->customer->name;
        }

        if ($this->lead !== null) {
            return $this->lead->display_name;
        }

        return $this->client_name
            ?: $this->client_phone_normalized
            ?: $this->client_phone
            ?: 'Unknown caller';
    }

    public function getAgentNameAttribute(): ?string
    {
        return $this->agent?->name ?: $this->employee_name;
    }

    /**
     * Duration as "4m 32s", the form every call list in the world uses.
     */
    public function getDurationForHumansAttribute(): ?string
    {
        $seconds = $this->talk_duration_seconds ?? $this->duration_seconds;

        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        if ($minutes < 60) {
            return $remainder === 0 ? $minutes . 'm' : sprintf('%dm %ds', $minutes, $remainder);
        }

        return sprintf('%dh %dm', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Whether a person is attached, by either automatic or manual matching.
     */
    public function isMatched(): bool
    {
        return $this->customer_user_id !== null || $this->lead_id !== null;
    }

    public function hasTranscript(): bool
    {
        return $this->transcription_status === TranscriptionStatus::Completed;
    }

    public function hasAnalysis(): bool
    {
        return $this->analysis_status === CallAnalysisStatus::Completed;
    }

    /**
     * Recalculate the denormalised pipeline flags from the child rows.
     *
     * Called after recordings, transcriptions or analyses change. They exist so
     * the call list can filter on "has a recording" without a subquery per row,
     * and they are always rebuildable from the children — if they ever disagree,
     * the children are right.
     */
    public function refreshPipelineFlags(): void
    {
        $recordings = $this->recordings()->get();

        $this->has_recording = $recordings->isNotEmpty();
        $this->recording_count = $recordings->count();

        $this->recording_status = match (true) {
            $recordings->isEmpty() => null,
            $recordings->contains(fn (CallRecording $r): bool => $r->storage_status === RecordingStorageStatus::Stored) => RecordingStorageStatus::Stored,
            $recordings->contains(fn (CallRecording $r): bool => $r->storage_status === RecordingStorageStatus::Downloading) => RecordingStorageStatus::Downloading,
            $recordings->contains(fn (CallRecording $r): bool => $r->storage_status === RecordingStorageStatus::Failed) => RecordingStorageStatus::Failed,
            $recordings->contains(fn (CallRecording $r): bool => $r->storage_status === RecordingStorageStatus::Purged) => RecordingStorageStatus::Purged,
            default => RecordingStorageStatus::RemoteOnly,
        };

        // A call is transcribed if any of its recordings is. A legged call
        // whose first leg is a 3-second greeting should not report itself as
        // untranscribed because that greeting was skipped.
        $this->transcription_status = match (true) {
            $recordings->isEmpty() => TranscriptionStatus::NotAvailable,
            $recordings->contains(fn (CallRecording $r): bool => $r->transcription_status === TranscriptionStatus::Completed) => TranscriptionStatus::Completed,
            $recordings->contains(fn (CallRecording $r): bool => $r->transcription_status === TranscriptionStatus::Processing) => TranscriptionStatus::Processing,
            $recordings->contains(fn (CallRecording $r): bool => $r->transcription_status === TranscriptionStatus::Pending) => TranscriptionStatus::Pending,
            $recordings->contains(fn (CallRecording $r): bool => $r->transcription_status === TranscriptionStatus::Failed) => TranscriptionStatus::Failed,
            default => TranscriptionStatus::NotAvailable,
        };

        $this->saveQuietly();
    }
}
