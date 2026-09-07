<?php

declare(strict_types=1);

use App\Models\{Permission, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The call feature's permissions exist and reach the super admin.
 *
 * Worth its own test because the failure is silent. Shield's generator creates
 * a resource's CRUD abilities but not PlayRecording:Call or ViewTranscript:Call
 * — those are added by hand — and when one is missing nothing errors: the list
 * renders, the row draws, the play button appears, and only the request for the
 * audio comes back 403. Which is indistinguishable from a broken player, and is
 * exactly what happened here.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Role::firstOrCreate([
        'name' => config('project.roles.super_admin'),
        'guard_name' => 'web',
    ]);
});

it('creates the call permissions when none exist', function (): void {
    expect(Permission::where('name', 'like', '%:Call')->count())->toBe(0);

    $this->artisan('calls:grant-permissions')->assertSuccessful();

    foreach (['ViewAny:Call', 'View:Call', 'PlayRecording:Call', 'ViewTranscript:Call'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
});

/**
 * The two the generator never makes, and the ones that were missing in
 * production while everything else looked fine.
 */
it('grants hearing and reading a call to the super admin', function (): void {
    $this->artisan('calls:grant-permissions')->assertSuccessful();

    $role = Role::where('name', config('project.roles.super_admin'))->first();
    $held = $role->permissions()->pluck('name')->all();

    expect($held)->toContain('PlayRecording:Call')
        ->toContain('ViewTranscript:Call')
        ->toContain('ViewAny:CallProviderAgent');
});

it('can be run repeatedly without duplicating anything', function (): void {
    $this->artisan('calls:grant-permissions')->assertSuccessful();
    $first = Permission::count();

    $this->artisan('calls:grant-permissions')
        ->expectsOutputToContain('already holds every call permission')
        ->assertSuccessful();

    expect(Permission::count())->toBe($first);
});

it('changes nothing on a dry run', function (): void {
    $this->artisan('calls:grant-permissions', ['--dry-run' => true])->assertSuccessful();

    expect(Permission::where('name', 'PlayRecording:Call')->exists())->toBeFalse();
});

/**
 * The raw provider payload is gated on the role itself, never on a permission —
 * it holds every phone number involved, unredacted — so it must not appear in
 * the grantable set.
 */
it('does not create a permission for the raw payload', function (): void {
    $this->artisan('calls:grant-permissions')->assertSuccessful();

    expect(Permission::where('name', 'like', '%RawPayload%')->exists())->toBeFalse();
});
