<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledWhatsAppMessageJob;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppScheduledMessage;
use App\Services\WhatsAppCampaignService;
use Illuminate\Console\Command;

class ProcessWhatsAppScheduled extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch job to process all due scheduled/recurring WhatsApp messages and campaigns';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (WhatsAppScheduledMessage::dueForSending()->exists()) {
            $this->info('Dispatching Scheduled WhatsApp Messages job...');
            ProcessScheduledWhatsAppMessageJob::dispatch();
        } else {
            $this->info('No due scheduled WhatsApp messages found.');
        }
        
        $this->info('Checking for due scheduled WhatsApp campaigns...');
        $dueCampaigns = WhatsAppCampaign::readyToSend()->get();
        if ($dueCampaigns->isNotEmpty()) {
            $campaignService = app(WhatsAppCampaignService::class);
            foreach ($dueCampaigns as $campaign) {
                $this->info("Executing campaign: {$campaign->name}");
                $campaignService->executeCampaign($campaign);
            }
        }

        $this->info('Scheduled tasks processing dispatched.');
    }
}
