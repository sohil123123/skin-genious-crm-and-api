<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How this call record reached the CRM.
 *
 * Worth storing because the same call routinely arrives twice — once by
 * webhook while it is happening and again by API sync hours later. When a
 * record looks wrong, the first question is always which path wrote it.
 */
enum CallSource: string implements HasColor, HasLabel
{
    case Webhook = 'webhook';
    case ApiSync = 'api_sync';
    case Manual = 'manual';
    case Import = 'import';

    public function getLabel(): string
    {
        return match ($this) {
            self::Webhook => 'Webhook',
            self::ApiSync => 'API Sync',
            self::Manual => 'Manual',
            self::Import => 'Import',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Webhook => 'success',
            self::ApiSync => 'info',
            self::Manual => 'gray',
            self::Import => 'warning',
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
