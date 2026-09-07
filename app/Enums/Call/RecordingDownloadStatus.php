<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Progress of fetching a recording from the provider.
 *
 * Separate from RecordingStorageStatus because the two fail independently: a
 * download can succeed and the write to disk still fail, and knowing which of
 * the two broke is the difference between retrying and fixing a disk.
 *
 * Skipped is not a failure. It is the state for a call whose recording will
 * never exist — a missed call, or a provider that offers no recording URL —
 * and it exists so those calls stop being retried forever.
 */
enum RecordingDownloadStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Downloaded = 'downloaded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Downloading => 'Downloading',
            self::Downloaded => 'Downloaded',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Downloaded => 'success',
            self::Downloading => 'info',
            self::Pending => 'warning',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Downloaded, self::Skipped], true);
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
