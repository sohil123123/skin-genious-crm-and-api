<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

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
                ->icon('heroicon-o-document-duplicate')
                ->badge(InvoiceResource::getEloquentQuery()->count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-arrow-path')
                ->query(fn ($query) => $query->where('status', 'pending'))
                ->badge(InvoiceResource::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),

            'paid' => Tab::make('Paid')
                ->icon('heroicon-o-check-circle')
                ->query(fn ($query) => $query->where('status', 'paid'))
                ->badge(InvoiceResource::getEloquentQuery()->where('status', 'paid')->count())
                ->badgeColor('success'),

            'unpaid' => Tab::make('Unpaid')
                ->icon('heroicon-o-exclamation-circle')
                ->query(fn ($query) => $query->where('status', 'unpaid'))
                ->badge(InvoiceResource::getEloquentQuery()->where('status', 'unpaid')->count())
                ->badgeColor('danger'),

            'partial' => Tab::make('Partial')
                ->icon('heroicon-o-clock')
                ->query(fn ($query) => $query->where('status', 'partial'))
                ->badge(InvoiceResource::getEloquentQuery()->where('status', 'partial')->count())
                ->badgeColor('warning'),

            'draft' => Tab::make('Draft')
                ->icon('heroicon-o-document')
                ->query(fn ($query) => $query->where('status', 'draft'))
                ->badge(InvoiceResource::getEloquentQuery()->where('status', 'draft')->count())
                ->badgeColor('gray'),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->query(fn ($query) => $query->where('status', 'cancelled'))
                ->badge(InvoiceResource::getEloquentQuery()->where('status', 'cancelled')->count())
                ->badgeColor('danger'),
        ];
    }
}
