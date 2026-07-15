<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected WhatsAppConversationService $conversationService;

    public function __construct(WhatsAppConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    /**
     * Verify the webhook request from Meta.
     */
    public function verify(Request $request)
    {
        Log::info('WHATSAPP VERIFY: ' . json_encode($request->all(), JSON_PRETTY_PRINT));

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = Setting::getValue('whatsapp_webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response()->json(['error' => 'Invalid verify token'], 403);
    }

    /**
     * Handle incoming webhook events from Meta.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('WHATSAPP RAW: ' . json_encode($payload, JSON_PRETTY_PRINT));

        if (isset($payload['object']) && $payload['object'] === 'whatsapp_business_account') {
            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $field = $change['field'] ?? '';

                    if ($field === 'messages') {
                        $value = $change['value'];

                        // Handle message status updates (sent, delivered, read, failed)
                        if (isset($value['statuses'])) {
                            foreach ($value['statuses'] as $statusUpdate) {
                                $this->updateMessageStatus($statusUpdate);
                            }
                        }

                        // Handle incoming messages
                        if (isset($value['messages'])) {
                            $contacts = $value['contacts'] ?? [];
                            foreach ($value['messages'] as $index => $messageData) {
                                $contactData = $contacts[$index] ?? ($contacts[0] ?? []);
                                $this->handleIncomingMessage($messageData, $contactData);
                            }
                        }
                    }

                    // Handle template status update events
                    if ($field === 'message_template_status_update') {
                        $this->handleTemplateStatusUpdate($change['value'] ?? []);
                    }
                }
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response()->json(['error' => 'Not Found'], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Incoming Messages
    |--------------------------------------------------------------------------
    */

    /**
     * Handle an incoming message from a user.
     */
    protected function handleIncomingMessage(array $messageData, array $contactData = []): void
    {
        try {
            $message = $this->conversationService->processIncomingMessage($messageData, $contactData);

            if ($message) {
                Log::info('WhatsApp incoming message processed', [
                    'message_id' => $message->message_id,
                    'from' => $messageData['from'] ?? 'unknown',
                    'type' => $messageData['type'] ?? 'unknown',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp incoming message error: ' . $e->getMessage(), [
                'message_data' => $messageData,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Message Status Updates
    |--------------------------------------------------------------------------
    */

    /**
     * Update the message status based on the webhook event.
     * Handles: sent, delivered, read, failed
     */
    protected function updateMessageStatus(array $statusUpdate): void
    {
        $messageId = $statusUpdate['id'] ?? null;
        $status = $statusUpdate['status'] ?? null; // sent, delivered, read, failed
        $timestamp = $statusUpdate['timestamp'] ?? null;
        $recipientId = $statusUpdate['recipient_id'] ?? null;

        if (!$messageId || !$status) {
            return;
        }

        // Update in WhatsAppMessage (new system)
        $message = WhatsAppMessage::where('message_id', $messageId)->first();

        if ($message) {
            switch ($status) {
                case 'sent':
                    if ($message->status?->value !== 'delivered' && $message->status?->value !== 'read') {
                        $message->markAsSent($messageId);
                    }
                    break;
                case 'delivered':
                    $message->markAsDelivered();
                    break;
                case 'read':
                    $message->markAsRead();
                    break;
                case 'failed':
                    $errorReason = $statusUpdate['errors'][0]['title'] ?? 'Unknown error';
                    $message->markAsFailed($errorReason);
                    break;
            }
        }

        // Update campaign recipient status if applicable
        $campaignRecipient = WhatsAppCampaignRecipient::where('message_id', $messageId)->first();

        if ($campaignRecipient) {
            $recipientUpdates = ['status' => $status];

            switch ($status) {
                case 'delivered':
                    $recipientUpdates['delivered_at'] = now();
                    $campaignRecipient->campaign?->increment('delivered_count');
                    break;
                case 'read':
                    $recipientUpdates['delivered_at'] = $campaignRecipient->delivered_at ?? now();
                    $recipientUpdates['read_at'] = now();
                    if (!$campaignRecipient->delivered_at) {
                        $campaignRecipient->campaign?->increment('delivered_count');
                    }
                    $campaignRecipient->campaign?->increment('read_count');
                    break;
                case 'failed':
                    $recipientUpdates['failed_at'] = now();
                    $recipientUpdates['failed_reason'] = $statusUpdate['errors'][0]['title'] ?? 'Unknown error';
                    break;
            }

            $campaignRecipient->update($recipientUpdates);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Template Status Updates
    |--------------------------------------------------------------------------
    */

    /**
     * Handle message_template_status_update webhook events.
     */
    protected function handleTemplateStatusUpdate(array $value): void
    {
        $event = $value['event'] ?? null;
        $templateName = $value['message_template_name'] ?? null;
        $templateLanguage = $value['message_template_language'] ?? null;
        $metaTemplateId = $value['message_template_id'] ?? null;
        $reason = $value['reason'] ?? null;

        Log::info('WhatsApp Template Status Update', [
            'event' => $event,
            'name' => $templateName,
            'language' => $templateLanguage,
            'id' => $metaTemplateId,
            'reason' => $reason,
        ]);

        if (!$templateName || !$event) {
            Log::warning('WhatsApp Template Status Update: Missing required fields.');
            return;
        }

        $query = WhatsAppTemplate::where('name', $templateName);

        if ($templateLanguage) {
            $query->where('language', $templateLanguage);
        }

        $template = $query->first();

        if (!$template) {
            Log::warning("WhatsApp Template Status Update: Template '{$templateName}' not found in database.");
            return;
        }

        $template->status = strtoupper($event);

        if ($metaTemplateId) {
            $template->meta_template_id = (string) $metaTemplateId;
        }

        if ($event === 'REJECTED' && $reason) {
            $template->rejected_reason = $reason;
        }

        $template->last_synced_at = now();
        $template->save();

        Log::info("WhatsApp Template '{$templateName}' status updated to: {$event}");
    }
}
