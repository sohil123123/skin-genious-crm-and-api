<?php

declare(strict_types=1);

use App\Models\{Call, Clinic, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The diagnosis has to be trustworthy in the one situation it exists for:
 * somebody on a production server, with a 403 and no other information. A
 * diagnostic that reports "looks correct" while the thing is broken is worse
 * than no diagnostic, because it sends the search somewhere else entirely.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // phpunit.xml runs on CACHE_STORE=array, which this command rightly
    // reports as unusable. Every test except the cache one is about a
    // different check, so give them a store that does not trip it.
    config(['cache.default' => 'file']);

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->admin = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111111', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->admin->assignRole(Role::firstOrCreate([
        'name' => config('project.roles.super_admin'), 'guard_name' => 'web',
    ]));
});

it('passes when the permission is granted', function (): void {
    Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'web']));

    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('can PlayRecording:Call: yes')
        ->assertSuccessful();
});

it('fails and names the missing permission', function (): void {
    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

/**
 * The production case: the permission exists and a role holds it, but not the
 * role this user has. Reporting only "no" would leave them guessing; the
 * command names who does hold it.
 */
it('names the role that holds a permission the user lacks', function (): void {
    Role::firstOrCreate(['name' => config('project.roles.therapist'), 'guard_name' => 'web'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'web']));

    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('the permission is held by')
        ->assertFailed();
});

/**
 * A permission created under a guard the app does not use is invisible to
 * can(), with no error anywhere — exactly the kind of thing nobody finds by
 * reading logs.
 */
it('catches a permission created under the wrong guard', function (): void {
    Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'api']);
    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('does not match the app guard')
        ->assertFailed();
});

it('warns when the permission cache cannot reach the web process', function (): void {
    // The classic: a per-process store, so a CLI grant never reaches php-fpm.
    config(['cache.default' => 'array', 'permission.cache.store' => 'default']);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('private to each PHP process')
        ->assertFailed();
});

it('separates missing audio from a refused one', function (): void {
    Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'web']));

    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    $call = Call::create([
        'uuid' => (string) Str::uuid(), 'clinic_id' => $this->clinic->getKey(),
        'provider' => 'exotel', 'provider_call_id' => 'diag-1', 'source' => 'webhook',
        'direction' => 'incoming', 'call_status' => 'completed', 'started_at' => now(),
    ]);

    \App\Models\CallRecording::create([
        'call_id' => $call->getKey(), 'provider' => 'exotel',
        'storage_status' => 'stored', 'storage_disk' => 'call_recordings',
        'storage_path' => 'calls/never-downloaded.mp3',
    ]);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('404, not 403')
        ->assertSuccessful();
});

/**
 * A recording whose call has been deleted cannot be played by anybody.
 * Reporting it as MISSING alongside audio that merely failed to download sends
 * the reader after a worker that was never the problem.
 *
 * The foreign key cascades, so a genuinely parentless row is close to
 * impossible; in practice the call is in the trash, and Call::count() does not
 * see it — which is why a server can show more recordings than calls.
 */
it('tells a recording on a deleted call apart from one that failed to download', function (): void {
    Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'web']));

    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    $call = Call::create([
        'uuid' => (string) Str::uuid(), 'clinic_id' => $this->clinic->getKey(),
        'provider' => 'exotel', 'provider_call_id' => 'orphan-1', 'source' => 'webhook',
        'direction' => 'incoming', 'call_status' => 'completed', 'started_at' => now(),
    ]);

    \App\Models\CallRecording::create([
        'call_id' => $call->getKey(), 'provider' => 'exotel',
        'storage_status' => 'remote_only', 'storage_path' => 'calls/orphan.mp3',
    ]);

    // Soft-deleted, so the row survives for the foreign key while dropping out
    // of every ordinary query.
    $call->delete();

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('its call is in the trash')
        ->expectsOutputToContain('belong to deleted calls')
        ->assertSuccessful();
});

/**
 * The check that catches a deployment rather than a configuration.
 *
 * Gate denies an ability it cannot resolve, silently and identically to a
 * policy that considered the request and said no. A server whose CallPolicy is
 * missing — or predates playRecording — refuses every play while the permission
 * is correctly granted, which is a combination no amount of reading roles will
 * explain.
 */
it('reports the policy the gate will consult', function (): void {
    Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'web']));

    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('Policy for Call')
        ->expectsOutputToContain('Policy method playRecording()')
        ->assertSuccessful();
});

it('fails loudly when no policy is registered for Call', function (): void {
    Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web'])
        ->givePermissionTo(Permission::firstOrCreate(['name' => 'PlayRecording:Call', 'guard_name' => 'web']));

    Permission::firstOrCreate(['name' => 'ViewTranscript:Call', 'guard_name' => 'web']);

    // Exactly what a server with the file missing looks like to the gate.
    \Illuminate\Support\Facades\Gate::guessPolicyNamesUsing(fn (): array => []);

    $this->artisan('calls:diagnose-playback')
        ->expectsOutputToContain('No policy is registered')
        ->assertFailed();
});
