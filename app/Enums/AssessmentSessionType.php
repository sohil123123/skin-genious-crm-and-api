<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AssessmentSessionType: string implements HasColor, HasIcon, HasLabel
{
    case Single = 'single';
    case Multiple = 'multiple';
    case Express = 'express';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Multiple => 'Multiple',
            self::Express => 'Express',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Single => 'info',
            self::Multiple => 'warning',
            self::Express => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Single => 'heroicon-o-chat-bubble-left-right',
            self::Multiple => 'heroicon-o-clipboard-document-check',
            self::Express => 'heroicon-o-clipboard-document-check',
        };
    }
}
