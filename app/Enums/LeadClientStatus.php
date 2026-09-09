<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether an enquiry brought somebody in, or reached somebody already ours.
 *
 * The distinction the Leads table could not previously draw. The Client column
 * read `matched_user_id`, which is written at import time when the matcher
 * looks for a client who already exists — so the only badge it could ever show
 * was "Existing", and the far more interesting population was invisible.
 *
 * The two states cost the clinic opposite things. A converted lead is an ad
 * that paid for itself. An existing client answering an ad is spend on somebody
 * who would probably have come anyway, and enough of them is a reason to change
 * the targeting.
 */
enum LeadClientStatus: string implements HasColor, HasIcon, HasLabel
{
    case Converted = 'converted';
    case Existing = 'existing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Converted => 'Converted',
            self::Existing => 'Existing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            // Green for the outcome the clinic is paying for; amber for the one
            // worth noticing but not celebrating.
            self::Converted => 'success',
            self::Existing => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Converted => 'heroicon-o-user-plus',
            self::Existing => 'heroicon-o-identification',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Converted => 'Became a client after this enquiry',
            self::Existing => 'Was already a client when this enquiry arrived',
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
