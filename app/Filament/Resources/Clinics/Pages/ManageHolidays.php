<?php

namespace App\Filament\Resources\Clinics\Pages;

use App\Filament\Resources\Clinics\ClinicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Actions\Action;

use App\Filament\Resources\Clinics\RelationManagers\HolidaysRelationManager;

class ManageHolidays extends ManageRelatedRecords
{
    protected static string $resource = ClinicResource::class;

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
                ->url(ClinicResource::getUrl('index')),
        ];
    }
}
