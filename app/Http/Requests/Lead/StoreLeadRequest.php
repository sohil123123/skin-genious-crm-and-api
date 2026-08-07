<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use App\Enums\CrmLeadField;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules for creating a lead by hand.
 *
 * The per-field rules are pulled from CrmLeadField so a manually entered lead
 * and an imported one are held to identical standards.
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Create:Lead') ?? false;
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
        $rules = [
            'clinic_id' => ['required', 'integer', Rule::exists('clinics', 'id')],
            'status' => ['required', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'source' => ['required', Rule::in(array_column(LeadSource::cases(), 'value'))],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        foreach (CrmLeadField::cases() as $field) {
            // status and source are constrained above to the CRM's own enums,
            // which is narrower than the generic string rule on the enum.
            if (in_array($field, [CrmLeadField::Status, CrmLeadField::Source, CrmLeadField::Notes], true)) {
                continue;
            }

            $rules[$field->value] = $field->rules();
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'A phone number is required so the lead can be contacted.',
        ];
    }
}
