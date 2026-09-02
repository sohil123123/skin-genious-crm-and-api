<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\LeadCustomField;
use App\Models\LeadFieldValue;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Breaks leads down by the answer to a dynamic lead-form question.
 *
 * This widget only exists because questions are stored as data rather than as
 * columns: the field to chart is chosen at runtime, so a new question added to
 * a Facebook form next month becomes chartable without a code change.
 */
class LeadsByConcernChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Leads by Answer';

    protected ?string $description = 'Choose any question captured from your lead forms.';

    protected static ?int $sort = 3;

    public ?string $filter = null;

    protected function getMaxHeight(): ?string
    {
        return '320px';
    }

    /**
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        $fields = $this->chartableFields();

        if ($fields->isEmpty()) {
            return null;
        }

        return $fields
            ->mapWithKeys(fn (LeadCustomField $field): array => [
                (string) $field->getKey() => Str::limit($field->display_label, 50),
            ])
            ->all();
    }

    protected function getData(): array
    {
        $fieldId = $this->filter !== null
            ? (int) $this->filter
            : (int) ($this->chartableFields()->first()?->getKey() ?? 0);

        if ($fieldId === 0) {
            return ['datasets' => [], 'labels' => []];
        }

        $field = LeadCustomField::find($fieldId);

        if ($field === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $counts = [];

        // Multi-answer values are pipe-joined in a single cell, so each choice
        // is counted separately rather than treating the combination as its own
        // category — otherwise a lead who picked six concerns would create a
        // one-off bucket instead of incrementing six real ones.
        LeadFieldValue::query()
            ->where('lead_custom_field_id', $field->getKey())
            ->whereHas('lead', fn (Builder $query) => $query
                ->when(! check_role(config('project.roles.super_admin')), fn (Builder $inner) => $inner->forCurrentClinic()))
            ->select('value', 'value_json')
            ->chunk(1000, function ($values) use (&$counts): void {
                foreach ($values as $row) {
                    $choices = is_array($row->value_json) && $row->value_json !== []
                        ? $row->value_json
                        : [$row->value];

                    foreach ($choices as $choice) {
                        $label = LeadCustomField::humanizeValue((string) $choice);

                        if ($label !== '') {
                            $counts[$label] = ($counts[$label] ?? 0) + 1;
                        }
                    }
                }
            });

        arsort($counts);
        $counts = array_slice($counts, 0, 10, preserve_keys: true);

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => array_values($counts),
                    'backgroundColor' => ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16', '#f97316', '#64748b'],
                ],
            ],
            'labels' => array_map(fn (string $label): string => Str::limit($label, 30), array_keys($counts)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'right'],
            ],
        ];
    }

    /**
     * Questions with a fixed answer set, which are the only ones worth charting.
     *
     * @return \Illuminate\Support\Collection<int, LeadCustomField>
     */
    protected function chartableFields()
    {
        return LeadCustomField::query()
            ->where('is_active', true)
            ->whereIn('type', ['select', 'multiselect'])
            ->where('usage_count', '>', 0)
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic())
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get();
    }

    public static function canView(): bool
    {
        return LeadCustomField::query()->where('is_active', true)->where('usage_count', '>', 0)->exists();
    }
}
