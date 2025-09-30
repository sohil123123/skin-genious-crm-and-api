<?php

namespace App\Traits;

use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

trait HasWidgetShield
{
    public static function getPermissionPrefixes(): array
    {
        return ['view'];
    }

    public static function getPermissionIdentifier(): string
    {
        return str(static::class)
            ->classBasename()
            ->replace('Widget', '') // optional, to clean up names
            ->value();
    }

    public static function canView(): bool
    {
        $prefix = ucfirst(self::getPermissionPrefixes()[0]); // "View"
        $identifier = static::getPermissionIdentifier();

        // Apply config rules
        $separator = config('filament-shield.permissions.separator', '.'); // default "."
        $case = strtolower(config('filament-shield.permissions.case', 'camel'));

        // Format identifier
        if ($case === 'pascal') {
            $identifier = str($identifier)->studly()->value(); // "UserStats"
        } elseif ($case === 'snake') {
            $identifier = str($identifier)->snake()->value(); // "user_stats"
        } elseif ($case === 'kebab') {
            $identifier = str($identifier)->kebab()->value(); // "user-stats"
        }

        $permission = "{$prefix}{$separator}{$identifier}";

        return auth()->check() && auth()->user()->can($permission);
    }
}
