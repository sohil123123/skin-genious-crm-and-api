<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What happened to one received webhook or one stored payload.
 *
 * Duplicate is its own state rather than an error: both providers retry, and a
 * retry arriving is normal behaviour, not a fault. Counting retries as failures
 * would make the integration health screen permanently alarming and therefore
 * permanently ignored.
 */
enum CallEventProcessingStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Processed => 'Processed',
            self::Duplicate => 'Duplicate',
            self::Ignored => 'Ignored',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Processed => 'success',
            self::Processing => 'info',
            self::Pending => 'warning',
            self::Duplicate, self::Ignored => 'gray',
            self::Failed => 'danger',
        };
    }

    /**
     * Whether reprocessing this event would achieve anything.
     */
    public function isSettled(): bool
    {
        return in_array($this, [self::Processed, self::Duplicate, self::Ignored], true);
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
