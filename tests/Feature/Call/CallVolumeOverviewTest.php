<?php

declare(strict_types=1);

use App\Filament\Widgets\CallVolumeOverview;
use App\Models\{Call, Clinic, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The phone at four lengths: today, yesterday, this month, and all of it.
 *
 * Sits above the thirty-day summary and answers a different question: that one
 * says how the line is performing, these say whether the phone is busier than
 * it was yesterday — which a rolling window structurally cannot tell you.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function volumeCall(string $direction, \Carbon\Carbon $startedAt, array $attributes = []): Call
{
    return Call::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => test()->clinic->getKey(),
        'provider' => 'callyzer',
        'provider_call_id' => 'vol-' . Str::random(8),
        'source' => 'webhook',
        'direction' => $direction,
        'call_status' => 'completed',
        'started_at' => $startedAt,
    ], $attributes));
}

/**
 * Every card as {label => [value, description, html]}.
 *
 * @return array<string, array{value: string, description: string, html: string}>
 */
function volumeStats(): array
{
    $widget = new CallVolumeOverview();
    $method = (new ReflectionClass($widget))->getMethod('getStats');
    $method->setAccessible(true);

    $out = [];

    foreach ($method->invoke($widget) as $stat) {
        $reflection = new ReflectionClass($stat);

        $read = function (string $name) use ($reflection, $stat) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);

            return $property->getValue($stat);
        };

        $description = $read('description');

        $html = $description instanceof \Illuminate\Contracts\Support\Htmlable
            ? $description->toHtml()
            : (string) $description;

        $out[$read('label')] = [
            'value' => (string) $read('value'),
            'description' => trim((string) preg_replace('/\s+/', ' ', strip_tags($html))),
            'html' => $html,
        ];
    }

    return $out;
}

it('gives every period its own card', function (): void {
    volumeCall('incoming', now()->startOfDay()->addHours(9), ['is_connected' => true]);
    volumeCall('outgoing', now()->startOfDay()->addHours(10), ['is_connected' => true]);
    volumeCall('incoming', now()->startOfDay()->addHours(11), ['call_status' => 'missed']);
    volumeCall('outgoing', now()->startOfDay()->subDay()->addHours(9), ['is_connected' => true]);

    $stats = volumeStats();

    expect(array_keys($stats))
        ->toBe(['Today', 'Yesterday', 'This month', 'All calls']);

    expect($stats['Today']['value'])->toBe('3')
        ->and($stats['Yesterday']['value'])->toBe('1')
        ->and($stats['This month']['value'])->toBe('4')
        ->and($stats['All calls']['value'])->toBe('4');
});

it('describes every period with the same four figures', function (): void {
    volumeCall('incoming', now()->startOfDay()->addHours(9), ['is_connected' => true]);
    volumeCall('outgoing', now()->startOfDay()->addHours(10), ['is_connected' => true]);
    volumeCall('incoming', now()->startOfDay()->addHours(11), ['call_status' => 'missed']);
    volumeCall('outgoing', now()->startOfDay()->addHours(12));

    expect(volumeStats()['Today']['description'])
        ->toBe('2 incoming 2 outgoing 1 missed 50% reached');
});

/**
 * The three dated cards are windows; the fourth is everything. A call from
 * three months ago belongs in "All calls" and in none of the others.
 */
it('counts older calls only in the standing total', function (): void {
    volumeCall('incoming', now()->startOfDay()->addHours(9));
    volumeCall('incoming', now()->subMonths(3));

    $stats = volumeStats();

    expect($stats['Today']['value'])->toBe('1')
        ->and($stats['This month']['value'])->toBe('1')
        ->and($stats['All calls']['value'])->toBe('2');
});

/**
 * The boundary is midnight, not a rolling 24 hours: a call at 11:50pm belongs
 * to yesterday, so the cards agree with the list beneath them.
 */
it('draws the day boundary at midnight', function (): void {
    volumeCall('incoming', now()->startOfDay()->subMinutes(10));
    volumeCall('incoming', now()->startOfDay()->addMinutes(10));

    $stats = volumeStats();

    expect($stats['Today']['value'])->toBe('1')
        ->and($stats['Yesterday']['value'])->toBe('1');
});

/**
 * The direction vocabulary has four values, not two. A call the provider
 * reported as internal or unknown still happened, so the headline is counted
 * independently — adding the two direction badges would drop it.
 */
it('counts a call that is neither incoming nor outgoing in the total', function (): void {
    volumeCall('incoming', now()->startOfDay()->addHour());
    volumeCall('outgoing', now()->startOfDay()->addHours(2));
    volumeCall('unknown', now()->startOfDay()->addHours(3));
    volumeCall('internal', now()->startOfDay()->addHours(4));

    $stats = volumeStats();

    expect($stats['Today']['value'])->toBe('4')
        ->and($stats['Today']['description'])->toStartWith('1 incoming 1 outgoing');
});

