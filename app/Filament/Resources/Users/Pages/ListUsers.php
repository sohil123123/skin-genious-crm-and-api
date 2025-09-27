<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use App\Filament\Resources\Users\Widgets\UserStats;
use Filament\Schemas\Components\Tabs\Tab;

use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListUsers extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

     protected function getHeaderWidgets(): array
    {
        return UserResource::getWidgets();
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make()->query(fn ($query) => $query->where('is_active', true)),
            'inactive' => Tab::make()->query(fn ($query) => $query->where('is_active', false)),
        ];
    }
}
