<?php

declare(strict_types=1);

use App\Models\{Call, Setting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * "Refresh from provider" has three silent endings — no credentials, a 404 from
 * Exotel, and a response with nothing worth merging — and they all reach the
 * screen as the same shrug. This command exists to tell them apart, so it has
 * to name each one correctly or it just moves the guessing.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();

    Setting::setValue('exotel_account_sid', 'simplimed1');
    Setting::setValue('exotel_api_key', 'key-123');
    Setting::setValue('exotel_api_token', 'token-456');
    Setting::setValue('exotel_subdomain', 'api.exotel.com');
    Setting::flushRuntimeCache();

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'sid-abc',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'started_at' => now(),
    ]);
});

it('stops when credentials are incomplete', function (): void {
    Setting::setValue('exotel_api_token', '');
    Setting::flushRuntimeCache();

    $this->artisan('calls:check-exotel')
        ->expectsOutputToContain('Credentials are incomplete')
        ->assertFailed();
});

it('never prints the API token', function (): void {
    Http::fake(['*' => Http::response(['Call' => ['Sid' => 'sid-abc']], 200)]);

    $this->artisan('calls:check-exotel')
        ->doesntExpectOutputToContain('token-456')
        ->expectsOutputToContain('set (9 characters)')
        ->assertSuccessful();
});

/**
 * The wrong region answers 404 for every call id, which reads as "the call does
 * not exist" and sends people looking at their data instead of their subdomain.
 */
it('names the region when Exotel answers 404', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    $this->artisan('calls:check-exotel')
        ->expectsOutputToContain('no call with that id')
        ->expectsOutputToContain('api.in.exotel.com')
        ->assertFailed();
});

it('separates rejected credentials from a missing call', function (): void {
    Http::fake(['*' => Http::response('', 401)]);

    $this->artisan('calls:check-exotel')
        ->expectsOutputToContain('rejected the credentials')
        ->assertFailed();
});

it('reports a recording that a refresh will attach', function (): void {
    Http::fake(['*' => Http::response(['Call' => [
        'Sid' => 'sid-abc',
        'Status' => 'completed',
        'Duration' => '257',
        'RecordingUrl' => 'https://recordings.exotel.com/sid-abc.mp3',
    ]], 200)]);

    $this->artisan('calls:check-exotel')
        ->expectsOutputToContain('will attach that recording')
        ->assertSuccessful();
});

/**
 * The case the clinic actually hit: Exotel knows the call but has published no
 * audio for it, so no amount of refreshing will produce any.
 */
it('says when Exotel has the call but no recording', function (): void {
    Http::fake(['*' => Http::response(['Call' => [
        'Sid' => 'sid-abc',
        'Status' => 'completed',
        'Duration' => '0',
    ]], 200)]);

    $this->artisan('calls:check-exotel')
        ->expectsOutputToContain('No RecordingUrl on this call')
        ->assertSuccessful();
});
