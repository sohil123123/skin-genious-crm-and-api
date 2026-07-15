<?php

namespace App\Jobs;

use App\Models\WhatsAppScheduledMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('whatsapp-scheduled');
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        $messages = WhatsAppScheduledMessage::dueForSending()->get();

        foreach ($messages as $scheduled) {
            $scheduled->markAsProcessing();

            try {
                switch ($scheduled->type) {
                    case 'template':
                        $template = WhatsAppTemplate::where('name', $scheduled->template_name)->first();
                        $components = [];
                        if ($template) {
                            $components = $template->buildComponentsForSending($scheduled->template_variables ?? []);
                        }

                        $log = $whatsAppService->sendTemplateMessage(
                            $scheduled->phone_number,
                            $scheduled->template_name,
                            'en_US',
                            $components,
                            $scheduled->user_id
                        );

                        if ($log && $log->message_id) {
                            if ($scheduled->is_recurring) {
                                $scheduled->calculateNextRun();
                            } else {
                                $scheduled->markAsCompleted();
                            }
                        } else {
                            $scheduled->update(['status' => 'failed']);
                        }
                        break;

                    case 'text':
                        $log = $whatsAppService->sendTextMessage(
                            $scheduled->phone_number,
                            $scheduled->content ?? '',
                            $scheduled->user_id
                        );

                        if ($log && $log->message_id) {
                            if ($scheduled->is_recurring) {
                                $scheduled->calculateNextRun();
                            } else {
                                $scheduled->markAsCompleted();
                            }
                        } else {
                            $scheduled->update(['status' => 'failed']);
                        }
                        break;

                    case 'media':
                        $media = $scheduled->mediaLibrary;
                        if ($media) {
                            $result = $whatsAppService->sendMediaMessage(
                                $scheduled->phone_number,
                                $media->type,
                                $media->isUploadedToMeta() ? $media->meta_media_id : $media->full_url,
                                $scheduled->content,
                                $media->file_name,
                                $scheduled->user_id,
                                $media->isUploadedToMeta()
                            );

                            if ($result && ($result['success'] ?? false)) {
                                if ($scheduled->is_recurring) {
                                    $scheduled->calculateNextRun();
                                } else {
                                    $scheduled->markAsCompleted();
                                }
                            } else {
                                $scheduled->update(['status' => 'failed']);
                            }
                        } else {
                            $scheduled->update(['status' => 'failed']);
                            Log::error("Scheduled message #{$scheduled->id}: media not found.");
                        }
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Scheduled message #{$scheduled->id} failed: " . $e->getMessage());
                $scheduled->update(['status' => 'failed']);
            }
        }
    }
}
