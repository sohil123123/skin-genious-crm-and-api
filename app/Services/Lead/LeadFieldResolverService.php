<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Enums\LeadFieldType;
use App\Models\LeadCustomField;

/**
 * Resolves a question label to its LeadCustomField row, creating it on first
 * sight.
 *
 * Imports call this once per custom column per chunk, so results are memoised
 * for the life of the request. Without that, a 100k-row file would issue a
 * lookup per row per question.
 */
class LeadFieldResolverService
{
    /** @var array<string, LeadCustomField> */
    protected array $cache = [];

    /**
     * Find or create the field for a label.
     */
    public function resolve(
        string $label,
        ?int $clinicId,
        LeadFieldType $type = LeadFieldType::Text,
        ?string $key = null,
    ): LeadCustomField {
        $key ??= LeadCustomField::makeKey($label);
        $cacheKey = ($clinicId ?? 'global') . ':' . $key;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $field = LeadCustomField::query()
            ->where('key', $key)
            ->where(fn ($query) => $query->where('clinic_id', $clinicId)->orWhereNull('clinic_id'))
            ->first();

        if ($field === null) {
            $field = LeadCustomField::create([
                'clinic_id' => $clinicId,
                'key' => $key,
                'label' => $label,
                'type' => $type->value,
                'options' => $type->hasOptions() ? [] : null,
                'is_active' => true,
                'usage_count' => 0,
                'sort_order' => $this->nextSortOrder($clinicId),
            ]);
        }

        return $this->cache[$cacheKey] = $field;
    }

    /**
     * Resolve an existing field by key without creating anything.
     *
     * Used when the mapping points at a field the user picked on the mapping
     * screen, where inventing a new row on a typo would be wrong.
     */
    public function find(string $key, ?int $clinicId): ?LeadCustomField
    {
        $cacheKey = ($clinicId ?? 'global') . ':' . $key;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $field = LeadCustomField::query()
            ->where('key', $key)
            ->where(fn ($query) => $query->where('clinic_id', $clinicId)->orWhereNull('clinic_id'))
            ->first();

        if ($field !== null) {
            $this->cache[$cacheKey] = $field;
        }

        return $field;
    }

    /**
     * Record that answers were stored against these fields.
     *
     * Counts are incremented in bulk at the end of a chunk rather than per row,
     * to keep a 100k-row import from issuing 100k UPDATE statements.
     *
     * @param  array<int, int>  $fieldIds  field id => number of answers stored
     */
    public function recordUsage(array $counts): void
    {
        foreach ($counts as $fieldId => $count) {
            if ($count > 0) {
                LeadCustomField::query()->whereKey($fieldId)->increment('usage_count', $count);
            }
        }
    }

    /**
     * Clear the memoisation, so a long-lived worker does not serve a field that
     * has since been renamed or deleted.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    protected function nextSortOrder(?int $clinicId): int
    {
        return (int) LeadCustomField::query()
            ->where(fn ($query) => $query->where('clinic_id', $clinicId)->orWhereNull('clinic_id'))
            ->max('sort_order') + 1;
    }
}
