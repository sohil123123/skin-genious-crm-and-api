<?php

declare(strict_types=1);

use App\Enums\Call\CallSource;
use App\Events\Call\CallAnnounced;
use App\Models\Clinic;
use App\Models\Setting;
use App\Models\User;
use App\Services\Call\CallIngestionService;
use App\Services\Call\Providers\Callyzer\CallyzerCallMapper;
use App\Services\Call\Providers\Exotel\ExotelCallMapper;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * The live screen pop.
 *
 * Two things have to hold. It must fire once, at the moment the phone starts
 * ringing — a popup that appears when the call ends is noise, and one that
 * appears three times is worse. And it must never reach a screen it does not
 * belong on: the payload carries a patient's name and number.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Queue::fake();

    $this->clinic = Clinic::create([
        'name' => 'Jaipur',
        'address_line1' => '1 Test Street',
        'city' => 'Jaipur',
        'pincode' => '302001',
        'is_active' => true,
    ]);

    $this->ingestion = app(CallIngestionService::class);
    $this->exotel = app(ExotelCallMapper::class);
    $this->callyzer = app(CallyzerCallMapper::class);
});

/**
 * A Call Start Passthru: ringing, no status, no duration yet.
 */
function ringingPayload(array $overrides = []): array
{
    return array_merge([
        'CallSid' => 'ringing-1',
        'CallFrom' => '09687784381',
        'From' => '09687784381',
        'To' => '01414937562',
        'CallTo' => '01414937562',
        'Direction' => 'incoming',
        // Exotel writes its timestamps in the account's timezone, so a naive
        // UTC string here would be read as IST and land hours in the past.
        'StartTime' => now()->timezone(config('calls.exotel.timezone'))->format('Y-m-d H:i:s'),
    ], $overrides);
}

function makeCallUser(string $mobile, ?int $clinicId): User
{
    return User::create([
        'clinic_id' => $clinicId,
        'first_name' => 'Anita',
        'last_name' => 'Shah',
        'mobile' => $mobile,
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);
}

// ──────────────── When it fires ────────────────

it('announces a call the moment it starts ringing', function (): void {
    Event::fake([CallAnnounced::class]);

    makeCallUser('9687784381', $this->clinic->getKey());

    $this->ingestion->ingest($this->exotel->map(ringingPayload()));

    Event::assertDispatched(CallAnnounced::class);
});

/**
 * A call produces several deliveries — ringing, answered, completed. Only the
 * first should reach a screen.
 */
it('announces a call once, not on every later event', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->ingestion->ingest($this->exotel->map(ringingPayload()));
    $this->ingestion->ingest($this->exotel->map(ringingPayload(['CallStatus' => 'in-progress'])));
    $this->ingestion->ingest($this->exotel->map(ringingPayload([
        'CallStatus' => 'completed',
        'DialCallDuration' => '70',
    ])));

    Event::assertDispatchedTimes(CallAnnounced::class, 1);
});

/**
 * A call whose only delivery arrives at hangup still announces itself, but it
 * must say so. This used to be suppressed entirely, on the reasoning that a
 * popup should never tell reception to answer a phone that stopped ringing -
 * true, and the reason the card now carries is_live rather than being silent.
 *
 * Suppressing it altogether meant Callyzer could never announce anything: its
 * log leaves the agent's handset after the conversation, so every Callyzer call
 * is "already finished" by the time this CRM hears about it.
 */
it('announces a call that has already ended, and says so', function (): void {
    Event::fake([CallAnnounced::class]);

    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload([
        'CallSid' => 'already-done',
        'CallStatus' => 'completed',
        'DialCallStatus' => 'completed',
        'DialCallDuration' => '70',
    ])));

    Event::assertDispatched(CallAnnounced::class);

    expect((new CallAnnounced($call))->broadcastWith()['is_live'])->toBeFalse();
});

it('announces a live call as live', function (): void {
    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload(['CallSid' => 'still-ringing'])));

    expect((new CallAnnounced($call))->broadcastWith()['is_live'])->toBeTrue();
});

it('announces outgoing calls too', function (): void {
    Event::fake([CallAnnounced::class]);

    $call = $this->ingestion->ingest($this->callyzer->map([
        'id' => 'cz-outgoing',
        'call_type' => 'Outgoing',
        'duration' => '30',
        'client_number' => '9687784381',
        'call_date' => now()->format('Y-m-d'),
        'call_time' => now()->format('H:i:s'),
    ]));

    Event::assertDispatched(CallAnnounced::class);

    // The card reads the opposite way round for an outgoing call, so the
    // direction has to reach it.
    expect((new CallAnnounced($call))->broadcastWith()['direction'])->toBe('outgoing');
});

/**
 * "Unknown" has no sentence to put on a card. Announcing it would be a popup
 * that cannot say what happened.
 */
it('says nothing about a call whose direction is unknown', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->ingestion->ingest($this->exotel->map(ringingPayload([
        'CallSid' => 'no-direction',
        'Direction' => '',
    ])));

    Event::assertNotDispatched(CallAnnounced::class);
});

/**
 * Re-importing history must not fill the clinic's screens with popups for calls
 * from months ago.
 */
it('does not announce a historical call pulled in by a backfill', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->ingestion->ingest($this->exotel->map(
        ringingPayload([
            'CallSid' => 'old-call',
            'StartTime' => now()->subMonths(3)->format('Y-m-d H:i:s'),
        ]),
        CallSource::ApiSync,
    ));

    Event::assertNotDispatched(CallAnnounced::class);
});

