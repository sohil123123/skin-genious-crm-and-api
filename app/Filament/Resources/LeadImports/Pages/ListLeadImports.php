<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadImports\Pages;

use App\Filament\Pages\LeadImportWizard;
use App\Filament\Resources\LeadImports\LeadImportResource;
use App\Filament\Widgets\LeadImportStatsOverview;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListLeadImports extends ListRecords
{
    protected static string $resource = LeadImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newImport')
                ->label('New import')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(LeadImportWizard::getUrl())
                ->visible(fn (): bool => LeadImportWizard::canAccess()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LeadImportStatsOverview::class,
        ];
    }
}
