<?php

namespace App\Models;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'user_id',
        'phone_number',
        'contact_name',
        'profile_picture_url',
        'last_message_at',
        'last_message_preview',
        'is_window_open',
        'window_expires_at',
        'unread_count',
        'is_starred',
        'labels',
        'assigned_to',
        'is_archived',
        'is_muted',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'window_expires_at' => 'datetime',
        'is_window_open' => 'boolean',
        'is_starred' => 'boolean',
        'is_archived' => 'boolean',
        'is_muted' => 'boolean',
        'labels' => 'array',
        'unread_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeWithOpenWindow($query)
    {
        return $query->where('is_window_open', true)
            ->where('window_expires_at', '>', now());
    }

    public function scopeUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeWithLabel($query, string $label)
    {
        return $query->whereJsonContains('labels', $label);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if the 24-hour messaging window is currently open.
     */
    public function isWindowOpen(): bool
    {
        return $this->is_window_open
            && $this->window_expires_at
            && $this->window_expires_at->isFuture();
    }

    /**
     * Refresh the 24-hour messaging window (called on incoming message).
     */
    public function refreshWindow(): void
    {
        $this->update([
            'is_window_open' => true,
            'window_expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Close the messaging window.
     */
    public function closeWindow(): void
    {
        $this->update([
            'is_window_open' => false,
            'window_expires_at' => null,
        ]);
    }

    /**
     * Mark all unread messages as read and reset unread count.
     */
    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    /**
     * Increment unread count.
     */
    public function incrementUnread(): void
    {
        $this->increment('unread_count');
    }

    /**
     * Update the last message preview.
     */
    public function updateLastMessage(string $preview, ?\Carbon\Carbon $timestamp = null): void
    {
        $this->update([
            'last_message_at' => $timestamp ?? now(),
            'last_message_preview' => \Illuminate\Support\Str::limit($preview, 100),
        ]);
    }

    /**
     * Get display name (contact name or user name or phone number).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->contact_name) {
            return $this->contact_name;
        }

        if ($this->user) {
            return $this->user->name;
        }

        return $this->phone_number;
    }

    /**
     * Get remaining window time in human-readable format.
     */
    public function getWindowRemainingAttribute(): ?string
    {
        if (!$this->isWindowOpen()) {
            return null;
        }

        return $this->window_expires_at->diffForHumans();
    }
}
