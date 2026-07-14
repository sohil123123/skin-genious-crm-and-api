<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Enums\AppointmentType;
use App\Enums\AppointmentStatus;
use App\Models\WhatsAppTemplate;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Setting;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

use Carbon\Carbon;

// use Guava\Calendar\Contracts\Eventable;
// use Guava\Calendar\ValueObjects\CalendarEvent;

class Appointment extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'type',
        'clinic_id',
        'user_id',
        'therapist_id',
        'assessment_id',
        'treatment_session_id',
        'start_datetime',
        'end_datetime',
        'duration_minutes',
        'status',
        'products_used',
        'resources_used',
        'notes',
        'is_emergency',
        'emergency_reason',
        'is_billable',
        'is_billed',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'products_used' => 'array',
        'resources_used' => 'array',
        'is_emergency' => 'boolean',
        'emergency_reason' => 'json',
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

    protected static function booted()
    {
        static::creating(function ($appointment) {
            $appointment->created_by ??= auth()->id();
        });

        static::created(function ($appointment) {
            $templateName = Setting::getValue('whatsapp_appointment_template_name', 'appointment_confirmation_v1');
            $template = WhatsAppTemplate::where('name', $templateName)->first();

            if ($template && $appointment->client && $appointment->client->mobile) {
                $clientName = $appointment->client->name ?? 'Client';
                $appointmentTime = $appointment->start_datetime
                    ? $appointment->start_datetime->format('jS F Y \a\t g:i A')
                    : 'Scheduled Time';

                // Dynamically build components (Header, Body, Buttons) for sending
                $components = $template->buildComponentsForSending(
                    // Body variables
                    [
                        'client_name' => $clientName,
                        'appointment_datetime' => $appointmentTime,
                        0 => $clientName,
                        1 => $appointmentTime,
                    ],
                    // Header variables
                    [
                        'client_name' => $clientName,
                        'appointment_datetime' => $appointmentTime,
                        0 => $clientName,
                        1 => $appointmentTime,
                    ],
                    // Button variables
                    []
                );

                SendWhatsAppMessageJob::dispatch(
                    $appointment->client->mobile,
                    $template->name,
                    $components,
                    $template->language ?? 'en_US',
                    $appointment->user_id
                );
            }
        });

        static::updating(function ($appointment) {
            $appointment->updated_by = auth()->id();
        });

        static::saving(function ($appointment) {
            if ($appointment->start_datetime && $appointment->end_datetime) {

                $start = Carbon::parse($appointment->start_datetime);
                $end = Carbon::parse($appointment->end_datetime);

                // Prevent negative duration
                $appointment->duration_minutes = max(0, $start->diffInMinutes($end));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'clinic_id',
                'therapist_id',
                'user_id',
                'assessment_id',
                'treatment_session_id',
                'start_datetime',
                'end_datetime',
                'duration_minutes',
                'status',
                'updated_by'
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                if ($eventName === 'updated' && $this->wasChanged('status')) {
                    return 'Status Changed';
                }
                return "Appointment {$eventName}";
            })
            ->useLogName('appointment');
    }

    //---------------------------- Relations --------------------------
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }
}
