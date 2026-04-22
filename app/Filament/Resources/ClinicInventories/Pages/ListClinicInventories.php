<?php

namespace App\Filament\Resources\ClinicInventories\Pages;

use App\Filament\Resources\ClinicInventories\ClinicInventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClinicInventories extends ListRecords
{
    protected static string $resource = ClinicInventoryResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make(),
    //     ];
    // }
}
