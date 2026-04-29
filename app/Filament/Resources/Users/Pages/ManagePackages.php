<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\RelationManagers\UserPackagesRelationManager;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Actions\Action;
use BackedEnum;

class ManagePackages extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'packages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function getTitle(): string
    {
        return 'Manage Packages for "' . $this->record->name . '"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Packages';
    }

    public function getRelationManagers(): array
    {
        return [
            UserPackagesRelationManager::class,
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

        return $record->hasRole('client');
    }
}
