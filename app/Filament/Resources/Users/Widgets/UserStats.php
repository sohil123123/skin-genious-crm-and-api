<?php

namespace App\Filament\Resources\Users\Widgets;

use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;


use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;


class UserStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ListUsers::class;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Admins', $this->getPageTableQuery()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count()),
            Stat::make('Therapists', $this->getPageTableQuery()->whereHas('roles', fn ($q) => $q->where('name', 'therapist'))->count()),
            Stat::make('Users', $this->getPageTableQuery()->whereHas('roles', fn ($q) => $q->where('name', 'user'))->count()),
            Stat::make('New This Month', $this->getPageTableQuery()->whereMonth('created_at', now()->month)->count()),
        ];
    }
}
