<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Support\Facades\Log;

class InvoicePaymentObserver
{
    /**
     * Handle the InvoicePayment "created" event.
     */
    public function created(InvoicePayment $payment): void
    {
        // $invoice = $payment->invoice;
        // if (!$invoice) return;

        // $client = $invoice->client;
        // if (!$client || !($client->mobile ?? $client->phone)) {
        //     return;
        // }

        // $components = [
        //     [
        //         'type' => 'body',
        //         'parameters' => [
        //             ['type' => 'text', 'text' => $client->name],
        //             ['type' => 'text', 'text' => "₹" . number_format($payment->amount, 2)],
        //             ['type' => 'text', 'text' => $invoice->invoice_number],
        //             ['type' => 'text', 'text' => "₹" . number_format($invoice->grand_total - $invoice->amount_paid, 2)],
        //         ],
        //     ]
        // ];

        // try {
        //     SendWhatsAppMessageJob::dispatch(
        //         $client->mobile ?? $client->phone,
        //         'payment_receipt',
        //         $components,
        //         'en_US',
        //         $client->id
        //     );
        // } catch (\Exception $e) {
        //     Log::error('Failed to dispatch payment WhatsApp job: ' . $e->getMessage());
        // }
    }
}
