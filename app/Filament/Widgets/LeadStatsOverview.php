<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Enums\PhoneStatus;
use App\Models\Lead;
use App\Services\Lead\LeadConversionService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LeadStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    // The badge rendering both overviews share.
    use \App\Filament\Widgets\Concerns\RendersStatBadges;

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
        $converted = $this->patientsCreatedFromLeads();

        // Half-open ranges rather than whereDate(): applying a function to the
        // column stops the index on created_at being used, and this widget runs
        // eagerly on every visit to the Leads page.
        //
        // The app runs on Asia/Kolkata, so Carbon's today() is the clinic's
        // today — the boundaries are the ones a receptionist would draw.
        $startOfToday = now()->startOfDay();

        $today = $base()->where('created_at', '>=', $startOfToday)->count();

        $yesterday = $base()
            ->whereBetween('created_at', [$startOfToday->copy()->subDay(), $startOfToday])
            ->count();

        $thisMonth = $base()->where('created_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Total leads', number_format($total))
                // Three periods rather than one rolling window. "48 in the last
                // 7 days" cannot answer the question anybody actually asks in
                // the morning — did the ads bring anything in today, and is that
                // better or worse than yesterday.
                ->description($this->badges([
                    [
                        'label' => 'today',
                        'value' => number_format($today),
                        // Green only when something actually arrived. A green
                        // zero reads as "all good" on the morning the ads
                        // stopped delivering, which is the one morning this
                        // card has to be read correctly.
                        'color' => $today > 0 ? 'success' : 'gray',
                    ],
                    [
                        'label' => 'yesterday',
                        'value' => number_format($yesterday),
                        'color' => 'gray',
                    ],
                    [
                        'label' => 'this month',
                        'value' => number_format($thisMonth),
                        'color' => 'info',
                    ],
                ]))
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

            // Badged too, so the two cards read as a pair rather than one
            // finished and one not.
            Stat::make('Clients from leads', number_format($converted))
                ->description($total > 0
                    ? $this->badges([[
                        'label' => 'of enquiries became clients',
                        'value' => round(($converted / $total) * 100) . '%',
                        'color' => $converted > 0 ? 'success' : 'gray',
                        // Not a period word, so it says what it wants rather
                        // than taking a calendar it has no use for.
                        'icon' => 'heroicon-m-arrow-trending-up',
                    ]])
                    : 'No leads yet')
                ->icon('heroicon-m-user-plus')
                ->color($converted > 0 ? 'success' : 'gray'),

            // Stat::make('Won', number_format($base()->ofStatus(LeadStatus::Won)->count()))
            //     ->description('Converted from an ad enquiry')
            //     ->icon('heroicon-m-check-badge')
            //     ->color('success'),
        ];
    }

    /**
     * Client records that exist because a lead enquired.
     *
     * The definition lives in LeadConversionService, which the Leads table also
     * reads to tint converted rows. Two copies of "did this enquiry become a
     * client" would drift the first time either was tuned, and the drift would
     * show as a stat that disagrees with the list directly beneath it.
     */
    protected function patientsCreatedFromLeads(): int
    {
        return app(LeadConversionService::class)->countClientsFromLeads(
            check_role(config('project.roles.super_admin')) ? null : auth()->user()?->clinic_id
        );
    }

    public function getColumns(): int
    {
        return 2;
    }
}
