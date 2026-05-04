<?php

namespace App\Filament\Resources\InvoicePayments\Pages;

use App\Filament\Resources\InvoicePayments\InvoicePaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;

class ListInvoicePayments extends ListRecords
{
    protected static string $resource = InvoicePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-banknotes')
                ->badge(InvoicePaymentResource::getEloquentQuery()->count())
                ->badgeColor('gray'),

            'cash' => Tab::make('Cash')
                ->icon('heroicon-o-currency-rupee')
                ->query(fn ($query) => $query->where('payment_method', 'cash'))
                ->badge(InvoicePaymentResource::getEloquentQuery()->where('payment_method', 'cash')->count())
                ->badgeColor('success'),

            'card' => Tab::make('Card')
                ->icon('heroicon-o-credit-card')
                ->query(fn ($query) => $query->where('payment_method', 'card'))
                ->badge(InvoicePaymentResource::getEloquentQuery()->where('payment_method', 'card')->count())
                ->badgeColor('warning'),

            'upi' => Tab::make('UPI')
                ->icon('heroicon-o-device-phone-mobile')
                ->query(fn ($query) => $query->where('payment_method', 'upi'))
                ->badge(InvoicePaymentResource::getEloquentQuery()->where('payment_method', 'upi')->count())
                ->badgeColor('info'),

            'bank_transfer' => Tab::make('Bank Transfer')
                ->icon('heroicon-o-building-library')
                ->query(fn ($query) => $query->where('payment_method', 'bank_transfer'))
                ->badge(InvoicePaymentResource::getEloquentQuery()->where('payment_method', 'bank_transfer')->count())
                ->badgeColor('gray'),

            'loyalty_points' => Tab::make('Loyalty Points')
                ->icon('heroicon-o-star')
                ->query(fn ($query) => $query->where('payment_method', 'loyalty_points'))
                ->badge(InvoicePaymentResource::getEloquentQuery()->where('payment_method', 'loyalty_points')->count())
                ->badgeColor('primary'),

            'other' => Tab::make('Other')
                ->icon('heroicon-o-ellipsis-horizontal-circle')
                ->query(fn ($query) => $query->where('payment_method', 'other'))
                ->badge(InvoicePaymentResource::getEloquentQuery()->where('payment_method', 'other')->count())
                ->badgeColor('gray'),
        ];
    }
}
