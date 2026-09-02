<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use App\DTOs\Lead\ColumnMappingDto;
use App\Enums\CrmLeadField;
use App\Enums\DuplicateStrategy;
use App\Enums\LeadFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules for the confirmed column mapping and import settings.
 */
class StoreLeadMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Create:LeadImport') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::sharedRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*.csv_column' => ['required', 'string', 'max:500'],
            'mapping.*.target' => ['required', 'string', 'max:255'],
            'mapping.*.custom_label' => ['nullable', 'string', 'max:1000'],
            'mapping.*.custom_type' => ['nullable', Rule::in(array_column(LeadFieldType::cases(), 'value'))],

            'duplicate_strategy' => ['required', Rule::in(array_column(DuplicateStrategy::cases(), 'value'))],
            'duplicate_match_fields' => ['required', 'array', 'min:1'],
            'duplicate_match_fields.*' => [
                'string',
                Rule::in(array_map(
                    fn (CrmLeadField $field): string => $field->value,
                    CrmLeadField::duplicateMatchFields()
                )),
            ],

            'settings' => ['nullable', 'array'],
            'settings.*' => ['boolean'],

            'template_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Confirm the mapping fills every required CRM field.
     *
     * Structural validation cannot express this: the rules above prove each
     * entry is well formed, but only inspecting the set as a whole shows
     * whether anything actually maps onto the phone column.
     *
     * @param  array<string, array<string, mixed>>  $mapping
     * @return array<int, string>  Labels of the required fields with no column.
     */
    public static function findUnmappedRequiredFields(array $mapping): array
    {
        $mappedTargets = [];

        foreach ($mapping as $definition) {
            $dto = ColumnMappingDto::fromArray($definition);

            if ($dto->isCore()) {
                $mappedTargets[$dto->target] = true;
            }
        }

        $missing = [];

        foreach (CrmLeadField::required() as $field) {
            if (! isset($mappedTargets[ColumnMappingDto::PREFIX_CORE . $field->value])) {
                $missing[] = $field->getLabel();
            }
        }

        return $missing;
    }

    /**
     * @return array<string, string>
     */
    public static function sharedMessages(): array
    {
        return [
            'duplicate_match_fields.required' => 'Choose at least one field to match duplicates on.',
            'mapping.required' => 'The file has no columns to map.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return static::sharedMessages();
    }
}
