<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\DTOs\Lead\ColumnMappingDto;
use App\Enums\CrmLeadField;
use App\Enums\LeadFieldType;
use App\Models\LeadCustomField;
use Illuminate\Support\Collection;

/**
 * Proposes a mapping for every column in an uploaded file.
 *
 * Resolution runs in three passes, most confident first:
 *
 *   1. Exact alias match against CrmLeadField. Meta's snake_cased headers
 *      ("full_name", "campaign_name") land here, which is why a genuine export
 *      maps with no clicks at all.
 *   2. Fuzzy match against the same aliases, for hand-written headers like
 *      "Customer Mobile No.".
 *   3. Match against custom fields that already exist. This is the pass that
 *      matters most in practice: the sample exports contain both
 *      "what_is_your_main_skin_concern?" and
 *      "what_is_your_main_skin_concern_right_now?", which are the same question
 *      worded differently. Without this pass they become two unrelated fields
 *      and every report about skin concern silently splits in half.
 *
 * Anything still unresolved becomes a proposed new custom field. Nothing is
 * ever discarded, and every proposal is presented to the user for confirmation
 * before a single row is imported.
 */
class ColumnAutoMapperService
{
    public function __construct(
        protected ValueNormalizerService $valueNormalizer,
    ) {}

    /**
     * Build a proposed mapping for a whole header row.
     *
     * @param  array<int, string>  $headers
     * @param  array<string, array<int, string>>  $distinctValues  header => sample answers, used to infer field type
     * @param  Collection<int, LeadCustomField>|null  $existingFields  Pre-loaded registry; fetched when omitted.
     * @return array<string, ColumnMappingDto>  keyed by CSV column
     */
    public function map(array $headers, ?int $clinicId = null, array $distinctValues = [], ?Collection $existingFields = null): array
    {
        // Accepting the registry lets a caller that has already loaded it — the
        // mapping screen renders it in the dropdown — avoid a second query.
        $existingFields ??= $this->existingCustomFields($clinicId);
        $claimedCoreFields = [];
        $mapping = [];

        foreach ($headers as $header) {
            $dto = $this->mapColumn($header, $existingFields, $distinctValues[$header] ?? []);

            // A CRM field can only be filled from one column. When two headers
            // both resolve to the same target, the first wins and the second
            // falls back to being a custom question rather than silently
            // overwriting the first during import.
            if ($dto->isCore()) {
                $target = $dto->target;

                if (isset($claimedCoreFields[$target])) {
                    $dto = $this->proposeCustomField($header, $existingFields, $distinctValues[$header] ?? []);
                } else {
                    $claimedCoreFields[$target] = $header;
                }
            }

            $mapping[$header] = $dto;
        }

        return $mapping;
    }

    /**
     * Resolve a single header.
     *
     * @param  Collection<int, LeadCustomField>  $existingFields
     * @param  array<int, string>  $sampleValues
     */
    public function mapColumn(string $header, Collection $existingFields, array $sampleValues = []): ColumnMappingDto
    {
        $normalized = $this->normalizeHeader($header);

        if ($normalized === '' || str_starts_with($header, '_column_')) {
            return ColumnMappingDto::ignored($header);
        }

        // ─── Pass 1: exact alias ────────────────────────────────────────
        $lookup = CrmLeadField::aliasLookup();

        if (isset($lookup[$normalized])) {
            return ColumnMappingDto::core($header, $lookup[$normalized], autoMapped: true, confidence: 100.0);
        }

        // ─── Pass 2: fuzzy alias ────────────────────────────────────────
        $threshold = (float) config('leads.auto_mapping.similarity_threshold', 82.0);
        $bestField = null;
        $bestScore = 0.0;

        foreach ($lookup as $alias => $field) {
            $score = $this->similarity($normalized, $alias);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestField = $field;
            }
        }

        if ($bestField !== null && $bestScore >= $threshold) {
            return ColumnMappingDto::core($header, $bestField, autoMapped: true, confidence: round($bestScore, 1));
        }

