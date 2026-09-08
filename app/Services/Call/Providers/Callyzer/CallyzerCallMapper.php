<?php

declare(strict_types=1);

namespace App\Services\Call\Providers\Callyzer;

use App\DTOs\Call\NormalizedCall;
use App\DTOs\Call\NormalizedRecording;
use App\Enums\Call\CallDirection;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallStatus;
use App\Services\Call\PhoneNumberNormalizer;
use Illuminate\Support\Carbon;

/**
 * Translates one Callyzer call log into the CRM's vocabulary.
 *
 * Every field name is looked up through a list of aliases rather than read
 * directly, because Callyzer has shipped both snake_case and camelCase for the
 * same field across versions and an integration that hard-codes one silently
 * loses data when the other arrives. Anything with no home in the unified
 * schema is carried through into providerData rather than dropped.
 *
 * The interesting work here is untangling direction from status. Callyzer
 * expresses both in one field — call_type is "Outgoing" or "Missed" — but the
 * CRM needs them apart, because "a call we made" and "nobody picked up" are
 * separate questions. A missed call becomes direction=incoming,
 * status=missed, which is what actually happened.
 */
class CallyzerCallMapper
{
    public function __construct(
        protected PhoneNumberNormalizer $phone,
    ) {}

    /**
     * Turn Callyzer's employee-shaped delivery into a flat list of calls.
     *
     * Callyzer does not send calls. It sends employees, each carrying the calls
     * they made:
     *
     *     [{ emp_name, emp_code, emp_number, emp_tags, call_logs: [ {...}, {...} ] }]
     *
     * Both the webhook body and the callHistory response use this shape, and
     * neither entry point used to descend into call_logs - so every delivery
     * was handed to the mapper as an employee record, which has no call id, and
     * was dropped as unmappable. Calls arrived, were acknowledged with a 200,
     * and vanished.
     *
     * The employee fields live on the wrapper rather than on each log, so they
     * are merged down into every call. Without that the agent identity is lost
     * and each call arrives unattributable. The log wins any key collision: a
     * value on the call itself is more specific than the same key on its
     * employee.
     *
     * Anything already flat is passed through untouched, so a future version
     * that sends bare call logs keeps working.
     *
     * @param  array<int, mixed>  $records
     * @return array<int, array<string, mixed>>
     */
    public static function flatten(array $records): array
    {
        $calls = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $logs = null;

            foreach (['call_logs', 'callLogs', 'calls'] as $key) {
                if (isset($record[$key]) && is_array($record[$key])) {
                    $logs = $record[$key];

                    break;
                }
            }

            if ($logs === null) {
                $calls[] = $record;

                continue;
            }

            $context = array_diff_key($record, array_flip(['call_logs', 'callLogs', 'calls']));

            foreach ($logs as $log) {
                if (is_array($log)) {
                    $calls[] = array_merge($context, $log);
                }
            }
        }

