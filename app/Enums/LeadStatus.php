<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Unqualified = 'unqualified';
    case Junk = 'junk';
    case Lost = 'lost';
    case Won = 'won';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Unqualified => 'Unqualified',
            self::Junk => 'Junk',
            self::Lost => 'Lost',
            self::Won => 'Won',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'primary',
            self::Qualified => 'warning',
            self::Unqualified => 'gray',
            self::Junk => 'danger',
            self::Lost => 'danger',
            self::Won => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-o-sparkles',
            self::Contacted => 'heroicon-o-phone',
            self::Qualified => 'heroicon-o-hand-thumb-up',
            self::Unqualified => 'heroicon-o-hand-thumb-down',
            self::Junk => 'heroicon-o-trash',
            self::Lost => 'heroicon-o-x-circle',
            self::Won => 'heroicon-o-check-badge',
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
