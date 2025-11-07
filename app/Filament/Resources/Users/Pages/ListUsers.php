<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

// use App\Filament\Resources\Users\Widgets\UserStats;
use Filament\Schemas\Components\Tabs\Tab;

use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListUsers extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-user-plus'),
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
                ->icon('heroicon-o-users')
                ->badge($this->getModel()::count())
                ->badgeColor('gray'),
            
            'client' => Tab::make('Clients')
                ->icon('heroicon-o-user')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'client')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'client'))->count())
                ->badgeColor('gray'),

            'therapist' => Tab::make('Therapists')
                ->icon('heroicon-o-hand-raised') // alt: heroicon-o-heart
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'therapist')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))->count())
                ->badgeColor('success'),

            'clinic_manager' => Tab::make('Clinic Managers')
                ->icon('heroicon-o-building-office')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager'))->count())
                ->badgeColor('info'),

            'super_admin' => Tab::make('Super Admins')
                ->icon('heroicon-o-shield-check')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'super_admin')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->count())
                ->badgeColor('danger'),

            
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'client'; // Default selected tab
    }
}
