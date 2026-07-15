<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupWhatsAppMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:cleanup-media {days=30 : The age in days of media files to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old downloaded WhatsApp media files from local storage';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days = (int) $this->argument('days');
        $this->info("Cleaning up WhatsApp media files older than {$days} days...");

        $files = Storage::disk('public')->allFiles('whatsapp-media');
        $expiryTime = time() - ($days * 24 * 60 * 60);
        $deletedCount = 0;

        foreach ($files as $file) {
            $lastModified = Storage::disk('public')->lastModified($file);

            if ($lastModified < $expiryTime) {
                Storage::disk('public')->delete($file);
                $deletedCount++;
            }
        }

        $this->info("Successfully deleted {$deletedCount} old media files.");
        Log::info("WhatsApp media cleanup deleted {$deletedCount} files older than {$days} days.");
    }
}
