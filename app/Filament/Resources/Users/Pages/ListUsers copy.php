<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use App\Filament\Resources\Users\Widgets\UserStats;
use Filament\Schemas\Components\Tabs\Tab;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        // return UserResource::getWidgets();
        return [
            UserStats::class,
        ];

        // return [
        //     \App\Filament\Resources\Users\Widgets\UserStats::make(['activeTab' => $this->activeTab]),
        // ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make()->query(fn ($query) => $query->where('is_active', true)),
            'inactive' => Tab::make()->query(fn ($query) => $query->where('is_active', false)),
        ];
    }

    // Add these for dynamic widget updates
    public function mount(): void
    {
        parent::mount();
        $this->dispatch('setUserStatsTab', tab: $this->activeTab ?? 'all'); // ✅ Livewire v3
    }

    public function updated($name): void
    {
        if ($name === 'activeTab') {
            $this->dispatch('setUserStatsTab', tab: $this->activeTab); // ✅ Livewire v3
        }
    }
}
