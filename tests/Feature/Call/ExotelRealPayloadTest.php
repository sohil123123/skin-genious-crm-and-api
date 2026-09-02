<?php

declare(strict_types=1);

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallStatus;
use App\Models\Setting;
use App\Services\Call\CallIngestionService;
use App\Services\Call\Providers\Exotel\ExotelCallMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * A real Exotel delivery, captured from the AIA - Jaipur flow.
 *
 * Built from an actual production payload rather than an invented one, because
 * the invented ones all passed. What they missed was Exotel sending an unset
 * EndTime as the Unix epoch — which is not a malformed date, parses fine, and
 * then fails at the database because it sits one second below what a MySQL
 * TIMESTAMP can hold. The call was mapped perfectly and lost anyway.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Queue::fake();

    $this->mapper = app(ExotelCallMapper::class);
    $this->ingestion = app(CallIngestionService::class);
});

/**
 * The payload exactly as it arrived, epoch EndTime included.
 */
function realExotelPayload(array $overrides = []): array
{
    return array_merge([
        'CallSid' => '55ac55e8e8181088285f354e1ff51a91',
        'CallFrom' => '09687784381',
        'CallTo' => '01414937562',
        'From' => '09687784381',
        'To' => '01414937562',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'DialCallStatus' => 'completed',
        'CallType' => 'completed',
        'DialWhomNumber' => '09167356935',
        'StartTime' => '2026-09-01 14:08:28',
        // Exotel's "no value" for a field it never filled in.
        'EndTime' => '1970-01-01 05:30:00',
        'DialCallDuration' => '70',
        'CurrentTime' => '2026-09-01 14:09:42',
        'OutgoingPhoneNumber' => '01414937562',
        'flow_id' => '1309758',
        'tenant_id' => '122962',
        'RecordingAvailableBy' => 'Tue, 01 Sep 2026 14:14:42',
        'Legs' => json_encode([[
            'Type' => 'single',
            'Cause' => '16',
            'Number' => '09167356935',
            'CallerId' => '01414937562',
            'CauseCode' => 'NORMAL_CLEARING',
            'DisconnectedBy' => 'Caller',
            'OnCallDuration' => '58',
        ]]),
    ], $overrides);
}

// ──────────────── The crash ────────────────

it('does not turn an epoch EndTime into a stored date', function (): void {
    $call = $this->mapper->map(realExotelPayload());

    // 1970 is what crashed the insert, and it must never be stored. The end
    // time is now worked out from the durations instead of abandoned - what
    // matters is that it is a real time, not Exotel's placeholder.
    expect($call->endedAt?->year)->toBe(2026)
        // The raw value survives, so what Exotel sent is still auditable.
        ->and($call->providerEndedAtRaw)->toBe('1970-01-01 05:30:00');
});

it('ingests the real payload without failing', function (): void {
    $call = $this->ingestion->ingest($this->mapper->map(realExotelPayload()));

    expect($call)->not->toBeNull()
        ->and($call->exists)->toBeTrue()
        ->and($call->ended_at?->format('Y-m-d H:i:s'))->toBe('2026-09-01 14:09:38');
});

it('rejects an epoch StartTime too', function (): void {
    $call = $this->mapper->map(realExotelPayload(['StartTime' => '1970-01-01 05:30:00']));

    expect($call->startedAt)->toBeNull();
});

it('still accepts a genuine end time', function (): void {
    $call = $this->mapper->map(realExotelPayload(['EndTime' => '2026-09-01 14:09:38']));

    expect($call->endedAt)->not->toBeNull()
        ->and($call->endedAt->year)->toBe(2026);
});

// ──────────────── Everything else the real call carried ────────────────

/**
 * The bug this pins: the mapper used to convert provider times to UTC, but
 * Eloquent reads every datetime column back in config('app.timezone'). On an
 * Asia/Kolkata install that made the stored value five and a half hours early,
 * so a 2:08 PM call showed as 8:38 AM on every screen in the CRM.
 */
it('stores a call at the time it actually happened', function (): void {
    $call = $this->ingestion->ingest($this->mapper->map(realExotelPayload()));

    // Exotel sent "2026-09-01 14:08:28" in the account's timezone. Read back
    // through the model, it has to still be 2:08 PM local.
    expect($call->fresh()->started_at->timezone(config('app.timezone'))->format('Y-m-d H:i'))
        ->toBe('2026-09-01 14:08')
        ->and($call->provider_started_at_raw)->toBe('2026-09-01 14:08:28');
});

