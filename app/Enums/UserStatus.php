<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'true';
    case Deactive = 'false';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Deactive => 'Deactive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Deactive => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-m-check-badge',
            self::Deactive => 'heroicon-m-x-circle',
        };
    }
}
