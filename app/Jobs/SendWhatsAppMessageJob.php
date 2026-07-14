<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $templateName;
    public string $languageCode;
    public array $components;
    public ?int $userId;
    public string $type;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $to,
        string $templateName,
        array $components = [],
        string $languageCode = 'en_US',
        ?int $userId = null,
        string $type = 'template'
    ) {
        $this->to = $to;
        $this->templateName = $templateName;
        $this->components = $components;
        $this->languageCode = $languageCode;
        $this->userId = $userId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        if ($this->type === 'text') {
            $whatsappService->sendTextMessage(
                $this->to,
                $this->templateName, // contains the raw text body
                $this->userId
            );
        } else {
            $whatsappService->sendTemplateMessage(
                $this->to,
                $this->templateName,
                $this->languageCode,
                $this->components,
                $this->userId
            );
        }
    }
}