/**
 * A rate over no calls is not zero, it is undefined. A red nought per cent on a
 * quiet morning is an alarm about nothing, so the badge is left off entirely.
 */
it('omits the rate on a period with no calls', function (): void {
    volumeCall('incoming', now()->startOfDay()->subDay()->addHours(9), ['is_connected' => true]);

    $stats = volumeStats();

    expect($stats['Today']['description'])->toBe('0 incoming 0 outgoing 0 missed')
        ->not->toContain('reached');

    // And it is present on the period that did have calls.
    expect($stats['Yesterday']['description'])->toContain('100% reached');
});

/**
 * Green only where there were calls to miss and none was missed. On a day with
 * no calls at all, a green nought claims a success nobody earned.
 */
it('does not call an empty period a clean one', function (): void {
    volumeCall('incoming', now()->startOfDay()->subDay()->addHours(9), ['is_connected' => true]);

    $stats = volumeStats();

    // Today had no calls: nothing on the card is coloured as a success.
    expect($stats['Today']['html'])->not->toContain('fi-color-success');

    // Yesterday had calls and missed none, so "0 missed" earns its green.
    expect($stats['Yesterday']['html'])->toContain('fi-color-success');
});

/**
 * On the first of the month, yesterday belongs to the previous one. Starting
 * the query at the month boundary would leave the yesterday card permanently
 * empty on exactly that day.
 */
it('still counts yesterday on the first of the month', function (): void {
    $this->travelTo(now()->startOfMonth()->addMonth()->addHours(10));

    volumeCall('incoming', now()->startOfDay()->subHours(3));
    volumeCall('outgoing', now()->startOfDay()->addHour());

    $stats = volumeStats();

    expect($stats['Yesterday']['value'])->toBe('1')
        ->and($stats['Today']['value'])->toBe('1')
        // Yesterday was last month, so the month card sees only today.
        ->and($stats['This month']['value'])->toBe('1')
        ->and($stats['All calls']['value'])->toBe('2');
});

it('reports zeroes rather than failing on a clinic with no calls', function (): void {
    $stats = volumeStats();

    foreach (['Today', 'Yesterday', 'This month', 'All calls'] as $card) {
        expect($stats[$card]['value'])->toBe('0')
            ->and($stats[$card]['description'])->toBe('0 incoming 0 outgoing 0 missed');
    }
});

/**
 * The thirty-day card sits directly below and reads the same calls. Two
 * definitions of "missed" or "connected" on one screen would show two numbers
 * that disagree.
 */
it('agrees with the thirty-day card about the same calls', function (): void {
    foreach ([true, true, false] as $index => $connected) {
        volumeCall('outgoing', now()->startOfMonth()->addDays($index)->addHours(9), [
            'is_connected' => $connected,
        ]);
    }

    volumeCall('incoming', now()->startOfMonth()->addHours(10), ['call_status' => 'missed']);

    $summary = app(\App\Services\Call\CallAnalyticsService::class)
        ->summary(now()->startOfMonth(), now());

    $month = volumeStats()['This month'];

    expect($month['value'])->toBe((string) $summary['total_calls'])
        ->and($month['description'])
        ->toContain($summary['incoming_calls'] . ' incoming')
        ->toContain($summary['outgoing_calls'] . ' outgoing')
        ->toContain($summary['missed_calls'] . ' missed')
        ->toContain($summary['connection_rate'] . '% reached');
});

/**
 * Each badge carries an icon: the two direction arrows the call rows use, the
 * missed-call glyph, and signal bars for the rate.
 */
it('draws an icon in every badge', function (): void {
    volumeCall('incoming', now()->startOfDay()->addHours(9), ['is_connected' => true]);

    $stats = volumeStats();

    // Four badges on a period with calls, three on one without.
    expect(substr_count($stats['Today']['html'], '<svg'))->toBe(4)
        ->and(substr_count($stats['Yesterday']['html'], '<svg'))->toBe(3);
});

it('renders the cards as badges on the calls page', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'widget_CallVolumeOverview'] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $admin = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111130', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $admin->assignRole($role);

    volumeCall('incoming', now()->startOfDay()->addHours(9));

    $html = \Livewire\Livewire::actingAs($admin)
        ->test(CallVolumeOverview::class)
        ->html();

    foreach (['Today', 'Yesterday', 'This month', 'All calls', 'incoming', 'outgoing', 'missed'] as $needle) {
        expect($html)->toContain($needle);
    }

    // Real Filament badges, not escaped markup.
    expect($html)->toContain('fi-badge')
        ->not->toContain('&lt;span');
});
