<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\DownloadWhatsAppMediaJob;
use App\Jobs\ProcessWhatsAppChatbotReplyJob;
use App\Jobs\SendWhatsAppMessageJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppConversationService
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /*
    |--------------------------------------------------------------------------
    | Conversation Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get or create a conversation for a given phone number.
     */
    public function getOrCreateConversation(
        string $phoneNumber,
        ?string $contactName = null,
        ?string $profilePictureUrl = null
    ): WhatsAppConversation {
        // Strip non-digits
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Strip leading zero if 11 digits (e.g. 09624936146 -> 9624936146)
        if (str_starts_with($phoneNumber, '0') && strlen($phoneNumber) === 11) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        // If 10 digits, prepend country code '91'
        if (strlen($phoneNumber) === 10) {
            $phoneNumber = '91' . $phoneNumber;
        }

        $last10 = substr($phoneNumber, -10);

        // 1. Try exact match first (normalized number)
        $conversation = WhatsAppConversation::where('phone_number', $phoneNumber)->first();

        // 2. If no exact match, fallback to matching by last 10 digits
        if (!$conversation) {
            $conversation = WhatsAppConversation::where('phone_number', 'LIKE', '%' . $last10)->first();
            
            // Safe to update to the normalized number since step 1 confirmed no exact match exists
            if ($conversation) {
                $conversation->update(['phone_number' => $phoneNumber]);
            }
        }

        if ($conversation) {
            // Update contact info if provided
            $updates = [];
            if ($contactName && !$conversation->contact_name) {
                $updates['contact_name'] = $contactName;
            }
            if ($profilePictureUrl) {
                $updates['profile_picture_url'] = $profilePictureUrl;
            }
            if (!empty($updates)) {
                $conversation->update($updates);
            }

            // Try to link user if not linked
            if (!$conversation->user_id) {
                $user = User::where('mobile', 'LIKE', '%' . $last10)->first();
                if ($user) {
                    $conversation->update(['user_id' => $user->id]);
                }
            }

            return $conversation;
        }

        // Try to find matching user
        $user = User::where('mobile', 'LIKE', '%' . $last10)->first();

        return WhatsAppConversation::create([
            'phone_number' => $phoneNumber,
            'contact_name' => $contactName ?? $user?->name,
            'profile_picture_url' => $profilePictureUrl,
            'user_id' => $user?->id,
            'is_window_open' => false,
            'unread_count' => 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Incoming Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Process an incoming message from the webhook.
     */
    public function processIncomingMessage(array $messageData, array $contactData = []): ?WhatsAppMessage
    {
        $phoneNumber = $messageData['from'] ?? null;
        $messageId = $messageData['id'] ?? null;
        $timestamp = $messageData['timestamp'] ?? null;
        $type = $messageData['type'] ?? 'text';

        if (!$phoneNumber || !$messageId) {
            Log::warning('WhatsApp incoming message missing required fields', $messageData);
            return null;
        }

        // Check for duplicate message
        $existing = WhatsAppMessage::where('message_id', $messageId)->first();
        if ($existing) {
            return $existing;
        }

        $contactName = $contactData['profile']['name'] ?? null;

        $conversation = $this->getOrCreateConversation($phoneNumber, $contactName);

        // Open the 24-hour messaging window
        $conversation->refreshWindow();

        // Parse message content
        $content = $this->parseMessageContent($messageData, $type);

        // Build media fields
        $mediaFields = $this->extractMediaFields($messageData, $type);

        // Create the message record
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'message_id' => $messageId,
            'direction' => WhatsAppMessageDirection::Incoming,
            'type' => $type,
            'content' => $content,
            'status' => WhatsAppMessageStatus::Delivered, // Incoming messages are "delivered" to us
            'meta_timestamp' => $timestamp ? Carbon::createFromTimestamp($timestamp) : now(),
            'delivered_at' => now(),
            'context_message_id' => $messageData['context']['id'] ?? null,
            ...$mediaFields,
        ]);

        // Update conversation
        $preview = $this->getMessagePreview($content, $type);
        $conversation->updateLastMessage($preview, $message->meta_timestamp);
        $conversation->incrementUnread();

        // Download media if present
        if (!empty($mediaFields['media_id'])) {
            DownloadWhatsAppMediaJob::dispatch($message->id);
        }

        // Mark message as read on Meta side
        $this->whatsAppService->markAsRead($messageId);

        // Dispatch chatbot reply job for text messages
        if ($type === 'text') {
            ProcessWhatsAppChatbotReplyJob::dispatch($conversation->id, $message->id);
        }

        return $message;
    }

    /**
     * Parse message content based on type.
     */
    protected function parseMessageContent(array $messageData, string $type): array
    {
        return match ($type) {
            'text' => [
                'body' => $messageData['text']['body'] ?? '',
            ],
            'image' => [
                'caption' => $messageData['image']['caption'] ?? '',
                'mime_type' => $messageData['image']['mime_type'] ?? '',
                'sha256' => $messageData['image']['sha256'] ?? '',
                'id' => $messageData['image']['id'] ?? '',
            ],
            'video' => [
                'caption' => $messageData['video']['caption'] ?? '',
                'mime_type' => $messageData['video']['mime_type'] ?? '',
                'sha256' => $messageData['video']['sha256'] ?? '',
                'id' => $messageData['video']['id'] ?? '',
            ],
            'audio' => [
                'mime_type' => $messageData['audio']['mime_type'] ?? '',
                'sha256' => $messageData['audio']['sha256'] ?? '',
                'id' => $messageData['audio']['id'] ?? '',
                'voice' => $messageData['audio']['voice'] ?? false,
            ],
            'document' => [
                'caption' => $messageData['document']['caption'] ?? '',
                'filename' => $messageData['document']['filename'] ?? '',
                'mime_type' => $messageData['document']['mime_type'] ?? '',
                'sha256' => $messageData['document']['sha256'] ?? '',
                'id' => $messageData['document']['id'] ?? '',
            ],
            'sticker' => [
                'mime_type' => $messageData['sticker']['mime_type'] ?? '',
                'sha256' => $messageData['sticker']['sha256'] ?? '',
                'id' => $messageData['sticker']['id'] ?? '',
                'animated' => $messageData['sticker']['animated'] ?? false,
            ],
            'location' => [
                'latitude' => $messageData['location']['latitude'] ?? 0,
                'longitude' => $messageData['location']['longitude'] ?? 0,
                'name' => $messageData['location']['name'] ?? '',
                'address' => $messageData['location']['address'] ?? '',
            ],
            'reaction' => [
                'emoji' => $messageData['reaction']['emoji'] ?? '',
                'message_id' => $messageData['reaction']['message_id'] ?? '',
            ],
            'contacts' => [
                'contacts' => $messageData['contacts'] ?? [],
            ],
            default => [
                'body' => json_encode($messageData[$type] ?? []),
            ],
        };
    }

    /**
     * Extract media-specific fields from message data.
     */
    protected function extractMediaFields(array $messageData, string $type): array
    {
        $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker'];

        if (!in_array($type, $mediaTypes)) {
            return [];
        }

        $mediaData = $messageData[$type] ?? [];

        return [
            'media_id' => $mediaData['id'] ?? null,
            'media_mime_type' => $mediaData['mime_type'] ?? null,
            'media_sha256' => $mediaData['sha256'] ?? null,
            'media_filename' => $mediaData['filename'] ?? null,
        ];
    }

    /**
     * Get a preview string for the conversation list.
     */
    protected function getMessagePreview(array $content, string $type): string
    {
        return match ($type) {
            'text' => $content['body'] ?? '',
            'image' => '📷 ' . ($content['caption'] ?? 'Photo'),
            'video' => '🎥 ' . ($content['caption'] ?? 'Video'),
            'audio' => '🎵 Voice message',
            'document' => '📄 ' . ($content['filename'] ?? 'Document'),
            'sticker' => '🎨 Sticker',
            'location' => '📍 Location',
            'reaction' => ($content['emoji'] ?? '👍'),
            'contacts' => '👤 Contact',
            default => "[$type]",
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Outgoing Messages (Replies)
    |--------------------------------------------------------------------------
    */

    /**
     * Send a text reply within the 24h window.
     */
    public function sendReply(
        WhatsAppConversation $conversation,
        string $text,
        ?string $contextMessageId = null
    ): ?WhatsAppMessage {
        if (!$conversation->isWindowOpen()) {
            Log::warning('WhatsApp 24h window is closed for: ' . $conversation->phone_number);
            return null;
        }

        // Create the outgoing message record
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessageDirection::Outgoing,
            'type' => WhatsAppMessageType::Text,
            'content' => ['body' => $text],
            'status' => WhatsAppMessageStatus::Pending,
            'context_message_id' => $contextMessageId,
        ]);

        // Dispatch background job to send the message
        SendWhatsAppMessageJob::dispatch(
            $conversation->phone_number,
            $text,
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

        // Update conversation
        $conversation->updateLastMessage($text);

        return $message;
    }

    /**
     * Send a media reply within the 24h window.
     */
    public function sendMediaReply(
        WhatsAppConversation $conversation,
        string $mediaType,
        string $mediaUrlOrId,
        ?string $caption = null,
        ?string $filename = null,
        bool $isMediaId = false,
        ?string $contextMessageId = null
    ): ?WhatsAppMessage {
        if (!$conversation->isWindowOpen()) {
            Log::warning('WhatsApp 24h window is closed for: ' . $conversation->phone_number);
            return null;
        }

        $content = [
            'caption' => $caption ?? '',
            'media_type' => $mediaType,
        ];

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessageDirection::Outgoing,
            'type' => $mediaType,
            'content' => $content,
            'status' => WhatsAppMessageStatus::Pending,
            'media_url' => $isMediaId ? null : $mediaUrlOrId,
            'media_id' => $isMediaId ? $mediaUrlOrId : null,
            'context_message_id' => $contextMessageId,
        ]);

        // Dispatch background job to send the media message
        SendWhatsAppMessageJob::dispatch(
            $conversation->phone_number,
            '', // templateName
            [], // components
            'en_US', // languageCode
            $conversation->user_id, // userId
            'media', // type
            $mediaType, // mediaType
            $mediaUrlOrId, // mediaUrlOrId
            $caption, // caption
            $filename, // filename
            $isMediaId, // isMediaId
            $message->id // dbMessageId
        );

        $preview = $this->getMessagePreview($content, $mediaType);
        $conversation->updateLastMessage($preview);

        return $message;
    }

    /**
     * Send a template message (works outside 24h window).
     */
    public function sendTemplateMessage(
        WhatsAppConversation $conversation,
        string $templateName,
        array $components = [],
        string $languageCode = 'en_US',
        ?array $variables = null
    ): ?WhatsAppMessage {
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => WhatsAppMessageDirection::Outgoing,
            'type' => WhatsAppMessageType::Template,
            'content' => ['template' => $templateName, 'language' => $languageCode],
            'template_name' => $templateName,
            'template_variables' => $variables,
            'status' => WhatsAppMessageStatus::Pending,
        ]);

        // Dispatch background job to send the template message
        SendWhatsAppMessageJob::dispatch(
            $conversation->phone_number,
            $templateName,
            $components,
            $languageCode,
            $conversation->user_id,
            'template',
            null,
            null,
            null,
            null,
            false,
            $message->id
        );

        $conversation->updateLastMessage("📋 Template: $templateName");

        return $message;
    }

    /*
    |--------------------------------------------------------------------------
    | Window Status
    |--------------------------------------------------------------------------
    */

    /**
     * Check and update window status for a conversation.
     */
    public function checkWindowStatus(WhatsAppConversation $conversation): bool
    {
        if ($conversation->is_window_open && $conversation->window_expires_at?->isPast()) {
            $conversation->closeWindow();
            return false;
        }

        return $conversation->isWindowOpen();
    }

}
