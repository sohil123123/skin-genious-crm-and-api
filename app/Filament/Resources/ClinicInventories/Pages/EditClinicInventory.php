<?php

namespace App\Filament\Resources\ClinicInventories\Pages;

use App\Filament\Resources\ClinicInventories\ClinicInventoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClinicInventory extends EditRecord
{
    protected static string $resource = ClinicInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
