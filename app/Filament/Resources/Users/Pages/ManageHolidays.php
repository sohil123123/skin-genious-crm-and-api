<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;

use App\Filament\Resources\Users\RelationManagers\HolidaysRelationManager;

class ManageHolidays extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'holidays';

    protected static ?string $relatedResource = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function getTitle(): string
    {
        return 'Manage Holidays for "' . $this->record->name.'"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Holidays';
    }

    public function getRelationManagers(): array
    {
        return [
            HolidaysRelationManager::class,
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if (! $record) {
            return false;
        }

        return $record->hasRole('therapist');
    }
}
