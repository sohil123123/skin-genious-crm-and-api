<?php

declare(strict_types=1);

use App\Models\{Call, CallRecording, Clinic, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Playing a recording from the list and from the detail page.
 *
 * The two use different mechanisms on purpose — one shared audio element behind
 * a play/pause button in the table, native <audio controls> on the detail page
 * where scrubbing matters — so both are worth pinning. What they must agree on
 * is where the audio comes from: the authorised streaming route, never the
 * provider URL, which can carry a signed token.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.env' => 'local']);
    Storage::fake('local');
    config()->set('calls.recording.disk', 'local');

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (config('project.roles') as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach ([
        'ViewAny:Call', 'View:Call', 'Update:Call',
        'PlayRecording:Call', 'ViewTranscript:Call',
    ] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $this->clinic = Clinic::create([
        'name' => 'Jaipur',
        'address_line1' => '1 Test Street',
        'city' => 'Jaipur',
        'pincode' => '302001',
        'is_active' => true,
    ]);

    $this->admin = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'mobile' => '9111111111',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->admin->assignRole($role);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $this->clinic->getKey(),
        'provider' => 'exotel',
        'provider_call_id' => 'player-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'is_connected' => true,
        'client_phone_normalized' => '+919687784381',
        'client_phone_key' => '9687784381',
        'started_at' => now()->subHour(),
        'duration_seconds' => 272,
        'talk_duration_seconds' => 245,
        'has_recording' => true,
        'recording_count' => 1,
    ]);
});

function storeRecording(Call $call, string $path = 'call-recordings/play.mp3'): CallRecording
{
    Storage::disk('local')->put($path, "ID3\x03\x00\x00\x00" . str_repeat("\x00\xFF\xFB\x90", 200));

    return CallRecording::create([
        'call_id' => $call->getKey(),
        'provider' => 'exotel',
        // Unique per recording: call_recordings has a unique key on
        // (call_id, source_url_hash), which is what stops the same audio being
        // attached to a call twice.
        'source_url' => 'https://rec.exotel.test/secret-token/' . basename($path),
        'source_url_hash' => hash('sha256', 'https://rec.exotel.test/secret-token/' . basename($path)),
        'storage_disk' => 'local',
        'storage_path' => $path,
        'download_status' => 'downloaded',
        'storage_status' => 'stored',
        'mime_type' => 'audio/mpeg',
        'extension' => 'mp3',
        'file_size' => 832,
        'duration_seconds' => 245,
    ]);
}

/**
 * Load the deferred table the way the browser does.
 *
 * Authenticates first: the page is policy-gated, and an unauthenticated
 * Livewire mount fails on the snapshot rather than with a useful 403.
 */
function callTableHtml(?User $as = null): string
{
    test()->actingAs($as ?? test()->admin);

    return \Livewire\Livewire::test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();
}

// ──────────────── The table player ────────────────

it('offers a play button on a call whose audio is stored', function (): void {
    $recording = storeRecording($this->call);

    expect(callTableHtml())->toContain('sgc-rec-btn')
        ->toContain('data-sgc-audio')
        ->toContain(route('calls.recordings.stream', ['recording' => $recording->getKey()]))
        // The duration, so a reviewer can tell a 4-second wrong number from a
        // real conversation before pressing anything.
        ->toContain('4:05');
});

/**
 * The provider URL can carry a signed token. It must never reach the browser,
 * where it would leak into history and referrer headers.
 */
it('never puts the provider recording URL in the table', function (): void {
    storeRecording($this->call);

    expect(callTableHtml())->not->toContain('rec.exotel.test')
        ->not->toContain('secret-token');
});

it('says the audio is still pending rather than offering a dead button', function (): void {
    CallRecording::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'exotel',
        'source_url' => 'https://rec.exotel.test/a.mp3',
        'source_url_hash' => hash('sha256', 'pending'),
        'download_status' => 'pending',
        'storage_status' => 'remote_only',
    ]);

    $this->call->refreshPipelineFlags();

    $html = callTableHtml();

    expect($html)->toContain('sgc-rec-empty')
        ->not->toContain('data-sgc-audio');
});

it('shows nothing to play when the call has no recording', function (): void {
    expect(callTableHtml())->not->toContain('data-sgc-audio');
});

// ──────────────── The shared controller ────────────────

/**
 * One audio element for the whole panel: a player per row would hold fifty
 * media elements on a fifty-row page, and starting a second call would leave
 * the first one talking over it.
 */
it('ships the shared audio controller and its styles', function (): void {
    storeRecording($this->call);

    $html = $this->actingAs($this->admin)
        ->get(\App\Filament\Resources\Calls\CallResource::getUrl('index'))
        ->getContent();

    expect($html)->toContain('window.sgCallAudio')
        ->toContain('window.sgCallAudioSolo')
        // Filament runs in SPA mode, so navigation must stop playback.
        ->toContain("livewire:navigating")
        ->toContain('.sgc-rec-btn')
        ->toContain('.sgc-rec-btn.is-playing');
});

