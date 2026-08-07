<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Pages\LeadImportWizard;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Widgets\LeadStatsOverview;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import leads')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(LeadImportWizard::getUrl())
                ->visible(fn (): bool => LeadImportWizard::canAccess()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LeadStatsOverview::class,
        ];
    }
}
