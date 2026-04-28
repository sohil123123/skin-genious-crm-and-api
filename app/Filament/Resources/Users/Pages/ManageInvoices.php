<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Actions\Action;
use BackedEnum;

class ManageInvoices extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'invoices';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public function getTitle(): string
    {
        return 'Manage Invoices for "' . $this->record->name . '"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }

    public function getRelationManagers(): array
    {
        return [
            InvoicesRelationManager::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(UserResource::getUrl('index')),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if (!$record) {
            return false;
        }

        // Assuming both clients and therapists can have invoices, or maybe just clients
        // Adjust based on your business logic. 
        return $record->hasRole('client');
    }
}
