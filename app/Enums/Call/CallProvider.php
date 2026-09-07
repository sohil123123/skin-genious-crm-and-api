<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The telephony system a call was recorded by.
 *
 * The CRM stores this so a call can be traced back to its source, not so that
 * features can branch on it. Anything that needs to behave differently per
 * provider belongs in that provider's adapter.
 */
enum CallProvider: string implements HasColor, HasIcon, HasLabel
{
    case Exotel = 'exotel';
    case Callyzer = 'callyzer';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Exotel => 'Exotel',
            self::Callyzer => 'Callyzer',
            self::Manual => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Exotel => 'info',
            self::Callyzer => 'warning',
            self::Manual => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Exotel => 'heroicon-o-phone-arrow-down-left',
            self::Callyzer => 'heroicon-o-phone-arrow-up-right',
            self::Manual => 'heroicon-o-pencil-square',
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
