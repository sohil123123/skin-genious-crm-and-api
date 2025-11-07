<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

use App\Models\User;

use App\Enums\HolidayStatus;
use App\Enums\HolidayType;

use Carbon\Carbon;

class Holiday extends Model
{
    protected $fillable = ['user_id', 'clinic_id', 'start_date', 'end_date', 'reason', 'status', 'approved_by', 'type', 'days'];

    // protected function status(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn ($value) => ucfirst($value),
    //     );
    // }

    protected $casts = [
        'status' => HolidayStatus::class,
        'type' => HolidayType::class,
    ];

    protected static function booted() {

        static::creating(function ($holiday) {
            $selectedUser = User::find($holiday->user_id);
            if ($selectedUser && $selectedUser->clinic_id) {
                $holiday->clinic_id = $selectedUser->clinic_id;
            }

            $holiday->updateEntitlementUsage();
        });

        static::updating(function ($holiday) {
            $holiday->updateEntitlementUsage();
        });

        // static::updating(function ($holiday) {
        //     // if ($holiday->isDirty('status') && $holiday->status->value === 'approved') {
        //     //     $holiday->user->incrementTakenLeave($holiday->type->value, $holiday->days, date('Y', strtotime($holiday->start_date)));
        //     //     // Trigger auto-adjust for appointments (implement your logic here, e.g., dispatch a job)
        //     //     // dispatch(new AdjustTherapistSchedule($holiday));
        //     // }

        //     // if ($holiday->status === 'approved' && $holiday->isDirty('status')) {
        //     //     // Logic to auto-adjust appointments: e.g., reschedule or notify
        //     //     // Query appointments for this therapist between start_date and end_date
        //     //     Appointment::where('therapist_id', $holiday->user_id)->whereBetween('date', [$holiday->start_date, $holiday->end_date])->update(['status' => 'rescheduled']);
        //     //     // Or dispatch a job/event for more complex logic
        //     // }
        // });

        static::deleted(function ($holiday) {
            $holiday->updateEntitlementUsage(true);
        });
    }

    // ---------------------- Relationships -------------------
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function therapists()
    {
        return $this->user()->whereHas('roles', fn ($q) => $q->where('name', 'therapist'));
    }

    public function clinic() {
        return $this->belongsTo(Clinic::class);
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ---------------------- Custom Function -------------------
    public function updateEntitlementUsage($isReversal = false)
    {
        $days = Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1;
        $entitlement = $this->user->leaveEntitlements()
            ->where('year', date('Y', strtotime($this->start_date)))
            ->where('leave_type', $this->type->value)
            ->first();

        if ($entitlement) {
            if ($isReversal) {
                $entitlement->used -= $days;
            } else {
                $entitlement->used += $days;
            }
            $entitlement->remaining = max($entitlement->total_allowed - $entitlement->used, 0);
            $entitlement->save();
        }

        $this->days = $days;
    }

}
