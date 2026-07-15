<?php

namespace App\Filament\Widgets;

use App\Services\WhatsAppAnalyticsService;
use Filament\Widgets\ChartWidget;

class WhatsAppDeliveryRateChart extends ChartWidget
{
    protected ?string $heading = 'Message Status Distribution';

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $analyticsService = app(WhatsAppAnalyticsService::class);
        $data = $analyticsService->getDeliveryRateBreakdown();

        return [
            'datasets' => [
                [
                    'data' => [
                        $data['read'],
                        $data['delivered'],
                        $data['sent_only'],
                        $data['failed'],
                        $data['pending'],
                    ],
                    'backgroundColor' => [
                        '#8b5cf6', // Read - purple
                        '#3b82f6', // Delivered - blue
                        '#10b981', // Sent - green
                        '#ef4444', // Failed - red
                        '#f59e0b', // Pending - amber
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Read', 'Delivered', 'Sent Only', 'Failed', 'Pending'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
