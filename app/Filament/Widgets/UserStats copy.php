<?php

namespace App\Filament\Resources\Users\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;


class UserStats extends StatsOverviewWidget
{
    // use InteractsWithPageTable;

    // public ?string $activeTab = null;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected $listeners = ['setUserStatsTab' => 'setTab'];

    public string $currentTab = 'all'; // Default to 'all'

    // protected function getTablePage(): string
    // {
    //     return ListUsers::class;
    // }

    public function setTab(string $tab): void
    {
        $this->currentTab = $tab;
    }

    protected function getStats(): array
    {
        $query = User::query();

        // Apply tab-specific filters to the base query
        if ($this->currentTab === 'active') {
            $query->where('is_active', true);
        } elseif ($this->currentTab === 'inactive') {
            $query->where('is_active', false);
        }
        // 'all' applies no filter

        return [
            Stat::make('Admins', $query->clone()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count()),
            Stat::make('Therapists', $query->clone()->whereHas('roles', fn ($q) => $q->where('name', 'therapist'))->count()),
            Stat::make('Users', $query->clone()->whereHas('roles', fn ($q) => $q->where('name', 'user'))->count()),
            Stat::make('New This Month', $query->clone()->whereMonth('created_at', now()->month)->count()),
        ];
    }

    // protected function getStats(): array
    // {
    //     $query = User::query();
    //     return [
    //         Stat::make('Admins', $query->clone()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->count()),
    //         Stat::make('Therapists', $query->clone()->whereHas('roles', fn ($query) => $query->where('name', 'therapist'))->count()),
    //         Stat::make('Users', $query->clone()->whereHas('roles', fn ($query) => $query->where('name', 'user'))->count()),
    //         Stat::make('New This Month', $query->clone()->whereMonth('created_at', now()->month)->count()),
    //     ];
    //     // return [
    //     //     Stat::make('Admins', $this->getPageTableQuery()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->count()),
    //     //     Stat::make('Therapists', $this->getPageTableQuery()->whereHas('roles', fn ($query) => $query->where('name', 'therapist'))->count()),
    //     //     Stat::make('Users', $this->getPageTableQuery()->whereHas('roles', fn ($query) => $query->where('name', 'user'))->count()),
    //     //     Stat::make('New This Month', $this->getPageTableQuery()->whereMonth('created_at', now()->month)->count()),
    //     // ];
    // }

    // protected function getStats(): array
    // {
    //     $base = $this->scopedBaseQuery();

    //     return [
    //         Stat::make('Admins', $this->countRole(clone $base, 'admin')),
    //         Stat::make('Therapists', $this->countRole(clone $base, 'therapist')),
    //         Stat::make('Users', $this->countRole(clone $base, 'user')),
    //         Stat::make('New This Month', (clone $base)->whereMonth('created_at', now()->month)->count()),
    //     ];
    // }

    // protected function scopedBaseQuery()
    // {
    //     $q = User::query();

    //     return match ($this->activeTab) {
    //         'active'   => $q->where('is_active', true),
    //         'inactive' => $q->where('is_active', false),
    //         default    => $q,
    //     };
    // }

    // protected function countRole($q, string $role): int
    // {
    //     return $q->whereHas('roles', fn ($r) => $r->where('name', $role))->count();
    // }
}
