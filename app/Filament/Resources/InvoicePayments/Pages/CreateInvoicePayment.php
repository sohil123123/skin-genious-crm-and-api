<?php

namespace App\Filament\Resources\InvoicePayments\Pages;

use App\Filament\Resources\InvoicePayments\InvoicePaymentResource;
use Filament\Resources\Pages\CreateRecord;

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

        foreach ($payments as $paymentData) {
            $record = static::getModel()::create([
                'invoice_id' => $data['invoice_id'],
                'payment_date' => $data['payment_date'],
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['payment_method'],
                'reference_number' => $paymentData['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (!$firstPayment) {
                $firstPayment = $record;
            }
        }

        return $firstPayment ?? new \App\Models\InvoicePayment();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
