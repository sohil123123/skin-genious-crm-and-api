<?php

declare(strict_types=1);

namespace App\DTOs\Lead;

use App\Enums\CrmLeadField;
use App\Enums\LeadFieldType;

/**
 * How one CSV column should be handled.
 *
 * The target is stored as a single descriptor string so the whole mapping can
 * live in one JSON column and round-trip through a Filament form field without
 * needing a parallel structure:
 *
 *   core:phone          → write to the leads.phone column
 *   custom:skin_concern → store as an answer to that dynamic question
 *   ignore              → do not read this column at all
 */
final readonly class ColumnMappingDto
{
    public const TARGET_IGNORE = 'ignore';
    public const PREFIX_CORE = 'core:';
    public const PREFIX_CUSTOM = 'custom:';

    /**
     * @param  array<int, string>  $suggestions  Ranked alternative custom fields the mapper considered.
     */
    public function __construct(
        public string $csvColumn,
        public string $target = self::TARGET_IGNORE,
        public ?string $customLabel = null,
        public LeadFieldType $customType = LeadFieldType::Text,
        public bool $autoMapped = false,
        public float $confidence = 0.0,
        public array $suggestions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            csvColumn: (string) $data['csv_column'],
            target: (string) ($data['target'] ?? self::TARGET_IGNORE),
            customLabel: $data['custom_label'] ?? null,
            customType: LeadFieldType::tryFrom((string) ($data['custom_type'] ?? 'text')) ?? LeadFieldType::Text,
            autoMapped: (bool) ($data['auto_mapped'] ?? false),
            confidence: (float) ($data['confidence'] ?? 0.0),
            suggestions: (array) ($data['suggestions'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'csv_column' => $this->csvColumn,
            'target' => $this->target,
            'custom_label' => $this->customLabel,
            'custom_type' => $this->customType->value,
            'auto_mapped' => $this->autoMapped,
            'confidence' => $this->confidence,
            'suggestions' => $this->suggestions,
        ];
    }

    public static function core(string $csvColumn, CrmLeadField $field, bool $autoMapped = false, float $confidence = 100.0): self
    {
        return new self(
            csvColumn: $csvColumn,
            target: self::PREFIX_CORE . $field->value,
            autoMapped: $autoMapped,
            confidence: $confidence,
        );
    }

    /**
     * @param  array<int, string>  $suggestions
     */
    public static function custom(
        string $csvColumn,
        string $key,
        string $label,
        LeadFieldType $type = LeadFieldType::Text,
        bool $autoMapped = false,
        float $confidence = 0.0,
        array $suggestions = [],
    ): self {
        return new self(
            csvColumn: $csvColumn,
            target: self::PREFIX_CUSTOM . $key,
            customLabel: $label,
            customType: $type,
            autoMapped: $autoMapped,
            confidence: $confidence,
            suggestions: $suggestions,
        );
    }

    public static function ignored(string $csvColumn): self
    {
        return new self(csvColumn: $csvColumn, target: self::TARGET_IGNORE);
    }

    public function isIgnored(): bool
    {
        return $this->target === self::TARGET_IGNORE || $this->target === '';
    }

    public function isCore(): bool
    {
        return str_starts_with($this->target, self::PREFIX_CORE);
    }

    public function isCustom(): bool
    {
        return str_starts_with($this->target, self::PREFIX_CUSTOM);
    }

    public function coreField(): ?CrmLeadField
    {
        if (! $this->isCore()) {
            return null;
        }

        return CrmLeadField::tryFrom(substr($this->target, strlen(self::PREFIX_CORE)));
    }

    public function customKey(): ?string
    {
        if (! $this->isCustom()) {
            return null;
        }

        $key = substr($this->target, strlen(self::PREFIX_CUSTOM));

        return $key === '' ? null : $key;
    }

    /**
     * The label to register the custom field under, defaulting to the raw
     * header when the user did not rename it.
     */
    public function resolvedCustomLabel(): string
    {
        return $this->customLabel !== null && trim($this->customLabel) !== ''
            ? trim($this->customLabel)
            : $this->csvColumn;
    }
}
