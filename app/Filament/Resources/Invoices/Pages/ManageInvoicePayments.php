<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\InvoicePayments\InvoicePaymentResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\LoyaltyPointService;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Filament\Resources\InvoicePayments\Schemas\InvoicePaymentForm;

class ManageInvoicePayments extends ManageRelatedRecords
{
    protected static string $resource = InvoiceResource::class;

    protected static string $relationship = 'payments';

    protected static ?string $relatedResource = InvoicePaymentResource::class;

    protected static ?string $navigationLabel = 'Payment History';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected string $view = 'filament.resources.invoices.pages.manage-invoice-payments';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                InvoicePaymentForm::getMakePaymentAction('create')
                    ->label('New invoice payment')
                    ->hidden(fn () => in_array($this->getOwnerRecord()->status, ['paid', 'cancelled'])),
            ]);
    }
}

