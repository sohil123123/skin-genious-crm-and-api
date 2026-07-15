<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WhatsAppDeliveryRateChart;
use App\Filament\Widgets\WhatsAppMessagesChart;
use App\Filament\Widgets\WhatsAppStatsOverview;
use Filament\Pages\Page;

class WhatsAppDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'WhatsApp Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 19;


    protected function getHeaderWidgets(): array
    {
        return [
            WhatsAppStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            WhatsAppMessagesChart::class,
            WhatsAppDeliveryRateChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
