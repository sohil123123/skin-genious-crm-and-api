<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AvailabilityExceptionType: string implements HasColor, HasIcon, HasLabel
{
    case LeaveFullDay = 'leave_full_day';
    case LeavePartial = 'leave_partial';
    case ExtraHours = 'extra_hours';
    case OverrideHours = 'override_hours';
    case BlockedHours = 'blocked_hours';

    public function getLabel(): string
    {
        return match ($this) {
            self::LeaveFullDay   => 'Full Day Leave',
            self::LeavePartial   => 'Partial Day Leave',
            self::ExtraHours     => 'Extra Working Hours',
            self::OverrideHours  => 'Override Working Hours',
            self::BlockedHours   => 'Blocked Hours',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LeaveFullDay   => 'success', // approved leave
            self::LeavePartial   => 'warning', // partial / caution
            self::ExtraHours     => 'primary', // additional effort
            self::OverrideHours  => 'info',    // system override
            self::BlockedHours   => 'danger',  // strict restriction
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::LeaveFullDay   => 'heroicon-o-calendar-days',
            self::LeavePartial   => 'heroicon-o-clock',
            self::ExtraHours     => 'heroicon-o-plus-circle',
            self::OverrideHours  => 'heroicon-o-adjustments-horizontal',
            self::BlockedHours   => 'heroicon-o-lock-closed',
        };
    }
}
