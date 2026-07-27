<?php

namespace App\Models;

use App\Models\User;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'message_id',
        'direction',
        'type',
        'content',
        'template_name',
        'template_variables',
        'status',
        'meta_timestamp',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failed_reason',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'media_id',
        'media_url',
        'media_mime_type',
        'media_filename',
        'media_sha256',
        'local_media_path',
        'context_message_id',
        'campaign_id',
    ];

    protected $casts = [
        'content' => 'array',
        'template_variables' => 'array',
        'direction' => WhatsAppMessageDirection::class,
        'type' => WhatsAppMessageType::class,
        'status' => WhatsAppMessageStatus::class,
        'meta_timestamp' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeIncoming($query)
    {
        return $query->where('direction', WhatsAppMessageDirection::Incoming);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', WhatsAppMessageDirection::Outgoing);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', WhatsAppMessageStatus::Failed);
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', WhatsAppMessageStatus::Failed)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeSent($query)
    {
        return $query->whereIn('status', [WhatsAppMessageStatus::Sent, WhatsAppMessageStatus::Delivered, WhatsAppMessageStatus::Read]);
    }

    public function scopeDelivered($query)
    {
        return $query->whereIn('status', [WhatsAppMessageStatus::Delivered, WhatsAppMessageStatus::Read]);
    }

    public function scopeRead($query)
    {
        return $query->where('status', WhatsAppMessageStatus::Read);
    }

    public function scopePending($query)
    {
        return $query->where('status', WhatsAppMessageStatus::Pending);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeMedia($query)
    {
        return $query->whereIn('type', [
            WhatsAppMessageType::Image,
            WhatsAppMessageType::Video,
            WhatsAppMessageType::Document,
            WhatsAppMessageType::Audio,
            WhatsAppMessageType::Sticker,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the text body of the message.
     */
    public function getTextBodyAttribute(): ?string
    {
        $content = $this->content;

        if (!is_array($content)) {
            return null;
        }

        return $content['body'] ?? $content['caption'] ?? $content['text'] ?? null;
    }

    /**
     * Check if this message has media content.
     */
    public function hasMedia(): bool
    {
        return $this->type?->isMedia() ?? false;
    }

    /**
     * Check if media has been downloaded locally.
     */
    public function isMediaDownloaded(): bool
    {
        return !empty($this->local_media_path);
    }

    /**
     * Get the display URL for media (local if downloaded, Meta URL otherwise).
     */
    public function getMediaDisplayUrlAttribute(): ?string
    {
        if ($this->local_media_path) {
            return asset('storage/' . $this->local_media_path);
        }

        return $this->media_url;
    }

    /**
     * Check if this message can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === WhatsAppMessageStatus::Failed
            && $this->retry_count < $this->max_retries;
    }

    /**
     * Calculate the next retry delay based on retry count.
     * Retry 1: 5 minutes, Retry 2: 30 minutes, Retry 3: 1 hour
     */
    public function getNextRetryDelay(): int
    {
        return match ($this->retry_count) {
            0 => 5,   // 5 minutes
            1 => 30,  // 30 minutes
            2 => 60,  // 1 hour
            default => 60,
        };
    }

    /**
     * Mark message as sent.
     */
    public function markAsSent(string $messageId): void
    {
        $this->update([
            'message_id' => $messageId,
            'status' => WhatsAppMessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark message as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => WhatsAppMessageStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => WhatsAppMessageStatus::Read,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark message as failed.
     */
    public function markAsFailed(string $reason = ''): void
    {
        $updates = [
            'status' => WhatsAppMessageStatus::Failed,
            'failed_at' => now(),
            'failed_reason' => $reason,
        ];

        if ($this->canRetry()) {
            $updates['next_retry_at'] = now()->addMinutes($this->getNextRetryDelay());
        }

        $this->update($updates);
    }

    /**
     * Get status icon for chat display (WhatsApp-style ticks).
     */
    public function getStatusTicksAttribute(): string
    {
        return match ($this->status) {
            WhatsAppMessageStatus::Pending => '🕐',
            WhatsAppMessageStatus::Sent => '✓',
            WhatsAppMessageStatus::Delivered => '✓✓',
            WhatsAppMessageStatus::Read => '✓✓',  // blue ticks shown via CSS
            WhatsAppMessageStatus::Failed => '⚠️',
            default => '',
        };
    }

    /**
     * Check if this is a reply to another message.
     */
    public function isReply(): bool
    {
        return !empty($this->context_message_id);
    }

    /**
     * Get the original message this is replying to.
     */
    public function getReplyToMessage(): ?self
    {
        if (!$this->isReply()) {
            return null;
        }

        return self::where('message_id', $this->context_message_id)->first();
    }
}
