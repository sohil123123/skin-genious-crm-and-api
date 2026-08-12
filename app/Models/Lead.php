<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\PhoneStatus;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lead extends Model
{
    use HasAuditColumns, LogsActivity, SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'lead_import_id',
        'full_name',
        'first_name',
        'last_name',
        'phone',
        'phone_raw',
        'phone_status',
        'email',
        'email_raw',
        'city',
        'state',
        'pincode',
        'source',
        'status',
        'assigned_to',
        'matched_user_id',
        'notes',
        'fb_lead_id',
        'fb_created_time',
        'campaign_name',
        'campaign_id',
        'adset_name',
        'adset_id',
        'ad_name',
        'ad_id',
        'form_name',
        'form_id',
        'page_name',
        'page_id',
        'platform',
        'is_organic',
        'fb_lead_status',
        'raw_payload',
        'row_hash',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'phone_status' => PhoneStatus::class,
            'fb_created_time' => 'datetime',
            'is_organic' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('lead');
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class, 'lead_import_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The existing patient this lead appears to be, when one was found.
     *
     * Set during import as a flag only — the lead is never merged into the
     * patient record automatically.
     */
    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(LeadFieldValue::class);
    }

    /**
     * Next Best Action entries generated for this lead.
     */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(LeadActionLog::class);
    }

    // ──────────────── Scopes ────────────────

    /**
     * Restrict to the authenticated user's clinic, matching the convention used
     * by Expense and the other clinic-owned models.
     */
    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        return $query->where('clinic_id', auth()->user()->clinic_id);
    }

    public function scopeOfStatus(Builder $query, LeadStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof LeadStatus ? $status->value : $status);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Leads whose phone number was salvaged from a malformed value and still
     * needs a human to confirm it.
     */
    public function scopeNeedsPhoneReview(Builder $query): Builder
    {
        return $query->where('phone_status', PhoneStatus::NeedsReview->value);
    }

    public function scopeMatchingExistingPatient(Builder $query): Builder
    {
        return $query->whereNotNull('matched_user_id');
    }

    /**
     * Match on the answer to a specific dynamic question.
     */
    public function scopeWhereCustomField(Builder $query, string $key, string $value): Builder
    {
        return $query->whereHas(
            'fieldValues',
            fn (Builder $inner): Builder => $inner
                ->whereHas('customField', fn (Builder $field): Builder => $field->where('key', $key))
                ->where(fn (Builder $match): Builder => $match
                    ->where('value_normalized', $value)
                    // Multi-answer rows keep every choice in value_json, so an
                    // exact column match alone would miss them.
                    ->orWhere('value', 'like', '%' . $value . '%'))
        );
    }

    // ──────────────── Accessors ────────────────

    /**
     * Display name, falling back to the phone number for anonymous submissions.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: ($this->phone ?: 'Unnamed Lead');
    }

    /**
     * Answers keyed by custom field label, for the lead detail screen.
     *
     * @return array<string, array{label: string, value: string, values: array<int, string>, type: string}>
     */
    public function getCustomAnswersAttribute(): array
    {
        return $this->fieldValues
            ->filter(fn (LeadFieldValue $fieldValue): bool => $fieldValue->customField !== null)
            ->sortBy(fn (LeadFieldValue $fieldValue): int => $fieldValue->customField->sort_order)
            ->mapWithKeys(fn (LeadFieldValue $fieldValue): array => [
                $fieldValue->customField->key => [
                    'label' => $fieldValue->customField->display_label,
                    'value' => (string) $fieldValue->value_normalized,
                    'values' => $fieldValue->display_values,
                    'type' => $fieldValue->customField->type->value,
                ],
            ])
            ->all();
    }

    /**
     * Build the identity hash used to spot repeats inside a single file.
     */
    public static function buildRowHash(?string $fbLeadId, ?string $phone, ?string $email): string
    {
        return hash('sha256', Str::lower(implode('|', [
            (string) $fbLeadId,
            (string) $phone,
            (string) $email,
        ])));
    }
}
