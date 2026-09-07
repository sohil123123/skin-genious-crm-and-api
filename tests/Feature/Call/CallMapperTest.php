<?php

declare(strict_types=1);

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallStatus;
use App\Services\Call\PhoneNumberNormalizer;
use App\Services\Call\Providers\Callyzer\CallyzerCallMapper;
use App\Services\Call\Providers\Exotel\ExotelCallMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Translating each provider's vocabulary into the CRM's.
 *
 * The recurring theme: both providers conflate direction and status, and the
 * CRM needs them apart. "A call we received" and "nobody answered it" are
 * different questions, and every report here has to ask them separately.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->exotel = app(ExotelCallMapper::class);
    $this->callyzer = app(CallyzerCallMapper::class);
    $this->phone = app(PhoneNumberNormalizer::class);
});

// ──────────────── Phone normalisation ────────────────

it('normalises Indian numbers into one canonical form', function (string $input, string $expected): void {
    expect($this->phone->normalize($input)->normalized)->toBe($expected);
})->with([
    'e164' => ['+919876543210', '+919876543210'],
    'country code, no plus' => ['919876543210', '+919876543210'],
    'national' => ['9876543210', '+919876543210'],
    'trunk zero' => ['09876543210', '+919876543210'],
    'international prefix' => ['00919876543210', '+919876543210'],
    'spaces' => ['+91 98765 43210', '+919876543210'],
    'hyphens' => ['098765-43210', '+919876543210'],
    'brackets' => ['(+91) 9876543210', '+919876543210'],
]);

it('builds the same match key from every form of a number', function (string $input): void {
    expect($this->phone->normalize($input)->key)->toBe('9876543210');
})->with(['+919876543210', '919876543210', '9876543210', '09876543210', '+91 98765 43210']);

/**
 * The original is evidence. A normaliser that overwrites its input destroys the
 * only way to answer "what did the provider actually send" when a match goes
 * wrong.
 */
it('never destroys the number as it was received', function (): void {
    $result = $this->phone->normalize('+91 98765 43210');

    expect($result->original)->toBe('+91 98765 43210')
        ->and($result->normalized)->toBe('+919876543210');
});

/**
 * Deliberately more permissive than the lead importer. An Exophone is a
 * landline, and refusing to normalise it would lose every incoming call.
 */
it('accepts a landline Exophone that the lead rules would reject', function (): void {
    $result = $this->phone->normalize('08047122334');

    expect($result->normalized)->toBe('+918047122334')
        ->and($result->key)->toBe('8047122334');
});

it('keeps an unrecognisable caller id without pretending it is a number', function (): void {
    $result = $this->phone->normalize('anonymous');

    expect($result->original)->toBe('anonymous')
        ->and($result->normalized)->toBeNull()
        ->and($result->isUsable())->toBeFalse();
});

it('uses a country code the provider sent as its own field', function (): void {
    $result = $this->phone->normalize('9876543210', '+91');

    expect($result->normalized)->toBe('+919876543210')
        ->and($result->countryCode)->toBe('91');
});

it('refuses to treat a short code as identifying anybody', function (): void {
    expect($this->phone->isMatchable($this->phone->matchKey('1234')))->toBeFalse()
        ->and($this->phone->isMatchable($this->phone->matchKey('9876543210')))->toBeTrue();
});

// ──────────────── Callyzer ────────────────

it('splits the Callyzer call type into a direction and a status', function (
    string $callType,
    int $duration,
    CallDirection $direction,
    CallStatus $status,
): void {
    $call = $this->callyzer->map([
        'id' => 'cz-1',
        'call_type' => $callType,
        'duration' => (string) $duration,
        'client_number' => '9876543210',
        'call_date' => '2026-08-29',
        'call_time' => '10:00:00',
    ]);

    expect($call->direction)->toBe($direction)
        ->and($call->status)->toBe($status);
})->with([
    'connected outgoing' => ['Outgoing', 272, CallDirection::Outgoing, CallStatus::Completed],
    'outgoing ring-out' => ['Outgoing', 0, CallDirection::Outgoing, CallStatus::NoAnswer],
    'connected incoming' => ['Incoming', 90, CallDirection::Incoming, CallStatus::Completed],
    // A missed call is an incoming call that was not answered. Storing "missed"
    // as its own direction would make every incoming-call count wrong.
    'missed' => ['Missed', 0, CallDirection::Incoming, CallStatus::Missed],
    'rejected' => ['Rejected', 0, CallDirection::Incoming, CallStatus::Rejected],
]);

it('reads the Callyzer fields the CRM depends on', function (): void {
    $call = $this->callyzer->map([
        'id' => 'cz-2',
        'emp_name' => 'Priya',
        'emp_code' => 'EMP-01',
        'emp_country_code' => '+91',
        'emp_number' => '9000000001',
        'client_name' => 'Anita Shah',
        'client_country_code' => '+91',
        'client_number' => '9876543210',
        'duration' => '272',
        'call_type' => 'Outgoing',
        'call_date' => '2026-08-29',
        'call_time' => '10:15:00',
        'note' => 'Discussed the facial package',
        'call_recording_url' => 'https://rec.callyzer.test/a.mp3',
        'crm_status' => 'Interested',
        'reminder_date' => '2026-09-02',
        'reminder_time' => '11:00:00',
        'lead_id' => 'lead-77',
        'call_method' => 'sim',
        'call_mode' => 'manual',
    ]);

    expect($call->employeeName)->toBe('Priya')
        ->and($call->employeeCode)->toBe('EMP-01')
        ->and($call->clientName)->toBe('Anita Shah')
        ->and($call->clientPhone->normalized)->toBe('+919876543210')
        ->and($call->employeePhone->normalized)->toBe('+919000000001')
        ->and($call->providerNote)->toBe('Discussed the facial package')
        ->and($call->providerCrmStatus)->toBe('Interested')
        ->and($call->providerLeadId)->toBe('lead-77')
        ->and($call->callMethod)->toBe('sim')
        ->and($call->callMode)->toBe('manual')
        ->and($call->providerReminderAt)->not->toBeNull()
        ->and($call->recordings)->toHaveCount(1)
        ->and($call->recordings[0]->sourceUrl)->toBe('https://rec.callyzer.test/a.mp3');
});

