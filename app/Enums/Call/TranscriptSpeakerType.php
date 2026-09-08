<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which side of the conversation a transcript segment came from.
 *
 * Kept separate from the provider's raw speaker label — diarisation returns
 * "SPEAKER_00", which is stable within one recording and meaningless across
 * two. Mapping that to agent or customer is an interpretation, so the raw label
 * is stored next to it and Unknown is a legitimate answer rather than a guess.
 */
enum TranscriptSpeakerType: string implements HasColor, HasLabel
{
    case Agent = 'agent';
    case Customer = 'customer';
    case System = 'system';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Agent => 'Agent',
            self::Customer => 'Customer',
            self::System => 'System',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Agent => 'info',
            self::Customer => 'success',
            self::System => 'warning',
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
