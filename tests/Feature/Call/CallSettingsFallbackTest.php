<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Call\CallRecordingService;
use App\Services\Call\Providers\Exotel\ExotelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A settings field left empty must fall back to its configured default.
 *
 * The settings screen saves every field on every submit, so an optional field
 * left blank stores an empty string rather than leaving the row absent. That
 * puts getValue()'s default permanently out of reach — harmless for a
 * credential, where "blank" genuinely means "none", but silently destructive
 * for a value with a real default: an empty API host produces "https:///v1/..."
 * and every provider call fails with a URL nobody would think to look at.
 */
uses(RefreshDatabase::class);

beforeEach(fn () => Setting::flushRuntimeCache());

it('falls back to the default when a setting was saved blank', function (): void {
    Setting::setValue('exotel_subdomain', '');
    Setting::flushRuntimeCache();

    expect(Setting::getValue('exotel_subdomain', 'api.exotel.com'))->toBe('')
        ->and(Setting::getConfigured('exotel_subdomain', 'api.exotel.com'))->toBe('api.exotel.com');
});

it('falls back to the default when the setting was never saved', function (): void {
    expect(Setting::getConfigured('exotel_subdomain', 'api.exotel.com'))->toBe('api.exotel.com');
});

it('prefers a real saved value over the default', function (): void {
    Setting::setValue('exotel_subdomain', 'api.in.exotel.com');
    Setting::flushRuntimeCache();

    expect(Setting::getConfigured('exotel_subdomain', 'api.exotel.com'))->toBe('api.in.exotel.com');
});

/**
 * The bug this was found through: a blank subdomain built a malformed host.
 */
it('builds a valid Exotel API URL when the subdomain was saved blank', function (): void {
    Setting::setValue('exotel_subdomain', '');
    Setting::setValue('exotel_account_sid', 'simplimed1');
    Setting::setValue('exotel_api_key', 'key');
    Setting::setValue('exotel_api_token', 'token');
    Setting::flushRuntimeCache();

    $client = app(ExotelClient::class);

    $url = (new ReflectionMethod($client, 'url'))->invoke($client, 'Calls/abc.json');

    expect($url)->toBe('https://api.exotel.com/v1/Accounts/simplimed1/Calls/abc.json')
        ->not->toContain('https:///');
});

it('falls back to the configured recording disk when saved blank', function (): void {
    Setting::setValue('call_recording_disk', '');
    Setting::flushRuntimeCache();

    $service = app(CallRecordingService::class);

    expect((new ReflectionMethod($service, 'disk'))->invoke($service))
        ->toBe(config('calls.recording.disk'));
});
