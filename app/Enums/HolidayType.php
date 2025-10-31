<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum HolidayType: string implements HasColor, HasIcon, HasLabel
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Sick = 'sick';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Unpaid => 'Unpaid',
            self::Sick => 'Sick',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Unpaid => 'warning',
            self::Sick => 'danger',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Paid => 'heroicon-m-currency-rupee',
            self::Unpaid => 'heroicon-m-clock',
            self::Sick => 'heroicon-m-heart',
            self::Other => 'heroicon-m-question-mark-circle',
        };
    }
}
