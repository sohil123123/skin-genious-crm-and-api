<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\WhatsAppMessageLog;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verify the webhook request from Meta.
     */
    public function verify(Request $request)
    {
        \Log::info("verify:" . json_encode($request->all()));
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
        \Log::info("handle:" . json_encode($payload));

        // Log the payload for debugging if needed
        // Log::debug('WhatsApp Webhook payload:', $payload);

        if (isset($payload['object']) && $payload['object'] === 'whatsapp_business_account') {
            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    if ($change['field'] === 'messages') {
                        $value = $change['value'];

                        // Check if it's a message status update
                        if (isset($value['statuses'])) {
                            foreach ($value['statuses'] as $statusUpdate) {
                                $this->updateMessageStatus($statusUpdate);
                            }
                        }

                        // We can also handle incoming messages here by checking $value['messages']
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
}
