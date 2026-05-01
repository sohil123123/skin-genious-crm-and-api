<?php

namespace App\Enums;

enum ExpenseReferenceType: string
{
    case Manual = 'manual';
    case Purchase = 'purchase';
    case Salary = 'salary';
    case Rent = 'rent';
    case Utility = 'utility';
    case Misc = 'misc';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Purchase => 'Purchase',
            self::Salary => 'Salary',
            self::Rent => 'Rent',
            self::Utility => 'Utility',
            self::Misc => 'Miscellaneous',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
