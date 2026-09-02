<?php

declare(strict_types=1);

use App\Events\Call\CallAnnounced;
use App\Models\{Call, Clinic};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * The demonstration command is a testing aid, which is exactly why it needs
 * tests: an aid that quietly announces the wrong thing sends someone hunting a
 * bug in the popup that is really in the tool they are hunting it with.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'demo-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'clinic_id' => $this->clinic->getKey(),
        'started_at' => now()->subDays(30),
    ]);
});

it('sends all four cards by default', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->artisan('calls:demo-popup')->assertSuccessful();

    Event::assertDispatchedTimes(CallAnnounced::class, 4);
});

it('sends just the pair asked for', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->artisan('calls:demo-popup', ['--direction' => ['outgoing']])->assertSuccessful();

    Event::assertDispatchedTimes(CallAnnounced::class, 2);

    Event::assertDispatched(CallAnnounced::class, fn (CallAnnounced $event): bool => $event
        ->broadcastWith()['direction'] === 'outgoing');
});

it('sends one card for one combination', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->artisan('calls:demo-popup', ['--direction' => ['incoming'], '--state' => ['live']])
        ->assertSuccessful();

    Event::assertDispatchedTimes(CallAnnounced::class, 1);

    Event::assertDispatched(CallAnnounced::class, function (CallAnnounced $event): bool {
        $payload = $event->broadcastWith();

        return $payload['direction'] === 'incoming' && $payload['is_live'] === true;
    });
});

/**
 * The borrowed call must come back untouched. A testing aid that edits real
 * records is worse than no aid at all.
 */
it('never saves what it borrows', function (): void {
    Event::fake([CallAnnounced::class]);

    $before = $this->call->only(['uuid', 'direction', 'call_status']);

    $this->artisan('calls:demo-popup')->assertSuccessful();

    expect($this->call->fresh()->only(['uuid', 'direction', 'call_status']))->toBe($before);
});

/**
 * The recency window belongs to ingestion, not to the event. A month-old call
 * would never announce itself through the normal path, and must still be
 * usable here — otherwise the aid only works on a system that has just taken a
 * call, which is precisely when nobody needs it.
 */
it('announces a call far older than the popup window', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->artisan('calls:demo-popup')->assertSuccessful();

    Event::assertDispatched(CallAnnounced::class);
});

it('rejects a direction it cannot draw', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->artisan('calls:demo-popup', ['--direction' => ['sideways']])->assertFailed();

    Event::assertNotDispatched(CallAnnounced::class);
});

it('stops when there is no call carrying a clinic', function (): void {
    Event::fake([CallAnnounced::class]);

    $this->call->forceFill(['clinic_id' => null])->save();

    $this->artisan('calls:demo-popup')
        ->expectsOutputToContain('No call with a clinic to borrow')
        ->assertFailed();

    Event::assertNotDispatched(CallAnnounced::class);
});
