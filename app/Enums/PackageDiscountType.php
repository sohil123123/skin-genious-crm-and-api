<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PackageDiscountType: string implements HasColor, HasIcon, HasLabel
{
    case Flat       = 'flat';
    case Percentage = 'percentage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Flat       => 'Flat (₹)',
            self::Percentage => 'Percentage (%)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Flat       => 'info',
            self::Percentage => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Flat       => 'heroicon-o-currency-rupee',
            self::Percentage => 'heroicon-o-percent-badge',
        };
    }
}
