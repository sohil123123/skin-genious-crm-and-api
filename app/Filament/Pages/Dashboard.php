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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_pigmentation')
                ->label('Test Pigmentation Assessment')
                ->color('warning')
                ->icon('heroicon-o-sparkles')
                ->url(fn () => new_assessment(Auth::user(), 'pigmentation-assessment'))
                ->openUrlInNewTab(),
        ];
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
