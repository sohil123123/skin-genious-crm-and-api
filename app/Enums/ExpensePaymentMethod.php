<?php

namespace App\Enums;

enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Wallet = 'wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Upi => 'UPI',
            self::BankTransfer => 'Bank Transfer',
            self::Cheque => 'Cheque',
            self::Wallet => 'Wallet',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Card => 'info',
            self::Upi => 'primary',
            self::BankTransfer => 'warning',
            self::Cheque => 'gray',
            self::Wallet => 'info',
            self::Other => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
