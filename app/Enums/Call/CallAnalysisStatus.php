<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Progress of the AI pass over a transcript.
 *
 * Mirrors TranscriptionStatus rather than reusing it, because the two will
 * diverge: analysis gains states transcription has no use for (awaiting review,
 * superseded by a newer analysis version) as soon as it is switched on.
 */
enum CallAnalysisStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case NotAvailable = 'not_available';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::NotAvailable => 'Not Available',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Processing => 'info',
            self::Pending => 'warning',
            self::Failed => 'danger',
            self::NotAvailable => 'gray',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::NotAvailable], true);
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
