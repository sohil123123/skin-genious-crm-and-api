<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppScheduledMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_scheduled_messages';

    protected $fillable = [
        'user_id',
        'phone_number',
        'type',
        'template_name',
        'template_variables',
        'content',
        'media_library_id',
        'scheduled_at',
        'timezone',
        'is_recurring',
        'recurrence_rule',
        'last_sent_at',
        'next_run_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'template_variables' => 'array',
        'scheduled_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_recurring' => 'boolean',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mediaLibrary(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMediaLibrary::class, 'media_library_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDueForSending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->where('scheduled_at', '<=', now())
                    ->orWhere(function ($q2) {
                        $q2->where('is_recurring', true)
                            ->where('next_run_at', '<=', now());
                    });
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this scheduled message is ready to send.
     */
    public function isReady(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if ($this->is_recurring && $this->next_run_at) {
            return $this->next_run_at->isPast();
        }

        return $this->scheduled_at->isPast();
    }

    /**
     * Mark as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark as completed (for one-time messages).
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'last_sent_at' => now(),
        ]);
    }

    /**
     * Calculate and set the next run time for recurring messages.
     */
    public function calculateNextRun(): void
    {
        if (!$this->is_recurring || !$this->recurrence_rule) {
            return;
        }

        // Simple recurrence: daily, weekly, monthly
        $nextRun = match ($this->recurrence_rule) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => null,
        };

        if ($nextRun) {
            $this->update([
                'next_run_at' => $nextRun,
                'last_sent_at' => now(),
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Get display name for the target user.
     */
    public function getRecipientNameAttribute(): string
    {
        return $this->user?->name ?? $this->phone_number;
    }
}
