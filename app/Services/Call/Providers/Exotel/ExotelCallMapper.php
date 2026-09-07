<?php

declare(strict_types=1);

namespace App\Services\Call\Providers\Exotel;

use App\DTOs\Call\NormalizedCall;
use App\DTOs\Call\NormalizedRecording;
use App\Enums\Call\CallDirection;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallStatus;
use App\Services\Call\PhoneNumberNormalizer;
use Illuminate\Support\Carbon;

/**
 * Translates one Exotel payload into the CRM's vocabulary.
 *
 * Exotel's Passthru applet delivers a GET with URL-encoded query parameters,
 * and which parameters arrive depends on where the applet sits in the call
 * flow: a Passthru placed before the Connect applet has no RecordingUrl, no
 * DialCallStatus and no Legs, while one placed after has all three. The same
 * call therefore hits the CRM two or three times with progressively more
 * information, and this mapper's job is to describe honestly what each delivery
 * knew — never to fill gaps with assumptions, because the ingestion service
 * relies on a null meaning "this event said nothing about that".
 *
 * The parameter names follow Exotel's documented Passthru set: CallSid,
 * CallFrom, CallTo, From, To, Direction, CallStatus, DialCallStatus, CallType,
 * DialWhomNumber, DialCallDuration, StartTime, EndTime, Created, CurrentTime,
 * RecordingUrl, OutgoingPhoneNumber and Legs.
 */
