<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether the CRM knows who this call was with.
 *
 * Ambiguous exists as a first-class state rather than being folded into
 * Unmatched because the two need opposite handling: an unmatched call is
 * usually a genuinely new person, while an ambiguous one is a data-quality
 * problem — the same number on two records — that a human must resolve. Guessing
 * would file a conversation into the wrong patient's history, which is the one
 * outcome this whole system must not produce.
 */
enum CallMatchingStatus: string implements HasColor, HasIcon, HasLabel
{
    case Matched = 'matched';
    case Unmatched = 'unmatched';
    case Ambiguous = 'ambiguous';
    case ManuallyMatched = 'manually_matched';

    public function getLabel(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::Unmatched => 'Unmatched',
            self::Ambiguous => 'Ambiguous',
            self::ManuallyMatched => 'Manually Matched',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Matched, self::ManuallyMatched => 'success',
            self::Unmatched => 'gray',
            self::Ambiguous => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Matched => 'heroicon-o-check-circle',
            self::ManuallyMatched => 'heroicon-o-hand-raised',
            self::Unmatched => 'heroicon-o-question-mark-circle',
            self::Ambiguous => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Whether a person has already made this decision.
     *
     * A manual match must survive every later provider event and every re-sync,
     * so automatic matching skips these records entirely.
     */
    public function isHumanDecided(): bool
    {
        return $this === self::ManuallyMatched;
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Unmatched, self::Ambiguous], true);
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
