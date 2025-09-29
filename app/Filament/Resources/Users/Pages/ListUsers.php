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
            'all' => Tab::make('All')
                ->badge($this->getModel()::count()),

            'admin' => Tab::make('Admins')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'admin')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count()),

            'therapist' => Tab::make('Therapists')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'therapist')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))->count()),

            'user' => Tab::make('users')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'user')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'user'))->count()),
        ];
    }
}
