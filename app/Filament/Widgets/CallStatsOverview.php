<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Call;
use App\Services\Call\CallAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline call figures for the last thirty days.
 *
 * Connection rate is given the prominent slot rather than call volume, because
 * volume flatters and connection rate informs: sixty calls that reached nobody
 * is a worse day than twenty that reached someone, and only one of those two
 * numbers says so.
 */
class CallStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Last 30 days';

    protected int|string|array $columnSpan = 'full';

    /**
     * Six across on a wide screen, collapsing to two on a phone: the six figures
     * are one scan of the last thirty days, and splitting them over two rows
     * reads as two unrelated groups.
     */
    // The union must match the parent exactly — StatsOverviewWidget declares
    // int|array|null, and adding "string" to it is a fatal error at class load,
    // which takes down every page that renders the panel.
    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
        'xl' => 4,
    ];

    /**
     * Polling off: this widget sits above a table that already reloads on every
     * filter change, sort and page, and thirty-day totals do not move minute to
     * minute.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Call::class) ?? false;
    }

    protected function getStats(): array
    {
        $analytics = app(CallAnalyticsService::class);

        $from = now()->subDays(30)->startOfDay();
        $summary = $analytics->summary($from, now());

        return [
            Stat::make('Calls', number_format($summary['total_calls']))
                ->description(sprintf(
                    '%s in · %s out',
                    number_format($summary['incoming_calls']),
                    number_format($summary['outgoing_calls']),
                ))
                ->descriptionIcon('heroicon-o-phone')
                ->color('info'),

            Stat::make('Connection rate', $summary['connection_rate'] . '%')
                ->description(sprintf('%s conversations', number_format($summary['connected_calls'])))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color(match (true) {
                    $summary['connection_rate'] >= 60 => 'success',
                    $summary['connection_rate'] >= 35 => 'warning',
                    default => 'danger',
                }),

            // Stat::make('Talk time', $this->humanDuration($summary['total_duration_seconds']))
            //     ->description($summary['average_duration_seconds'] !== null
            //         ? 'Averaging ' . $this->humanDuration($summary['average_duration_seconds']) . ' per conversation'
            //         : 'No connected calls yet')
            //     ->descriptionIcon('heroicon-o-clock')
            //     ->color('gray'),

            Stat::make('Missed', number_format($summary['missed_calls']))
                ->description(sprintf('%s people reached', number_format($summary['unique_customers'])))
                ->descriptionIcon('heroicon-o-phone-x-mark')
                ->color($summary['missed_calls'] > 0 ? 'warning' : 'success'),

            // Surfaced as a headline because an unattributed call is work
            // waiting to be done, not a statistic.
            Stat::make('Unattributed', number_format($summary['unattributed_calls']))
                ->description('Calls not linked to a patient or lead')
                ->descriptionIcon('heroicon-o-question-mark-circle')
                ->color($summary['unattributed_calls'] > 0 ? 'warning' : 'success'),

            // Stat::make('Follow-ups due', number_format($summary['follow_ups_pending']))
            //     ->description('Flagged and not yet done')
            //     ->descriptionIcon('heroicon-o-flag')
            //     ->color($summary['follow_ups_pending'] > 0 ? 'danger' : 'success'),
        ];
    }

    protected function humanDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds === 0) {
            return '0m';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return $minutes > 0 ? $minutes . 'm' : $seconds . 's';
    }
}
