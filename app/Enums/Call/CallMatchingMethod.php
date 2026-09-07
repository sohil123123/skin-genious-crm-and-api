<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasLabel;

/**
 * How the customer on a call was identified.
 *
 * Stored alongside the result so a wrong match can be diagnosed without
 * re-running the matcher: "matched by provider lead id" and "matched by phone"
 * fail for entirely different reasons.
 */
enum CallMatchingMethod: string implements HasLabel
{
    case Phone = 'phone';
    case AlternatePhone = 'alternate_phone';
    case ProviderLeadId = 'provider_lead_id';
    case Manual = 'manual';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::AlternatePhone => 'Alternate Phone',
            self::ProviderLeadId => 'Provider Lead ID',
            self::Manual => 'Manual',
            self::Unknown => 'Unknown',
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
