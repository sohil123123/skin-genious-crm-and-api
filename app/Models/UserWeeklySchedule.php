<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\Attribute;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class UserWeeklySchedule extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('user_weekly_schedule');
    }

    protected $fillable = [
        'user_id',
        'clinic_id',
        'day_of_week',
        'start_time',
        'end_time',
        'allows_overlap',
        'has_overlap',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allows_overlap' => 'boolean',
        'has_overlap'    => 'boolean',
        'is_active'      => 'boolean',
        // 'weekly_schedule' => 'array',
    ];

    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => date('H:i', strtotime($value)),
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => date('H:i', strtotime($value)),
        );
    }

    public function scopeActive($query) {
        return $query->where('is_active', 1);
    }

    /* -----------------------------------------------------------------
     |  Booted
     | -----------------------------------------------------------------
     */

    // protected static function booted() {
    //     static::saved(function (UserWeeklySchedule $schedule) {
    //         $auth_user_id = auth()->user()->id;
    //         $update_array = ['created_by' => $auth_user_id];
    //         dd($schedule->detectOverlap());
    //         $hasOverlap = $schedule->detectOverlap();
    //         if ($schedule->has_overlap !== $hasOverlap) {
    //             $update_array['has_overlap'] = $hasOverlap;
    //         }
    //         $schedule->updateQuietly($update_array);
    //     });

    //     static::updating(function ($model) {
    //         dd('dd');
    //         $model->updated_by = auth()->user()->id;
    //     });

    // }

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Therapist (User with role = therapist)
     */
    public function therapist()
    {
        return $this->user()->whereHas('roles', fn ($q) => $q->where('name', 'therapist'));
    }

    /**
     * Clinic where therapist works
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Audit relations
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }


    /* -----------------------------------------------------------------
     |  Convenience Helpers
     | -----------------------------------------------------------------
     */

    // public function shiftsForDay(int $day): array
    // {
    //     if (! is_array($this->weekly_schedule)) {
    //         return [];
    //     }

    //     return collect($this->weekly_schedule)
    //         ->where('day', $day)
    //         ->map(fn ($s) =>
    //             substr($s['start'], 0, 5) . ' – ' . substr($s['end'], 0, 5)
    //         )
    //         ->values()
    //         ->all();
    // }

    // public static function dayOptions(): array
    // {
    //     return [
    //         1 => 'Monday',
    //         2 => 'Tuesday',
    //         3 => 'Wednesday',
    //         4 => 'Thursday',
    //         5 => 'Friday',
    //         6 => 'Saturday',
    //         7 => 'Sunday',
    //     ];
    // }

    // public function getDayLabelAttribute(): string
    // {
    //     return static::dayOptions()[$this->day_of_week] ?? (string) $this->day_of_week;
    // }

    // public function isOverlapping(): bool
    // {
    //     return $this->has_overlap === true;
    // }

    // --------------------------- Other ---------------------------
    /**
     * Detect overlaps against DB (used on save)
     */
    public function detectOverlap(): bool
    {
        return self::query()
            ->where('id', '!=', $this->id)
            ->where('user_id', $this->user_id)
            ->where('clinic_id', $this->clinic_id)
            ->where('day_of_week', $this->day_of_week)
            // ->where('is_active', true)
            ->whereTime('start_time', '<', $this->end_time)
            ->whereTime('end_time', '>', $this->start_time)
            ->exists();
    }


}
