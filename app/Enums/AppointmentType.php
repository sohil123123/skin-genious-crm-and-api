<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AppointmentType: string implements HasColor, HasIcon, HasLabel
{
    case Consult = 'consult';
    case Treatment = 'treatment';
    case Express = 'express';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consult => 'Consult',
            self::Treatment => 'Treatment',
            self::Express => 'Express',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Consult => 'info',
            self::Treatment => 'success',
            self::Express => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Consult => 'heroicon-o-chat-bubble-left-right',
            self::Treatment => 'heroicon-o-clipboard-document-check',
            self::Express => 'heroicon-o-clock',
        };
    }
}
