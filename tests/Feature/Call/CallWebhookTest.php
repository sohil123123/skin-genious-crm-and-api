<?php

declare(strict_types=1);

use App\Enums\Call\CallEventProcessingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallStatus;
use App\Models\Call;
use App\Models\CallProviderPayload;
use App\Models\CallWebhookEvent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * The webhook endpoints' contract with the two providers.
 *
 * Three things matter above all, and every test here is one of them:
 *
 *  - An unauthenticated request must never be able to inject a call. These
 *    endpoints are public by necessity, and a call record ends up in a
 *    patient's medical history.
 *  - A redelivery must never produce a second call. Both providers retry
 *    whenever they do not see a prompt 2xx, so this is routine traffic rather
 *    than an edge case.
 *  - Anything that is not an authentication failure must answer 2xx, because a
 *    non-2xx makes the provider redeliver for hours over a payload that will
 *    never become valid.
 */
uses(RefreshDatabase::class);

const EXOTEL_SECRET = 'exotel-test-secret';
const CALLYZER_SECRET = 'callyzer-test-secret';

beforeEach(function (): void {
    // The settings cache is memoised in a static property that outlives a
    // request, so without this one test's credentials leak into the next.
    Setting::flushRuntimeCache();

    Setting::setValue('exotel_enabled', '1');
    Setting::setValue('exotel_webhook_secret', EXOTEL_SECRET);
    Setting::setValue('callyzer_enabled', '1');
    Setting::setValue('callyzer_webhook_secret', CALLYZER_SECRET);

    Queue::fake();
});

/**
 * A representative Exotel Passthru delivery: a GET with URL-encoded params.
 */
function exotelPayload(array $overrides = []): array
{
    return array_merge([
        'CallSid' => 'exotel-call-001',
        'CallFrom' => '+919876543210',
        'CallTo' => '08047122334',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'DialCallStatus' => 'completed',
        'CallType' => 'completed',
        'DialWhomNumber' => '+919000000001',
        'StartTime' => '2026-08-29 10:15:00',
        'DialCallDuration' => '272',
        'ConversationDuration' => '245',
    ], $overrides);
}

function callyzerPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'callyzer-call-001',
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
        'crm_status' => 'Interested',
        'modified_at' => '2026-08-29 10:20:00',
    ], $overrides);
}

// ──────────────── Authentication ────────────────

it('rejects an Exotel webhook with no secret', function (): void {
    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(exotelPayload()))
        ->assertStatus(403);

    expect(Call::count())->toBe(0)
        ->and(CallWebhookEvent::count())->toBe(0);
});

it('rejects an Exotel webhook with the wrong secret', function (): void {
    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload() + ['token' => 'not-the-secret']
    ))->assertStatus(403);

    expect(Call::count())->toBe(0);
});

/**
 * Failing closed is the whole point: an endpoint that waves requests through
 * while unconfigured is an open door to a patient's file.
 */
it('rejects every Exotel webhook while no secret is configured', function (): void {
    Setting::setValue('exotel_webhook_secret', '');
    Setting::flushRuntimeCache();

    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(exotelPayload()))
        ->assertStatus(403);
});

it('rejects a Callyzer webhook with the wrong secret', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=wrong', callyzerPayload())
        ->assertStatus(403);

    expect(Call::count())->toBe(0);
});

// ──────────────── Acceptance ────────────────

it('accepts a signed Exotel webhook and archives the payload', function (): void {
    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload() + ['token' => EXOTEL_SECRET]
    ))->assertOk()->assertJson(['status' => 'accepted']);

    expect(CallWebhookEvent::count())->toBe(1)
        ->and(CallProviderPayload::count())->toBe(1);

    $payload = CallProviderPayload::first();

    expect($payload->provider)->toBe(CallProvider::Exotel)
        ->and($payload->provider_call_id)->toBe('exotel-call-001')
        // The secret travels in the query string on a Passthru GET and must not
        // be archived in full alongside every call.
        ->and($payload->payload['token'])->toBe('[redacted]');
});

