<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\InvoicePayments\InvoicePaymentResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

class ManageInvoicePayments extends ManageRelatedRecords
{
    protected static string $resource = InvoiceResource::class;

    protected static string $relationship = 'payments';

    protected static ?string $relatedResource = InvoicePaymentResource::class;

    protected static ?string $navigationLabel = 'Payment History';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->using(function (array $data, string $model): \Illuminate\Database\Eloquent\Model {
                        $payments = $data['payments'] ?? [];
                        $firstPayment = null;

                        if (empty($payments)) {
                            $data['created_by'] = auth()->id();
                            return $model::create($data);
                        }

                        $invoiceId = $this->getOwnerRecord()->id;

                        foreach ($payments as $paymentData) {
                            $record = $model::create([
                                'invoice_id' => $invoiceId,
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

                        return $firstPayment ?? new $model();
                    }),
            ]);
    }
}
