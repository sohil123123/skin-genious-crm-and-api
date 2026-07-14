<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl = 'https://graph.facebook.com/v25.0/';
    protected ?string $phoneNumberId;
    protected ?string $accessToken;
    protected ?string $businessAccountId;

    public function __construct()
    {
        $this->phoneNumberId = Setting::getValue('whatsapp_phone_number_id');
        $this->accessToken = Setting::getValue('whatsapp_access_token');
        $this->businessAccountId = Setting::getValue('whatsapp_business_account_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Template Management — Cloud API
    |--------------------------------------------------------------------------
    */

    /**
     * List all message templates from Meta Cloud API.
     */
    public function listTemplates(?string $status = null, int $limit = 100): array
    {
        if (!$this->businessAccountId || !$this->accessToken) {
            Log::error('WhatsApp API: Business Account ID or Access Token not set.');
            return [];
        }

        $endpoint = $this->apiUrl . $this->businessAccountId . '/message_templates';

        $params = [
            'limit'  => $limit,
            'fields' => 'id,name,category,language,status,components,quality_score,rejected_reason,parameter_format',
        ];

        if ($status) {
            $params['status'] = $status;
        }

        try {
            $allTemplates = [];
            $url = $endpoint;

            do {
                $response = Http::withToken($this->accessToken)->get($url, $params);

                if (!$response->successful()) {
                    Log::error('WhatsApp List Templates Error: ' . $response->body());
                    return $allTemplates;
                }

                $data = $response->json();
                $allTemplates = array_merge($allTemplates, $data['data'] ?? []);

                // Handle pagination
                $url = $data['paging']['next'] ?? null;
                $params = []; // Next URL already contains params
            } while ($url);

            return $allTemplates;
        } catch (\Exception $e) {
            Log::error('WhatsApp List Templates Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single template by its Meta template ID.
     */
    public function getTemplate(string $templateId): ?array
    {
        if (!$this->accessToken) {
            return null;
        }

        $endpoint = $this->apiUrl . $templateId;

        try {
            $response = Http::withToken($this->accessToken)
                ->get($endpoint, [
                    'fields' => 'id,name,category,language,status,components,quality_score,rejected_reason,parameter_format',
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WhatsApp Get Template Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Get Template Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new message template on Meta Cloud API.
     */
    public function createTemplate(array $data): array
    {
        if (!$this->businessAccountId || !$this->accessToken) {
            return [
                'success' => false,
                'error'   => 'Business Account ID or Access Token not configured.',
            ];
        }

        $endpoint = $this->apiUrl . $this->businessAccountId . '/message_templates';

        $parameterFormat = 'POSITIONAL';
        if (isset($data['parameter_format'])) {
            $parameterFormat = strtoupper($data['parameter_format']);
        } elseif (isset($data['variable_type'])) {
            $parameterFormat = ($data['variable_type'] === 'name') ? 'NAMED' : 'POSITIONAL';
        }

        $payload = [
            'name'             => $data['name'],
            'category'         => $data['category'],
            'language'         => $data['language'],
            'components'       => $data['components'],
            'parameter_format' => $parameterFormat,
        ];

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success'     => true,
                    'template_id' => $responseData['id'] ?? null,
                    'status'      => $responseData['status'] ?? 'PENDING',
                    'category'    => $responseData['category'] ?? $data['category'],
                ];
            }

            Log::error('WhatsApp Create Template Error: ' . json_encode($responseData));

            $errorMsg = 'Unknown error from Meta API.';
            if (isset($responseData['error'])) {
                $error = $responseData['error'];
                $errorMsg = $error['error_user_msg'] ?? $error['message'] ?? $errorMsg;
                if (isset($error['error_user_title'])) {
                    $errorMsg = $error['error_user_title'] . ': ' . $errorMsg;
                }
            }

            return [
                'success' => false,
                'error'   => $errorMsg,
                'details' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Create Template Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Edit an existing message template on Meta Cloud API.
     */
    public function editTemplate(string $metaTemplateId, array $data): array
    {
        if (!$this->accessToken) {
            return [
                'success' => false,
                'error'   => 'Access Token not configured.',
            ];
        }

        $endpoint = $this->apiUrl . $metaTemplateId;

        $payload = [
            'components' => $data['components'],
        ];

        // Category can be updated
        if (isset($data['category'])) {
            $payload['category'] = $data['category'];
        }

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $responseData,
                ];
            }

            Log::error('WhatsApp Edit Template Error: ' . json_encode($responseData));

            $errorMsg = 'Unknown error from Meta API.';
            if (isset($responseData['error'])) {
                $error = $responseData['error'];
                $errorMsg = $error['error_user_msg'] ?? $error['message'] ?? $errorMsg;
                if (isset($error['error_user_title'])) {
                    $errorMsg = $error['error_user_title'] . ': ' . $errorMsg;
                }
            }

            return [
                'success' => false,
                'error'   => $errorMsg,
                'details' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Edit Template Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a message template from Meta Cloud API by name.
     */
    public function deleteTemplate(string $templateName): array
    {
        if (!$this->businessAccountId || !$this->accessToken) {
            return [
                'success' => false,
                'error'   => 'Business Account ID or Access Token not configured.',
            ];
        }

        $endpoint = $this->apiUrl . $this->businessAccountId . '/message_templates';

        try {
            $response = Http::withToken($this->accessToken)
                ->delete($endpoint, [
                    'name' => $templateName,
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $responseData,
                ];
            }

            Log::error('WhatsApp Delete Template Error: ' . json_encode($responseData));

            return [
                'success' => false,
                'error'   => $responseData['error']['message'] ?? 'Unknown error from Meta API.',
                'details' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Delete Template Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync all templates from Meta Cloud API to local database.
     * Returns the number of templates synced.
     */
    public function syncTemplatesFromMeta(): int
    {
        $metaTemplates = $this->listTemplates();

        if (empty($metaTemplates)) {
            return 0;
        }

        $syncedCount = 0;

        foreach ($metaTemplates as $metaTemplate) {
            $parsedData = $this->parseMetaTemplateComponents($metaTemplate);

            WhatsAppTemplate::updateOrCreate(
                [
                    'name'     => $metaTemplate['name'],
                    'language' => $metaTemplate['language'],
                ],
                [
                    'meta_template_id' => $metaTemplate['id'] ?? null,
                    'category'         => $metaTemplate['category'] ?? null,
                    'variable_type'    => (strtolower($metaTemplate['parameter_format'] ?? '') === 'named') ? 'name' : 'number',
                    'components'       => $metaTemplate['components'] ?? [],
                    'status'           => $metaTemplate['status'] ?? 'PENDING',
                    'header_type'      => $parsedData['header_type'],
                    'header_content'   => $parsedData['header_content'],
                    'body_text'        => $parsedData['body_text'],
                    'footer_text'      => $parsedData['footer_text'],
                    'buttons'          => $parsedData['buttons'],
                    'variable_samples' => $parsedData['variable_samples'],
                    'rejected_reason'  => $metaTemplate['rejected_reason'] ?? null,
                    'quality_score'    => $metaTemplate['quality_score']['score'] ?? null,
                    'last_synced_at'   => now(),
                ]
            );

            $syncedCount++;
        }

        return $syncedCount;
    }

    /**
     * Parse components from a Meta API response into local structured fields.
     */
    public function parseMetaTemplateComponents(array $metaTemplate): array
    {
        $result = [
            'header_type'      => 'none',
            'header_content'   => null,
            'body_text'        => null,
            'footer_text'      => null,
            'buttons'          => null,
            'variable_samples' => null,
        ];

        $components = $metaTemplate['components'] ?? [];

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            switch ($type) {
                case 'HEADER':
                    $format = strtolower($component['format'] ?? 'text');
                    $result['header_type'] = $format;
                    if ($format === 'text') {
                        $result['header_content'] = $component['text'] ?? null;
                    }
                    break;

                case 'BODY':
                    $result['body_text'] = $component['text'] ?? null;

                    // Extract sample variables if present
                    if (isset($component['example']['body_text'][0])) {
                        $samples = $component['example']['body_text'][0];
                        $variableSamples = [];
                        foreach ($samples as $index => $sample) {
                            if (is_array($sample) && isset($sample['param_name'])) {
                                // Named variable: {"param_name": "...", "example": "..."}
                                $variableSamples[$sample['param_name']] = $sample['example'] ?? '';
                            } else {
                                // Positional variable
                                $variableSamples[(string) ($index + 1)] = $sample;
                            }
                        }
                        $result['variable_samples'] = $variableSamples;
                    }
                    break;

                case 'FOOTER':
                    $result['footer_text'] = $component['text'] ?? null;
                    break;

                case 'BUTTONS':
                    $buttons = [];
                    foreach ($component['buttons'] ?? [] as $btn) {
                        $buttonData = [
                            'type' => $btn['type'] ?? 'QUICK_REPLY',
                            'text' => $btn['text'] ?? '',
                        ];

                        if (($btn['type'] ?? '') === 'URL') {
                            $buttonData['url'] = $btn['url'] ?? '';
                            $buttonData['url_example'] = $btn['example'][0] ?? '';
                        } elseif (($btn['type'] ?? '') === 'PHONE_NUMBER') {
                            $buttonData['phone_number'] = $btn['phone_number'] ?? '';
                        }

                        $buttons[] = $buttonData;
                    }
                    $result['buttons'] = $buttons;
                    break;
            }
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Message Sending
    |--------------------------------------------------------------------------
    */

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
     * Send a plain text message (customer reply) using Meta Cloud API.
     */
    public function sendTextMessage(string $to, string $text, ?int $userId = null): ?WhatsAppMessageLog
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp API credentials are not set.');
            return null;
        }

        // Format phone number
        $to = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ];

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            $log = WhatsAppMessageLog::create([
                'user_id' => $userId,
                'phone_number' => $to,
                'template_name' => 'TEXT_MESSAGE_REPLY',
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
                'template_name' => 'TEXT_MESSAGE_REPLY',
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
