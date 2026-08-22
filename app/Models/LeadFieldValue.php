<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

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
                fn ($value): string => static::presentAnswer((string) $value)
                    ?? LeadCustomField::humanizeValue((string) $value),
                $this->value_json
            );
        }

        // Detection runs against the raw value rather than the normalised one,
        // because the normalised column is truncated for indexing and a long
        // timestamp could arrive here already clipped.
        $presented = static::presentAnswer((string) $this->value);

        if ($presented !== null) {
            return [$presented];
        }

        $normalized = (string) ($this->value_normalized ?? $this->value);

        return $normalized === '' ? [] : [$normalized];
    }

    /**
     * Render an answer that is really a date or timestamp in clinic-local form.
     *
     * Meta returns scheduling answers as ISO-8601 — "2026-08-22T03:55:21+0530"
     * — which is accurate but unreadable on a lead card. Only the display is
     * localised: the value column keeps the exact original so filtering,
     * matching and re-export are unaffected.
     *
     * Returns null when the answer is not a date, which is the common case, so
     * the caller falls back to its normal handling.
     */
    protected static function presentAnswer(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Deliberately strict. A loose parse would turn "5999" into a year and
        // a budget answer would silently become a date.
        $isDateOnly = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
        $isDateTime = (bool) preg_match(
            '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/',
            $value
        );

        if (! $isDateOnly && ! $isDateTime) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        $timezone = (string) app_timezone();

        // A date with no time of day must not gain a misleading "12:00 AM", and
        // shifting it between timezones could move it a day either way.
        if ($isDateOnly) {
            return $parsed->format('d M Y');
        }

        return $parsed
            ->timezone($timezone)
            ->format((string) app_datetime_format());
    }
}
