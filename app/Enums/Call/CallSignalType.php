<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The families a call signal can belong to.
 *
 * Seven families rather than one flat list, because the Next Best Action
 * engines treat them differently: an objection asks for a message that answers
 * it, a risk signal asks for recovery, and an opportunity asks for speed. The
 * family is what a rule matches on before it looks at the individual key.
 */
enum CallSignalType: string implements HasColor, HasLabel
{
    case Engagement = 'engagement';
    case Intent = 'intent';
    case Objection = 'objection';
    case Sentiment = 'sentiment';
    case FollowUp = 'follow_up';
    case Risk = 'risk';
    case Opportunity = 'opportunity';

    public function getLabel(): string
    {
        return match ($this) {
            self::Engagement => 'Engagement',
            self::Intent => 'Intent',
            self::Objection => 'Objection',
            self::Sentiment => 'Sentiment',
            self::FollowUp => 'Follow-up',
            self::Risk => 'Risk',
            self::Opportunity => 'Opportunity',
        };
    }

    /**
     * Order these matter in when opening a conversation. Lower comes first.
     *
     * Deliberately not the same order as pressure, which ranks how hard a
     * signal argues for ringing somebody. That is the right question for a
     * queue and the wrong one for a script: a lead who sounded keen and also
     * said they must ask their wife scores highest on the keenness, but opening
     * with "you sounded keen, shall we book you in" walks straight past the one
     * thing standing between them and a booking.
     *
     * So: apologise first if something went wrong, then answer what was asked
     * for, then handle the objection, and only then sell. Enthusiasm is why the
     * call is happening; it is not what needs saying.
     */
    public function scriptRank(): int
    {
        return match ($this) {
            self::Risk => 1,
            self::FollowUp => 2,
            self::Objection => 3,
            self::Intent, self::Opportunity => 4,
            self::Sentiment, self::Engagement => 5,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Opportunity, self::Intent => 'success',
            self::Objection, self::FollowUp => 'warning',
            self::Risk => 'danger',
            self::Sentiment, self::Engagement => 'gray',
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
            [],
        );
    }
}
