<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Pages\LeadImportWizard;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Widgets\LeadsByCampaignChart;
use App\Filament\Widgets\LeadsByConcernChart;
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

    /**
     * Charts sit below the table rather than above it: the header already
     * carries the stats row, and pushing the leads themselves below the fold
     * would make the list harder to work.
     */
    protected function getFooterWidgets(): array
    {
        return [
            LeadsByCampaignChart::class,
            LeadsByConcernChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
