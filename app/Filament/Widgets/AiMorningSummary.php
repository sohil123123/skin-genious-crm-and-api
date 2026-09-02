<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use App\Services\AiActionService;

class AiMorningSummary extends BaseWidget
{
    protected ?string $heading = 'Morning Summary — Today\'s Opportunity';

    protected ?string $pollingInterval = '30s';

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|array|null $columns = 4;

    public function getColumns(): int|array
    {
        return 4;
    }

    protected function getStats(): array
    {
        $service = app(AiActionService::class);

        $clinicId = null;
        $user = auth()->user();

        if ($user && !$user->hasRole('super_admin') && $user->clinic_id) {
            $clinicId = $user->clinic_id;
        }

        $stats = $service->getSummaryStats($clinicId);

        return [
            Stat::make('High Priority', $stats['high_priority'])
                ->icon('heroicon-o-fire')
                ->description('Actions scoring ' . \App\Services\AiActionService::HIGH_PRIORITY_THRESHOLD . '+')
                ->color('danger')
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make('Empty Slots Today', $stats['empty_slots_today'])
                ->icon('heroicon-o-calendar')
                ->description('Available appointment slots')
                ->color($stats['empty_slots_today'] > 3 ? 'warning' : 'success')
                ->descriptionIcon('heroicon-m-clock'),

            Stat::make('Rescue', $stats['rescue_count'])
                ->icon('heroicon-o-shield-exclamation')
                ->description('Cancelled & no-show recovery')
                ->color('danger')
                ->descriptionIcon('heroicon-m-arrow-path'),

            Stat::make('Conversion', $stats['conversion_count'])
                ->icon('heroicon-o-arrow-right-circle')
                ->description('Scan & consult follow-ups')
                ->color('warning')
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make('Retention', $stats['retention_count'])
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->description('Overdue packages & plans')
                ->color('info')
                ->descriptionIcon('heroicon-m-users'),

            Stat::make('Capacity', $stats['capacity_count'])
                ->icon('heroicon-o-chart-bar')
                ->description('Risk list & slot fills')
                ->color('success')
                ->descriptionIcon('heroicon-m-chart-bar'),

            Stat::make('Completed', $stats['completed_count'])
                ->icon('heroicon-o-check-badge')
                ->description('Actions with outcomes logged')
                ->color('success')
                ->descriptionIcon('heroicon-m-check-circle'),

            Stat::make('Est. Potential', "{$stats['estimated_potential_min']}–{$stats['estimated_potential_max']}")
                ->icon('heroicon-o-sparkles')
                ->description('Incremental kept appointments')
                ->color('primary')
                ->descriptionIcon('heroicon-m-sparkles'),
        ];
    }
}