/**
 * Callyzer has shipped both snake_case and camelCase for the same field across
 * versions. An integration that hard-codes one silently loses data when the
 * other arrives.
 */
it('reads Callyzer fields in either naming convention', function (): void {
    $call = $this->callyzer->map([
        'id' => 'cz-3',
        'empName' => 'Priya',
        'clientNumber' => '9876543210',
        'callType' => 'Outgoing',
        'callDate' => '2026-08-29',
        'callTime' => '10:15:00',
        'duration' => '60',
    ]);

    expect($call->employeeName)->toBe('Priya')
        ->and($call->clientPhone->normalized)->toBe('+919876543210');
});

/**
 * A field this mapper has never heard of must still survive, or the raw payload
 * is the only copy and every query has to go through it.
 */
it('keeps unrecognised Callyzer fields rather than dropping them', function (): void {
    $call = $this->callyzer->map([
        'id' => 'cz-4',
        'call_type' => 'Outgoing',
        'duration' => '10',
        'some_future_field' => 'a value nobody has written a column for',
        'emp_tags' => ['field-team'],
    ]);

    expect($call->providerData)->toHaveKey('some_future_field')
        ->and($call->providerData['some_future_field'])->toBe('a value nobody has written a column for')
        ->and($call->providerData)->toHaveKey('emp_tags');
});

// ──────────────── Exotel ────────────────

/**
 * Exotel names the two ends differently depending on direction. Reading them
 * positionally would file half the calls under the clinic's own number as the
 * customer.
 */
it('reads the customer from the right end of the call', function (): void {
    $incoming = $this->exotel->map([
        'CallSid' => 'in-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
    ]);

    expect($incoming->clientPhone->normalized)->toBe('+919876543210')
        ->and($incoming->virtualNumber->normalized)->toBe('+918047122334');

    $outgoing = $this->exotel->map([
        'CallSid' => 'out-1',
        'From' => '08047122334',
        'To' => '+919876543210',
        'Direction' => 'outbound-dial',
        'CallStatus' => 'completed',
    ]);

    expect($outgoing->clientPhone->normalized)->toBe('+919876543210')
        ->and($outgoing->direction)->toBe(CallDirection::Outgoing);
});

/**
 * A call whose CallStatus is "completed" but whose DialCallStatus is
 * "no-answer" reached the flow and never reached a person. Recording it as
 * completed would count a conversation that did not happen.
 */
it('prefers the agent leg status over the flow status', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'legs-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'DialCallStatus' => 'no-answer',
    ]);

    // An unanswered incoming call is what the clinic calls a missed call, and
    // what the follow-up queue is built around.
    expect($call->status)->toBe(CallStatus::Missed)
        ->and($call->status->isConnected())->toBeFalse();
});

it('separates flow duration from time actually spent talking', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'dur-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'DialCallDuration' => '272',
        'ConversationDuration' => '245',
    ]);

    // Crediting IVR menus and hold music as conversation would inflate every
    // average-talk-time figure the clinic looks at.
    expect($call->durationSeconds)->toBe(272)
        ->and($call->talkDurationSeconds)->toBe(245);
});

it('picks up both a recording and a voicemail from one payload', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'rec-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'RecordingUrl' => 'https://rec.exotel.test/a.mp3',
        'VoicemailUrl' => 'https://rec.exotel.test/vm.mp3',
    ]);

    expect($call->recordings)->toHaveCount(2);
});

it('reads the hangup cause from the last connect leg', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'leg-cause',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        // Arrives as a JSON string over the Passthru query string.
        'Legs' => json_encode([
            ['Number' => '+919000000001', 'Cause' => 'NoAnswer', 'CauseCode' => '19', 'OnCallDuration' => 0],
        ]),
    ]);

    expect($call->disposition)->toBe('NoAnswer')
        ->and($call->hangupCauseCode)->toBe('19');
});

it('handles an Exotel epoch end time', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'epoch-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => '2026-08-29 10:00:00',
        'EndTime' => '1787047200',
    ]);

    expect($call->endedAt)->not->toBeNull();
});

/**
 * The raw strings are kept because when a timestamp looks wrong the question is
 * always what the provider actually sent.
 */
it('keeps the provider timestamps exactly as written', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'raw-1',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => '2026-08-29 10:15:00',
    ]);

    expect($call->providerStartedAtRaw)->toBe('2026-08-29 10:15:00');
});

it('survives a malformed timestamp rather than losing the call', function (): void {
    $call = $this->exotel->map([
        'CallSid' => 'bad-time',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => 'not a date at all',
    ]);

    expect($call)->not->toBeNull()
        ->and($call->startedAt)->toBeNull()
        // The original is still on the record for whoever debugs it.
        ->and($call->providerStartedAtRaw)->toBe('not a date at all');
});

it('refuses a payload with no CallSid', function (): void {
    expect($this->exotel->map(['Direction' => 'incoming']))->toBeNull();
});
