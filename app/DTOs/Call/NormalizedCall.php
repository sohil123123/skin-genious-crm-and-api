<?php

declare(strict_types=1);

namespace App\DTOs\Call;

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallStatus;
use Illuminate\Support\Carbon;

/**
 * A provider's call, translated into the CRM's vocabulary.
 *
 * The boundary of the whole provider abstraction: everything upstream of this
 * object knows about Exotel and Callyzer, and everything downstream knows about
 * neither. A third provider is a new mapper that returns one of these.
 *
 * Every field is nullable on purpose. Calls arrive in stages — a "ringing"
 * event has no duration and no recording — and a DTO that demanded them would
 * force mappers to invent values. Null here means "this event said nothing
 * about that", which the ingestion service treats as leave-alone rather than
 * as clear.
 */
final readonly class NormalizedCall
{
    /**
     * @param  array<int, NormalizedRecording>  $recordings
     * @param  array<string, mixed>  $providerData
     */
    public function __construct(
        public CallProvider $provider,
        public string $providerCallId,
        public CallSource $source,
        public ?CallDirection $direction = null,
        public ?CallStatus $status = null,
        public ?string $providerDirection = null,
        public ?string $providerStatus = null,
        public ?string $disposition = null,
        public ?string $hangupCauseCode = null,
        public ?string $providerReferenceId = null,
        public ?string $providerParentCallId = null,
        public ?string $providerEventId = null,

        // Customer side
        public ?PhoneNumber $clientPhone = null,
        public ?string $clientName = null,
        public ?string $clientCountryCode = null,

        // Agent side
        public ?PhoneNumber $employeePhone = null,
        public ?string $employeeName = null,
        public ?string $employeeCode = null,
        public ?string $employeeCountryCode = null,

        // The clinic's own number
        public ?PhoneNumber $virtualNumber = null,

        // Timing
        public ?Carbon $startedAt = null,
        public ?Carbon $answeredAt = null,
        public ?Carbon $endedAt = null,
        public ?int $durationSeconds = null,
        public ?int $ringDurationSeconds = null,
        public ?int $talkDurationSeconds = null,
        public ?int $holdDurationSeconds = null,
        public ?int $waitDurationSeconds = null,
        public ?string $timezone = null,
        public ?string $providerStartedAtRaw = null,
        public ?string $providerAnsweredAtRaw = null,
        public ?string $providerEndedAtRaw = null,

        // Provider-side CRM fields, which never overwrite the CRM's own
        public ?string $providerNote = null,
        public ?string $providerCrmStatus = null,
        public ?string $providerLeadId = null,
        public ?Carbon $providerReminderAt = null,
        public ?Carbon $providerSyncedAt = null,
        public ?Carbon $providerModifiedAt = null,
        public ?string $callMethod = null,
        public ?string $callMode = null,

        public array $recordings = [],
        public array $providerData = [],
    ) {}

    /**
     * The columns this event has something to say about.
     *
     * Nulls are stripped rather than written, which is what makes staged
     * updates safe: a late "ringing" retry arriving after "completed" carries
     * no duration, so it cannot blank the duration that already landed.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $attributes = [
            'provider' => $this->provider->value,
            'provider_call_id' => $this->providerCallId,
            'provider_reference_id' => $this->providerReferenceId,
            'provider_parent_call_id' => $this->providerParentCallId,
            'provider_event_id' => $this->providerEventId,
            'direction' => $this->direction?->value,
            'call_status' => $this->status?->value,
            'is_connected' => $this->status?->isConnected(),
            'provider_direction' => $this->providerDirection,
            'provider_call_status' => $this->providerStatus,
            'disposition' => $this->disposition,
            'hangup_cause_code' => $this->hangupCauseCode,

            'client_name' => $this->clientName,
            'client_country_code' => $this->clientCountryCode,
            'client_phone' => $this->clientPhone?->original,
            'client_phone_normalized' => $this->clientPhone?->normalized,
            'client_phone_key' => $this->clientPhone?->key,

            'employee_name' => $this->employeeName,
            'employee_code' => $this->employeeCode,
            'employee_country_code' => $this->employeeCountryCode,
            'employee_phone' => $this->employeePhone?->original,
            'employee_phone_normalized' => $this->employeePhone?->normalized,
            'employee_phone_key' => $this->employeePhone?->key,

            'virtual_number' => $this->virtualNumber?->original,
            'virtual_number_normalized' => $this->virtualNumber?->normalized,

            'started_at' => $this->startedAt,
            'answered_at' => $this->answeredAt,
            'ended_at' => $this->endedAt,
            'duration_seconds' => $this->durationSeconds,
            'ring_duration_seconds' => $this->ringDurationSeconds,
            'talk_duration_seconds' => $this->talkDurationSeconds,
            'hold_duration_seconds' => $this->holdDurationSeconds,
            'wait_duration_seconds' => $this->waitDurationSeconds,
            'timezone' => $this->timezone,
            'provider_started_at_raw' => $this->providerStartedAtRaw,
            'provider_answered_at_raw' => $this->providerAnsweredAtRaw,
            'provider_ended_at_raw' => $this->providerEndedAtRaw,

            'provider_note' => $this->providerNote,
            'provider_crm_status' => $this->providerCrmStatus,
            'provider_lead_id' => $this->providerLeadId,
            'provider_reminder_at' => $this->providerReminderAt,
            'provider_synced_at' => $this->providerSyncedAt,
            'provider_modified_at' => $this->providerModifiedAt,
            'call_method' => $this->callMethod,
            'call_mode' => $this->callMode,
        ];

        return array_filter($attributes, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Whether this event is worth writing at all.
     *
     * A payload with no provider call id cannot be deduplicated and cannot be
     * updated by the events that follow it, so it is archived as a raw payload
     * and goes no further.
     */
    public function isIdentifiable(): bool
    {
        return trim($this->providerCallId) !== '';
    }

    public function hasRecordings(): bool
    {
        return $this->recordings !== [];
    }
}
