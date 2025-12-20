<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use App\Filament\Resources\AvailabilityExceptions\AvailabilityExceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListAvailabilityExceptions extends ListRecords
{
    protected static string $resource = AvailabilityExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Define therapist availability';
    }

    public function getTable(): Table
    {
        return parent::getTable()->poll('5s');
    }

}
