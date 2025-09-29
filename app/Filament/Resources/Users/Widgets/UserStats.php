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
            Stat::make('Active users', $this->getPageTableQuery()->where('is_active', true)->count())
                ->icon('heroicon-m-user-group')
                ->color('success'), // green

            Stat::make('Inactive users', $this->getPageTableQuery()->where('is_active', false)->count())
                ->icon('heroicon-m-user-minus')
                ->color('danger'), // red

            Stat::make('New This Month users', $this->getPageTableQuery()->whereMonth('created_at', now()->month)->count())
                ->icon('heroicon-m-user-plus')
                ->color('info'), // blue
        ];
    }
}
