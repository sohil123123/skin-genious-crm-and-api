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
        $fields = static::chartableFields();

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
            : (int) (static::chartableFields()->first()?->getKey() ?? 0);

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
     * The largest number of distinct answers still worth drawing as slices.
     *
     * Above this the question is free text or a date, and a doughnut of it is
     * a hundred one-lead slices rather than a distribution.
     */
    protected const MAX_DISTINCT_ANSWERS = 25;

    /**
     * Questions whose answers actually repeat.
     *
     * Chosen by how the answers behave, not by the type the field was imported
     * as. The previous rule was `type IN ('select', 'multiselect')`, and on
     * real Meta forms nothing is either: the questions arrive as `boolean`
     * ("Have you visited us before?" — yes or no, the most chartable shape
     * there is) and as `date`. So the widget looked for a kind of question this
     * CRM never receives, found none, and drew an empty card.
     *
     * Declared select and multiselect are still trusted outright — their
     * answers come from a fixed list, and a multiselect stores the combination
     * pipe-joined in one cell, so counting distinct raw values would
     * over-count. Everything else earns its place by having between two and
     * MAX_DISTINCT_ANSWERS distinct answers, which admits the yes/no questions
     * and excludes the timestamps without either being named here.
     *
     * @return \Illuminate\Support\Collection<int, LeadCustomField>
     */
    protected static function chartableFields()
    {
        $fields = LeadCustomField::query()
            ->where('is_active', true)
            ->where('usage_count', '>', 0)
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic())
            ->orderByDesc('usage_count')
            ->get();

        if ($fields->isEmpty()) {
            return $fields;
        }

        // One aggregate for every candidate rather than a count per field.
        $spread = LeadFieldValue::query()
            ->whereIn('lead_custom_field_id', $fields->modelKeys())
            ->whereHas('lead', fn (Builder $query) => $query
                ->when(! check_role(config('project.roles.super_admin')), fn (Builder $inner) => $inner->forCurrentClinic()))
            ->selectRaw('lead_custom_field_id, COUNT(*) as responses, COUNT(DISTINCT value) as distinct_answers')
            ->groupBy('lead_custom_field_id')
            ->get()
            ->keyBy('lead_custom_field_id');

        return $fields
            ->filter(function (LeadCustomField $field) use ($spread): bool {
                $type = $field->type instanceof \BackedEnum ? $field->type->value : $field->type;

                if (in_array($type, ['select', 'multiselect'], true)) {
                    return true;
                }

                $stats = $spread->get($field->getKey());

                if ($stats === null) {
                    return false;
                }

                $distinct = (int) $stats->distinct_answers;
                $responses = (int) $stats->responses;

                // One answer is not a distribution and too many is not a chart,
                // but the test that matters is whether answers repeat at all.
                // A "what is your name?" field has few distinct answers when
                // few people have answered it, and charting it would draw one
                // slice per lead — so an answer has to have been given twice,
                // on average, before the question counts as categorical.
                return $distinct >= 2
                    && $distinct <= self::MAX_DISTINCT_ANSWERS
                    && $distinct * 2 <= $responses;
            })
            ->take(10)
            ->values();
    }

    /**
     * Hidden when there is nothing to draw.
     *
     * Asked with the same question getFilters() and getData() ask, which is the
     * bug this replaced: canView() only checked that some active field existed,
     * so the card appeared, announced itself, and rendered blank.
     */
    public static function canView(): bool
    {
        return static::chartableFields()->isNotEmpty();
    }
}