        return $calls;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function map(array $payload, CallSource $source = CallSource::ApiSync): ?NormalizedCall
    {
        $providerCallId = $this->string($payload, ['id', 'call_id', 'callId', '_id']);

        if (blank($providerCallId)) {
            return null;
        }

        $callType = $this->string($payload, ['call_type', 'callType', 'type']);
        $duration = $this->int($payload, ['duration', 'call_duration', 'callDuration']);

        [$direction, $status] = $this->interpretCallType($callType, $duration);

        $timezone = (string) config('calls.callyzer.timezone', 'Asia/Kolkata');

        $startedAtRaw = $this->combineDateTime(
            $this->string($payload, ['call_date', 'callDate', 'date']),
            $this->string($payload, ['call_time', 'callTime', 'time']),
        );

        $startedAt = $this->parse($startedAtRaw, $timezone);
        $endedAt = $startedAt !== null && $duration !== null
            ? (clone $startedAt)->addSeconds($duration)
            : null;

        $clientPhone = $this->phone->normalize(
            $this->string($payload, ['client_number', 'clientNumber', 'client_no']),
            $this->string($payload, ['client_country_code', 'clientCountryCode']),
        );

        $employeePhone = $this->phone->normalize(
            $this->string($payload, ['emp_number', 'empNumber', 'employee_number']),
            $this->string($payload, ['emp_country_code', 'empCountryCode']),
        );

        return new NormalizedCall(
            provider: CallProvider::Callyzer,
            providerCallId: $providerCallId,
            source: $source,
            direction: $direction,
            status: $status,
            providerDirection: $callType,
            providerStatus: $callType,
            providerEventId: $this->string($payload, ['event_id', 'eventId']),

            clientPhone: $clientPhone,
            clientName: $this->string($payload, ['client_name', 'clientName']),
            clientCountryCode: $this->string($payload, ['client_country_code', 'clientCountryCode']),

            employeePhone: $employeePhone,
            employeeName: $this->string($payload, ['emp_name', 'empName', 'employee_name']),
            employeeCode: $this->string($payload, ['emp_code', 'empCode', 'employee_code']),
            employeeCountryCode: $this->string($payload, ['emp_country_code', 'empCountryCode']),

            startedAt: $startedAt,
            // Callyzer reports duration as talk time on connected calls and 0
            // on everything else, so a missed call must not be recorded as
            // having been answered at the moment it started ringing.
            answeredAt: $status->isConnected() ? $startedAt : null,
            endedAt: $endedAt,
            durationSeconds: $duration,
            // Callyzer's duration field IS talk time: it reports the seconds
            // two people were connected, and 0 for everything else. Gating it
            // on the status word instead would zero the talk time on any call
            // whose type this mapper does not recognise.
            talkDurationSeconds: $duration,
            timezone: $timezone,
            providerStartedAtRaw: $startedAtRaw,

            providerNote: $this->string($payload, ['note', 'call_note', 'remarks']),
            providerCrmStatus: $this->string($payload, ['crm_status', 'crmStatus']),
            providerLeadId: $this->string($payload, ['lead_id', 'leadId']),
            providerReminderAt: $this->parse(
                $this->combineDateTime(
                    $this->string($payload, ['reminder_date', 'reminderDate']),
                    $this->string($payload, ['reminder_time', 'reminderTime']),
                ),
                $timezone,
            ),
            providerSyncedAt: $this->parse($this->string($payload, ['synced_at', 'syncedAt']), $timezone),
            providerModifiedAt: $this->parse($this->string($payload, ['modified_at', 'modifiedAt', 'updated_at']), $timezone),
            callMethod: $this->string($payload, ['call_method', 'callMethod']),
            callMode: $this->string($payload, ['call_mode', 'callMode']),

            recordings: $this->mapRecordings($payload, $duration),
            providerData: $this->extras($payload),
        );
    }

