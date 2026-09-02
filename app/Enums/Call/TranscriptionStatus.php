<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Progress of turning a recording into text.
 *
 * Shared by call_recordings (where it tracks the pipeline) and
 * call_transcriptions (where it tracks one attempt).
 *
 * NotAvailable covers the permanent cases — no recording, audio too short to
 * carry speech, transcription switched off — and is what stops the retry loop.
 * Disabled would be a worse name: whether the feature is on is a setting, but
 * whether this particular recording can ever be transcribed is a fact.
 */
enum TranscriptionStatus: string implements HasColor, HasLabel
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