it('accepts a signed Callyzer webhook', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, callyzerPayload())
        ->assertOk()
        ->assertJson(['status' => 'accepted', 'accepted' => 1]);

    expect(CallWebhookEvent::where('provider', CallProvider::Callyzer->value)->count())->toBe(1);
});

it('accepts a Callyzer batch and claims each record separately', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, [
        'data' => [
            callyzerPayload(['id' => 'batch-1']),
            callyzerPayload(['id' => 'batch-2']),
            callyzerPayload(['id' => 'batch-3']),
        ],
    ])->assertOk()->assertJson(['accepted' => 3]);

    expect(CallWebhookEvent::count())->toBe(3);
});

// ──────────────── Idempotency ────────────────

it('does not claim the same Exotel delivery twice', function (): void {
    $query = http_build_query(exotelPayload() + ['token' => EXOTEL_SECRET]);

    $this->getJson('/api/webhooks/exotel/calls?' . $query)->assertOk();
    $this->getJson('/api/webhooks/exotel/calls?' . $query)
        ->assertOk()
        ->assertJson(['status' => 'duplicate']);

    expect(CallWebhookEvent::count())->toBe(1)
        // One archived payload per call: the retry replaced the row rather
        // than adding one. The evidence that a retry happened is not lost -
        // it lives on the event's duplicate_count, which is where it belongs,
        // since counting retries was never the payload table's job.
        ->and(CallProviderPayload::count())->toBe(1)
        ->and(CallWebhookEvent::first()->duplicate_count)->toBe(1);
});

/**
 * A call legitimately produces several deliveries as it progresses. Keying on
 * the CallSid alone would accept the first and silently discard the one
 * carrying the duration and the recording.
 */
it('treats a later stage of the same call as a new event', function (): void {
    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload(['CallStatus' => 'ringing', 'DialCallStatus' => '']) + ['token' => EXOTEL_SECRET]
    ))->assertOk();

    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload(['CallStatus' => 'completed']) + ['token' => EXOTEL_SECRET]
    ))->assertOk()->assertJson(['status' => 'accepted']);

    expect(CallWebhookEvent::count())->toBe(2)
        ->and(CallWebhookEvent::pluck('provider_call_id')->unique())->toHaveCount(1);
});

/**
 * A delivery that differs only by having the recording ready is a real
 * progression, not a retry.
 */
it('treats a recording becoming available as a new event', function (): void {
    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload() + ['token' => EXOTEL_SECRET]
    ))->assertOk();

    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload(['RecordingUrl' => 'https://recordings.exotel.test/a.mp3']) + ['token' => EXOTEL_SECRET]
    ))->assertOk()->assertJson(['status' => 'accepted']);

    expect(CallWebhookEvent::count())->toBe(2);
});

// ──────────────── Unusable payloads ────────────────

/**
 * Answered 2xx on purpose. A non-2xx makes the provider redeliver for hours,
 * and a payload with no call id will not acquire one on the fifth attempt.
 */
it('accepts but ignores a payload with no call identifier', function (): void {
    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query([
        'Direction' => 'incoming',
        'token' => EXOTEL_SECRET,
    ]))->assertOk()->assertJson(['status' => 'ignored']);

    expect(CallWebhookEvent::count())->toBe(0)
        // Still archived: an unusable payload is exactly the one worth keeping.
        ->and(CallProviderPayload::count())->toBe(1)
        ->and(CallProviderPayload::first()->processing_status)
        ->toBe(CallEventProcessingStatus::Ignored);
});

it('answers 200 without recording anything when the integration is switched off', function (): void {
    Setting::setValue('exotel_enabled', '0');
    Setting::flushRuntimeCache();

    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload() + ['token' => EXOTEL_SECRET]
    ))->assertOk()->assertJson(['status' => 'disabled']);

    expect(CallProviderPayload::count())->toBe(0);
});

// ──────────────── The legacy screen pop ────────────────

/**
 * The reception software reads this response while a patient is on the line.
 * The contract predates this system and must survive it untouched.
 */