/**
 * A call nobody could attribute to a clinic has no screen to appear on, and
 * broadcasting it on channel "clinic..calls" would be a subscription nobody
 * authorises.
 */
it('does not announce a call with no clinic', function (): void {
    Event::fake([CallAnnounced::class]);

    // A second clinic means the sole-clinic fallback cannot resolve one, and
    // the caller matches no patient.
    Clinic::create([
        'name' => 'Second',
        'address_line1' => '2 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
        'is_active' => true,
    ]);

    $this->ingestion->ingest($this->exotel->map(ringingPayload(['CallSid' => 'no-clinic'])));

    Event::assertNotDispatched(CallAnnounced::class);
});

// ──────────────── What it carries, and where ────────────────

it('broadcasts on the private channel of the clinic that was called', function (): void {
    makeCallUser('9687784381', $this->clinic->getKey());

    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload()));

    $channels = (new CallAnnounced($call))->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-clinic.' . $this->clinic->getKey() . '.calls');
});

it('tells the popup who is calling when the caller is a patient', function (): void {
    $patient = makeCallUser('9687784381', $this->clinic->getKey());

    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload()));

    $payload = (new CallAnnounced($call))->broadcastWith();

    expect($payload['name'])->toBe('Anita Shah')
        ->and($payload['is_patient'])->toBeTrue()
        ->and($payload['customer_user_id'])->toBe($patient->getKey())
        ->and($payload['phone'])->toBe('+919687784381')
        ->and($payload['exophone'])->toBe('+911414937562');
});

/**
 * "Existing patient" and "new caller" call for different opening lines, so an
 * unmatched caller must say so rather than guess at a name.
 */
it('says the caller is unknown rather than inventing a name', function (): void {
    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload()));

    $payload = (new CallAnnounced($call))->broadcastWith();

    expect($payload['name'])->toBeNull()
        ->and($payload['is_patient'])->toBeFalse()
        ->and($payload['is_lead'])->toBeFalse()
        ->and($payload['phone'])->toBe('+919687784381');
});

/**
 * This crosses a socket to every logged-in member of the clinic, so it must
 * carry what a person answering a phone needs and nothing else.
 */
it('does not put provider internals on the socket', function (): void {
    makeCallUser('9687784381', $this->clinic->getKey());

    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload()));

    $payload = (new CallAnnounced($call))->broadcastWith();

    expect($payload)->not->toHaveKey('provider_data')
        ->not->toHaveKey('employee_phone')
        ->not->toHaveKey('provider_call_id')
        ->and(array_keys($payload))->toContain('uuid', 'phone', 'name');
});

it('uses a stable event name the front end can bind to', function (): void {
    $call = $this->ingestion->ingest($this->exotel->map(ringingPayload()));

    expect((new CallAnnounced($call))->broadcastAs())->toBe('call-announced');
});

// ──────────────── Who may listen ────────────────

it('lets clinic staff subscribe to their own clinic', function (): void {
    $staff = makeCallUser('9111111111', $this->clinic->getKey());
    $staff->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.clinic_manager'),
        'guard_name' => 'web',
    ]));

    expect(channelAuthorises($staff, $this->clinic->getKey()))->toBeTrue();
});

it('refuses staff from another clinic', function (): void {
    $other = Clinic::create([
        'name' => 'Other',
        'address_line1' => '9 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
        'is_active' => true,
    ]);

    $staff = makeCallUser('9222222222', $other->getKey());
    $staff->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.clinic_manager'),
        'guard_name' => 'web',
    ]));

    expect(channelAuthorises($staff, $this->clinic->getKey()))->toBeFalse();
});

/**
 * A patient with a portal login is authenticated, and would otherwise be able
 * to subscribe to their own clinic's channel and watch every caller arrive.
 */
it('refuses a patient portal login even for their own clinic', function (): void {
    $patient = makeCallUser('9333333333', $this->clinic->getKey());
    $patient->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.client'),
        'guard_name' => 'web',
    ]));

    expect(channelAuthorises($patient, $this->clinic->getKey()))->toBeFalse();
});

it('lets a super admin listen to any clinic', function (): void {
    $admin = makeCallUser('9444444444', null);
    $admin->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.super_admin'),
        'guard_name' => 'web',
    ]));

    expect(channelAuthorises($admin, $this->clinic->getKey()))->toBeTrue();
});

/**
 * Run the real channel authorisation, exactly as a subscribing browser would.
 *
 * Goes through the broadcaster rather than calling the closure directly, so the
 * test exercises the registration in routes/channels.php and not a copy of its
 * logic. A refusal surfaces as an AccessDeniedHttpException, which is Laravel's
 * way of saying false.
 */
function channelAuthorises(User $user, int $clinicId): bool
{
    $request = request()->duplicate();
    $request->merge([
        'channel_name' => 'private-clinic.' . $clinicId . '.calls',
        // The broadcaster signs the subscription once it has authorised it, and
        // the signature needs the socket id a real browser would have sent.
        'socket_id' => '1234.5678',
    ]);
    $request->setUserResolver(fn () => $user);

    try {
        app(\Illuminate\Contracts\Broadcasting\Broadcaster::class)->auth($request);

        return true;
    } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
        return false;
    }
}
