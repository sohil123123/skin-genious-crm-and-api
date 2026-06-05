<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Support\Facades\Log;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // // When an invoice is created, if it's unpaid or we just want to notify them
        // // Let's send a WhatsApp message if the client has a phone number.

        // $client = $invoice->client;
        // if (!$client || !($client->mobile ?? $client->phone)) {
        //     return;
        // }

        // $components = [
        //     [
        //         'type' => 'body',
        //         'parameters' => [
        //             ['type' => 'text', 'text' => $client->name],
        //             ['type' => 'text', 'text' => $invoice->invoice_number],
        //             ['type' => 'text', 'text' => "₹" . number_format($invoice->grand_total, 2)],
        //             ['type' => 'text', 'text' => $invoice->status],
        //         ],
        //     ]
        // ];

        // try {
        //     SendWhatsAppMessageJob::dispatch(
        //         $client->mobile ?? $client->phone,
        //         'invoice_notification',
        //         $components,
        //         'en_US',
        //         $client->id
        //     );
        // } catch (\Exception $e) {
        //     Log::error('Failed to dispatch invoice WhatsApp job: ' . $e->getMessage());
        // }
    }
}
