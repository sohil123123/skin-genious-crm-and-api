<?php

declare(strict_types=1);

namespace App\DTOs\Lead;

use App\Enums\PhoneStatus;

/**
 * The outcome of normalising one raw phone value.
 *
 * Carries the reason alongside the result so a salvaged number can explain
 * itself in the UI ("22 digits found, kept the first valid 10") rather than
 * appearing silently altered.
 */
final readonly class PhoneNormalizationResult
{
    public function __construct(
        public ?string $value,
        public string $raw,
        public PhoneStatus $status,
        public ?string $reason = null,
    ) {}

    public static function valid(string $value, string $raw): self
    {
        return new self($value, $raw, PhoneStatus::Valid);
    }

    public static function salvaged(string $value, string $raw, string $reason): self
    {
        return new self($value, $raw, PhoneStatus::NeedsReview, $reason);
    }

    public static function invalid(string $raw, string $reason): self
    {
        return new self(null, $raw, PhoneStatus::Invalid, $reason);
    }

    public function isUsable(): bool
    {
        return $this->value !== null && $this->status !== PhoneStatus::Invalid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'raw' => $this->raw,
            'status' => $this->status->value,
            'reason' => $this->reason,
        ];
    }
}
