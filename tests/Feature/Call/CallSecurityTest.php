<?php

declare(strict_types=1);

use App\Models\Call;
use App\Models\CallProviderPayload;
use App\Models\CallRecording;
use App\Models\Clinic;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Who can hear a recorded conversation, and who cannot.
 *
 * A call recording is a patient discussing a medical concern. The guarantees
 * under test:
 *
 *  - No anonymous access, ever.
 *  - Viewing a call and hearing it are separate grants. Knowing that a patient
 *    rang at 3pm is roster information; hearing what they said is not.
 *  - Clinic scoping is enforced in the policy, not only in the list query, so a
 *    detail URL cannot be guessed across clinics.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Storage::fake('local');

    $this->clinicA = Clinic::create([
        'name' => 'Clinic A',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    $this->clinicB = Clinic::create([
        'name' => 'Clinic B',
        'address_line1' => '2 Test Street',
        'city' => 'Pune',
        'pincode' => '411001',
    ]);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $this->clinicA->getKey(),
        'provider' => 'exotel',
        'provider_call_id' => 'secure-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'client_phone' => '+919876543210',
        'client_phone_key' => '9876543210',
        'started_at' => now(),
    ]);

    Storage::disk('local')->put('call-recordings/secure.mp3', 'audio-bytes-that-are-long-enough');

    $this->recording = CallRecording::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'exotel',
        'source_url' => 'https://rec.exotel.test/secure.mp3',
        'source_url_hash' => hash('sha256', 'https://rec.exotel.test/secure.mp3'),
        'storage_disk' => 'local',
        'storage_path' => 'call-recordings/secure.mp3',
        'download_status' => 'downloaded',
        'storage_status' => 'stored',
        'mime_type' => 'audio/mpeg',
        'file_size' => 32,
    ]);
});

/**
 * Build a staff user holding exactly the named permissions and nothing else.
 */
function staffWith(array $permissions, ?int $clinicId = null): User
{
    $user = User::create([
        'clinic_id' => $clinicId,
        'first_name' => 'Staff',
        'last_name' => Str::random(6),
        'mobile' => (string) random_int(9000000000, 9999999999),
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $role = Role::firstOrCreate(['name' => 'staff-' . Str::random(6), 'guard_name' => 'web']);

    foreach ($permissions as $permission) {
        $role->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'])
        );
    }

    $user->assignRole($role);

    return $user;
}

// ──────────────── Anonymous access ────────────────

it('never serves a recording to an unauthenticated request', function (): void {
    // Requested as JSON so the assertion is on the 401 itself rather than on
    // where the panel happens to redirect a browser to.
    $this->getJson(route('calls.recordings.stream', ['recording' => $this->recording->getKey()]))
        ->assertUnauthorized();
});

// ──────────────── Viewing is not hearing ────────────────

it('refuses playback to a user who may view calls but not hear them', function (): void {
    $user = staffWith(['ViewAny:Call', 'View:Call'], $this->clinicA->getKey());

    $this->actingAs($user)
        ->get(route('calls.recordings.stream', ['recording' => $this->recording->getKey()]))
        ->assertForbidden();
});

it('serves the audio to a user granted the playback permission', function (): void {
    $user = staffWith(['View:Call', 'PlayRecording:Call'], $this->clinicA->getKey());

    $response = $this->actingAs($user)
        ->get(route('calls.recordings.stream', ['recording' => $this->recording->getKey()]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');

    // Recordings must not sit in a shared cache or a proxy. Asserted on the
    // directives rather than the whole header, which Symfony reorders.
    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('private')->toContain('no-store');
});

// ──────────────── Clinic scoping ────────────────

/**
 * Enforced in the policy rather than only in the list query: a detail URL
 * reached by guessing would otherwise show one clinic's patient conversations
 * to another's staff.
 */
it('refuses playback across clinics', function (): void {
    $user = staffWith(['View:Call', 'PlayRecording:Call'], $this->clinicB->getKey());

    $this->actingAs($user)
        ->get(route('calls.recordings.stream', ['recording' => $this->recording->getKey()]))
        ->assertForbidden();
});

it('refuses to show a call from another clinic', function (): void {
    $user = staffWith(['View:Call'], $this->clinicB->getKey());

    expect($user->can('view', $this->call))->toBeFalse();
});

it('allows a user from the owning clinic to view the call', function (): void {
    $user = staffWith(['View:Call'], $this->clinicA->getKey());

    expect($user->can('view', $this->call))->toBeTrue();
});

// ──────────────── The raw payload is a diagnostic artefact ────────────────

/**
 * The payload holds every phone number involved, in full and unredacted. It is
 * gated on the role rather than a permission somebody might hand out casually.
 */
it('hides raw provider payloads from everyone but a super admin', function (): void {
    $staff = staffWith(['View:Call', 'PlayRecording:Call', 'ViewTranscript:Call'], $this->clinicA->getKey());

    expect($staff->can('viewRawPayload', Call::class))->toBeFalse();

    $admin = staffWith([], $this->clinicA->getKey());
    $admin->assignRole(Role::firstOrCreate([
        'name' => config('project.roles.super_admin'),
        'guard_name' => 'web',
    ]));

    expect($admin->fresh()->can('viewRawPayload', Call::class))->toBeTrue();
});

// ──────────────── Missing audio ────────────────

/**
 * A file removed from disk outside the application leaves the row saying
 * Stored. The player must not offer a link to nothing.
 */
it('returns not found when the stored audio is missing from disk', function (): void {
    Storage::disk('local')->delete('call-recordings/secure.mp3');

    $user = staffWith(['View:Call', 'PlayRecording:Call'], $this->clinicA->getKey());

    $this->actingAs($user)
        ->get(route('calls.recordings.stream', ['recording' => $this->recording->getKey()]))
        ->assertNotFound();
});

// ──────────────── Credentials never reach storage ────────────────

it('strips credential headers before archiving a payload', function (): void {
    $safe = CallProviderPayload::redactHeaders([
        'authorization' => ['Bearer super-secret'],
        'x-exotel-webhook-secret' => ['shhh'],
        'user-agent' => ['Exotel/1.0'],
    ]);

    expect($safe['authorization'])->toBe('[redacted]')
        ->and($safe['x-exotel-webhook-secret'])->toBe('[redacted]')
        // Keeping the useful ones is the point: this is for debugging delivery.
        ->and($safe['user-agent'])->toBe(['Exotel/1.0']);
});

it('strips the webhook secret out of a query-string payload', function (): void {
    $safe = CallProviderPayload::redactPayload([
        'CallSid' => 'abc',
        'token' => 'the-shared-secret',
    ]);

    expect($safe['token'])->toBe('[redacted]')
        ->and($safe['CallSid'])->toBe('abc');
});

/**
 * Some providers embed a signed token in the recording URL, so a URL in a JSON
 * response is a credential leak rather than a convenience.
 */
it('keeps the provider recording URL out of serialised output', function (): void {
    expect($this->recording->toArray())->not->toHaveKey('source_url');
});
