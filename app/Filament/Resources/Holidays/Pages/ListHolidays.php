<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }

    public function getTable(): Table
    {
        return parent::getTable()->poll('5s');
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon(Heroicon::CalendarDays)
                ->badge($this->getModel()::count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pending')
                ->icon(Heroicon::Clock)
                ->query(fn ($query) => $query->where('status', 'pending'))
                ->badge($this->getModel()::where('status', 'pending')->count())
                ->badgeColor('info'),

            'approved' => Tab::make('Approved')
                ->icon(Heroicon::CheckCircle)
                ->query(fn ($query) => $query->where('status', 'approved'))
                ->badge($this->getModel()::where('status', 'approved')->count())
                ->badgeColor('success'),

            'rejected' => Tab::make('Rejected')
                ->icon(Heroicon::XCircle)
                ->query(fn ($query) => $query->where('status', 'rejected'))
                ->badge($this->getModel()::where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }
}
