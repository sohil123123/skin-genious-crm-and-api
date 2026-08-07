<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ImportFailureReason: string implements HasColor, HasIcon, HasLabel
{
    case Validation = 'validation';
    case Duplicate = 'duplicate';
    case MissingRequired = 'missing_required';
    case Transform = 'transform';
    case Database = 'database';

    public function getLabel(): string
    {
        return match ($this) {
            self::Validation => 'Validation Error',
            self::Duplicate => 'Duplicate',
            self::MissingRequired => 'Missing Required Field',
            self::Transform => 'Transformation Error',
            self::Database => 'Database Error',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Validation => 'warning',
            self::Duplicate => 'info',
            self::MissingRequired => 'danger',
            self::Transform => 'warning',
            self::Database => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Validation => 'heroicon-o-exclamation-triangle',
            self::Duplicate => 'heroicon-o-document-duplicate',
            self::MissingRequired => 'heroicon-o-no-symbol',
            self::Transform => 'heroicon-o-wrench',
            self::Database => 'heroicon-o-circle-stack',
        };
    }

    /**
     * Whether re-running the row unchanged could plausibly succeed.
     *
     * A transient database error is worth retrying; a row missing its phone
     * number will fail identically until a human edits it.
     */
    public function isRetryable(): bool
    {
        return $this === self::Database;
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
