<?php

declare(strict_types=1);

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * One house style for dates, editable without a deploy.
 *
 * The helpers are called per table cell, so the two properties that matter are
 * that a saved setting takes effect immediately, and that a missing or empty
 * setting still yields a usable format rather than an empty string.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Setting memoises values in a static, which RefreshDatabase does not
    // touch, so without this one test's format leaks into the next.
    Setting::flushRuntimeCache();
});

it('falls back to the configured default when nothing is saved', function (): void {
    expect(app_date_format())->toBe(config('display.date_format'))
        ->and(app_datetime_format())->toBe(config('display.datetime_format'))
        ->and(app_time_format())->toBe(config('display.time_format'))
        ->and(app_timezone())->toBe(config('display.timezone'));
});

it('prefers the saved setting over the default', function (): void {
    Setting::setValue('display_datetime_format', 'D, d/m/Y g:ia');

    expect(app_datetime_format())->toBe('D, d/m/Y g:ia');
});

it('picks up a change without a deploy', function (): void {
    Setting::setValue('display_date_format', 'd-m-Y');
    expect(app_date_format())->toBe('d-m-Y');

    Setting::setValue('display_date_format', 'jS F Y');
    expect(app_date_format())->toBe('jS F Y');
});

it('ignores an empty setting rather than rendering nothing', function (): void {
    // A cleared field must not turn every date on the site into a blank cell.
    Setting::setValue('display_datetime_format', '');
    Setting::setValue('display_timezone', '');

    expect(app_datetime_format())->toBe(config('display.datetime_format'))
        ->and(app_timezone())->toBe(config('display.timezone'));
});

it('renders a Meta timestamp in the configured style', function (): void {
    $moment = Carbon::parse('2026-08-22T03:55:21+0530');

    expect($moment->timezone(app_timezone())->format(app_datetime_format()))
        ->toBe('22 Aug 2026, 03:55 AM');

    Setting::setValue('display_datetime_format', 'd/m/Y H:i');

    expect($moment->timezone(app_timezone())->format(app_datetime_format()))
        ->toBe('22/08/2026 03:55');
});

it('converts a UTC value into the configured timezone', function (): void {
    expect(Carbon::parse('2026-08-21T21:25:00Z')->timezone(app_timezone())->format(app_datetime_format()))
        ->toBe('22 Aug 2026, 02:55 AM');
});
