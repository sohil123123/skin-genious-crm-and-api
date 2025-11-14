<?php

namespace App\Filament\Resources\Clinics\Pages;

use App\Filament\Resources\Clinics\ClinicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Actions\Action;

use App\Filament\Resources\Clinics\RelationManagers\ClientsRelationManager;

class ManageClients extends ManageRelatedRecords
{
    protected static string $resource = ClinicResource::class;

    protected static string $relationship = 'clients';

    protected static ?string $relatedResource = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    public function getTitle(): string
    {
        return 'Manage Clients for "' . $this->record->name.'"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Clients';
    }

    public function getRelationManagers(): array
    {
        return [
            ClientsRelationManager::class,
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
                ->url(ClinicResource::getUrl('index')),
        ];
    }

    // public static function canAccess(array $parameters = []): bool
    // {
    //     $record = $parameters['record'] ?? null;

    //     if (! $record) {
    //         return false;
    //     }

    //     return $record->hasRole('therapist');
    // }
}
