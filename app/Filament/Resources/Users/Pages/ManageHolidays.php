<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Actions\Action;

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

        if (! $record) {
            return false;
        }

        return $record->hasRole('therapist');
    }
}
