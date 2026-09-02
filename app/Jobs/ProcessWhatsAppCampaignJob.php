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
use Illuminate\Support\Facades\Storage;

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

        $messageType = $campaign->message_type ?? 'template';

        if ($messageType === 'media') {
            $this->processMediaCampaign($campaign, $whatsAppService);
        } else {
            $this->processTemplateCampaign($campaign, $whatsAppService, $campaignService);
        }
    }

    /**
     * Process template message campaign.
     */
    protected function processTemplateCampaign(
        WhatsAppCampaign $campaign,
        WhatsAppService $whatsAppService,
        WhatsAppCampaignService $campaignService
    ): void {
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

        Log::info("Processing Template Campaign #{$this->campaignId}: {$recipients->count()} recipients");

        $variableMapping = $campaign->template_variables ?? [];

        // Upload header image to Meta if template has IMAGE header and image path is stored
        $headerVariables = [];
        if (strtolower($template->header_type ?? '') === 'image' && !empty($campaign->header_image_path)) {
            $fullPath = Storage::disk('public')->path($campaign->header_image_path);

            if (file_exists($fullPath)) {
                $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
                $mediaId = $whatsAppService->uploadMedia($fullPath, $mimeType);

                if ($mediaId) {
                    $headerVariables = ['media_id' => $mediaId];
                    Log::info("Campaign #{$this->campaignId}: Header image uploaded, media_id: {$mediaId}");
                } else {
                    Log::warning("Campaign #{$this->campaignId}: Failed to upload header image to Meta.");
                }
            } else {
                Log::warning("Campaign #{$this->campaignId}: Header image file not found at {$fullPath}");
            }
        }

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

        $this->checkCompletion($campaign);
    }

    /**
     * Process media message campaign.
     */
    protected function processMediaCampaign(
        WhatsAppCampaign $campaign,
        WhatsAppService $whatsAppService
    ): void {
        $media = $campaign->mediaLibrary;

        if (!$media) {
            Log::error("WhatsApp Campaign #{$this->campaignId} has no media file assigned.");
            $campaign->update(['status' => 'cancelled']);
            return;
        }

        $recipients = $campaign->recipients()->where('status', 'pending')->get();

        if ($recipients->isEmpty()) {
            $campaign->markAsCompleted();
            return;
        }

        Log::info("Processing Media Campaign #{$this->campaignId}: {$recipients->count()} recipients");

        // Determine media URL and type
        $mediaType = $media->type ?? 'image';
        $mediaUrl = $media->full_url;
        $caption = $campaign->media_caption;

        foreach ($recipients as $recipient) {
            // Rate limiting
            usleep(100000); // 100ms delay between messages (10/sec)

            // Check if campaign was cancelled mid-processing
            $campaign->refresh();
            if ($campaign->status->value === 'cancelled') {
                Log::info("Campaign #{$this->campaignId} cancelled mid-processing.");
                return;
            }

            try {
                $result = $whatsAppService->sendMediaMessage(
                    $recipient->phone_number,
                    $mediaType,
                    $mediaUrl,
                    $caption,
                    $media->file_name,
                    $recipient->user_id,
                    false,
                    $this->campaignId
                );

                if ($result && ($result['success'] ?? false)) {
                    $messageId = $result['message_id'] ?? '';
                    $recipient->markAsSent($messageId);
                    $campaign->increment('sent_count');
                } else {
                    $recipient->markAsFailed($result['error'] ?? 'API send failed');
                    $campaign->increment('failed_count');
                }
            } catch (\Exception $e) {
                Log::error("Campaign #{$this->campaignId} recipient #{$recipient->id} media error: " . $e->getMessage());
                $recipient->markAsFailed($e->getMessage());
                $campaign->increment('failed_count');
            }
        }

        $this->checkCompletion($campaign);
    }

    /**
     * Check if all recipients have been processed and mark campaign as completed.
     */
    protected function checkCompletion(WhatsAppCampaign $campaign): void
    {
        $pendingCount = $campaign->recipients()->where('status', 'pending')->count();

        if ($pendingCount === 0) {
            $campaign->markAsCompleted();
            Log::info("WhatsApp Campaign #{$this->campaignId} completed.");
        }
    }
}
