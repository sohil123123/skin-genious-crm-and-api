<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Outcome of one provider synchronisation run.
 *
 * Partial is the state that earns this enum its keep: a run that imported 180
 * of 200 calls before hitting a rate limit is neither a success nor a failure,
 * and calling it either one would mislead whoever reads the health screen next.
 */
enum CallSyncStatus: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Running => 'info',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::Running;
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