it('keeps the legacy screen-pop response shape', function (): void {
    $this->getJson('/api/exotel/webhook?' . http_build_query([
        'CallSid' => 'pop-1',
        'CallFrom' => '9876543210',
        'Direction' => 'incoming',
    ]))->assertOk()->assertJsonStructure(['select']);
});

it('answers the screen pop even when the call pipeline cannot run', function (): void {
    // No secret configured, so ingestion refuses the request outright. The
    // receptionist must still get their screen.
    Setting::setValue('exotel_webhook_secret', '');
    Setting::flushRuntimeCache();

    $this->getJson('/api/exotel/webhook?' . http_build_query([
        'CallSid' => 'pop-2',
        'CallFrom' => '9876543210',
        'Direction' => 'incoming',
    ]))->assertOk()->assertJson(['select' => 'new']);
});

// ──────────────── The shape Callyzer actually sends ────────────────

/**
 * Callyzer does not post calls. It posts employees, each carrying the calls
 * they made - which is what its own "Configure Webhook" screen shows in the
 * sample request:
 *
 *     [{ emp_name, emp_code, emp_number, emp_tags, call_logs: [ ... ] }]
 *
 * Every other test in this file feeds a flat call object, so this shape went
 * uncovered and the endpoint dropped every real delivery: an employee record
 * has no call id, so the mapper returned null and the call vanished behind a
 * 200 that Callyzer's own log proudly recorded as "Success".
 *
 * @param  array<int, array<string, mixed>>  $logs
 * @return array<int, array<string, mixed>>
 */
function callyzerEmployeeDelivery(array $logs = []): array
{
    return [[
        'emp_name' => 'Shivani',
        'emp_code' => 'EMP-01',
        'emp_country_code' => '+91',
        'emp_number' => '8169308873',
        'emp_tags' => ['emp1', 'ABC'],
        'call_logs' => $logs ?: [[
            'id' => 'callyzer-nested-001',
            'client_name' => 'Meena Bhawnani',
            'client_country_code' => '+91',
            'client_number' => '7568900753',
            'duration' => '52',
            'call_type' => 'Outgoing',
            'call_date' => '2026-09-02',
            'call_time' => '13:03:00',
        ]],
    ]];
}

it('accepts a call delivered inside its employee wrapper', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, callyzerEmployeeDelivery())
        ->assertOk()
        // Before the fix this was "ignored", with 0 accepted.
        ->assertJson(['status' => 'accepted', 'accepted' => 1]);

    expect(CallWebhookEvent::where('provider', CallProvider::Callyzer->value)->count())->toBe(1);
});

it('claims every call in an employee batch separately', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, callyzerEmployeeDelivery([
        ['id' => 'nested-a', 'call_type' => 'Outgoing', 'duration' => '30',
            'call_date' => '2026-09-02', 'call_time' => '13:00:00', 'client_number' => '7568900753'],
        ['id' => 'nested-b', 'call_type' => 'Outgoing', 'duration' => '15',
            'call_date' => '2026-09-02', 'call_time' => '13:05:00', 'client_number' => '9011623310'],
    ]))->assertOk()->assertJson(['accepted' => 2]);

    expect(CallWebhookEvent::count())->toBe(2);
});

it('ignores an employee who made no calls', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, [[
        'emp_name' => 'Idle', 'emp_number' => '9000000009', 'call_logs' => [],
    ]])->assertOk()->assertJson(['status' => 'ignored']);

    expect(CallWebhookEvent::count())->toBe(0);
});

// ──────────────── Flattening, in isolation ────────────────

it('unwraps employee-nested calls and merges the employee onto each', function (): void {
    $flat = \App\Services\Call\Providers\Callyzer\CallyzerCallMapper::flatten(callyzerEmployeeDelivery([
        ['id' => 'nested-a', 'call_type' => 'Outgoing'],
        ['id' => 'nested-b', 'call_type' => 'Missed'],
    ]));

    expect($flat)->toHaveCount(2)
        ->and($flat[0]['id'])->toBe('nested-a')
        // The employee lives on the wrapper; without merging it down every
        // call arrives unattributable.
        ->and($flat[0]['emp_name'])->toBe('Shivani')
        ->and($flat[1]['emp_number'])->toBe('8169308873')
        // The wrapper key itself does not travel with the call.
        ->and($flat[0])->not->toHaveKey('call_logs');
});

