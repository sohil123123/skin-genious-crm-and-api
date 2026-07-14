<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verify the webhook request from Meta.
     */
    public function verify(Request $request)
    {
        // Log::debug('WhatsApp Webhook verify:', $request->all());
        Log::info(
            'WHATSAPP VERIFY: ' .
            json_encode($request->all(), JSON_PRETTY_PRINT)
        );

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

        // Log the payload for debugging if needed
        // Log::debug('WhatsApp Webhook payload:', $payload);
        Log::info(
            'WHATSAPP RAW: ' .
            json_encode($payload, JSON_PRETTY_PRINT)
        );


        if (isset($payload['object']) && $payload['object'] === 'whatsapp_business_account') {
            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $field = $change['field'] ?? '';

                    if ($field === 'messages') {
                        $value = $change['value'];

                        // Check if it's a message status update
                        if (isset($value['statuses'])) {
                            foreach ($value['statuses'] as $statusUpdate) {
                                $this->updateMessageStatus($statusUpdate);
                            }
                        }

                        // We can also handle incoming messages here by checking $value['messages']
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

    /**
     * Update the message log status based on the webhook event.
     */
    protected function updateMessageStatus(array $statusUpdate)
    {
        $messageId = $statusUpdate['id'] ?? null;
        $status = $statusUpdate['status'] ?? null; // sent, delivered, read, failed

        if ($messageId && $status) {
            $log = WhatsAppMessageLog::where('message_id', $messageId)->first();

            if ($log) {
                $log->status = $status;

                if ($status === 'failed' && isset($statusUpdate['errors'])) {
                    $log->error_response = $statusUpdate['errors'];
                }

                $log->save();
            }
        }
    }

    /**
     * Handle message_template_status_update webhook events.
     *
     * Meta sends these when a template is approved, rejected, or paused.
     * Payload example:
     * {
     *   "event": "APPROVED" | "REJECTED" | "PAUSED" | "DISABLED",
     *   "message_template_id": 123456789,
     *   "message_template_name": "template_name",
     *   "message_template_language": "en_US",
     *   "reason": "Optional rejection reason"
     * }
     */
    protected function handleTemplateStatusUpdate(array $value)
    {
        $event = $value['event'] ?? null;
        $templateName = $value['message_template_name'] ?? null;
        $templateLanguage = $value['message_template_language'] ?? null;
        $metaTemplateId = $value['message_template_id'] ?? null;
        $reason = $value['reason'] ?? null;

        Log::info('WhatsApp Template Status Update', [
            'event'    => $event,
            'name'     => $templateName,
            'language' => $templateLanguage,
            'id'       => $metaTemplateId,
            'reason'   => $reason,
        ]);

        if (!$templateName || !$event) {
            Log::warning('WhatsApp Template Status Update: Missing required fields.');
            return;
        }

        // Find the template in our database
        $query = WhatsAppTemplate::where('name', $templateName);

        if ($templateLanguage) {
            $query->where('language', $templateLanguage);
        }

        $template = $query->first();

        if (!$template) {
            Log::warning("WhatsApp Template Status Update: Template '{$templateName}' not found in database.");
            return;
        }

        // Update the template status
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
