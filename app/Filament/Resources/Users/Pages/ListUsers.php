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
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return UserResource::getWidgets();
    }

    // public function getTabs(): array
    // {
    //     return [
    //         'all' => Tab::make('All')
    //             ->badge($this->getModel()::count()),

    //         'admin' => Tab::make('Admins')
    //             ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'admin')))
    //             ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count()),

    //         'therapist' => Tab::make('Therapists')
    //             ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'therapist')))
    //             ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))->count()),

    //         'clinic_manager' => Tab::make('Clinic Managers')
    //             ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager')))
    //             ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager'))->count()),

    //         'doctor' => Tab::make('Doctors')
    //             ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'doctor')))
    //             ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))->count()),

    //         'user' => Tab::make('Users')
    //             ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'user')))
    //             ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'user'))->count()),
    //     ];
    // }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-users')
                ->badge($this->getModel()::count())
                ->badgeColor('gray'),

            'admin' => Tab::make('Admins')
                ->icon('heroicon-o-shield-check')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'admin')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count())
                ->badgeColor('danger'),

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

            'doctor' => Tab::make('Doctors')
                ->icon('heroicon-o-user-circle')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'doctor')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))->count())
                ->badgeColor('warning'),

            'user' => Tab::make('Users')
                ->icon('heroicon-o-user')
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'user')))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->where('name', 'user'))->count())
                ->badgeColor('gray'),
        ];
    }
}
