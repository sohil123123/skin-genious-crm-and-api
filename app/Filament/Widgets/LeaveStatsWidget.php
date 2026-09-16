<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use App\Models\UserLeaveEntitlement;
use Illuminate\Support\Facades\Auth;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class LeaveStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    // Aliased rather than replaced: the override below adds to the Shield
    // check, it does not stand in for it.
    use HasWidgetShield {
        canView as protected canViewViaShield;
    }

    protected ?string $heading = 'Leave Summary';

    /**
     * Hidden from super admins.
     *
     * These cards report the signed-in user's own leave balance, and a super
     * admin holds no entitlement rows — so the widget rendered four zeroes on
     * their dashboard, which reads as broken data rather than "does not apply
     * to you".
     */
    public static function canView(): bool
    {
        if (check_role(config('project.roles.super_admin'))) {
            return false;
        }

        return static::canViewViaShield();
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $year = isset($this->pageFilters['selectedYear']) ? $this->pageFilters['selectedYear'] : now()->year;

        // Fetch leave data for the logged-in user for the current year
        $entitlements = UserLeaveEntitlement::where('user_id', $user->id)
            ->where('year', $year)
            ->get()
            ->keyBy(fn ($item) => strtolower($item->leave_type->value));

        $get = fn($type, $field) => $entitlements[$type][$field] ?? 0;

        return [
            Stat::make('Paid Leave', "{$get('paid', 'remaining')} / {$get('paid', 'total_allowed')}")
                ->description("Used: {$get('paid', 'used')} days")
                ->descriptionIcon('heroicon-m-currency-rupee')
                ->color('success'),

            Stat::make('Unpaid Leave', "{$get('unpaid', 'remaining')} / {$get('unpaid', 'total_allowed')}")
                ->description("Used: {$get('unpaid', 'used')} days")
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Sick Leave', "{$get('sick', 'remaining')} / {$get('sick', 'total_allowed')}")
                ->description("Used: {$get('sick', 'used')} days")
                ->descriptionIcon('heroicon-o-heart')
                ->color('danger'),

            Stat::make('Other Leave', "{$get('other', 'remaining')} / {$get('other', 'total_allowed')}")
                ->description("Used: {$get('other', 'used')} days")
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('gray'),
        ];
    }
}
