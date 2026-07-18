<?php

namespace App\Filament\Resources\InvoicePayments\Pages;

use App\Filament\Resources\InvoicePayments\InvoicePaymentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\LoyaltyPointService;

class CreateInvoicePayment extends CreateRecord
{
    protected static string $resource = InvoicePaymentResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $payments = $data['payments'] ?? [];
        $firstPayment = null;

        if (empty($payments)) {
            $data['created_by'] = auth()->id();
            return static::getModel()::create($data);
        }

        // Sort payments so loyalty_points redemptions are processed first
        usort($payments, function ($a, $b) {
            $aIsLoyalty = ($a['payment_method'] ?? '') === 'loyalty_points';
            $bIsLoyalty = ($b['payment_method'] ?? '') === 'loyalty_points';
            if ($aIsLoyalty && !$bIsLoyalty) {
                return -1;
            }
            if (!$aIsLoyalty && $bIsLoyalty) {
                return 1;
            }
            return 0;
        });

        $invoice = Invoice::find($data['invoice_id']);
        $loyaltyService = app(LoyaltyPointService::class);

        foreach ($payments as $paymentData) {
            if (($paymentData['payment_method'] ?? '') === 'loyalty_points' && $invoice) {
                // Use the loyalty service to handle redemption logic
                $result = $loyaltyService->redeemPoints(
                    $invoice->client,
                    $invoice->id,
                    (int) $paymentData['amount'],
                    auth()->id()
                );
                $record = $result['payment'];
            } else {
                // Standard payment creation
                $record = static::getModel()::create([
                    'invoice_id' => $data['invoice_id'],
                    'payment_date' => $data['payment_date'],
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method'],
                    'reference_number' => $paymentData['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            if (!$firstPayment) {
                $firstPayment = $record;
            }
        }

        return $firstPayment ?? new InvoicePayment();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
