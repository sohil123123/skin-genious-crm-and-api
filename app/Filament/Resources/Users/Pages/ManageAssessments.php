<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Actions\Action;

use App\Filament\Resources\Users\RelationManagers\AssessmentsRelationManager;

class ManageAssessments extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'assessments';

    protected static ?string $relatedResource = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document';

    public function getTitle(): string
    {
        return 'Manage assessments for "' . $this->record->name.'"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Assessments';
    }

    public function getRelationManagers(): array
    {
        return [
            AssessmentsRelationManager::class,
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

            // Action::make('new_assessment')
            //     ->label('New Assessment')
            //     ->visible(fn ($record) => $record->hasRole('client'))
            //     ->icon('heroicon-o-clipboard-document')
            //     ->action(function ($record) {
            //         $assessmentUrl = new_assessment($record);
            //         return redirect($assessmentUrl);
            //     }),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if (! $record) {
            return false;
        }

        return $record->hasRole('client');
    }
}
