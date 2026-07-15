<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $templateName;
    public string $languageCode;
    public array $components;
    public ?int $userId;
    public string $type;
    
    // Add additional properties for media and tracking
    public ?string $mediaType;
    public ?string $mediaUrlOrId;
    public ?string $caption;
    public ?string $filename;
    public bool $isMediaId;
    public ?int $dbMessageId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $to,
        string $templateName,
        array $components = [],
        string $languageCode = 'en_US',
        ?int $userId = null,
        string $type = 'template',
        ?string $mediaType = null,
        ?string $mediaUrlOrId = null,
        ?string $caption = null,
        ?string $filename = null,
        bool $isMediaId = false,
        ?int $dbMessageId = null
    ) {
        $this->to = $to;
        $this->templateName = $templateName;
        $this->components = $components;
        $this->languageCode = $languageCode;
        $this->userId = $userId;
        $this->type = $type;
        $this->mediaType = $mediaType;
        $this->mediaUrlOrId = $mediaUrlOrId;
        $this->caption = $caption;
        $this->filename = $filename;
        $this->isMediaId = $isMediaId;
        $this->dbMessageId = $dbMessageId;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        $dbMessage = $this->dbMessageId ? WhatsAppMessage::find($this->dbMessageId) : null;
        
        try {
            if ($this->type === 'text') {
                $log = $whatsappService->sendTextMessage(
                    $this->to,
                    $this->templateName, // contains the raw text body
                    $this->userId,
                    $dbMessage?->campaign_id,
                    $dbMessage?->conversation_id,
                    $dbMessage?->context_message_id,
                    $this->dbMessageId
                );
                
                if ($dbMessage) {
                    if ($log && $log->message_id) {
                        $dbMessage->markAsSent($log->message_id);
                    } else {
                        $dbMessage->markAsFailed($log ? 'API send failed' : 'Unknown error');
                    }
                }
            } elseif ($this->type === 'media') {
                $result = $whatsappService->sendMediaMessage(
                    $this->to,
                    $this->mediaType,
                    $this->mediaUrlOrId,
                    $this->caption,
                    $this->filename,
                    $this->userId,
                    $this->isMediaId,
                    $dbMessage?->campaign_id,
                    $dbMessage?->conversation_id,
                    $dbMessage?->context_message_id,
                    $this->dbMessageId
                );
                
                if ($dbMessage) {
                    if ($result && ($result['success'] ?? false)) {
                        $dbMessage->markAsSent($result['message_id']);
                    } else {
                        $dbMessage->markAsFailed($result['error'] ?? 'Media send failed');
                    }
                }
            } else {
                $log = $whatsappService->sendTemplateMessage(
                    $this->to,
                    $this->templateName,
                    $this->languageCode,
                    $this->components,
                    $this->userId,
                    $dbMessage?->campaign_id,
                    $dbMessage?->conversation_id,
                    $this->dbMessageId
                );
                
                if ($dbMessage) {
                    if ($log && $log->message_id) {
                        $dbMessage->markAsSent($log->message_id);
                    } else {
                        $dbMessage->markAsFailed('Template send failed');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("SendWhatsAppMessageJob error: " . $e->getMessage());
            if ($dbMessage) {
                $dbMessage->markAsFailed($e->getMessage());
            }
            throw $e;
        }
    }
}