it('maps the whole real call correctly', function (): void {
    $call = $this->ingestion->ingest($this->mapper->map(realExotelPayload()));

    expect($call->provider_call_id)->toBe('55ac55e8e8181088285f354e1ff51a91')
        ->and($call->direction)->toBe(CallDirection::Incoming)
        ->and($call->call_status)->toBe(CallStatus::Completed)
        ->and($call->is_connected)->toBeTrue()
        // The caller, the agent Exotel dialled, and the clinic's own Exophone.
        ->and($call->client_phone_normalized)->toBe('+919687784381')
        ->and($call->employee_phone_normalized)->toBe('+919167356935')
        ->and($call->virtual_number_normalized)->toBe('+911414937562')
        ->and($call->duration_seconds)->toBe(70)
        // From the leg, not the flow: 58 seconds of actual conversation.
        ->and($call->talk_duration_seconds)->toBe(58)
        ->and($call->started_at->format('Y-m-d H:i:s'))->toBe('2026-09-01 14:08:28');
});

/**
 * Exotel inverts these: Cause holds the number, CauseCode holds the name.
 * Read the obvious way round, staff see "16" where a reason should be.
 */
it('puts the readable hangup reason in disposition and the number in the code', function (): void {
    $call = $this->mapper->map(realExotelPayload());

    expect($call->disposition)->toBe('NORMAL_CLEARING')
        ->and($call->hangupCauseCode)->toBe('16');
});

it('does not store CallType as a provider reference id', function (): void {
    $call = $this->mapper->map(realExotelPayload());

    expect($call->providerReferenceId)->toBeNull()
        // It is still preserved, just in the right place.
        ->and($call->providerData)->toHaveKey('CallType');
});

/**
 * This delivery carried no RecordingUrl — Exotel said the audio would not be
 * ready for another five minutes. A hangup Passthru therefore never has audio,
 * which is why the recording has to be collected afterwards.
 */
it('records that a completed call arrived with no recording yet', function (): void {
    $call = $this->ingestion->ingest($this->mapper->map(realExotelPayload()));

    expect($call->has_recording)->toBeFalse()
        ->and($call->provider_data)->toHaveKey('RecordingAvailableBy');
});

it('keeps the Exotel flow and tenant ids for debugging', function (): void {
    $call = $this->ingestion->ingest($this->mapper->map(realExotelPayload()));

    expect($call->provider_data['flow_id'])->toBe('1309758')
        ->and($call->provider_data['tenant_id'])->toBe('122962');
});

// ──────────────── What Exotel omits but implies ────────────────

/**
 * Exotel sends no answer time and no ring timer, and writes the epoch into
 * EndTime when the flow never set one. The Timing card was four dashes on calls
 * where the numbers to fill it were already in the payload: DialCallDuration
 * covers ringing plus talking, OnCallDuration is the talking.
 */
it('works out the end, the answer and the ring from the durations', function (): void {
    $call = $this->mapper->map(realExotelPayload());

    // 14:08:28 + 70s total.
    expect($call->endedAt?->format('Y-m-d H:i:s'))->toBe('2026-09-01 14:09:38')
        // 70 total - 58 talking.
        ->and($call->ringDurationSeconds)->toBe(12)
        // Picked up 12s after it started ringing.
        ->and($call->answeredAt?->format('Y-m-d H:i:s'))->toBe('2026-09-01 14:08:40')
        ->and($call->talkDurationSeconds)->toBe(58);
});

/**
 * A real end time is the provider's word and must win over the arithmetic.
 */
it('prefers the end time Exotel actually sent', function (): void {
    $call = $this->mapper->map(realExotelPayload(['EndTime' => '2026-09-01 14:20:00']));

    expect($call->endedAt?->format('Y-m-d H:i:s'))->toBe('2026-09-01 14:20:00');
});

/**
 * Nobody picked up, so there is no moment of answer to record. Ring time still
 * exists - it rang, then stopped.
 */
it('gives a missed call a ring time but no answer time', function (): void {
    $call = $this->mapper->map(realExotelPayload([
        'DialCallStatus' => 'no-answer',
        'CallStatus' => 'no-answer',
        'DialCallDuration' => '18',
        'Legs' => json_encode([[
            'Type' => 'single',
            'CauseCode' => 'NO_ANSWER',
            'Number' => '09167356935',
            'OnCallDuration' => '0',
        ]]),
    ]));

    expect($call->answeredAt)->toBeNull()
        ->and($call->ringDurationSeconds)->toBe(18)
        ->and($call->talkDurationSeconds)->toBe(0);
});

/**
 * Two numbers from different legs cannot be subtracted. No ring time beats a
 * negative one.
 */
it('refuses to report a negative ring time', function (): void {
    $call = $this->mapper->map(realExotelPayload(['DialCallDuration' => '30']));

    expect($call->ringDurationSeconds)->toBeNull()
        ->and($call->answeredAt)->toBeNull();
});

/**
 * Derived, not reported. The raw columns carry only what Exotel said, so a
 * later reconciliation can tell the two apart.
 */
it('does not pass a computed answer time off as the provider word', function (): void {
    $call = $this->mapper->map(realExotelPayload());

    expect($call->answeredAt)->not->toBeNull()
        ->and($call->providerAnsweredAtRaw ?? null)->toBeNull();
});
