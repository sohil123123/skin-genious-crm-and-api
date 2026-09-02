<?php

declare(strict_types=1);

namespace App\Actions\Lead;

use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\DuplicateStrategy;
use App\Enums\LeadFieldType;
use App\Models\Lead;
use App\Models\LeadCustomField;
use App\Models\LeadFieldValue;
use App\Services\Lead\LeadFieldResolverService;
use App\Services\Lead\ValueNormalizerService;

/**
 * Writes one lead and its dynamic answers, honouring the chosen duplicate
 * strategy.
 *
 * Kept separate from the row importer because "how do we decide what to write"
 * and "how do we write it" change for different reasons: the former follows the
 * user's import settings, the latter follows the schema.
 */
class PersistLeadAction
{
    /** @var array<int, int> field id => answers written, flushed by the caller */
    protected array $fieldUsage = [];

    public function __construct(
        protected LeadFieldResolverService $fieldResolver,
        protected ValueNormalizerService $valueNormalizer,
    ) {}

    /**
     * Create a new lead.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array{label: string, key: string, type: LeadFieldType, value: string}>  $customAnswers
     */
    public function create(array $attributes, array $customAnswers, ImportSettingsDto $settings): Lead
    {
        $lead = Lead::create($attributes);

        $this->syncCustomAnswers($lead, $customAnswers, $settings, overwrite: true);

        return $lead;
    }

    /**
     * Apply an incoming row to a lead that already exists.
     *
     * Update overwrites every mapped field. Merge only fills gaps, which is the
     * safe option when re-importing an overlapping export: a phone number a
     * staff member corrected by hand is not clobbered by the original bad value
     * from Facebook.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array{label: string, key: string, type: LeadFieldType, value: string}>  $customAnswers
     */
    public function apply(
        Lead $lead,
        array $attributes,
        array $customAnswers,
        DuplicateStrategy $strategy,
        ImportSettingsDto $settings,
    ): Lead {
        $payload = match ($strategy) {
            DuplicateStrategy::Update => $this->updatePayload($attributes),
            DuplicateStrategy::Merge => $this->mergePayload($lead, $attributes),
            default => [],
        };

        if ($payload !== []) {
            $lead->fill($payload)->save();
        }

        $this->syncCustomAnswers(
            $lead,
            $customAnswers,
            $settings,
            overwrite: $strategy === DuplicateStrategy::Update,
        );

        return $lead;
    }

    /**
     * On update, everything mapped wins — except provenance columns, which
     * describe where the lead originally came from and must not be rewritten by
     * a later file.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function updatePayload(array $attributes): array
    {
        unset($attributes['clinic_id'], $attributes['created_by'], $attributes['row_hash']);

        // A blank cell in a later export is an absence of information, not an
        // instruction to erase what is already known.
        return array_filter($attributes, fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * On merge, only fields currently empty on the lead are filled.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function mergePayload(Lead $lead, array $attributes): array
    {
        $payload = [];

        foreach ($this->updatePayload($attributes) as $key => $value) {
            $current = $lead->getAttribute($key);

            if ($current === null || $current === '') {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Store the lead's answers to the dynamic questions.
     *
     * @param  array<string, array{label: string, key: string, type: LeadFieldType, value: string}>  $customAnswers
     */
    public function syncCustomAnswers(Lead $lead, array $customAnswers, ImportSettingsDto $settings, bool $overwrite): void
    {
        if ($customAnswers === []) {
            return;
        }

        foreach ($customAnswers as $answer) {
            $value = trim((string) $answer['value']);

            if ($value === '') {
                continue;
            }

            $field = $this->fieldResolver->resolve(
                label: $answer['label'],
                clinicId: $lead->clinic_id,
                type: $answer['type'],
                key: $answer['key'],
            );

            $existing = LeadFieldValue::query()
                ->where('lead_id', $lead->getKey())
                ->where('lead_custom_field_id', $field->getKey())
                ->first();

            if ($existing !== null && ! $overwrite) {
                continue;
            }

            $parts = $this->valueNormalizer->splitMultiValue($value);

            LeadFieldValue::updateOrCreate(
                [
                    'lead_id' => $lead->getKey(),
                    'lead_custom_field_id' => $field->getKey(),
                ],
                [
                    'value' => $value,
                    'value_json' => $parts,
                    'value_normalized' => $this->valueNormalizer->normalizeAnswerForSearch($value, $settings),
                ]
            );

            $this->rememberOptions($field, $parts ?? [$value]);

            if ($existing === null) {
                $this->fieldUsage[$field->getKey()] = ($this->fieldUsage[$field->getKey()] ?? 0) + 1;
            }
        }
    }

    /**
     * Grow a select field's option list as new answers are seen.
     *
     * Options are what make these questions filterable, and they can only be
     * discovered from the data — Meta's export carries no schema.
     *
     * @param  array<int, string>  $values
     */
    protected function rememberOptions(LeadCustomField $field, array $values): void
    {
        if (! $field->type->hasOptions()) {
            return;
        }

        $known = $field->options ?? [];
        $new = array_values(array_diff(array_map('trim', $values), $known));

        if ($new !== []) {
            $field->mergeOptions(array_merge($known, $new));
        }
    }

    /**
     * Hand back the usage tallies so the caller can flush them in one go at the
     * end of a chunk rather than per row.
     *
     * @return array<int, int>
     */
    public function pullFieldUsage(): array
    {
        $usage = $this->fieldUsage;
        $this->fieldUsage = [];

        return $usage;
    }
}
