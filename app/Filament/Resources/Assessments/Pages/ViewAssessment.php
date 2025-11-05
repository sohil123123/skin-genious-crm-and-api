<?php

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

use Filament\Actions\Action;

class ViewAssessment extends ViewRecord
{
    protected static string $resource = AssessmentResource::class;

    public function getTitle(): string | Htmlable
    {
        /** @var User */
        $record = $this->getRecord();

        return 'View assessment for "' . $this->record->user->name.'"';
    }

    protected function getActions(): array
    {
        return [];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(AssessmentResource::getUrl('index')),
        ];
    }
}
