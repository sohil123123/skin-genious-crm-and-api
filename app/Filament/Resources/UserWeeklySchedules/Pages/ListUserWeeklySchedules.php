<?php

namespace App\Filament\Resources\UserWeeklySchedules\Pages;

use App\Filament\Resources\UserWeeklySchedules\UserWeeklyScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class ListUserWeeklySchedules extends ListRecords
{
    protected static string $resource = UserWeeklyScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus')->label('New Schedule'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Define therapist working days and shifts';
    }

    public function getTable(): Table
    {
        return parent::getTable()->poll('5s');
    }

}
