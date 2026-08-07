<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Outcome of normalising a raw phone value.
 *
 * Meta exports contain genuinely broken numbers — two numbers concatenated into
 * one 22-digit string, numbers with whitespace in the middle, numbers that are
 * only 7 digits long. Discarding all of them loses real leads, so anything we
 * can plausibly salvage is imported and flagged for a human to confirm.
 */
enum PhoneStatus: string implements HasColor, HasIcon, HasLabel
{
    case Valid = 'valid';
    case NeedsReview = 'needs_review';
    case Invalid = 'invalid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::NeedsReview => 'Needs Review',
            self::Invalid => 'Invalid',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::NeedsReview => 'warning',
            self::Invalid => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Valid => 'heroicon-o-check-circle',
            self::NeedsReview => 'heroicon-o-exclamation-triangle',
            self::Invalid => 'heroicon-o-x-circle',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->getLabel()],
            []
        );
    }
}
