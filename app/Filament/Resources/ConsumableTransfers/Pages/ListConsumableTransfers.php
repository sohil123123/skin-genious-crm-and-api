<?php

namespace App\Filament\Resources\ConsumableTransfers\Pages;

use App\Filament\Resources\ConsumableTransfers\ConsumableTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConsumableTransfers extends ListRecords
{
    protected static string $resource = ConsumableTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
