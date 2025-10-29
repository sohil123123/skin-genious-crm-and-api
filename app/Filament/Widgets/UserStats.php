<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
// use Flowframe\Trend\Trend;
// use Flowframe\Trend\TrendValue;


use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;

// use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
// use App\Traits\HasWidgetShield;

// class UserStats extends BaseWidget implements HasShieldPermissions
class UserStats extends BaseWidget
{
    use InteractsWithPageTable;
    use HasWidgetShield;

    protected ?string $heading = 'User States Overview';
    // protected ?string $description = 'An overview of some analytics.';

    protected ?string $pollingInterval = '5s';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getTablePage(): string
    {
        return ListUsers::class;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Active users', $this->getPageTableQuery()->where('is_active', true)->count())
                ->icon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Inactive users', $this->getPageTableQuery()->where('is_active', false)->count())
                ->icon('heroicon-m-user-minus')
                ->color('danger'),

            Stat::make('Deleted users', $this->getPageTableQuery()->onlyTrashed()->count())
                ->icon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('New This Month users', $this->getPageTableQuery()->whereMonth('created_at', now()->month)->count())
                ->icon('heroicon-m-user-plus')
                ->color('info'),
        ];
    }

    // public static function canView(): bool
    // {
    //     $user = auth()->user();

    //     return $user && (
    //         $user->hasRole('admin') ||
    //         $user->can('View:User')  // permission from Shield
    //     );
    // }

    // public static function canView(): bool
    // {
    //     return auth()->user()?->can('View:UserStats');
    // }

    // // ⬇️ Shield will call this to decide permission name
    // public static function getPermissionPrefixes(): array
    // {
    //     return ['view']; // creates "view_user_stats"
    // }

    // public static function getPermissionIdentifier(): string
    // {
    //     return 'user_stats'; // will become "view_user_stats"
    // }
}
