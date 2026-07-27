<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCampaignRecipient extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'user_id',
        'phone_number',
        'contact_name',
        'status',
        'message_id',
        'variables_used',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failed_reason',
        'retry_count',
    ];

    protected $casts = [
        'variables_used' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Mark recipient as sent.
     */
    public function markAsSent(string $messageId): void
    {
        $this->update([
            'message_id' => $messageId,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark recipient as failed.
     */
    public function markAsFailed(string $reason = ''): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failed_reason' => $reason,
        ]);
    }

    /**
     * Get display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->contact_name ?? $this->user?->name ?? $this->phone_number;
    }
}
