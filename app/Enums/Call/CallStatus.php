<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What happened to the call, independent of which way it went.
 *
 * Ordered as a lifecycle: a call may arrive as Ringing and be updated to
 * InProgress and then Completed by later events on the same provider call id.
 * Anything the providers say that is not in this list is preserved verbatim in
 * provider_call_status and lands here as Unknown, so a new provider vocabulary
 * degrades rather than breaks.
 */
enum CallStatus: string implements HasColor, HasIcon, HasLabel
{
    case Queued = 'queued';
    case Ringing = 'ringing';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Missed = 'missed';
    case Rejected = 'rejected';
    case Busy = 'busy';
    case NoAnswer = 'no_answer';
    case NotConnected = 'not_connected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Ringing => 'Ringing',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Missed => 'Missed',
            self::Rejected => 'Rejected',
            self::Busy => 'Busy',
            self::NoAnswer => 'No Answer',
            self::NotConnected => 'Not Connected',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::InProgress, self::Ringing, self::Queued => 'info',
            self::Missed, self::Rejected => 'danger',
            self::Busy, self::NoAnswer, self::NotConnected, self::Cancelled => 'warning',
            self::Failed => 'danger',
            self::Unknown => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Completed => 'heroicon-o-check-circle',
            self::InProgress => 'heroicon-o-phone',
            self::Ringing, self::Queued => 'heroicon-o-clock',
            self::Missed => 'heroicon-o-phone-x-mark',
            self::Rejected, self::Failed => 'heroicon-o-x-circle',
            self::Busy, self::NoAnswer, self::NotConnected, self::Cancelled => 'heroicon-o-exclamation-triangle',
            self::Unknown => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Whether two people actually spoke.
     *
     * This is the distinction every call report is really built on — "we made
     * 60 calls" means nothing next to "we had 22 conversations".
     */
    public function isConnected(): bool
    {
        return in_array($this, [self::InProgress, self::Completed], true);
    }

    /**
     * Whether the call reached someone but nobody spoke.
     */
    public function isUnanswered(): bool
    {
        return in_array($this, [
            self::Missed,
            self::Rejected,
            self::Busy,
            self::NoAnswer,
            self::NotConnected,
            self::Cancelled,
        ], true);
    }

    /**
     * Whether the provider may still send another event for this call.
     *
     * Guards the update path: a late-arriving "ringing" must never overwrite a
     * "completed" that already landed, which happens whenever a provider
     * retries an early webhook after a later one succeeded.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Queued, self::Ringing, self::InProgress, self::Unknown], true);
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

    /**
     * @return array<int, string>
     */
    public static function connectedValues(): array
    {
        return [self::InProgress->value, self::Completed->value];
    }

    /**
     * @return array<int, string>
     */
    public static function unansweredValues(): array
    {
        return [
            self::Missed->value,
            self::Rejected->value,
            self::Busy->value,
            self::NoAnswer->value,
            self::NotConnected->value,
            self::Cancelled->value,
        ];
    }
}
