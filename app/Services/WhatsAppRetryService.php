<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppRetryService
{
    /**
     * Retry schedule: attempt => minutes to wait
     */
    protected array $retrySchedule = [
        0 => 5,    // First retry after 5 minutes
        1 => 30,   // Second retry after 30 minutes
        2 => 60,   // Third retry after 1 hour
    ];

    protected int $maxRetries = 3;

    /**
     * Check if a message should be retried.
     */
    public function shouldRetry(WhatsAppMessage $message): bool
    {
        return $message->status->value === 'failed'
            && $message->retry_count < $this->maxRetries
            && ($message->next_retry_at === null || $message->next_retry_at->isPast());
    }

    /**
     * Schedule the next retry for a failed message.
     */
    public function scheduleRetry(WhatsAppMessage $message): void
    {
        if (!$this->shouldRetry($message)) {
            Log::info("WhatsApp message #{$message->id} has exhausted retry attempts.");
            return;
        }

        $delayMinutes = $this->retrySchedule[$message->retry_count] ?? 60;
        $nextRetryAt = now()->addMinutes($delayMinutes);

        $message->update([
            'next_retry_at' => $nextRetryAt,
        ]);

        Log::info("WhatsApp message #{$message->id} scheduled for retry at {$nextRetryAt} (attempt {$message->retry_count})");
    }

    /**
     * Process all messages that are due for retry.
     */
    public function processRetryQueue(): int
    {
        $messages = WhatsAppMessage::retryable()->get();

        $processedCount = 0;

        foreach ($messages as $message) {
            $this->retryMessage($message);
            $processedCount++;
        }

        if ($processedCount > 0) {
            Log::info("WhatsApp retry: processed {$processedCount} messages.");
        }

        return $processedCount;
    }

    /**
     * Retry a single message.
     */
    public function retryMessage(WhatsAppMessage $message): void
    {
        $message->increment('retry_count');

        $message->update([
            'status' => 'pending',
            'failed_reason' => null,
            'failed_at' => null,
            'next_retry_at' => null,
        ]);

        // Determine message type and dispatch appropriate job
        $conversation = $message->conversation;

        if (!$conversation) {
            $message->markAsFailed('Conversation not found for retry');
            return;
        }

        $type = $message->type?->value ?? 'text';
        $phoneNumber = $conversation->phone_number;

        if ($type === 'template' && $message->template_name) {
            SendWhatsAppMessageJob::dispatch(
                $phoneNumber,
                $message->template_name,
                $message->template_variables ?? [],
                'en_US',
                $conversation->user_id,
                'template',
                null,
                null,
                null,
                null,
                false,
                $message->id
            );
        } else {
            $textBody = $message->content['body'] ?? '';
            SendWhatsAppMessageJob::dispatch(
                $phoneNumber,
                $textBody,
                [],
                'en_US',
                $conversation->user_id,
                'text',
                null,
                null,
                null,
                null,
                false,
                $message->id
            );
        }
    }


}
