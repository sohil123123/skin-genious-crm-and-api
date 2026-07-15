<?php

namespace App\Filament\Widgets;

use App\Services\WhatsAppAnalyticsService;
use Filament\Widgets\ChartWidget;

class WhatsAppMessagesChart extends ChartWidget
{
    protected ?string $heading = 'Messages per Day (Last 30 Days)';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $analyticsService = app(WhatsAppAnalyticsService::class);
        $data = $analyticsService->getMessagesPerDay(30);

        return [
            'datasets' => [
                [
                    'label' => 'Sent',
                    'data' => array_column($data, 'sent'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Delivered',
                    'data' => array_column($data, 'delivered'),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Read',
                    'data' => array_column($data, 'read'),
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Failed',
                    'data' => array_column($data, 'failed'),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => array_column($data, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
