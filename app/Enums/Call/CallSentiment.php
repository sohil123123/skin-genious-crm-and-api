<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How the customer sounded, as judged by AI analysis.
 *
 * A coarse label stored alongside a numeric sentiment_score, because the two
 * are used differently: the label is what staff read on a call card, the score
 * is what engagement trends are computed from. Mixed is included because a real
 * clinic call is frequently both — pleased with the treatment, unhappy with the
 * price — and forcing that into positive or negative would lose the objection.
 */
enum CallSentiment: string implements HasColor, HasLabel
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
    case Mixed = 'mixed';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Positive => 'Positive',
            self::Neutral => 'Neutral',
            self::Negative => 'Negative',
            self::Mixed => 'Mixed',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Neutral => 'gray',
            self::Negative => 'danger',
            self::Mixed => 'warning',
            self::Unknown => 'gray',
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
