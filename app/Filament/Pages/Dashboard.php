<?php

namespace App\Filament\Pages;

// use Filament\Pages\Page;
use Filament\Pages\Dashboard as BaseDashboard;

use Filament\Widgets\AccountWidget;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

use App\Models\UserLeaveEntitlement;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\Action;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * Widgets that are auto-discovered from App\Filament\Widgets but should not
     * be rendered on the dashboard. Remove an entry here to bring it back.
     */
    protected const HIDDEN_WIDGETS = [
        \App\Filament\Widgets\WhatsAppDeliveryRateChart::class,      // Message Status Distribution
        \App\Filament\Widgets\WhatsAppMessagesChart::class,          // Messages per Day (Last 30 Days)
        \App\Filament\Widgets\WhatsAppStatsOverview::class,          // WhatsApp Messaging Overview
        \App\Filament\Widgets\AiMorningSummary::class,               // Morning Summary — Today's Opportunity
        \App\Filament\Widgets\AiActionQueue::class,                  // Action Queue
        \App\Filament\Widgets\LeadImportStatsOverview::class,        // Import Activity
        \App\Filament\Widgets\LeadStatsOverview::class,              // Lead Overview
        \App\Filament\Widgets\AiCapacityGaps::class,                 // Clinic Capacity — Today & Tomorrow
        \App\Filament\Widgets\LeadsByCampaignChart::class,           // Leads by Campaign
        \App\Filament\Widgets\LeadsByConcernChart::class,            // Leads by Answer
        \App\Filament\Widgets\ExpenseCategoryBreakdownChart::class,  // Expenses by Category
        \App\Filament\Widgets\ExpenseTrendChart::class,              // Daily Expense Trend
        \App\Filament\Widgets\LeadMorningSummary::class,             // Morning Summary — Today's Opportunity
        \App\Filament\Widgets\LeadActionQueue::class,                // Lead Action Queue
        \App\Filament\Widgets\CallStatsOverview::class,              // Call Stats Overview
        \App\Filament\Widgets\LatestClients::class,                  // Latest Clients
        // \App\Filament\Widgets\LatestCompletedSessions::class,        // Latest Completed Sessions
    ];

    public function getWidgets(): array
    {
        return array_values(array_filter(
            parent::getWidgets(),
            fn ($widget) => ! in_array($widget, static::HIDDEN_WIDGETS, true),
        ));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    // protected string $view = 'filament.pages.dashboard';

    // protected function getHeaderWidgets(): array
    // {
    //     return [
    //         // Include other widgets if needed, but omit the user stats one
    //         // e.g., App\Filament\Widgets\SomeOtherWidget::class,
    //         AccountWidget::class,
    //         \App\Filament\Widgets\UserChart::class,
    //     ];
    // }

    // public function filtersForm(Schema $schema): Schema
    // {
    //     // Show filters only for authenticated users with 'therapist' role
    //     // Assumes User model has a method like hasRole('therapist') - adjust as per your implementation
    //     // e.g., if using Spatie Permission: auth()->user()->hasRole('therapist')
    //     // or if role column: auth()->user()->role === 'therapist'
    //     if (!Auth::user()->hasRole('therapist')) {
    //         return $schema; // Returns empty schema, hiding the form
    //     }

    //     $years = UserLeaveEntitlement::select('year')
    //         ->distinct()
    //         ->orderByDesc('year')
    //         ->pluck('year', 'year')
    //         ->toArray();

    //     $years = $years ?: [now()->year => now()->year];
    //     return $schema
    //         ->components([
    //             Section::make()
    //                 ->schema([
    //                     Select::make('selectedYear')
    //                         ->label('Year')
    //                         ->options($years)
    //                         ->live()       // triggers re-render
    //                         ->searchable(false)
    //                         ->native(false)
    //                         ->default(now()->year),
    //                 ])
    //                 ->columns(4)
    //                 ->columnSpanFull(),
    //         ]);
    // }
}
