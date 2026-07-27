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
use Illuminate\Support\Facades\Storage;

class DownloadWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $messageId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
        $this->onQueue('whatsapp-media');
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        $message = WhatsAppMessage::find($this->messageId);

        if (!$message || !$message->media_id) {
            Log::warning("DownloadWhatsAppMedia: Message #{$this->messageId} not found or has no media_id.");
            return;
        }

        // Skip if already downloaded
        if ($message->isMediaDownloaded()) {
            return;
        }

        // Step 1: Get the temporary download URL from Meta
        $mediaUrl = $whatsAppService->getMediaUrl($message->media_id);

        if (!$mediaUrl) {
            Log::error("DownloadWhatsAppMedia: Could not get URL for media_id: {$message->media_id}");
            return;
        }

        // Step 2: Download the media content
        $content = $whatsAppService->downloadMedia($mediaUrl);

        if (!$content) {
            Log::error("DownloadWhatsAppMedia: Could not download media from URL.");
            return;
        }

        // Step 3: Determine filename and path
        $extension = $this->getExtensionFromMimeType($message->media_mime_type ?? '');
        $filename = $message->media_filename ?? ($message->media_id . '.' . $extension);
        $directory = 'whatsapp-media/' . date('Y/m');
        $path = $directory . '/' . $filename;

        // Step 4: Store the file
        Storage::disk('public')->put($path, $content);

        // Step 5: Update the message record
        $message->update([
            'local_media_path' => $path,
            'media_url' => $mediaUrl,
        ]);

        Log::info("WhatsApp media downloaded: {$path} for message #{$this->messageId}");
    }

    /**
     * Get file extension from MIME type.
     */
    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/ogg' => 'ogg',
            'audio/ogg; codecs=opus' => 'ogg',
            'application/pdf' => 'pdf',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
        ];

        return $map[$mimeType] ?? 'bin';
    }
}
