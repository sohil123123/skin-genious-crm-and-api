<?php

namespace App\Jobs;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppAiService;
use App\Services\WhatsAppConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppChatbotReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $conversationId;
    public int $messageId;

    public function __construct(int $conversationId, int $messageId)
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
        $this->onQueue('whatsapp-chatbot');
    }

    /**
     * Execute the job.
     */
    public function handle(
        WhatsAppConversationService $conversationService,
        WhatsAppAiService $aiService
    ): void {
        if (!$aiService->isConfigured()) {
            return;
        }

        $conversation = WhatsAppConversation::find($this->conversationId);
        $message = WhatsAppMessage::find($this->messageId);

        if (!$conversation || !$message) {
            return;
        }

        // Only reply to incoming text messages
        if (($message->direction?->value ?? $message->direction) !== 'incoming') {
            return;
        }

        // Verify if window is still open
        if (!$conversation->isWindowOpen()) {
            return;
        }

        // Let's generate a reply
        $replyText = $aiService->generateReply($conversation);

        if (empty($replyText)) {
            Log::info("Chatbot: Could not generate reply for message #{$this->messageId}");
            return;
        }

        // Send the reply
        $conversationService->sendReply($conversation, $replyText, $message->message_id);
        
        Log::info("Chatbot auto-replied to message #{$this->messageId} in conversation #{$this->conversationId}");
    }
}
