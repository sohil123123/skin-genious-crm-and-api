<?php

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Actions\Action;

use App\Filament\Resources\Assessments\RelationManagers\TreatmentSessionsRelationManager;

class ManageTreatmentSessions extends ManageRelatedRecords
{
    protected static string $resource = AssessmentResource::class;

    protected static string $relationship = 'treatmentSessions';

    protected static ?string $relatedResource = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-clipboard-document-list';

    public function getTitle(): string
    {
        return 'Manage treatment sessions for "' . $this->record->user->name.'"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Treatment Sessions';
    }

    public function getRelationManagers(): array
    {
        return [
            TreatmentSessionsRelationManager::class,
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
                ->url(url()->previous()),
        ];
    }

    // public static function canAccess(array $parameters = []): bool
    // {
    //     $record = $parameters['record'] ?? null;

    //     if (! $record) {
    //         return false;
    //     }

    //     return $record->user->hasRole('client');
    // }
}
