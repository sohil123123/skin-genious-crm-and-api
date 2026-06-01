<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

// use App\Filament\Resources\Users\Widgets\UserStats;
use Filament\Schemas\Components\Tabs\Tab;
use App\Models\Role;

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
        $roles = Role::all();
        $tabs = [];

        $tabs['all'] = Tab::make('All')
            ->icon('heroicon-o-users')
            ->badge(UserResource::getEloquentQuery()->count())
            ->badgeColor('gray');

        foreach ($roles as $role) {
            $name = $role->name;
            $label = str($name)->headline()->plural();

            $icon = match ($name) {
                'client' => 'heroicon-o-user',
                'therapist' => 'heroicon-o-hand-raised',
                'clinic_manager' => 'heroicon-o-building-office',
                'super_admin' => 'heroicon-o-shield-check',
                'clinic_head' => 'heroicon-o-user-circle',
                default => 'heroicon-o-user',
            };

            $color = match ($name) {
                'client' => 'gray',
                'therapist' => 'success',
                'clinic_manager' => 'info',
                'super_admin' => 'danger',
                'clinic_head' => 'warning',
                default => 'primary',
            };

            $tabs[$name] = Tab::make($label)
                ->icon($icon)
                ->visible(fn () => $name !== config('project.roles.super_admin', 'super_admin') || auth()->user()->hasRole(config('project.roles.super_admin', 'super_admin')))
                ->query(fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $name)))
                ->badge(UserResource::getEloquentQuery()->whereHas('roles', fn ($q) => $q->where('name', $name))->count())
                ->badgeColor($color);
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'client'; // Default selected tab
    }
}
