<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasColor, HasIcon, HasLabel
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Manual = 'manual';
    case Referral = 'referral';
    case Walkin = 'walkin';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Manual => 'Manual Entry',
            self::Referral => 'Referral',
            self::Walkin => 'Walk-in',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Facebook => 'info',
            self::Instagram => 'danger',
            self::Manual => 'gray',
            self::Referral => 'success',
            self::Walkin => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Facebook, self::Instagram => 'heroicon-o-megaphone',
            self::Manual => 'heroicon-o-pencil-square',
            self::Referral => 'heroicon-o-user-group',
            self::Walkin => 'heroicon-o-building-storefront',
            self::Other => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Resolve the CRM source from Meta's "platform" column.
     *
     * Real exports use the short codes "ig" and "fb".
     */
    public static function fromMetaPlatform(?string $platform): self
    {
        return match (strtolower(trim((string) $platform))) {
            'ig', 'instagram' => self::Instagram,
            'fb', 'facebook' => self::Facebook,
            default => self::Facebook,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->getLabel()],
            []
        );
    }
}
