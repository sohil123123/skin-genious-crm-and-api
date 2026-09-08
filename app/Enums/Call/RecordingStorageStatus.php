<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where the audio for a recording currently lives.
 *
 * RemoteOnly is the honest starting state: the provider has published a URL and
 * the CRM has written it down, but the audio is still on their server and will
 * disappear when their retention window closes. Stored is the only state in
 * which the clinic actually owns the conversation.
 *
 * Purged is deliberate deletion under the retention policy, and is kept
 * distinct from Failed so an audit can tell "we deleted this on purpose" from
 * "we lost this".
 */
enum RecordingStorageStatus: string implements HasColor, HasLabel
{
    case RemoteOnly = 'remote_only';
    case Downloading = 'downloading';
    case Stored = 'stored';
    case Failed = 'failed';
    case Purged = 'purged';

    public function getLabel(): string
    {
        return match ($this) {
            self::RemoteOnly => 'Remote Only',
            self::Downloading => 'Downloading',
            self::Stored => 'Stored',
            self::Failed => 'Failed',
            self::Purged => 'Purged',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stored => 'success',
            self::Downloading => 'info',
            self::RemoteOnly => 'warning',
            self::Failed => 'danger',
            self::Purged => 'gray',
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
