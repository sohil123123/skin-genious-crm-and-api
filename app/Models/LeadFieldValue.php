<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lead's answer to one dynamic question.
 */
class LeadFieldValue extends Model
{
    protected $fillable = [
        'lead_id',
        'lead_custom_field_id',
        'value',
        'value_json',
        'value_normalized',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(LeadCustomField::class, 'lead_custom_field_id');
    }

    /**
     * Every answer as a list, so single and multi-answer questions render the
     * same way in the UI.
     *
     * @return array<int, string>
     */
    public function getDisplayValuesAttribute(): array
    {
        if (is_array($this->value_json) && $this->value_json !== []) {
            return array_map(
                fn ($value): string => LeadCustomField::humanizeValue((string) $value),
                $this->value_json
            );
        }

        $normalized = (string) ($this->value_normalized ?? $this->value);

        return $normalized === '' ? [] : [$normalized];
    }
}
