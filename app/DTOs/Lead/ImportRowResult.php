<?php

declare(strict_types=1);

namespace App\DTOs\Lead;

use App\Enums\ImportFailureReason;

/**
 * What happened to a single row.
 */
final readonly class ImportRowResult
{
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public string $status,
        public int $rowNumber,
        public ?int $leadId = null,
        public ?ImportFailureReason $reasonCode = null,
        public ?string $reason = null,
        public array $errors = [],
    ) {}

    public static function imported(int $rowNumber, int $leadId): self
    {
        return new self(self::STATUS_IMPORTED, $rowNumber, $leadId);
    }

    public static function updated(int $rowNumber, int $leadId): self
    {
        return new self(self::STATUS_UPDATED, $rowNumber, $leadId);
    }

    public static function skipped(int $rowNumber, string $reason, ?int $leadId = null): self
    {
        return new self(self::STATUS_SKIPPED, $rowNumber, $leadId, ImportFailureReason::Duplicate, $reason);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function failed(int $rowNumber, ImportFailureReason $reasonCode, string $reason, array $errors = []): self
    {
        return new self(self::STATUS_FAILED, $rowNumber, null, $reasonCode, $reason, $errors);
    }

    public function isFailure(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * The counter column on lead_imports this result increments.
     */
    public function counterColumn(): string
    {
        return match ($this->status) {
            self::STATUS_IMPORTED => 'imported_rows',
            self::STATUS_UPDATED => 'updated_rows',
            self::STATUS_SKIPPED => 'skipped_rows',
            default => 'failed_rows',
        };
    }
}
