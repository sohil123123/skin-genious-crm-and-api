<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Models\WhatsAppTemplate;
use App\Models\Setting;
use App\Models\LoyaltyPointTransaction;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Support\Facades\Log;

class InvoicePaymentObserver
{
    /**
     * Handle the InvoicePayment "created" event.
     */
    public function created(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;
        if (!$invoice) {
            return;
        }

        $client = $invoice->client;
        if (!$client || !($client->mobile ?? $client->phone)) {
            return;
        }

        // Get the loyalty points transaction created for this specific client and payment
        $transaction = LoyaltyPointTransaction::where('invoice_payment_id', $payment->id)
            ->where('user_id', $client->id)
            ->where('type', 'earn')
            ->first();
        if (!$transaction) {
            return;
        }

        $currentPointsEarned = (int) $transaction->points;
        if ($currentPointsEarned <= 0) {
            return;
        }

        // Get total points directly from the transaction ledger to avoid memory model staleness
        $totalPoints = (int) $transaction->balance_after;

        // Find the loyalty points earn template from dynamic settings table
        $templateName = Setting::getValue('whatsapp_loyalty_earn_template_name', 'loyalty_points_earn_v1');
        $template = WhatsAppTemplate::where('name', $templateName)->first();
        if (!$template) {
            return;
        }

        $clientName = $client->name;

        // Build components for sending (body variables)
        $components = $template->buildComponentsForSending(
            // Body variables supporting both Named and Positional templates in Meta Cloud API
            [
                'client_name' => $clientName,
                'current_point_earn' => $currentPointsEarned,
                'total_point' => $totalPoints,
                
                // Positional fallbacks (order of placeholders: 1. Name, 2. Earned, 3. Total)
                0 => $clientName,
                1 => $currentPointsEarned,
                2 => $totalPoints,
                
                '1' => $clientName,
                '2' => $currentPointsEarned,
                '3' => $totalPoints,
            ],
            // Header variables
            [],
            // Button variables
            []
        );

        try {
            SendWhatsAppMessageJob::dispatch(
                $client->mobile ?? $client->phone,
                $template->name,
                $components,
                $template->language ?? 'en_US',
                $client->id
            );
        } catch (\Exception $e) {
            Log::error('Failed to dispatch loyalty points earn WhatsApp job: ' . $e->getMessage());
        }
    }
}