class ExotelCallMapper
{
    public function __construct(
        protected PhoneNumberNormalizer $phone,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function map(array $payload, CallSource $source = CallSource::Webhook): ?NormalizedCall
    {
        $callSid = $this->string($payload, ['CallSid', 'callSid', 'Sid', 'sid']);

        if (blank($callSid)) {
            return null;
        }

        $direction = $this->mapDirection($this->string($payload, ['Direction', 'direction']));

        // DialCallStatus describes the agent leg and is the more specific
        // answer when both are present: a call whose CallStatus is "completed"
        // but whose DialCallStatus is "no-answer" reached the flow and never
        // reached a person, and recording it as completed would count a
        // conversation that did not happen.
        $rawStatus = $this->string($payload, ['DialCallStatus', 'dialCallStatus'])
            ?? $this->string($payload, ['Status', 'CallStatus', 'callStatus', 'status']);

        $status = $this->mapStatus($rawStatus, $direction);

        $timezone = (string) config('calls.exotel.timezone', 'Asia/Kolkata');

        $startedAtRaw = $this->string($payload, ['StartTime', 'startTime', 'Created', 'created', 'DateCreated']);
        $endedAtRaw = $this->string($payload, ['EndTime', 'endTime', 'DateUpdated']);

        $startedAt = $this->parse($startedAtRaw, $timezone);
        $endedAt = $this->parse($endedAtRaw, $timezone);

        // Exotel names the two ends differently depending on direction. On an
        // incoming call, From is the customer and To is the Exophone they
        // dialled; on an outgoing one the roles swap. Reading them positionally
        // instead of by direction would file half the calls under the clinic's
        // own number as the customer.
        [$customerRaw, $clinicRaw] = $this->resolveParties($payload, $direction);

        $agentRaw = $this->string($payload, ['DialWhomNumber', 'dialWhomNumber', 'AgentNumber']);

        $legs = $this->legs($payload);
        $lastLeg = $legs !== [] ? end($legs) : null;

        $dialDuration = $this->int($payload, ['DialCallDuration', 'dialCallDuration']);
        $conversationDuration = $this->int($payload, ['ConversationDuration', 'conversationDuration', 'OnCallDuration']);

        $totalDuration = $dialDuration ?? $conversationDuration;
        $talkDuration = $conversationDuration
            ?? (isset($lastLeg['OnCallDuration']) ? (int) $lastLeg['OnCallDuration'] : null);

        // ─── What Exotel does not send, but does let us work out ───────────
        //
        // Exotel has no "answered at" field and no ring timer, and it writes
        // the Unix epoch into EndTime when the flow ends without a clean hangup
        // time - which parse() rejects, correctly, since 1970 is not a real
        // call time and MySQL will not store it. The result was a Timing card
        // of dashes on calls where the arithmetic was sitting right there:
        // total duration covers ringing plus talking, so the difference is the
        // ring, and the end is the start plus the total.
        //
        // Derived, not reported: the provider_*_raw columns stay null, because
        // Exotel said none of this. Only what it actually sent is stored as its
        // word.
        if ($endedAt === null && $startedAt !== null && $totalDuration !== null) {
            $endedAt = $startedAt->copy()->addSeconds($totalDuration);
        }

        // A total shorter than the talk time means the two numbers came from
        // different legs and cannot be subtracted; better no ring time than a
        // negative one.
        $ringDuration = $totalDuration !== null && $talkDuration !== null && $totalDuration >= $talkDuration
            ? $totalDuration - $talkDuration
            : null;

        // Only a call somebody actually picked up has a moment of answer. A
        // missed call has ring time and no answer, and stamping one on it would
        // invent a conversation.
        $answeredAt = $startedAt !== null && $ringDuration !== null && ($talkDuration ?? 0) > 0
            ? $startedAt->copy()->addSeconds($ringDuration)
            : null;

        return new NormalizedCall(
            provider: CallProvider::Exotel,
            providerCallId: $callSid,
            source: $source,
            direction: $direction,
            status: $status,
            providerDirection: $this->string($payload, ['Direction', 'direction']),
            providerStatus: $rawStatus,
            disposition: $this->disposition($payload, $lastLeg),
            hangupCauseCode: $this->hangupCauseCode($payload, $lastLeg),
            // Deliberately null. Exotel publishes no second identifier, and the
            // CallType that used to live here is not a reference — it is
            // already carried verbatim in provider_data.
            providerReferenceId: null,
            providerParentCallId: $this->string($payload, ['ParentCallSid', 'parentCallSid']),
            providerEventId: $this->string($payload, ['EventType', 'eventType']),

            clientPhone: $this->phone->normalize($customerRaw),
            clientName: null,

            employeePhone: $this->phone->normalize($agentRaw),
            employeeName: null,

            virtualNumber: $this->phone->normalize(
                $clinicRaw ?? $this->string($payload, ['OutgoingPhoneNumber', 'outgoingPhoneNumber'])
            ),

            startedAt: $startedAt,
            answeredAt: $answeredAt,
            endedAt: $endedAt,
            ringDurationSeconds: $ringDuration,
            // Exotel reports the total flow duration and, separately, the time
            // an agent was actually on the line. Only the second is talk time;
            // treating the first as talk time would credit IVR menus and hold
            // music as conversation.
            durationSeconds: $totalDuration,
            talkDurationSeconds: $talkDuration,
            timezone: $timezone,
            providerStartedAtRaw: $startedAtRaw,
            providerEndedAtRaw: $endedAtRaw,

            recordings: $this->mapRecordings($payload),
            providerData: $this->extras($payload),
        );
    }

    /**
     * Which number is the customer's, and which is the clinic's.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolveParties(array $payload, CallDirection $direction): array
    {
        $from = $this->string($payload, ['From', 'from', 'CallFrom', 'callFrom']);
        $to = $this->string($payload, ['To', 'to', 'CallTo', 'callTo']);

        return $direction === CallDirection::Outgoing
            ? [$to, $from]
            : [$from, $to];
    }

    /**
     * Exotel says "incoming" or a variant of "outbound dial".
     */
    protected function mapDirection(?string $value): CallDirection
    {
        $normalized = strtolower(trim((string) $value));

        return match (true) {
            $normalized === '' => CallDirection::Unknown,
            str_contains($normalized, 'incoming'),
            str_contains($normalized, 'inbound') => CallDirection::Incoming,
            str_contains($normalized, 'outbound'),
            str_contains($normalized, 'outgoing'),
            str_contains($normalized, 'click') => CallDirection::Outgoing,
            default => CallDirection::Unknown,
        };
    }

    /**
     * Map an Exotel status word onto the unified vocabulary.
     *
     * An unanswered incoming call is recorded as Missed rather than NoAnswer.
     * They are the same event technically, but "missed call" is what the clinic
     * calls it and what the follow-up queue is built around, and the direction
     * is what distinguishes the two.
     */
    protected function mapStatus(?string $value, CallDirection $direction): CallStatus
    {
        $normalized = strtolower(trim((string) $value));
        $mapped = (array) config('calls.exotel.status_map', []);
        $key = $mapped[$normalized] ?? $normalized;

        $status = CallStatus::tryFrom((string) $key) ?? CallStatus::Unknown;

        if ($direction === CallDirection::Incoming && in_array($status, [CallStatus::NoAnswer, CallStatus::Cancelled], true)) {
            return CallStatus::Missed;
        }

        return $status;
    }

    /**
     * A readable reason the call ended, from the leg that ended it.
     *
     * Exotel's field names invert what you would expect: a real leg carries
     * Cause="16" and CauseCode="NORMAL_CLEARING", so the *name* is in CauseCode
     * and the *number* is in Cause. Reading them the obvious way round puts a
     * bare integer in front of staff where a reason should be.
     *
     * Both orders are tolerated, because a Cause that is not numeric is already
     * the readable form.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $lastLeg
     */
    protected function disposition(array $payload, ?array $lastLeg): ?string
    {
        $named = $this->string($payload, ['CauseCode', 'HangupCause'])
            ?? (isset($lastLeg['CauseCode']) ? (string) $lastLeg['CauseCode'] : null);

        if (filled($named) && ! ctype_digit($named)) {
            return $named;
        }

        $cause = $this->string($payload, ['Cause'])
            ?? (isset($lastLeg['Cause']) ? (string) $lastLeg['Cause'] : null);

        return filled($cause) && ! ctype_digit($cause) ? $cause : $named;
    }

    /**
     * The numeric hangup cause, whichever field Exotel put it in.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $lastLeg
     */
    protected function hangupCauseCode(array $payload, ?array $lastLeg): ?string
    {
        foreach ([
            $this->string($payload, ['Cause']),
            isset($lastLeg['Cause']) ? (string) $lastLeg['Cause'] : null,
            $this->string($payload, ['CauseCode']),
            isset($lastLeg['CauseCode']) ? (string) $lastLeg['CauseCode'] : null,
        ] as $candidate) {
            if (filled($candidate) && ctype_digit($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The per-attempt detail Exotel attaches when a Connect applet ran.
     *
     * Arrives as an array over JSON callbacks and as a JSON string over the
     * Passthru query string, so both are accepted.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function legs(array $payload): array
    {
        $legs = $payload['Legs'] ?? $payload['legs'] ?? null;

        if (is_string($legs)) {
            $decoded = json_decode($legs, true);
            $legs = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($legs)) {
            return [];
        }

        return array_values(array_filter($legs, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, NormalizedRecording>
     */
    protected function mapRecordings(array $payload): array
    {
        $recordings = [];

        $url = $this->string($payload, ['RecordingUrl', 'recordingUrl', 'RecordingUrlList']);

        if (filled($url) && str_starts_with(strtolower($url), 'http')) {
            $recordings[] = new NormalizedRecording(
                sourceUrl: $url,
                providerRecordingId: $this->string($payload, ['RecordingSid', 'recordingSid']),
                durationSeconds: $this->int($payload, ['RecordingDuration', 'ConversationDuration']),
                metadata: array_filter([
                    'call_type' => $this->string($payload, ['CallType']),
                    'exophone' => $this->string($payload, ['To', 'OutgoingPhoneNumber']),
                ], static fn (mixed $value): bool => $value !== null),
            );
        }

        // A flow with a voicemail applet publishes a second, separate URL.
        // Losing it would mean losing the only thing the caller actually said.
        $voicemail = $this->string($payload, ['VoicemailUrl', 'voicemailUrl']);

        if (filled($voicemail) && str_starts_with(strtolower($voicemail), 'http')) {
            $recordings[] = new NormalizedRecording(
                sourceUrl: $voicemail,
                metadata: ['kind' => 'voicemail'],
            );
        }

        return $recordings;
    }

    /**
     * Every Exotel field the unified schema has no column for.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extras(array $payload): array
    {
        $claimed = [
            'CallSid', 'callSid', 'Sid', 'sid',
            'From', 'from', 'To', 'to',
            'CallFrom', 'callFrom', 'CallTo', 'callTo',
            'Direction', 'direction',
            'Status', 'CallStatus', 'callStatus', 'status',
            'DialCallStatus', 'dialCallStatus',
            'StartTime', 'startTime', 'EndTime', 'endTime',
            'Created', 'created', 'DateCreated', 'DateUpdated',
            'DialCallDuration', 'dialCallDuration',
            'ConversationDuration', 'conversationDuration', 'OnCallDuration',
            'DialWhomNumber', 'dialWhomNumber', 'AgentNumber',
            'RecordingUrl', 'recordingUrl', 'RecordingUrlList',
            'RecordingSid', 'recordingSid', 'RecordingDuration',
            'VoicemailUrl', 'voicemailUrl',
            'ParentCallSid', 'parentCallSid',
        ];

        $extras = array_diff_key($payload, array_flip($claimed));

        // The secret protecting the endpoint travels in the query string on a
        // Passthru GET, and must not be archived alongside every call.
        unset($extras[(string) config('calls.exotel.webhook.secret_query_key', 'token')]);

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

    /**
     * Parse an Exotel timestamp.
     *
     * EndTime is documented as a Unix epoch while StartTime is a formatted
     * string, so both forms are handled. An unparseable value returns null
     * rather than throwing: the raw string is preserved on the row, and one bad
     * date must not cost the CRM the call.
     */
    protected function parse(?string $value, string $timezone): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            // Converted into the application's timezone, not UTC. Eloquent's
            // datetime cast reads every stored value back in
            // config('app.timezone'), so a UTC wall-clock written to the column
            // is re-read as local time and the call appears hours earlier than
            // it happened. This app runs on Asia/Kolkata, which made every call
            // five and a half hours early.
            $parsed = ctype_digit($value) && strlen($value) >= 9
                ? Carbon::createFromTimestamp((int) $value)->setTimezone(config('app.timezone'))
                : Carbon::parse($value, $timezone)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $this->isPlausible($parsed) ? $parsed : null;
    }

    /**
     * Reject a timestamp that cannot describe a real call.
     *
     * Exotel sends an unset EndTime as the Unix epoch — "1970-01-01 05:30:00"
     * in IST — which means "no value", not "this call ended in 1970". Storing
     * it is wrong on its own terms, and it is also one second below the range
     * a MySQL TIMESTAMP column can hold, so it takes the whole insert down and
     * loses a call that was otherwise mapped perfectly.
     *
     * The raw string is still kept in provider_*_raw, so nothing is hidden —
     * only the normalised column is left null.
     */
    protected function isPlausible(Carbon $moment): bool
    {
        return $moment->year >= 2000 && $moment->year <= 2100;
    }
}
