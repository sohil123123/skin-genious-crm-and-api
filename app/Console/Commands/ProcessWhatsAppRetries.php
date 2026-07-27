<?php

namespace App\Console\Commands;

use App\Services\WhatsAppRetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppRetries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:process-retries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find failed WhatsApp messages due for retry and re-queue them';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppRetryService $retryService): void
    {
        $this->info('Starting WhatsApp retries processing...');
        
        $processed = $retryService->processRetryQueue();
        
        $this->info("Processed {$processed} messages.");
    }
}
