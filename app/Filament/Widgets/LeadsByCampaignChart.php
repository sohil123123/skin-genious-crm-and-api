<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadsByCampaignChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Leads by Campaign';

    protected ?string $description = 'Where the enquiries are actually coming from, over the selected period.';

    protected static ?int $sort = 2;

    public ?string $filter = '30';

    protected function getMaxHeight(): ?string
    {
        return '320px';
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            'all' => 'All time',
        ];
    }

    protected function getData(): array
    {
        $rows = Lead::query()
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic())
            // Filtered on the enquiry date, not created_at: created_at is when
            // the CSV was imported, which for a backfilled export is the same
            // day for every row — "Last 7 days" would return the whole file.
            // Non-Meta leads have no fb_created_time, so they fall back to it.
            ->when($this->filter !== 'all', fn (Builder $query) => $query
                ->whereRaw(
                    'COALESCE(fb_created_time, created_at) >= ?',
                    [now()->subDays((int) $this->filter)]
                ))
            ->whereNotNull('campaign_name')
            ->select('campaign_name', DB::raw('COUNT(*) as lead_count'))
            ->groupBy('campaign_name')
            ->orderByDesc('lead_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $rows->pluck('lead_count')->all(),
                    'backgroundColor' => '#6366f1',
                    'borderRadius' => 4,
                ],
            ],
            // Meta campaign names are long ("AI Mapping Premium Leads | Jaipur |
            // Ad4 - Copy") and would otherwise squeeze the plot area to nothing.
            'labels' => $rows->pluck('campaign_name')
                ->map(fn (string $name): string => Str::limit($name, 28))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
