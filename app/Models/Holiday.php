<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

use App\Models\User;

use App\Enums\HolidayStatus;

class Holiday extends Model
{
    protected $fillable = ['user_id', 'clinic_id', 'start_date', 'end_date', 'reason', 'status', 'approved_by'];

    // protected function status(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn ($value) => ucfirst($value),
    //     );
    // }

    protected $casts = [
        'status' => HolidayStatus::class,
    ];

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

    // Hook for auto-adjusting schedules (integrate with your Appointment model)
    protected static function booted() {
        static::creating(function ($holiday) {
            $selectedUser = User::find($holiday->user_id);
            if ($selectedUser && $selectedUser->clinic_id) {
                $holiday->clinic_id = $selectedUser->clinic_id;
            }
        });
        static::updating(function ($holiday) {
            if ($holiday->status === 'approved' && $holiday->isDirty('status')) {
                // Logic to auto-adjust appointments: e.g., reschedule or notify
                // Query appointments for this therapist between start_date and end_date
                // Appointment::where('therapist_id', $holiday->user_id)->whereBetween('date', [$holiday->start_date, $holiday->end_date])->update(['status' => 'rescheduled']);
                // Or dispatch a job/event for more complex logic
            }
        });
    }
}