it('lets a call override a field its employee also carries', function (): void {
    $flat = \App\Services\Call\Providers\Callyzer\CallyzerCallMapper::flatten(callyzerEmployeeDelivery([
        ['id' => 'nested-c', 'emp_name' => 'Stood In For Shivani'],
    ]));

    expect($flat[0]['emp_name'])->toBe('Stood In For Shivani');
});

it('passes an already-flat call log through untouched', function (): void {
    $flat = \App\Services\Call\Providers\Callyzer\CallyzerCallMapper::flatten([callyzerPayload()]);

    expect($flat)->toHaveCount(1)
        ->and($flat[0]['id'])->toBe('callyzer-call-001');
});

/**
 * The end of the chain: a nested delivery has to become a mapped outgoing call
 * with its agent attached, not merely be "accepted".
 */
it('maps a nested delivery into an attributed outgoing call', function (): void {
    $flat = \App\Services\Call\Providers\Callyzer\CallyzerCallMapper::flatten(callyzerEmployeeDelivery());

    $normalized = app(\App\Services\Call\Providers\Callyzer\CallyzerCallMapper::class)->map($flat[0]);

    expect($normalized)->not->toBeNull()
        ->and($normalized->providerCallId)->toBe('callyzer-nested-001')
        ->and($normalized->direction)->toBe(\App\Enums\Call\CallDirection::Outgoing)
        ->and($normalized->employeeName)->toBe('Shivani')
        ->and($normalized->employeeCode)->toBe('EMP-01')
        ->and($normalized->talkDurationSeconds)->toBe(52);
});

/**
 * Independent of the nesting: a plain list of flat call logs, delivered to the
 * URL Callyzer actually posts to. Request::all() folds ?secret=... in beside
 * the body, so the list stopped being a list and the batch collapsed into one
 * unmappable record. This is the narrower of the two bugs and needs its own
 * test, because fixing either one alone still leaves real traffic broken.
 */
it('reads a JSON list body even though the secret is in the query string', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, [
        callyzerPayload(['id' => 'listed-1']),
        callyzerPayload(['id' => 'listed-2']),
    ])->assertOk()->assertJson(['accepted' => 2]);

    expect(CallWebhookEvent::count())->toBe(2);
});

/**
 * The secret must not survive into the stored payload either - it is a
 * credential, and provider_data is shown on the call's Provider and sync panel.
 */
it('does not carry the secret into the archived payload', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, callyzerEmployeeDelivery())
        ->assertOk();

    $archived = CallProviderPayload::where('provider', CallProvider::Callyzer->value)->first();

    expect($archived)->not->toBeNull()
        ->and(json_encode($archived->payload))->not->toContain(CALLYZER_SECRET);
});

// ──────────────── The delivery that carries the recording ────────────────

/**
 * Callyzer posts a call the moment it ends, then posts it again once the
 * recording has finished uploading. Both deliveries carry the same synced_at
 * and no modified_at, so keying on synced_at made the second one a duplicate -
 * and the second one is the only one holding call_recording_url.
 *
 * The call was stored, the audio was not, and nothing reported a failure. This
 * is the shape of that exact pair, taken from a real archived payload.
 *
 * @return array<string, mixed>
 */
function callyzerTwoStageDelivery(?string $recordingUrl): array
{
    return callyzerPayload([
        'id' => 'two-stage-001',
        'synced_at' => '2026-09-02 15:11:15',
        'modified_at' => null,
        'call_recording_url' => $recordingUrl,
    ]);
}

it('does not treat the recording delivery as a duplicate of the one before it', function (): void {
    $provider = app(\App\Services\Call\CallProviderManager::class)->get(CallProvider::Callyzer);

    $withoutAudio = $provider->eventKey(callyzerTwoStageDelivery(null));
    $withAudio = $provider->eventKey(callyzerTwoStageDelivery('https://media1.callyzer.co/public/x.mp3'));

    expect($withoutAudio)->not->toBe($withAudio);
});

