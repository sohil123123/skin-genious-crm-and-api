<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What to do when an incoming row matches a lead that already exists.
 */
enum DuplicateStrategy: string implements HasColor, HasIcon, HasLabel
{
    case Skip = 'skip';
    case Update = 'update';
    case Merge = 'merge';
    case CreateDuplicate = 'create_duplicate';

    public function getLabel(): string
    {
        return match ($this) {
            self::Skip => 'Skip duplicate rows',
            self::Update => 'Update the existing lead',
            self::Merge => 'Merge into the existing lead',
            self::CreateDuplicate => 'Import as a separate lead',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Skip => 'Leave the existing lead untouched and record the row as skipped. Safest for re-uploading an overlapping export.',
            self::Update => 'Overwrite the existing lead with every mapped value from the file, including blanks.',
            self::Merge => 'Fill in only the fields that are currently empty on the existing lead, and add any custom answers it does not already have. Nothing existing is overwritten.',
            self::CreateDuplicate => 'Always create a new lead, even when a match is found. Use when the same person legitimately submitted two different forms.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Skip => 'gray',
            self::Update => 'warning',
            self::Merge => 'info',
            self::CreateDuplicate => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Skip => 'heroicon-o-forward',
            self::Update => 'heroicon-o-pencil-square',
            self::Merge => 'heroicon-o-arrows-pointing-in',
            self::CreateDuplicate => 'heroicon-o-document-duplicate',
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

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->getDescription()],
            []
        );
    }
}
