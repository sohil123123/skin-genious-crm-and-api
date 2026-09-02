<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Lead\LeadActionService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadMorningSummary extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Meta Leads — Today\'s Opportunity';

    protected ?string $pollingInterval = '30s';

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    public function getColumns(): int|array
    {
        return 4;
    }

    protected function getStats(): array
    {
        $service = app(LeadActionService::class);

        $user = auth()->user();
        $clinicId = ($user && ! $user->hasRole('super_admin') && $user->clinic_id)
            ? $user->clinic_id
            : null;

        $stats = $service->getSummaryStats($clinicId);

        return [
            Stat::make('Wants to Visit Now', $stats['hot_now'])
                ->icon('heroicon-o-fire')
                ->description('Asked for today, tomorrow or this week')
                ->color($stats['hot_now'] > 0 ? 'danger' : 'gray')
                ->descriptionIcon('heroicon-m-bolt'),

            Stat::make('High Priority', $stats['high_priority'])
                ->icon('heroicon-o-arrow-trending-up')
                ->description('Scoring ' . LeadActionService::HIGH_PRIORITY_THRESHOLD . '+')
                ->color($stats['high_priority'] > 0 ? 'warning' : 'gray')
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make('Already Patients', $stats['existing_patients'])
                ->icon('heroicon-o-identification')
                ->description('Existing patients who replied to an ad')
                ->color($stats['existing_patients'] > 0 ? 'warning' : 'gray')
                ->descriptionIcon('heroicon-m-user-circle'),

            Stat::make('Never Contacted', $stats['never_contacted'])
                ->icon('heroicon-o-inbox-arrow-down')
                ->description('New enquiries awaiting first contact')
                ->color($stats['never_contacted'] > 0 ? 'info' : 'gray')
                ->descriptionIcon('heroicon-m-phone-arrow-up-right'),

            // Measured against leads rather than actions: this is the number
            // that shows work being left undone, and it should sting.
            Stat::make('Aging & Untouched', $stats['aging_uncontacted'])
                ->icon('heroicon-o-exclamation-triangle')
                ->description('Still "new" after more than a week')
                ->color($stats['aging_uncontacted'] > 0 ? 'danger' : 'success')
                ->descriptionIcon('heroicon-m-clock'),

            Stat::make('Total Actions', $stats['total_actions'])
                ->icon('heroicon-o-queue-list')
                ->description('In today\'s lead queue')
                ->color('primary')
                ->descriptionIcon('heroicon-m-list-bullet'),

            Stat::make('Open Leads', $stats['total_open_leads'])
                ->icon('heroicon-o-users')
                ->description('Not yet won, lost or marked junk')
                ->color('gray')
                ->descriptionIcon('heroicon-m-user-group'),

            Stat::make('Completed', $stats['completed_count'])
                ->icon('heroicon-o-check-badge')
                ->description('Outcomes logged today')
                ->color('success')
                ->descriptionIcon('heroicon-m-check-circle'),
        ];
    }
}