// ──────────────── The detail page ────────────────

it('gives the detail page real transport controls, not just play and stop', function (): void {
    $recording = storeRecording($this->call);

    $html = $this->actingAs($this->admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)
        // Our own transport, not the browser's. The native control set is a
        // different widget in every browser, ignores the panel's styling, and
        // puts a download menu on a patient's recorded consultation.
        ->toContain('data-sgc-player')
        ->toContain('data-sgc-toggle')
        ->toContain('data-sgc-rail')
        ->not->toContain('<audio class="sgc-rec-audio" controls')
        // metadata, not auto: enough to show the length without pulling three
        // recordings down the moment the page opens.
        ->toContain('preload="metadata"')
        ->toContain(route('calls.recordings.stream', ['recording' => $recording->getKey()]))
        // Length and size, so it is clear what is about to be played.
        ->toContain('4:05');
});

it('keeps two recordings on one call from talking over each other', function (): void {
    storeRecording($this->call, 'call-recordings/part-1.mp3');
    storeRecording($this->call, 'call-recordings/part-2.mp3');

    $html = $this->actingAs($this->admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect(substr_count($html, 'sgc-rec-audio'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('sgCallAudioSolo(this)')
        // Numbered, so "part 2" is identifiable rather than being a second
        // anonymous player.
        ->toContain('Part 1');
});

/**
 * A file removed from disk outside the application leaves the row saying
 * Stored. The page must not offer a player for nothing.
 */
it('does not offer a player when the stored file is missing', function (): void {
    $recording = storeRecording($this->call);

    Storage::disk('local')->delete($recording->storage_path);

    $html = $this->actingAs($this->admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)->not->toContain('<audio class="sgc-rec-audio"')
        ->toContain('has not been downloaded yet');
});

// ──────────────── Authorisation still holds ────────────────

it('hides the player from a user who may not hear recordings', function (): void {
    storeRecording($this->call);

    $role = Role::firstOrCreate(['name' => 'reception-' . Str::random(5), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call'] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $staff = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Front',
        'last_name' => 'Desk',
        'mobile' => '9222222222',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $staff->assignRole($role);

    $html = $this->actingAs($staff)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    // The class name also appears in the injected stylesheet, which ships on
    // every page. Assert on the element, not the string.
    expect($html)->not->toContain('<audio class="sgc-rec-audio"');
});

// ──────────────── A renamed disk must not break playback ────────────────

/**
 * The bug this pins: `call_recording` was renamed to `call_recordings` in
 * filesystems.php long after the audio was written. Storage::disk() throws on a
 * name it does not know, so every player 500'd — a one-line config change that
 * silently broke a feature.
 */
it('still finds the audio after its disk was renamed', function (): void {
    $recording = storeRecording($this->call);

    // The row remembers a disk that no longer exists.
    $recording->forceFill(['storage_disk' => 'call_recording_old'])->save();

    expect($recording->fresh()->diskName())->toBe('local')
        ->and($recording->fresh()->fileExists())->toBeTrue();
});

it('never throws when the recorded disk is gone', function (): void {
    $recording = storeRecording($this->call);
    $recording->forceFill(['storage_disk' => 'nope', 'storage_path' => 'missing.mp3'])->save();

    // A missing recording, not a broken page.
    expect(fn (): bool => $recording->fresh()->fileExists())->not->toThrow(\Throwable::class)
        ->and($recording->fresh()->fileExists())->toBeFalse();
});

it('repairs stale disk names only when the file is really there', function (): void {
    $present = storeRecording($this->call, 'call-recordings/here.mp3');
    $present->forceFill(['storage_disk' => 'call_recording_old'])->save();

    $absent = storeRecording($this->call, 'call-recordings/gone.mp3');
    $absent->forceFill(['storage_disk' => 'call_recording_old'])->save();
    Storage::disk('local')->delete('call-recordings/gone.mp3');

    $this->artisan('calls:repair-recording-disks', ['--disk' => 'local'])->assertSuccessful();

    expect($present->fresh()->storage_disk)->toBe('local')
        // Left alone on purpose: a row pointing at a file that is not there is
        // a different problem, and relabelling it would bury that.
        ->and($absent->fresh()->storage_disk)->toBe('call_recording_old');
});

it('leaves recordings on a valid disk alone', function (): void {
    $recording = storeRecording($this->call);

    $this->artisan('calls:repair-recording-disks', ['--disk' => 'local'])
        ->expectsOutputToContain('No recordings point at a missing disk')
        ->assertSuccessful();

    expect($recording->fresh()->storage_disk)->toBe('local');
});
