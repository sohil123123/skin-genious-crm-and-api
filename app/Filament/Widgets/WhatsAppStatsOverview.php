<?php

namespace App\Filament\Widgets;

use App\Services\WhatsAppAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WhatsAppStatsOverview extends BaseWidget
{
    protected ?string $heading = 'WhatsApp Messaging Overview';

    protected ?string $pollingInterval = '30s';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $analyticsService = app(WhatsAppAnalyticsService::class);
        $stats = $analyticsService->getDashboardStats();

        return [
            Stat::make('Total Sent', number_format($stats['total_sent']))
                ->icon('heroicon-o-paper-airplane')
                ->description('Messages successfully sent')
                ->color('success')
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make('Delivered', number_format($stats['total_delivered']))
                ->icon('heroicon-o-check-circle')
                ->description('Messages delivered to device')
                ->color('info')
                ->descriptionIcon('heroicon-m-check'),

            Stat::make('Read', number_format($stats['total_read']))
                ->icon('heroicon-o-eye')
                ->description('Messages read by recipient')
                ->color('primary')
                ->descriptionIcon('heroicon-m-eye'),

            Stat::make('Failed', number_format($stats['total_failed']))
                ->icon('heroicon-o-x-circle')
                ->description('Messages failed to send')
                ->color('danger')
                ->descriptionIcon('heroicon-m-exclamation-triangle'),

            Stat::make('Pending', number_format($stats['total_pending']))
                ->icon('heroicon-o-clock')
                ->description('Messages in queue')
                ->color('warning')
                ->descriptionIcon('heroicon-m-clock'),

            Stat::make('Success Rate', $stats['success_rate'] . '%')
                ->icon('heroicon-o-chart-pie')
                ->description('Overall delivery success')
                ->color($stats['success_rate'] >= 90 ? 'success' : ($stats['success_rate'] >= 70 ? 'warning' : 'danger'))
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make("Today's Messages", number_format($stats['today_messages']))
                ->icon('heroicon-o-calendar')
                ->description('Messages sent today')
                ->color('info'),

            Stat::make('This Month', number_format($stats['this_month_messages']))
                ->icon('heroicon-o-calendar-days')
                ->description('Messages this month')
                ->color('primary'),
        ];
    }
}
