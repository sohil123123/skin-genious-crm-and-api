<?php

declare(strict_types=1);

use App\Models\Call;
use App\Models\Clinic;
use App\Models\Setting;
use App\Models\User;
use App\Services\Call\CallAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The metrics the dashboards and the Next Best Action engine both read.
 *
 * They share one service on purpose. Two definitions of "connected call" is how
 * a dashboard and an action queue end up disagreeing about the same patient,
 * and the disagreement is invisible until someone chases a patient the screen
 * says was already spoken to.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();

    $this->clinic = Clinic::create([
        'name' => 'Test Clinic',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    $this->patient = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Anita',
        'last_name' => 'Shah',
        'mobile' => '9876543210',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->analytics = app(CallAnalyticsService::class);
});

function makeCall(array $attributes = []): Call
{
    return Call::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => test()->clinic->getKey(),
        'provider' => 'exotel',
        'provider_call_id' => 'call-' . Str::random(8),
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'is_connected' => true,
        'customer_user_id' => test()->patient->getKey(),
        'client_phone_key' => '9876543210',
        'started_at' => now()->subDay(),
        'duration_seconds' => 120,
        'talk_duration_seconds' => 100,
    ], $attributes));
}

it('counts calls by direction and connection', function (): void {
    makeCall();
    makeCall(['direction' => 'outgoing']);
    makeCall(['direction' => 'incoming', 'call_status' => 'missed', 'is_connected' => false, 'talk_duration_seconds' => 0]);

    $summary = $this->analytics->summary(now()->subWeek(), now());

    expect($summary['total_calls'])->toBe(3)
        ->and($summary['incoming_calls'])->toBe(2)
        ->and($summary['outgoing_calls'])->toBe(1)
        ->and($summary['missed_calls'])->toBe(1)
        ->and($summary['connected_calls'])->toBe(2);
});

/**
 * The single most useful number on the dashboard: how much of the clinic's
 * calling actually reached a person. Volume flatters; this informs.
 */
it('computes a connection rate', function (): void {
    makeCall();
    makeCall();
    makeCall(['call_status' => 'no_answer', 'is_connected' => false]);
    makeCall(['call_status' => 'missed', 'is_connected' => false]);

    expect($this->analytics->summary(now()->subWeek(), now())['connection_rate'])->toBe(50.0);
});

/**
 * Averaged over connected calls only. Including ring-outs would drag the mean
 * towards zero and describe nothing that actually happened.
 */
it('averages talk time over connected calls only', function (): void {
    makeCall(['talk_duration_seconds' => 100]);
    makeCall(['talk_duration_seconds' => 200]);
    makeCall(['call_status' => 'missed', 'is_connected' => false, 'talk_duration_seconds' => 0]);

    expect($this->analytics->summary(now()->subWeek(), now())['average_duration_seconds'])->toBe(150);
});

it('reports the engagement figures the action engine consumes', function (): void {
    makeCall(['started_at' => now()->subDays(10), 'direction' => 'outgoing']);
    makeCall(['started_at' => now()->subDays(3), 'direction' => 'incoming']);

    $stats = $this->analytics->forUser($this->patient);

    expect($stats['total_calls'])->toBe(2)
        ->and($stats['incoming_calls'])->toBe(1)
        ->and($stats['outgoing_calls'])->toBe(1)
        ->and($stats['connected_calls'])->toBe(2)
        ->and($stats['last_call_at'])->not->toBeNull()
        ->and($stats['days_since_last_call'])->toBeLessThanOrEqual(3);
});

/**
 * Distinct from days_since_last_call and more useful: three unanswered attempts
 * are not contact, and a rule that treats them as contact stops chasing a
 * patient who was never actually reached.
 */
it('counts unanswered attempts in a row', function (): void {
    makeCall(['started_at' => now()->subDays(5)]);
    makeCall(['started_at' => now()->subDays(3), 'call_status' => 'no_answer', 'is_connected' => false]);
    makeCall(['started_at' => now()->subDays(2), 'call_status' => 'no_answer', 'is_connected' => false]);
    makeCall(['started_at' => now()->subDay(), 'call_status' => 'missed', 'is_connected' => false]);

    $stats = $this->analytics->forUser($this->patient);

    expect($stats['consecutive_unanswered'])->toBe(3)
        ->and($stats['last_connected_call_at'])->not->toBeNull()
        ->and($stats['days_since_last_conversation'])->toBeGreaterThanOrEqual(4);
});

/**
 * A call that arrived before the CRM knew who the caller was is still their
 * call. A history that silently omitted those would be worse than none,
 * because staff would trust it.
 */
it('includes calls matched only by phone number in a patient history', function (): void {
    makeCall(['customer_user_id' => null, 'matching_status' => 'unmatched']);

    $stats = $this->analytics->forUser($this->patient);

    expect($stats['total_calls'])->toBe(1);
});

it('reports zeroes rather than failing for someone with no calls', function (): void {
    $stats = $this->analytics->forUser($this->patient);

    expect($stats['total_calls'])->toBe(0)
        ->and($stats['last_call_at'])->toBeNull()
        ->and($stats['days_since_last_call'])->toBeNull()
        ->and($stats['consecutive_unanswered'])->toBe(0);
});
