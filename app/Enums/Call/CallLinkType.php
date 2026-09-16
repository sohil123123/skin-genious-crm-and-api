<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Which side of the CRM a call is attached to.
 *
 * Distinct from CallMatchingStatus, which answers "did we work out who this
 * was" — a question about the matching process. This answers "who is it",
 * which is what a receptionist actually needs before picking a row: a patient
 * has a history, a package and an appointment diary; a lead has none of those
 * and a sales conversation instead. The two are opened in different resources
 * and handled by different people, so a list that shows only a name asks
 * everybody to remember which of the two each caller is.
 *
 * Kept as an enum rather than a pair of booleans because both action engines,
 * the table filter and the card badge all need the same three-way answer, and
 * three copies of the same match expression drift the first time a call can be
 * attached to something else.
 */
enum CallLinkType: string implements HasColor, HasIcon, HasLabel
{
    case Patient = 'patient';
    case Lead = 'lead';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Patient => 'Client',
            self::Lead => 'Lead',
            self::None => 'Not linked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Patient => 'success',
            self::Lead => 'info',
            self::None => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Patient => 'heroicon-m-user-circle',
            self::Lead => 'heroicon-m-user-plus',
            self::None => 'heroicon-m-question-mark-circle',
        };
    }

    /**
     * What the badge says on hover, since the label alone is one word.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Patient => 'An existing patient record',
            self::Lead => 'A lead, not yet a patient',
            self::None => 'Not linked to a patient or a lead',
        };
    }

    public function isLinked(): bool
    {
        return $this !== self::None;
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
