<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules for saving a reusable column mapping.
 */
class StoreMappingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Create:LeadMappingTemplate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return static::sharedRules($this->input('clinic_id'), $this->route('template')?->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(mixed $clinicId = null, ?int $ignoreId = null): array
    {
        return [
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('lead_mapping_templates', 'name')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A mapping template with this name already exists for this clinic.',
        ];
    }
}
