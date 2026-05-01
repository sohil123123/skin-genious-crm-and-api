<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Variable => 'Variable',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
