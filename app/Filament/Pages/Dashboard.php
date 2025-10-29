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

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

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

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('businessCustomersOnly')
                            ->boolean(),
                        DatePicker::make('startDate')
                            ->maxDate(fn (Get $get) => $get('endDate') ?: now()),
                        DatePicker::make('endDate')
                            ->minDate(fn (Get $get) => $get('startDate') ?: now())
                            ->maxDate(now()),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
