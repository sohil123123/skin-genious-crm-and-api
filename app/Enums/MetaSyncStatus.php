<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Where one Meta lead has got to in the ingestion pipeline.
 *
 * Skipped is deliberately distinct from Success: both mean "nothing is wrong",
 * but skipped says no lead was written because the CRM already had it, which is
 * the expected outcome of Meta redelivering a webhook rather than a problem to
 * investigate.
 */
enum MetaSyncStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Success => 'Imported',
            self::Skipped => 'Already present',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'info',
            self::Success => 'success',
            self::Skipped => 'warning',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Processing => 'heroicon-o-arrow-path',
            self::Success => 'heroicon-o-check-circle',
            self::Skipped => 'heroicon-o-minus-circle',
            self::Failed => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Whether the pipeline has finished with this record, either way.
     */
    public function isSettled(): bool
    {
        return in_array($this, [self::Success, self::Skipped], true);
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
