<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsAppMessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl = 'https://graph.facebook.com/v20.0/';
    protected ?string $phoneNumberId;
    protected ?string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = Setting::getValue('whatsapp_phone_number_id');
        $this->accessToken = Setting::getValue('whatsapp_access_token');
    }

    /**
     * Send a template message using the Meta Cloud API.
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en_US',
        array $components = [],
        ?int $userId = null
    ): ?WhatsAppMessageLog {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp API credentials are not set.');
            return null;
        }

        // Format phone number (remove any + or non-numeric characters)
        $to = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->accessToken)
                ->post($endpoint, $payload);

            $responseData = $response->json();

            // Create Log
            $log = WhatsAppMessageLog::create([
                'user_id' => $userId,
                'phone_number' => $to,
                'template_name' => $templateName,
                'message_id' => $responseData['messages'][0]['id'] ?? null,
                'status' => $response->successful() ? 'sent' : 'failed',
                'error_response' => $response->successful() ? null : $responseData,
                'sent_at' => $response->successful() ? now() : null,
            ]);

            if (!$response->successful()) {
                Log::error('WhatsApp API Error: ' . json_encode($responseData));
            }

            return $log;
        } catch (\Exception $e) {
            Log::error('WhatsApp Exception: ' . $e->getMessage());

            return WhatsAppMessageLog::create([
                'user_id' => $userId,
                'phone_number' => $to,
                'template_name' => $templateName,
                'status' => 'failed',
                'error_response' => ['exception' => $e->getMessage()],
            ]);
        }
    }

    /**
     * Upload Media to Meta and return the media ID.
     * This is useful for sending invoices as PDF.
     */
    public function uploadMedia(string $filePath, string $mimeType = 'application/pdf'): ?string
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return null;
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/media';

        try {
            $response = Http::withToken($this->accessToken)
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            if ($response->successful()) {
                return $response->json()['id'] ?? null;
            }

            Log::error('WhatsApp Media Upload Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Media Upload Exception: ' . $e->getMessage());
            return null;
        }
    }
}
