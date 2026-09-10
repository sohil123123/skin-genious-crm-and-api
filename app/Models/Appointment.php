<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
            $statusValue = $appointment->status instanceof AppointmentStatus
                ? $appointment->status->value
                : (string) $appointment->status;

            if ($statusValue === 'confirmed') {
                $appointment->sendConfirmationWhatsAppTemplate();
            }
        });

        static::updated(function ($appointment) {
            if ($appointment->wasChanged('status')) {
                $statusValue = $appointment->status instanceof AppointmentStatus
                    ? $appointment->status->value
                    : (string) $appointment->status;

                if ($statusValue === 'confirmed') {
                    $appointment->sendConfirmationWhatsAppTemplate();
                }
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
    /**
     * Appointments that mean "this person does not need chasing to book".
     *
     * Read by both Next Best Action engines and by the observer that reconciles
     * the queues after a booking. One scope rather than three copies of the
     * same two conditions, because the copies drifted the first time this was
     * touched.
     *
     * The boundary is the start of today, not the current moment, and that is
     * the correction. It used to be `start_datetime >= now()`, so an
     * appointment stopped counting the instant it began: Pallavi Bhatnagar was
     * booked at 12:30 and 12:56, and at 13:01 — while she was in the chair —
     * the lead queue rebuilt and put her back on it as somebody who "wants to
     * visit now" and should be rung. Anybody seen earlier today is in the same
     * position; the queue is read all day and must not start chasing people the
     * moment their appointment starts.
     *
     * Yesterday is deliberately outside it. Chasing somebody after a visit is
     * what the retention triggers are for.
     *
     * Cancelled and no-show never count: somebody whose appointment fell
     * through is exactly who the queue should be chasing.
     */
    public function scopeCountsAsBooked(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('start_datetime'), '>=', Carbon::today())
            ->whereNotIn($query->qualifyColumn('status'), [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ]);
    }

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

    /**
     * Send WhatsApp appointment confirmation template message.
     */
    public function sendConfirmationWhatsAppTemplate(): void
    {
        $templateName = Setting::getValue('whatsapp_appointment_template_name', 'appointment_confirmation_v1');
        $template = WhatsAppTemplate::where('name', $templateName)->first();

        if ($template && $this->client && $this->client->mobile) {
            $clientName = $this->client->name ?? 'Client';
            $appointmentTime = $this->start_datetime
                ? $this->start_datetime->format('jS F Y \a\t g:i A')
                : 'Scheduled Time';

            $components = $template->buildComponentsForSending(
                [
                    'client_name' => $clientName,
                    'appointment_datetime' => $appointmentTime,
                    0 => $clientName,
                    1 => $appointmentTime,
                ],
                [
                    'client_name' => $clientName,
                    'appointment_datetime' => $appointmentTime,
                    0 => $clientName,
                    1 => $appointmentTime,
                ],
                []
            );

            SendWhatsAppMessageJob::dispatch(
                $this->client->mobile,
                $template->name,
                $components,
                $template->language ?? 'en_US',
                $this->user_id
            );
        }
    }
}
