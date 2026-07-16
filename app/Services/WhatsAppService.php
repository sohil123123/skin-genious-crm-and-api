<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
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
            'limit' => $limit,
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
                'error' => 'Business Account ID or Access Token not configured.',
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
            'name' => $data['name'],
            'category' => $data['category'],
            'language' => $data['language'],
            'components' => $data['components'],
            'parameter_format' => $parameterFormat,
        ];

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'template_id' => $responseData['id'] ?? null,
                    'status' => $responseData['status'] ?? 'PENDING',
                    'category' => $responseData['category'] ?? $data['category'],
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
                'error' => $errorMsg,
                'details' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Create Template Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
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
                'error' => 'Access Token not configured.',
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
                    'data' => $responseData,
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
                'error' => $errorMsg,
                'details' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Edit Template Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
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
                'error' => 'Business Account ID or Access Token not configured.',
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
                    'data' => $responseData,
                ];
            }

            Log::error('WhatsApp Delete Template Error: ' . json_encode($responseData));

            return [
                'success' => false,
                'error' => $responseData['error']['message'] ?? 'Unknown error from Meta API.',
                'details' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Delete Template Exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
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
        $metaTemplateIds = [];

        if (empty($metaTemplates)) {
            // Optional: Handle full sync deletion here if no templates exist
            return 0;
        }

        $syncedCount = 0;

        foreach ($metaTemplates as $metaTemplate) {
            $parsedData = $this->parseMetaTemplateComponents($metaTemplate);
            
            $metaId = $metaTemplate['id'] ?? null;
            if ($metaId) {
                $metaTemplateIds[] = $metaId;
            }

            WhatsAppTemplate::updateOrCreate(
                [
                    'name' => $metaTemplate['name'],
                    'language' => $metaTemplate['language'],
                ],
                [
                    'meta_template_id' => $metaId,
                    'category' => $metaTemplate['category'] ?? null,
                    'variable_type' => (strtolower($metaTemplate['parameter_format'] ?? '') === 'named') ? 'name' : 'number',
                    'components' => $metaTemplate['components'] ?? [],
                    'status' => $metaTemplate['status'] ?? 'PENDING',
                    'header_type' => $parsedData['header_type'],
                    'header_content' => $parsedData['header_content'],
                    'body_text' => $parsedData['body_text'],
                    'footer_text' => $parsedData['footer_text'],
                    'buttons' => $parsedData['buttons'],
                    'variable_samples' => $parsedData['variable_samples'],
                    'rejected_reason' => $metaTemplate['rejected_reason'] ?? null,
                    'quality_score' => $metaTemplate['quality_score']['score'] ?? null,
                    'last_synced_at' => now(),
                ]
            );

            $syncedCount++;
        }

        // Delete local records that have been deleted on Meta (records with a meta_template_id not present in the Meta API response)
        $query = WhatsAppTemplate::query()->whereNotNull('meta_template_id');
        if (!empty($metaTemplateIds)) {
            $query->whereNotIn('meta_template_id', $metaTemplateIds);
        }
        $query->delete();

        return $syncedCount;
    }

    /**
     * Parse components from a Meta API response into local structured fields.
     */
    public function parseMetaTemplateComponents(array $metaTemplate): array
    {
        $result = [
            'header_type' => 'none',
            'header_content' => null,
            'body_text' => null,
            'footer_text' => null,
            'buttons' => null,
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
        ?int $userId = null,
        ?int $campaignId = null,
        ?int $conversationId = null,
        ?int $dbMessageId = null
    ): ?WhatsAppMessage {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp API credentials are not set.');
            return null;
        }

        // Format phone number (remove any + or non-numeric characters)
        $to = preg_replace('/[^0-9]/', '', $to);

        if (!$conversationId && $to) {
            $conversation = WhatsAppConversation::where('phone_number', $to)->first();
            if (!$conversation) {
                $conversationService = app(WhatsAppConversationService::class);
                $conversation = $conversationService->getOrCreateConversation($to);
            }
            $conversationId = $conversation->id;
        }

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

            // Create or update Message
            $message = $dbMessageId ? WhatsAppMessage::find($dbMessageId) : new WhatsAppMessage();
            $message->fill([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'message_id' => $responseData['messages'][0]['id'] ?? null,
                'status' => $response->successful() ? 'sent' : 'failed',
                'direction' => 'outgoing',
                'type' => 'template',
                'template_name' => $templateName,
                'campaign_id' => $campaignId,
                'sent_at' => $response->successful() ? now() : null,
                'failed_at' => $response->successful() ? null : now(),
                'failed_reason' => $response->successful() ? null : ($responseData['error']['message'] ?? 'API error'),
            ]);
            $message->save();

            if (!$response->successful()) {
                Log::error('WhatsApp API Error: ' . json_encode($responseData));
            }

            return $message;
        } catch (\Exception $e) {
            Log::error('WhatsApp Exception: ' . $e->getMessage());

            $message = $dbMessageId ? WhatsAppMessage::find($dbMessageId) : new WhatsAppMessage();
            $message->fill([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'direction' => 'outgoing',
                'type' => 'template',
                'template_name' => $templateName,
                'campaign_id' => $campaignId,
                'status' => 'failed',
                'failed_at' => now(),
                'failed_reason' => $e->getMessage(),
            ]);
            $message->save();

            return $message;
        }
    }

    /**
     * Send a plain text message (customer reply) using Meta Cloud API.
     */
    public function sendTextMessage(
        string $to,
        string $text,
        ?int $userId = null,
        ?int $campaignId = null,
        ?int $conversationId = null,
        ?string $contextMessageId = null,
        ?int $dbMessageId = null
    ): ?WhatsAppMessage {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp API credentials are not set.');
            return null;
        }

        // Format phone number
        $to = preg_replace('/[^0-9]/', '', $to);

        if (!$conversationId && $to) {
            $conversation = WhatsAppConversation::where('phone_number', $to)->first();
            if (!$conversation) {
                $conversationService = app(WhatsAppConversationService::class);
                $conversation = $conversationService->getOrCreateConversation($to);
            }
            $conversationId = $conversation->id;
        }

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

        if ($contextMessageId) {
            $payload['context'] = [
                'message_id' => $contextMessageId,
            ];
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            // Create or update Message entry
            $message = $dbMessageId ? WhatsAppMessage::find($dbMessageId) : new WhatsAppMessage();
            $message->fill([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'message_id' => $responseData['messages'][0]['id'] ?? null,
                'status' => $response->successful() ? 'sent' : 'failed',
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => ['body' => $text],
                'context_message_id' => $contextMessageId,
                'campaign_id' => $campaignId,
                'sent_at' => $response->successful() ? now() : null,
                'failed_at' => $response->successful() ? null : now(),
                'failed_reason' => $response->successful() ? null : ($responseData['error']['message'] ?? 'API error'),
            ]);
            $message->save();

            if (!$response->successful()) {
                Log::error('WhatsApp API Error: ' . json_encode($responseData));
            }

            return $message;
        } catch (\Exception $e) {
            Log::error('WhatsApp Exception: ' . $e->getMessage());

            $message = $dbMessageId ? WhatsAppMessage::find($dbMessageId) : new WhatsAppMessage();
            $message->fill([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => ['body' => $text],
                'context_message_id' => $contextMessageId,
                'campaign_id' => $campaignId,
                'status' => 'failed',
                'failed_at' => now(),
                'failed_reason' => $e->getMessage(),
            ]);
            $message->save();

            return $message;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Media Message Sending
    |--------------------------------------------------------------------------
    */

    /**
     * Send a media message (image, video, document, audio) using Meta Cloud API.
     */
    public function sendMediaMessage(
        string $to,
        string $mediaType,
        string $mediaUrlOrId,
        ?string $caption = null,
        ?string $filename = null,
        ?int $userId = null,
        bool $isMediaId = false,
        ?int $campaignId = null,
        ?int $conversationId = null,
        ?string $contextMessageId = null,
        ?int $dbMessageId = null
    ): ?array {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp API credentials are not set.');
            return null;
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        if (!$conversationId && $to) {
            $conversation = WhatsAppConversation::where('phone_number', $to)->first();
            if (!$conversation) {
                $conversationService = app(WhatsAppConversationService::class);
                $conversation = $conversationService->getOrCreateConversation($to);
            }
            $conversationId = $conversation->id;
        }

        $mediaPayload = [];
        if ($isMediaId) {
            $mediaPayload['id'] = $mediaUrlOrId;
        } else {
            $mediaPayload['link'] = $mediaUrlOrId;
        }

        if ($caption && in_array($mediaType, ['image', 'video', 'document'])) {
            $mediaPayload['caption'] = $caption;
        }

        if ($filename && $mediaType === 'document') {
            $mediaPayload['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $mediaType,
            $mediaType => $mediaPayload,
        ];

        if ($contextMessageId) {
            $payload['context'] = [
                'message_id' => $contextMessageId,
            ];
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            $messageId = $responseData['messages'][0]['id'] ?? null;

            // Create or update message entry
            $message = $dbMessageId ? WhatsAppMessage::find($dbMessageId) : new WhatsAppMessage();
            $message->fill([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'message_id' => $messageId,
                'status' => $response->successful() ? 'sent' : 'failed',
                'direction' => 'outgoing',
                'type' => $mediaType,
                'content' => ['caption' => $caption ?? '', 'media_type' => $mediaType],
                'media_url' => $isMediaId ? null : $mediaUrlOrId,
                'media_id' => $isMediaId ? $mediaUrlOrId : null,
                'context_message_id' => $contextMessageId,
                'campaign_id' => $campaignId,
                'sent_at' => $response->successful() ? now() : null,
                'failed_at' => $response->successful() ? null : now(),
                'failed_reason' => $response->successful() ? null : ($responseData['error']['message'] ?? 'API error'),
            ]);
            $message->save();

            if (!$response->successful()) {
                Log::error('WhatsApp Media Send Error: ' . json_encode($responseData));
                return ['success' => false, 'error' => $responseData['error']['message'] ?? 'Unknown error'];
            }

            return ['success' => true, 'message_id' => $messageId];
        } catch (\Exception $e) {
            Log::error('WhatsApp Media Send Exception: ' . $e->getMessage());

            $message = $dbMessageId ? WhatsAppMessage::find($dbMessageId) : new WhatsAppMessage();
            $message->fill([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'direction' => 'outgoing',
                'type' => $mediaType,
                'content' => ['caption' => $caption ?? '', 'media_type' => $mediaType],
                'media_url' => $isMediaId ? null : $mediaUrlOrId,
                'media_id' => $isMediaId ? $mediaUrlOrId : null,
                'context_message_id' => $contextMessageId,
                'campaign_id' => $campaignId,
                'status' => 'failed',
                'failed_at' => now(),
                'failed_reason' => $e->getMessage(),
            ]);
            $message->save();

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a reaction message using Meta Cloud API.
     */
    public function sendReactionMessage(string $to, string $messageId, string $emoji): ?array
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return null;
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji,
            ],
        ];

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'message_id' => $responseData['messages'][0]['id'] ?? null];
            }

            return ['success' => false, 'error' => $responseData['error']['message'] ?? 'Unknown error'];
        } catch (\Exception $e) {
            Log::error('WhatsApp Reaction Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Message Read Receipt
    |--------------------------------------------------------------------------
    */

    /**
     * Mark an incoming message as read on Meta's side.
     */
    public function markAsRead(string $messageId): bool
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return false;
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp Mark Read Exception: ' . $e->getMessage());
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Media Download
    |--------------------------------------------------------------------------
    */

    /**
     * Get the download URL for a media file from Meta.
     */
    public function getMediaUrl(string $mediaId): ?string
    {
        if (!$this->accessToken) {
            return null;
        }

        $endpoint = $this->apiUrl . $mediaId;

        try {
            $response = Http::withToken($this->accessToken)->get($endpoint);

            if ($response->successful()) {
                return $response->json()['url'] ?? null;
            }

            Log::error('WhatsApp Get Media URL Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Get Media URL Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download media content from Meta and return the binary content.
     */
    public function downloadMedia(string $mediaUrl): ?string
    {
        if (!$this->accessToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withOptions(['stream' => true])
                ->get($mediaUrl);

            if ($response->successful()) {
                return $response->body();
            }

            Log::error('WhatsApp Download Media Error: ' . $response->status());
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Download Media Exception: ' . $e->getMessage());
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Business Profile Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get the business profile information.
     */
    public function getBusinessProfile(): ?array
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return null;
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/whatsapp_business_profile';

        try {
            $response = Http::withToken($this->accessToken)->get($endpoint, [
                'fields' => 'about,address,description,email,profile_picture_url,websites,vertical',
            ]);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? [];
                return $data[0] ?? null;
            }

            Log::error('WhatsApp Get Business Profile Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Get Business Profile Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update business profile information.
     */
    public function updateBusinessProfile(array $data): array
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return ['success' => false, 'error' => 'API credentials not configured.'];
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/whatsapp_business_profile';

        $allowedFields = ['about', 'address', 'description', 'email', 'websites', 'vertical'];
        $payload = ['messaging_product' => 'whatsapp'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $payload[$field] = $data[$field];
            }
        }

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'data' => $responseData];
            }

            return [
                'success' => false,
                'error' => $responseData['error']['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Update Business Profile Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload media file to Meta Cloud API and return the media ID.
     */
    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::error('WhatsApp Media Upload: API credentials not configured.');
            return null;
        }

        if (!file_exists($filePath)) {
            Log::error("WhatsApp Media Upload: File does not exist at {$filePath}");
            return null;
        }

        $endpoint = $this->apiUrl . $this->phoneNumberId . '/media';

        try {
            $response = Http::withToken($this->accessToken)
                ->attach('file', file_get_contents($filePath), basename($filePath), [
                    'Content-Type' => $mimeType
                ])
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['id'])) {
                return $responseData['id'];
            }

            Log::error('WhatsApp Media Upload Error: ' . json_encode($responseData));
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Media Upload Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update business profile photo.
     */
    public function updateBusinessProfilePhoto(string $filePath): array
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return ['success' => false, 'error' => 'API credentials not configured.'];
        }

        // First upload the photo
        $mediaId = $this->uploadMedia($filePath, 'image/jpeg');

        if (!$mediaId) {
            return ['success' => false, 'error' => 'Failed to upload profile photo.'];
        }

        // Then set it as profile picture
        $endpoint = $this->apiUrl . $this->phoneNumberId . '/whatsapp_business_profile';

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'profile_picture_handle' => $mediaId,
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'data' => $responseData];
            }

            return [
                'success' => false,
                'error' => $responseData['error']['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Update Profile Photo Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get app subscription details from Meta.
     */
    public function getAppSubscription(): ?array
    {
        if (!$this->businessAccountId || !$this->accessToken) {
            return null;
        }

        $endpoint = $this->apiUrl . $this->businessAccountId . '/subscribed_apps';

        try {
            $response = Http::withToken($this->accessToken)->get($endpoint);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    foreach ($data['data'] as $sub) {
                        if (isset($sub['override_callback_uri'])) {
                            return [
                                'callback_url' => $sub['override_callback_uri']
                            ];
                        }
                    }
                }
                return null;
            }
            Log::error('WhatsApp Get App Subscription Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Get App Subscription Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update app subscription details on Meta.
     */
    public function updateAppSubscription(string $callbackUrl, string $verifyToken): array
    {
        if (!$this->businessAccountId || !$this->accessToken) {
            return ['success' => false, 'error' => 'Business Account ID or Access Token not configured.'];
        }

        $endpoint = $this->apiUrl . $this->businessAccountId . '/subscribed_apps';

        $payload = [
            'override_callback_uri' => $callbackUrl,
            'verify_token' => $verifyToken,
        ];

        try {
            $response = Http::withToken($this->accessToken)->post($endpoint, $payload);
            $responseData = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'data' => $responseData];
            }

            return [
                'success' => false,
                'error' => $responseData['error']['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Update App Subscription Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

