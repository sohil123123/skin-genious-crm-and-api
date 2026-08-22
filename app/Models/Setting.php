<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Setting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('setting');
    }

    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    protected static array $runtimeCache = [];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::saved(function ($setting) {
            unset(static::$runtimeCache[$setting->key]);
            Cache::forget("setting_{$setting->key}");
        });

        static::deleted(function ($setting) {
            unset(static::$runtimeCache[$setting->key]);
            Cache::forget("setting_{$setting->key}");
        });
    }

    /**
     * Drop the in-process memo of every setting.
     *
     * The runtime cache is static, so it outlives a single request: a queue
     * worker that never saves a setting would otherwise serve the value it read
     * when it booted, hours after someone changed it. Tests need the same
     * escape hatch to stop one case leaking into the next.
     */
    public static function flushRuntimeCache(): void
    {
        static::$runtimeCache = [];
    }

    /**
     * Get a setting value by key, with optional default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$runtimeCache)) {
            return static::$runtimeCache[$key];
        }

        $value = Cache::rememberForever("setting_{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });

        static::$runtimeCache[$key] = $value;

        return $value;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get the loyalty earning rate percentage.
     */
    public static function getLoyaltyRate(): float
    {
        return (float) static::getValue('loyalty_points_rate', 5);
    }

    /**
     * Get the minimum points required for redemption.
     */
    public static function getLoyaltyMinRedeem(): int
    {
        return (int) static::getValue('loyalty_min_redeem_points', 1000);
    }

    /**
     * Get OTP expiry duration in minutes.
     */
    public static function getLoyaltyOtpExpiry(): int
    {
        return (int) static::getValue('loyalty_otp_expiry_minutes', 5);
    }
}