    /**
     * Split Callyzer's single call_type into a direction and a status.
     *
     * The duration is consulted as a tiebreaker: a call typed "Outgoing" with
     * zero seconds did not connect, whatever the label says, and counting it as
     * a conversation would inflate every connection-rate figure the clinic
     * looks at.
     *
     * @return array{0: CallDirection, 1: CallStatus}
     */
    protected function interpretCallType(?string $callType, ?int $duration): array
    {
        $normalized = strtolower(trim((string) $callType));
        $mapped = (array) config('calls.callyzer.call_type_map', []);
        $key = $mapped[$normalized] ?? $normalized;

        return match ($key) {
            'outgoing' => [
                CallDirection::Outgoing,
                ($duration ?? 0) > 0 ? CallStatus::Completed : CallStatus::NoAnswer,
            ],
            'incoming' => [
                CallDirection::Incoming,
                ($duration ?? 0) > 0 ? CallStatus::Completed : CallStatus::NoAnswer,
            ],
            // A missed call is by definition one that came in and was not
            // answered. Storing it as its own "direction" would make every
            // incoming-call count wrong.
            'missed' => [CallDirection::Incoming, CallStatus::Missed],
            'rejected' => [CallDirection::Incoming, CallStatus::Rejected],
            'not_connected' => [CallDirection::Outgoing, CallStatus::NotConnected],
            // An unrecognised call type still tells us something if it carried
            // a duration: people spoke. Reporting that as Unknown would drop a
            // real conversation out of every connection-rate figure.
            default => [
                CallDirection::Unknown,
                ($duration ?? 0) > 0 ? CallStatus::Completed : CallStatus::Unknown,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, NormalizedRecording>
     */
    protected function mapRecordings(array $payload, ?int $duration): array
    {
        $url = $this->string($payload, ['call_recording_url', 'callRecordingUrl', 'recording_url', 'recordingUrl']);

        if (blank($url) || ! str_starts_with(strtolower($url), 'http')) {
            return [];
        }

        return [
            new NormalizedRecording(
                sourceUrl: $url,
                providerRecordingId: $this->string($payload, ['recording_id', 'recordingId']),
                durationSeconds: $duration,
                metadata: array_filter([
                    'call_method' => $this->string($payload, ['call_method', 'callMethod']),
                    'call_mode' => $this->string($payload, ['call_mode', 'callMode']),
                ], static fn (mixed $value): bool => $value !== null),
            ),
        ];
    }

    /**
     * Everything the unified schema has no column for.
     *
     * Kept as sent — including fields this mapper has never heard of — so a new
     * Callyzer field is available the day it appears rather than the day
     * somebody writes a migration for it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extras(array $payload): array
    {
        $claimed = [
            'id', 'call_id', 'callId', '_id',
            'emp_name', 'empName', 'employee_name',
            'emp_code', 'empCode', 'employee_code',
            'emp_country_code', 'empCountryCode',
            'emp_number', 'empNumber', 'employee_number',
            'client_name', 'clientName',
            'client_country_code', 'clientCountryCode',
            'client_number', 'clientNumber', 'client_no',
            'duration', 'call_duration', 'callDuration',
            'call_type', 'callType', 'type',
            'call_date', 'callDate', 'date',
            'call_time', 'callTime', 'time',
            'note', 'call_note', 'remarks',
            'call_recording_url', 'callRecordingUrl', 'recording_url', 'recordingUrl',
            'crm_status', 'crmStatus',
            'reminder_date', 'reminderDate',
            'reminder_time', 'reminderTime',
            'synced_at', 'syncedAt',
            'modified_at', 'modifiedAt', 'updated_at',
            'lead_id', 'leadId',
            'call_method', 'callMethod',
            'call_mode', 'callMode',
        ];

        $extras = array_diff_key($payload, array_flip($claimed));

        // emp_tags is claimed back deliberately: it has no column but the agent
        // resolver reads it, so it must be present under a predictable key.
        if (isset($payload['emp_tags'])) {
            $extras['emp_tags'] = $payload['emp_tags'];
        }

        return $extras;
    }

    // ──────────────── Field readers ────────────────

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    protected function string(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    protected function int(array $payload, array $keys): ?int
    {
        $value = $this->string($payload, $keys);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    protected function combineDateTime(?string $date, ?string $time): ?string
    {
        if (blank($date)) {
            return null;
        }

        return blank($time) ? $date : trim($date . ' ' . $time);
    }

    /**
     * Parse a provider timestamp in the provider's timezone.
     *
     * Returns null rather than throwing on an unparseable value: one malformed
     * date must not cost the CRM the whole call, and the original string is
     * preserved on the row either way.
     */
    protected function parse(?string $value, string $timezone): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            // Into the application's timezone, not UTC — Eloquent reads the
            // column back in config('app.timezone'), so storing a UTC wall
            // clock shifts every call by the offset between the two.
            $parsed = Carbon::parse($value, $timezone)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        // An epoch-zero timestamp means "unset", not "1970". Providers send it
        // for fields they have no value for, and it is below the range a MySQL
        // TIMESTAMP column can hold — so accepting it loses the whole call.
        // The raw string survives in provider_*_raw either way.
        return $parsed->year >= 2000 && $parsed->year <= 2100 ? $parsed : null;
    }
}
