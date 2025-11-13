<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Enums\AppointmentType;
use App\Enums\AppointmentStatus;

use Carbon\Carbon;

class Appointment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'client_id', 'therapist_id', 'clinic_id', 'assessment_id',
        'treatment_plan_id', 'parent_id', 'appointment_datetime',
        'status', 'products_used', 'resources_used', 'notes', 'billed_at',
    ];

    protected $casts = [
        'appointment_datetime' => 'datetime',
        'billed_at' => 'datetime',
        'products_used' => 'array',
        'resources_used' => 'array',
        'type' => AppointmentType::class,
        'status' => AppointmentStatus::class,
    ];

    // Scopes for Filament (e.g., uninvoiced)
    public function scopeUninvoiced($query) { 
        return $query->whereNull('billed_at'); 
    }

    public function scopeByType($query, $type) { 
        return $query->where('type', $type); 
    }

    // public function scopeOverlapping($query, $therapistId, $clinicId, $appointment_datetime, $excludeId = null) {
    //     $appointment_datetime = Carbon::createFromFormat('Y-m-d h:i A', $appointment_datetime)->format('Y-m-d H:i:s');
    //     return $query->where('therapist_id', $therapistId)
    //                 ->where('clinic_id', $clinicId)
    //                 ->where('appointment_datetime', $appointment_datetime)
    //                 ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
    //                 ->where('status', '!=', 'cancelled'); // Ignore cancelled
    // }

    public function scopeOverlapping($query, $therapistId, $clinicId, $start, $end, $excludeId = null, $gap = 30) {
        return $query->where('therapist_id', $therapistId)
                ->where('clinic_id', $clinicId)
                ->whereBetween('appointment_datetime', [
                    Carbon::instance($start)->subMinutes($gap),
                    Carbon::instance($end)->addMinutes($gap),
                ])
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->where('status', '!=', 'cancelled');
    }

    //---------------------------- Relations --------------------------
    public function client(): BelongsTo { 
        return $this->belongsTo(User::class, 'client_id');
    }

    public function therapist(): BelongsTo { 
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function clinic(): BelongsTo { 
        return $this->belongsTo(Clinic::class); 
    }

    public function assessment(): BelongsTo {
        return $this->belongsTo(Assessment::class); 
    }

    public function treatmentSession(): BelongsTo {
        return $this->belongsTo(TreatmentSession::class); 
    }
}
