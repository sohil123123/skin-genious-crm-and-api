<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppService;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppWebhook extends Command
{
    protected $signature = 'whatsapp:sync-webhook';
    protected $description = 'Sync WhatsApp Webhook configuration to Meta';

    public function handle(): int
    {
        $callbackUrl = Setting::getValue('whatsapp_override_callback_url') ?: url('/api/whatsapp/webhook');
        $verifyToken = Setting::getValue('whatsapp_webhook_verify_token');

        if (!$callbackUrl || !$verifyToken) {
            $this->error('Webhook configuration missing.');
            return 1;
        }

        $whatsAppService = app(WhatsAppService::class);
        $result = $whatsAppService->updateAppSubscription($callbackUrl, $verifyToken);

        if ($result['success']) {
            $this->info('Webhook synced successfully.');
            Log::info('WhatsApp Webhook background sync success.');
        } else {
            $this->error('Sync failed: ' . ($result['error'] ?? 'Unknown error'));
            Log::warning('WhatsApp Webhook background sync failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        return 0;
    }
}
