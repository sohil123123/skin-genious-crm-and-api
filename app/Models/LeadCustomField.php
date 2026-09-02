<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A dynamic question captured from a lead form.
 *
 * Facebook lead forms ask a different set of questions per form and the set
 * changes whenever marketing edits a form, so questions are registered as rows
 * here rather than as columns on the leads table.
 */
class LeadCustomField extends Model
{
    protected $fillable = [
        'clinic_id',
        'key',
        'label',
        'source_label',
        'type',
        'options',
        'description',
        'is_active',
        'usage_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeadFieldType::class,
            'options' => 'array',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────── Relationships ────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(LeadFieldValue::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Fields usable by the given clinic: its own, plus any shared global ones.
     */
    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole(config('project.roles.super_admin'))) {
            return $query;
        }

        $clinicId = auth()->user()->clinic_id;

        return $query->where(fn (Builder $inner): Builder => $inner
            ->where('clinic_id', $clinicId)
            ->orWhereNull('clinic_id'));
    }

    // ──────────────── Accessors ────────────────

    /**
     * The label rendered in the UI.
     *
     * Meta headers arrive snake_cased with a trailing question mark, so
     * "what_is_your_main_skin_concern?" is shown as
     * "What is your main skin concern?".
     */
    public function getDisplayLabelAttribute(): string
    {
        return static::humanizeLabel($this->label);
    }

    /**
     * The question as the lead form actually asked it.
     *
     * Falls back to the label for rows that predate source_label being stored.
     */
    public function getSourceQuestionAttribute(): string
    {
        return (string) ($this->source_label ?: $this->label);
    }

    /**
     * Whether the displayed wording has been rewritten away from the original.
     *
     * Comparison is on the humanised forms, so merely tidying underscores and
     * capitalisation does not count as a rewrite worth flagging.
     */
    public function labelWasRewritten(): bool
    {
        if (blank($this->source_label)) {
            return false;
        }

        return static::humanizeLabel($this->source_label) !== static::humanizeLabel($this->label);
    }

    /**
     * Options rendered for filters and forms, keyed by the stored raw value.
     *
     * @return array<string, string>
     */
    public function getOptionListAttribute(): array
    {
        $options = [];

        foreach ($this->options ?? [] as $option) {
            $options[$option] = static::humanizeValue((string) $option);
        }

        asort($options);

        return $options;
    }

    // ──────────────── Helpers ────────────────

    /**
     * Turn a raw header into a readable question.
     */
    public static function humanizeLabel(?string $label): string
    {
        $label = trim(str_replace('_', ' ', (string) $label));
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;

        return $label === '' ? '' : Str::ucfirst($label);
    }

    /**
     * Turn a raw answer into a readable one.
     *
     * Meta snake_cases answers too, so "dullness_/_tanning" becomes
     * "Dullness / Tanning" while the original string stays in the value column
     * for exact matching.
     */
    public static function humanizeValue(?string $value): string
    {
        $value = trim(str_replace('_', ' ', (string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? '' : Str::ucfirst($value);
    }

    /**
     * Derive a stable slug for a question label.
     *
     * Question text runs long — the budget question in the sample exports is 157
     * characters — so anything beyond the column width is truncated and given a
     * short hash suffix, which keeps the slug unique without risking a silent
     * collision between two questions that share a prefix.
     */
    public static function makeKey(string $label): string
    {
        $slug = Str::slug(str_replace(['_', '/'], ' ', $label), '_');

        if ($slug === '') {
            $slug = 'question_' . substr(hash('sha256', $label), 0, 12);
        }

        if (strlen($slug) > 191) {
            $slug = substr($slug, 0, 178) . '_' . substr(hash('sha256', $label), 0, 12);
        }

        return $slug;
    }

    /**
     * Add newly seen answers to the stored option list.
     *
     * @param  array<int, string>  $values
     */
    public function mergeOptions(array $values): void
    {
        if (! $this->type->hasOptions()) {
            return;
        }

        $existing = $this->options ?? [];
        $merged = array_values(array_unique(array_merge($existing, array_filter($values, fn ($value): bool => $value !== '' && $value !== null))));

        // Guard against a mis-detected select swallowing thousands of free-text
        // answers and turning the filter dropdown into a wall of noise.
        if (count($merged) > config('leads.csv.max_distinct_for_select')) {
            $this->forceFill([
                'type' => LeadFieldType::Text->value,
                'options' => null,
            ])->save();

            return;
        }

        if ($merged !== $existing) {
            $this->forceFill(['options' => $merged])->save();
        }
    }
}
