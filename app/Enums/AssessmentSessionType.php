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
    case BudgetOption = 'budget_option';
    case PlanOption = 'plan_option';
    case SingleSessionOption1 = 'single_session_option_1';
    case SingleSessionOption2 = 'single_session_option_2';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Multiple => 'Multiple',
            self::Express => 'Express',
            self::BudgetOption => 'Budget Option',
            self::PlanOption => 'Plan Option',
            self::SingleSessionOption1 => 'Single Session Option 1',
            self::SingleSessionOption2 => 'Single Session Option 2',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Single => 'info',
            self::Multiple => 'warning',
            self::Express => 'success',
            self::BudgetOption => 'success',
            self::PlanOption => 'success',
            self::SingleSessionOption1 => 'success',
            self::SingleSessionOption2 => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Single => 'heroicon-o-chat-bubble-left-right',
            self::Multiple => 'heroicon-o-clipboard-document-check',
            self::Express => 'heroicon-o-clipboard-document-check',
            self::BudgetOption => 'heroicon-o-clipboard-document-check',
            self::PlanOption => 'heroicon-o-clipboard-document-check',
            self::SingleSessionOption1 => 'heroicon-o-clipboard-document-check',
            self::SingleSessionOption2 => 'heroicon-o-clipboard-document-check',
        };
    }
}
