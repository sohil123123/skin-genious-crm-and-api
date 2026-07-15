<?php

namespace App\Jobs;

use App\Services\WhatsAppRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('whatsapp-retries');
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppRetryService $retryService): void
    {
        $retryService->processRetryQueue();
        $retryService->processLogRetryQueue();
    }
}
