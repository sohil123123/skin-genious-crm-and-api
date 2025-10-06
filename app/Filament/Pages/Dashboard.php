<?php

namespace App\Filament\Pages;

// use Filament\Pages\Page;
use Filament\Pages\Dashboard as BaseDashboard;

use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';
    protected function getHeaderWidgets(): array
    {
        return [
            // Include other widgets if needed, but omit the user stats one
            // e.g., App\Filament\Widgets\SomeOtherWidget::class,
            AccountWidget::class,
        ];
    }
}
