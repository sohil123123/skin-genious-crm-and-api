<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\DownloadReportActions;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserReportPdfService;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Actions\Action;

use App\Models\User;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string | Htmlable
    {
        /** @var User */
        $record = $this->getRecord();

        return $record->name;
    }

    protected function getActions(): array
    {
        return [];
    }

    public function getHeaderActions(): array
    {
        // One query for all four counts, rather than one per download button.
        $this->getRecord()->loadCount(UserReportPdfService::countRelations());

        return [
            // Every report shown as its own button, not tucked into a menu;
            // each hides itself when the client has nothing in it.
            ...DownloadReportActions::make(),

            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(UserResource::getUrl('index')),
        ];
    }
}
