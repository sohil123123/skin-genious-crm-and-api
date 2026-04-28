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
                CreateAction::make()->icon('heroicon-o-plus'),
            ]);
    }
}
