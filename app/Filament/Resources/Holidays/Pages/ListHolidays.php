<?php

namespace App\Filament\Resources\Holidays\Pages;

use App\Filament\Resources\Holidays\HolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-users')
                ->badge($this->getModel()::count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-m-sparkles')
                ->query(fn ($query) => $query->where('status', 'pending'))
                ->badge($this->getModel()::where('status', 'pending')->count())
                ->badgeColor('info'),

            'approved' => Tab::make('Approved')
                ->icon('heroicon-m-arrow-path')
                ->query(fn ($query) => $query->where('status', 'approved'))
                ->badge($this->getModel()::where('status', 'approved')->count())
                ->badgeColor('success'),

            'rejected' => Tab::make('Rejected')
                ->icon('heroicon-m-x-circle')
                ->query(fn ($query) => $query->where('status', 'rejected'))
                ->badge($this->getModel()::where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }
}
