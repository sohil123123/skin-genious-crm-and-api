<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Enums\AppointmentType;
use App\Enums\AppointmentStatus;

use Carbon\Carbon;

// use Guava\Calendar\Contracts\Eventable;
// use Guava\Calendar\ValueObjects\CalendarEvent;

class Appointment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'clinic_id', 'user_id', 'therapist_id', 'assessment_id',
        'treatment_session_id', 'start_datetime', 'end_datetime', 'duration_minutes',
        'status', 'products_used', 'resources_used', 'notes', 'is_billable', 'is_billed', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'products_used' => 'array',
        'resources_used' => 'array',
        'is_billable' => 'boolean',
        'is_billed' => 'boolean',
        'type' => AppointmentType::class,
        'status' => AppointmentStatus::class,
    ];

    // // This is where you map your model into a calendar object
    // public function toCalendarEvent(): CalendarEvent
    // {
    //     // For eloquent models, make sure to pass the model to the constructor
    //     return CalendarEvent::make($this)
    //         ->title($this->client->first_name)
    //         ->start($this->start_datetime)
    //         ->end($this->end_datetime);
    // }

    protected static function booted() {
        static::creating(function ($appointment) {
            $appointment->created_by ??= auth()->id();
        });

        static::saving(function ($appointment) {
            if ($appointment->start_datetime && $appointment->end_datetime) {

                $start = Carbon::parse($appointment->start_datetime);
                $end   = Carbon::parse($appointment->end_datetime);

                // Prevent negative duration
                $appointment->duration_minutes = max(0, $start->diffInMinutes($end));
            }
        });
    }

    //---------------------------- Relations --------------------------
    public function client(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function therapist(): BelongsTo {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function clinic(): BelongsTo {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class);
    }

    public function treatmentSession(): BelongsTo {
        return $this->belongsTo(TreatmentSession::class);
    }
}
