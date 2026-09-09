<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Enums\PhoneStatus;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LeadStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Lead Overview';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $base = fn (): Builder => Lead::query()
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());

        $total = $base()->count();
        // $new = $base()->ofStatus(LeadStatus::New)->count();
        // $unassigned = $base()->unassigned()->count();
        // $needsReview = $base()->needsPhoneReview()->count();
        $existingPatients = $base()->matchingExistingPatient()->count();
        $thisWeek = $base()->where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Total leads', number_format($total))
                ->description(number_format($thisWeek) . ' added in the last 7 days')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->icon('heroicon-m-user-group')
                ->color('primary'),

            // Stat::make('Awaiting first contact', number_format($new))
            //     ->description($total > 0 ? round(($new / $total) * 100) . '% of all leads' : 'No leads yet')
            //     ->icon('heroicon-m-sparkles')
            //     ->color($new > 0 ? 'info' : 'gray'),

            // Stat::make('Unassigned', number_format($unassigned))
            //     ->description('No staff member is following these up')
            //     ->icon('heroicon-m-user-minus')
            //     ->color($unassigned > 0 ? 'warning' : 'success'),

            // Stat::make('Phone needs review', number_format($needsReview))
            //     ->description('Repaired from a malformed number during import')
            //     ->icon('heroicon-m-exclamation-triangle')
            //     ->color($needsReview > 0 ? 'warning' : 'success'),

            Stat::make('Already clients', number_format($existingPatients))
                ->description('Lead matches an existing patient record')
                ->icon('heroicon-m-identification')
                ->color($existingPatients > 0 ? 'warning' : 'gray'),

            // Stat::make('Won', number_format($base()->ofStatus(LeadStatus::Won)->count()))
            //     ->description('Converted from an ad enquiry')
            //     ->icon('heroicon-m-check-badge')
            //     ->color('success'),
        ];
    }

    public function getColumns(): int
    {
        return 2;
    }
}