it('still collapses a byte-identical redelivery', function (): void {
    $provider = app(\App\Services\Call\CallProviderManager::class)->get(CallProvider::Callyzer);

    expect($provider->eventKey(callyzerTwoStageDelivery(null)))
        ->toBe($provider->eventKey(callyzerTwoStageDelivery(null)));
});

/**
 * synced_at changes on every sync run without the call changing. If it fed the
 * fingerprint, each run would look like new work on every call it returned.
 */
it('ignores the batch timestamp when deciding what is new', function (): void {
    $provider = app(\App\Services\Call\CallProviderManager::class)->get(CallProvider::Callyzer);

    $first = $provider->eventKey(callyzerPayload(['id' => 'stable-1', 'synced_at' => '2026-09-02 15:11:15']));
    $later = $provider->eventKey(callyzerPayload(['id' => 'stable-1', 'synced_at' => '2026-09-02 16:00:00']));

    expect($first)->toBe($later);
});

it('reorders keys without inventing a new event', function (): void {
    $provider = app(\App\Services\Call\CallProviderManager::class)->get(CallProvider::Callyzer);

    $payload = callyzerPayload(['id' => 'ordered-1']);

    expect($provider->eventKey($payload))->toBe($provider->eventKey(array_reverse($payload, true)));
});

/**
 * End to end: the second delivery must actually attach the audio to the call
 * the first one created, rather than being waved through as already seen.
 */
it('attaches the recording that arrives in a later delivery', function (): void {
    $secret = '?secret=' . CALLYZER_SECRET;

    $this->postJson('/api/webhooks/callyzer/calls' . $secret, callyzerTwoStageDelivery(null))
        ->assertOk()->assertJson(['accepted' => 1]);

    $this->postJson('/api/webhooks/callyzer/calls' . $secret, callyzerTwoStageDelivery(
        'https://media1.callyzer.co/public/DER/9619620681_6377259030.mp3'
    ))->assertOk()->assertJson(['accepted' => 1, 'duplicates' => 0]);

    expect(CallWebhookEvent::count())->toBe(2);
});

// ──────────────── One archived payload per call ────────────────

it('keeps one payload row per call and updates it in place', function (): void {
    $secret = '?secret=' . CALLYZER_SECRET;

    $this->postJson('/api/webhooks/callyzer/calls' . $secret, callyzerTwoStageDelivery(null))->assertOk();
    $this->postJson('/api/webhooks/callyzer/calls' . $secret, callyzerTwoStageDelivery(
        'https://media1.callyzer.co/public/DER/later.mp3'
    ))->assertOk();

    $rows = CallProviderPayload::where('provider_call_id', 'two-stage-001')->get();

    expect($rows)->toHaveCount(1)
        // The surviving row is the newest delivery, the one with the audio.
        ->and($rows->first()->payload['call_recording_url'])
        ->toBe('https://media1.callyzer.co/public/DER/later.mp3');
});

/**
 * Each unreadable delivery is its own puzzle. Collapsing them would leave no
 * evidence of what arrived.
 */
it('keeps every payload that has no readable call id', function (): void {
    $secret = '?secret=' . CALLYZER_SECRET;

    $this->postJson('/api/webhooks/callyzer/calls' . $secret, ['nonsense' => 'one'])->assertOk();
    $this->postJson('/api/webhooks/callyzer/calls' . $secret, ['nonsense' => 'two'])->assertOk();

    expect(CallProviderPayload::whereNull('provider_call_id')->count())->toBe(2);
});

it('does not let two providers collide on the same call id', function (): void {
    $this->postJson('/api/webhooks/callyzer/calls?secret=' . CALLYZER_SECRET, callyzerPayload(['id' => 'shared-id']))
        ->assertOk();

    $this->getJson('/api/webhooks/exotel/calls?' . http_build_query(
        exotelPayload(['CallSid' => 'shared-id']) + ['token' => EXOTEL_SECRET]
    ))->assertOk();

    expect(CallProviderPayload::where('provider_call_id', 'shared-id')->count())->toBe(2);
});
