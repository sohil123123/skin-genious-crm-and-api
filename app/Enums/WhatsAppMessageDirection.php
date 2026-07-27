<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppMessageDirection: string implements HasColor, HasIcon, HasLabel
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Incoming => 'Incoming',
            self::Outgoing => 'Outgoing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Incoming => 'info',
            self::Outgoing => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Incoming => 'heroicon-o-arrow-down-left',
            self::Outgoing => 'heroicon-o-arrow-up-right',
        };
    }
}
