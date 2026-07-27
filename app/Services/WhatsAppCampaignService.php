<?php

namespace App\Services;

use App\Enums\WhatsAppCampaignStatus;
use App\Jobs\ProcessWhatsAppCampaignJob;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WhatsAppCampaignService
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Build the audience list based on campaign settings.
     */
    public function buildAudience(WhatsAppCampaign $campaign): Collection
    {
        $query = User::query()->role('client')->where('is_active', true)->whereNotNull('mobile');

        switch ($campaign->audience_type) {
            case 'specific':
                if (!empty($campaign->audience_user_ids)) {
                    $query->whereIn('id', $campaign->audience_user_ids);
                }
                break;

            case 'filter':
                $filters = $campaign->audience_filter ?? [];

                if (!empty($filters['clinic_id'])) {
                    $query->where('clinic_id', $filters['clinic_id']);
                }
                if (!empty($filters['gender'])) {
                    $query->where('gender', $filters['gender']);
                }
                if (!empty($filters['city'])) {
                    $query->where('city', $filters['city']);
                }
                if (!empty($filters['state'])) {
                    $query->where('state', $filters['state']);
                }
                if (!empty($filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $filters['date_to']);
                }
                break;

            case 'all':
            default:
                // No additional filters
                break;
        }

        return $query->get();
    }

    /**
     * Populate campaign recipients from the audience.
     */
    public function populateRecipients(WhatsAppCampaign $campaign): int
    {
        $audience = $this->buildAudience($campaign);

        // Remove existing recipients if re-populating
        $campaign->recipients()->delete();

        $recipients = [];
        foreach ($audience as $user) {
            $recipients[] = [
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'phone_number' => preg_replace('/[^0-9]/', '', $user->mobile),
                'contact_name' => $user->name,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert in chunks
        foreach (array_chunk($recipients, 500) as $chunk) {
            WhatsAppCampaignRecipient::insert($chunk);
        }

        $count = count($recipients);

        $campaign->update(['total_recipients' => $count]);

        return $count;
    }

    /**
     * Execute a campaign — dispatch the processing job.
     */
    public function executeCampaign(WhatsAppCampaign $campaign): bool
    {
        if (!in_array($campaign->status, [WhatsAppCampaignStatus::Draft, WhatsAppCampaignStatus::Scheduled])) {
            Log::warning("Campaign #{$campaign->id} cannot be executed in status: {$campaign->status->value}");
            return false;
        }

        // Ensure recipients are populated
        if ($campaign->recipients()->count() === 0) {
            $this->populateRecipients($campaign);
        }

        $campaign->markAsSending();

        // Dispatch the campaign processing job
        ProcessWhatsAppCampaignJob::dispatch($campaign->id);

        return true;
    }

    /**
     * Duplicate an existing campaign.
     */
    public function duplicateCampaign(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        $newCampaign = $campaign->replicate();
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->status = WhatsAppCampaignStatus::Draft;
        $newCampaign->total_recipients = 0;
        $newCampaign->sent_count = 0;
        $newCampaign->delivered_count = 0;
        $newCampaign->read_count = 0;
        $newCampaign->failed_count = 0;
        $newCampaign->started_at = null;
        $newCampaign->completed_at = null;
        $newCampaign->scheduled_at = null;
        $newCampaign->created_by = auth()->id();
        $newCampaign->save();

        return $newCampaign;
    }

    /**
     * Get campaign analytics summary.
     */
    public function getCampaignAnalytics(WhatsAppCampaign $campaign): array
    {
        $campaign->refreshCounts();

        return [
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'read_count' => $campaign->read_count,
            'failed_count' => $campaign->failed_count,
            'pending_count' => $campaign->total_recipients - $campaign->sent_count - $campaign->failed_count,
            'progress_percentage' => $campaign->getProgressPercentage(),
            'success_rate' => $campaign->getSuccessRate(),
            'delivery_rate' => $campaign->getDeliveryRate(),
            'read_rate' => $campaign->getReadRate(),
        ];
    }

    /**
     * Resolve template variables for a specific user.
     */
    public function resolveVariablesForUser(array $variableMapping, User $user): array
    {
        $resolved = [];

        foreach ($variableMapping as $key => $field) {
            $resolved[$key] = match ($field) {
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'name', 'full_name' => $user->name ?? '',
                'mobile', 'phone' => $user->mobile ?? '',
                'email' => $user->email ?? '',
                'city' => $user->city ?? '',
                'state' => $user->state ?? '',
                'clinic' => $user->clinic?->name ?? '',
                default => is_string($field) ? $field : '',
            };
        }

        return $resolved;
    }
}
