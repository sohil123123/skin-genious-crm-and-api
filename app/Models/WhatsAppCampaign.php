<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'name',
        'description',
        'template_id',
        'template_variables',
        'audience_type',
        'audience_filter',
        'audience_user_ids',
        'status',
        'scheduled_at',
        'timezone',
        'is_recurring',
        'recurrence_rule',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'template_variables' => 'array',
        'audience_filter' => 'array',
        'audience_user_ids' => 'array',
        'status' => WhatsAppCampaignStatus::class,
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_recurring' => 'boolean',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'read_count' => 'integer',
        'failed_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeDraft($query)
    {
        return $query->where('status', WhatsAppCampaignStatus::Draft);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', WhatsAppCampaignStatus::Scheduled);
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', WhatsAppCampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now());
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the campaign progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        $processed = $this->sent_count + $this->failed_count;

        return round(($processed / $this->total_recipients) * 100, 1);
    }

    /**
     * Get the message success rate.
     */
    public function getSuccessRate(): float
    {
        $total = $this->sent_count + $this->failed_count;

        if ($total === 0) {
            return 0;
        }

        return round(($this->sent_count / $total) * 100, 1);
    }

    /**
     * Get the delivery rate.
     */
    public function getDeliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / $this->sent_count) * 100, 1);
    }

    /**
     * Get the read rate.
     */
    public function getReadRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }

        return round(($this->read_count / $this->delivered_count) * 100, 1);
    }

    /**
     * Check if the campaign can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status === WhatsAppCampaignStatus::Draft;
    }

    /**
     * Check if the campaign can be cancelled.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [
            WhatsAppCampaignStatus::Draft,
            WhatsAppCampaignStatus::Scheduled,
            WhatsAppCampaignStatus::Sending,
            WhatsAppCampaignStatus::Paused,
        ]);
    }

    /**
     * Mark campaign as sending.
     */
    public function markAsSending(): void
    {
        $this->update([
            'status' => WhatsAppCampaignStatus::Sending,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    /**
     * Mark campaign as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => WhatsAppCampaignStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Refresh campaign counters from recipients.
     */
    public function refreshCounts(): void
    {
        $this->update([
            'sent_count' => $this->recipients()->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered_count' => $this->recipients()->whereIn('status', ['delivered', 'read'])->count(),
            'read_count' => $this->recipients()->where('status', 'read')->count(),
            'failed_count' => $this->recipients()->where('status', 'failed')->count(),
        ]);
    }
}
