<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppCampaignService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
        $this->onQueue('whatsapp-campaigns');
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService, WhatsAppCampaignService $campaignService): void
    {
        $campaign = WhatsAppCampaign::find($this->campaignId);

        if (!$campaign) {
            Log::error("WhatsApp Campaign #{$this->campaignId} not found.");
            return;
        }

        if ($campaign->status->value === 'cancelled') {
            Log::info("WhatsApp Campaign #{$this->campaignId} was cancelled.");
            return;
        }

        $template = $campaign->template;

        if (!$template) {
            Log::error("WhatsApp Campaign #{$this->campaignId} has no template assigned.");
            $campaign->update(['status' => 'cancelled']);
            return;
        }

        $recipients = $campaign->recipients()->where('status', 'pending')->get();

        if ($recipients->isEmpty()) {
            $campaign->markAsCompleted();
            return;
        }

        Log::info("Processing WhatsApp Campaign #{$this->campaignId}: {$recipients->count()} recipients");

        $variableMapping = $campaign->template_variables ?? [];

        // Extract header variables (e.g. media_id for image templates) from audience_filter
        $audienceFilter = $campaign->audience_filter ?? [];
        $headerVariables = $audienceFilter['__header_variables'] ?? [];

        foreach ($recipients as $recipient) {
            // Rate limiting: 80 messages per second is Meta's limit, we'll be conservative
            usleep(100000); // 100ms delay between messages (10/sec)

            // Check if campaign was cancelled mid-processing
            $campaign->refresh();
            if ($campaign->status->value === 'cancelled') {
                Log::info("Campaign #{$this->campaignId} cancelled mid-processing.");
                return;
            }

            try {
                // Resolve variables for this recipient
                $resolvedVariables = [];
                if (!empty($variableMapping) && $recipient->user) {
                    $resolvedVariables = $campaignService->resolveVariablesForUser(
                        $variableMapping,
                        $recipient->user
                    );
                }

                // Build components for sending (with header variables for image/media templates)
                $components = $template->buildComponentsForSending(
                    $resolvedVariables,
                    $headerVariables
                );

                // Send the message
                $log = $whatsAppService->sendTemplateMessage(
                    $recipient->phone_number,
                    $template->name,
                    $template->language ?? 'en_US',
                    $components,
                    $recipient->user_id,
                    $this->campaignId
                );

                if ($log && $log->message_id) {
                    $recipient->markAsSent($log->message_id);
                    $campaign->increment('sent_count');
                } else {
                    $recipient->markAsFailed('API send failed');
                    $campaign->increment('failed_count');
                }

                // Store variables used
                $recipient->update(['variables_used' => $resolvedVariables]);
            } catch (\Exception $e) {
                Log::error("Campaign #{$this->campaignId} recipient #{$recipient->id} error: " . $e->getMessage());
                $recipient->markAsFailed($e->getMessage());
                $campaign->increment('failed_count');
            }
        }

        // Check if all recipients have been processed
        $pendingCount = $campaign->recipients()->where('status', 'pending')->count();

        if ($pendingCount === 0) {
            $campaign->markAsCompleted();
            Log::info("WhatsApp Campaign #{$this->campaignId} completed.");
        }
    }
}
