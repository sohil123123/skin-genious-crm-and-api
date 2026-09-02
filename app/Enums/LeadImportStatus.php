<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LeadImportStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Analyzing = 'analyzing';
    case Mapping = 'mapping';
    case Ready = 'ready';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Analyzing => 'Analyzing',
            self::Mapping => 'Awaiting Mapping',
            self::Ready => 'Ready to Import',
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with Errors',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::Mapping => 'gray',
            self::Analyzing, self::Queued => 'info',
            self::Ready => 'primary',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Analyzing => 'heroicon-o-magnifying-glass',
            self::Mapping => 'heroicon-o-arrows-right-left',
            self::Ready => 'heroicon-o-check',
            self::Queued => 'heroicon-o-queue-list',
            self::Processing => 'heroicon-o-arrow-path',
            self::Completed => 'heroicon-o-check-circle',
            self::CompletedWithErrors => 'heroicon-o-exclamation-triangle',
            self::Failed => 'heroicon-o-x-circle',
            self::Cancelled => 'heroicon-o-no-symbol',
        };
    }

    /**
     * Whether the import is still moving, which is what drives UI polling.
     */
    public function isRunning(): bool
    {
        return in_array($this, [self::Queued, self::Processing, self::Analyzing], true);
    }

    /**
     * Whether the import has reached a terminal state.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors, self::Failed, self::Cancelled], true);
    }

    /**
     * Whether a running import may still be cancelled.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Mapping, self::Ready, self::Queued, self::Processing], true);
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
