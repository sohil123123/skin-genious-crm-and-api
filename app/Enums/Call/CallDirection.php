<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Which way the call went.
 *
 * Deliberately kept separate from CallStatus. Both providers conflate the two —
 * Callyzer's call_type says "Missed" and Exotel's Direction says "incoming"
 * while CallStatus says "no-answer" — but "a call we received" and "nobody
 * picked it up" answer different questions, and every report that matters here
 * needs to ask them independently. A missed call is an incoming call that was
 * not answered, and is stored as exactly that.
 */
enum CallDirection: string implements HasColor, HasIcon, HasLabel
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
    case Internal = 'internal';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Incoming => 'Incoming',
            self::Outgoing => 'Outgoing',
            self::Internal => 'Internal',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Incoming => 'success',
            self::Outgoing => 'info',
            self::Internal => 'gray',
            self::Unknown => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Incoming => 'heroicon-o-arrow-down-left',
            self::Outgoing => 'heroicon-o-arrow-up-right',
            self::Internal => 'heroicon-o-arrows-right-left',
            self::Unknown => 'heroicon-o-question-mark-circle',
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
