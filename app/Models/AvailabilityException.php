<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

use App\Enums\LeaveType;
use App\Enums\AvailabilityExceptionType;
use App\Enums\AvailabilityExceptionStatus;

use App\Models\Clinic;

class AvailabilityException extends Model
{
    protected $fillable = [
        'exceptionable_type',
        'exceptionable_id',
        'clinic_id',
        'type',
        'effect',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'leave_type',
        'leave_days',
        'reason',
        'notes',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'is_active',
    ];

    protected $casts = [
        'start_date'  => 'date:Y-m-d',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'is_active'   => 'boolean',
        'type' => AvailabilityExceptionType::class,
        'leave_type' => LeaveType::class,
        'status' => AvailabilityExceptionStatus::class
    ];

    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('H:i', strtotime($value)) : null,
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('H:i', strtotime($value)) : null,
        );
    }

    /* ---------------- Scopes ---------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'approved');
    }

    // public function scopeForDate(Builder $query, $date): Builder
    // {
    //     return $query
    //         ->whereDate('start_date', '<=', $date)
    //         ->whereDate('end_date', '>=', $date);
    // }

    protected static function booted() {
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id;

            $record->updateEntitlementUsage();
        });

        static::updating(function ($record) {
            $record->updateEntitlementUsage();
        });

        static::deleted(function ($record) {
            $record->updateEntitlementUsage(true);
        });
    }

    /* ---------------- Relations ---------------- */

    public function exceptionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ---------------- Logic Helpers ---------------- */

    // public function isFullDay(): bool
    // {
    //     return is_null($this->start_time) && is_null($this->end_time);
    // }

    // public function isMultiDay(): bool
    // {
    //     return $this->start_date->lt($this->end_date);
    // }

    // public function blocksAvailability(): bool
    // {
    //     return $this->effect === 'block';
    // }

    // public function addsAvailability(): bool
    // {
    //     return $this->effect === 'add';
    // }

    // public function overridesAvailability(): bool
    // {
    //     return $this->effect === 'override';
    // }

    /**
     * Enforce 15-minute boundary
     */
    public function isValidSlotBoundary(): bool
    {
        foreach ([$this->start_time, $this->end_time] as $time) {
            if ($time && Carbon::createFromFormat('H:i:s', $time)->minute % 15 !== 0) {
                return false;
            }
        }
        return true;
    }

    public function updateEntitlementUsage(bool $isReversal = false): void
    {
        // Only leave types affect entitlement
        if($this->exceptionable_type == Clinic::class || !in_array($this->type, ['leave_full_day', 'leave_partial']))
            return;

        $leaveDays = 0;

        // ============================
        // FULL DAY LEAVE
        // ============================
        if ($this->type === 'leave_full_day')
            $leaveDays = Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1;

        // ============================
        // PARTIAL DAY LEAVE
        // ============================
        if ($this->type === 'leave_partial') {
            // if (! $this->start_time || ! $this->end_time) {
            //     return; // invalid partial leave
            // }

            // $start = Carbon::parse($this->start_time);
            // $end   = Carbon::parse($this->end_time);

            // $minutes = $start->diffInMinutes($end);

            // // Standard working hours (configurable)
            // $workingMinutesPerDay = config('project.working_hours_per_day', 8) * 60;

            // $leaveDays = round($minutes / $workingMinutesPerDay, 2);
            // $leaveDays = min($leaveDays, 1);

            $leaveDays = 0.5;
        }

        // ============================
        // UPDATE ENTITLEMENT
        // ============================
        $entitlement = $this->exceptionable
            ?->leaveEntitlements()
            ->where('year', Carbon::parse($this->start_date)->year)
            ->where('leave_type', $this->leave_type)
            ->first();

        if (! $entitlement) {
            return;
        }

        if ($isReversal) {
            $entitlement->used -= $leaveDays;
        } else {
            $entitlement->used += $leaveDays;
        }
        $entitlement->remaining = $entitlement->total_allowed - $entitlement->used;
        $entitlement->save();

        // Save computed leave days on exception
        $this->leave_days = $leaveDays;
        // $this->saveQuietly();
    }
}