        // ─── Pass 3: existing custom field ──────────────────────────────
        return $this->proposeCustomField($header, $existingFields, $sampleValues);
    }

    /**
     * Map to an existing custom field when one is close enough, otherwise
     * propose a new one — always carrying the runners-up as suggestions so the
     * user can fold near-identical questions together from the mapping screen.
     *
     * @param  Collection<int, LeadCustomField>  $existingFields
     * @param  array<int, string>  $sampleValues
     */
    protected function proposeCustomField(string $header, Collection $existingFields, array $sampleValues = []): ColumnMappingDto
    {
        $normalized = $this->normalizeHeader($header);
        $threshold = (float) config('leads.auto_mapping.custom_field_similarity_threshold', 78.0);
        $maxSuggestions = (int) config('leads.auto_mapping.max_suggestions', 3);

        $ranked = $existingFields
            ->map(fn (LeadCustomField $field): array => [
                'key' => $field->key,
                'label' => $field->display_label,
                'score' => $this->similarity($normalized, $this->normalizeHeader($field->label)),
                'model' => $field,
            ])
            ->sortByDesc('score')
            ->values();

        $suggestions = $ranked
            ->filter(fn (array $candidate): bool => $candidate['score'] >= $threshold)
            ->take($maxSuggestions)
            ->map(fn (array $candidate): array => [
                'key' => $candidate['key'],
                'label' => $candidate['label'],
                'score' => round($candidate['score'], 1),
            ])
            ->values()
            ->all();

        $best = $ranked->first();

        // An exact label match is folded in automatically; anything merely
        // similar is offered as a suggestion but left for the user to confirm,
        // because "...right now?" may genuinely be a different question.
        if ($best !== null && $this->normalizeHeader($best['model']->label) === $normalized) {
            return ColumnMappingDto::custom(
                csvColumn: $header,
                key: $best['key'],
                label: $best['model']->label,
                type: $best['model']->type,
                autoMapped: true,
                confidence: 100.0,
                suggestions: $suggestions,
            );
        }

        return ColumnMappingDto::custom(
            csvColumn: $header,
            key: LeadCustomField::makeKey($header),
            label: $header,
            type: $this->inferType($sampleValues),
            autoMapped: true,
            confidence: $best['score'] ?? 0.0,
            suggestions: $suggestions,
        );
    }

    /**
     * Infer a field type from the answers actually present in the file.
     *
     * Meta lead form questions are closed sets — no question in the sample
     * exports has more than six distinct answers — so detecting Select rather
     * than defaulting to Text is what makes these answers filterable instead of
     * dead free text.
     *
     * @param  array<int, string>  $values
     */
    public function inferType(array $values): LeadFieldType
    {
        $values = array_values(array_filter(array_map('trim', $values), fn (string $value): bool => $value !== ''));

        if ($values === []) {
            return LeadFieldType::Text;
        }

        $separator = (string) config('leads.csv.multi_value_separator', '|');

        foreach ($values as $value) {
            if (str_contains($value, $separator)) {
                return LeadFieldType::MultiSelect;
            }
        }

        $distinct = array_values(array_unique($values));

        if (count($distinct) <= 2 && $this->allBoolean($distinct)) {
            return LeadFieldType::Boolean;
        }

        if ($this->allNumeric($values)) {
            return LeadFieldType::Number;
        }

        if ($this->allDates($values)) {
            return LeadFieldType::Date;
        }

        $maxDistinct = (int) config('leads.csv.max_distinct_for_select', 25);

        if (count($distinct) <= $maxDistinct) {
            return LeadFieldType::Select;
        }

        // Long free-text answers are better shown in a textarea than a table cell.
        $longest = max(array_map('mb_strlen', $values));

        return $longest > 120 ? LeadFieldType::Textarea : LeadFieldType::Text;
    }

    /**
     * Reduce a header to a comparable form.
     *
     * "what_is_your_main_skin_concern?" and "What is your main skin concern"
     * must collapse to the same string for matching to work at all.
     */
    public function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(['_', '-', '/', '\\'], ' ', $header);
        $header = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $header) ?? $header;
        $header = preg_replace('/\s+/u', ' ', $header) ?? $header;

        return trim($header);
    }

    /**
     * Percentage similarity between two normalised strings.
     *
     * similar_text is used rather than levenshtein because it is not biased by
     * length, which matters when comparing a three-word header against a
     * twenty-word question.
     */
    public function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 100.0;
        }

        // A short header that is wholly contained in a longer one is usually a
        // genuine match ("skin concern" inside "what is your main skin
        // concern"), which raw character similarity would score too low.
        if (str_contains($b, $a) || str_contains($a, $b)) {
            $shorter = min(strlen($a), strlen($b));
            $longer = max(strlen($a), strlen($b));

            return max(80.0, ($shorter / $longer) * 100);
        }

        similar_text($a, $b, $percent);

        return (float) $percent;
    }

    /**
     * Custom fields usable by this clinic, including shared global ones.
     *
     * @return Collection<int, LeadCustomField>
     */
    protected function existingCustomFields(?int $clinicId): Collection
    {
        return LeadCustomField::query()
            ->where('is_active', true)
            ->when(
                $clinicId !== null,
                fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id'))
            )
            ->get();
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function allBoolean(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->valueNormalizer->normalizeBoolean($value) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_numeric(str_replace([',', ' '], '', $value))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function allDates(array $values): bool
    {
        foreach ($values as $value) {
            // A bare number parses as a date under a loose parser, so numeric
            // answers such as a budget must not be classified as dates.
            if (is_numeric($value) || $this->valueNormalizer->normalizeDateTime($value) === null) {
                return false;
            }
        }

        return true;
    }
}
