<?php

declare(strict_types=1);

namespace App\Filament\Resources\CallProviderAgents\Pages;

use App\Filament\Resources\CallProviderAgents\CallProviderAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCallProviderAgents extends ListRecords
{
    protected static string $resource = CallProviderAgentResource::class;

    public function getSubheading(): ?string
    {
        return 'Each unmapped row is calls being recorded against a person the CRM cannot name.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add mapping')
                ->icon('heroicon-o-plus')
                ->modalWidth('2xl'),
        ];
    }
}
