<?php

declare(strict_types=1);

use App\Models\LeadFieldValue;

/**
 * How a dynamic form answer is rendered on the lead detail screen.
 *
 * Meta returns scheduling answers as ISO-8601, which is correct but unreadable.
 * The risk in formatting them is over-reach: a budget of "5999" or a year
 * "2026" must never be mistaken for a date, so the non-date cases are asserted
 * as carefully as the date ones.
 *
 * No database is involved — display_values is pure model behaviour.
 */
function answer(?string $value, ?array $json = null, ?string $normalized = null): LeadFieldValue
{
    return LeadFieldValue::make([
        'value' => $value,
        'value_json' => $json,
        'value_normalized' => $normalized ?? $value,
    ]);
}

it('renders a Meta scheduling answer in clinic-local form', function (): void {
    // The exact value Meta sent for "which session are you interested in?".
    expect(answer('2026-08-22T03:55:21+0530')->display_values)
        ->toBe(['22 Aug 2026, 03:55 AM']);
});

it('accepts the offset with or without a colon', function (): void {
    expect(answer('2026-08-22T03:55:21+05:30')->display_values)
        ->toBe(['22 Aug 2026, 03:55 AM']);
});

it('converts a UTC answer into clinic local time', function (): void {
    // 21:25 UTC is 02:55 the next morning in Asia/Kolkata.
    expect(answer('2026-08-21T21:25:00Z')->display_values)
        ->toBe(['22 Aug 2026, 02:55 AM']);
});

it('shows a date-only answer without inventing a midnight time', function (): void {
    expect(answer('2026-08-22')->display_values)->toBe(['22 Aug 2026']);
});

it('renders the written shape Meta also sends', function (string $stored, string $expected) {
    // Meta does not settle on one shape. The same scheduling question arrives
    // as ISO-8601 from some forms and as prose from others, and both must read
    // identically on the lead.
    expect(answer($stored)->display_values)->toBe([$expected]);
})->with([
    ['Aug 23, 2026 at 2:00 PM IST', '23 Aug 2026, 02:00 PM'],
    ['Aug 22, 2026 at 12:15 PM IST', '22 Aug 2026, 12:15 PM'],
    ['Aug 11, 2026 at 12:05 AM IST', '11 Aug 2026, 12:05 AM'],
    ['Aug 9, 2026 at 5:40 PM IST', '09 Aug 2026, 05:40 PM'],
    // The same instant, written as an offset instead of an abbreviation.
    ['Aug 12, 2026 at 2:05 PM GMT+5:30', '12 Aug 2026, 02:05 PM'],
    ['Aug 14, 2026 at 3:45 PM GMT+5:30', '14 Aug 2026, 03:45 PM'],
]);

it('reads IST as the clinic timezone, not Israel', function (): void {
    // PHP resolves the abbreviation "IST" to Israel Standard Time, which would
    // drag every Indian appointment back by three and a half hours.
    expect(answer('Aug 23, 2026 at 2:00 PM IST')->display_values)
        ->toBe(['23 Aug 2026, 02:00 PM']);
});

it('honours a timezone that is genuinely not the clinic zone', function (): void {
    // 2:00 PM UTC is 7:30 PM in Asia/Kolkata.
    expect(answer('Aug 23, 2026 at 2:00 PM UTC')->display_values)
        ->toBe(['23 Aug 2026, 07:30 PM']);
});

it('assumes clinic time when the answer names no timezone', function (): void {
    expect(answer('Aug 23, 2026 at 2:00 PM')->display_values)
        ->toBe(['23 Aug 2026, 02:00 PM']);
});

it('does not treat prose that merely starts with a month as a date', function (): void {
    expect(answer('March')->display_values)->toBe(['March'])
        ->and(answer('May be later')->display_values)->toBe(['May be later'])
        ->and(answer('Aug is fine')->display_values)->toBe(['Aug is fine']);
});

it('leaves answers that merely look numeric alone', function (): void {
    // A loose date parser reads "5999" and "2026" as years. Neither is a date.
    expect(answer('5999')->display_values)->toBe(['5999'])
        ->and(answer('2026')->display_values)->toBe(['2026'])
        ->and(answer('28')->display_values)->toBe(['28']);
});

it('leaves ordinary text answers alone', function (): void {
    expect(answer('Evening')->display_values)->toBe(['Evening'])
        ->and(answer('+918619169041')->display_values)->toBe(['+918619169041']);
});

it('keeps using the normalised value for non-date answers', function (): void {
    // Humanising still happens upstream; this must not bypass it.
    expect(answer('dullness_/_tanning', normalized: 'Dullness / tanning')->display_values)
        ->toBe(['Dullness / tanning']);
});

it('formats each choice of a multi-answer question', function (): void {
    expect(answer('a|b', json: ['2026-08-22T03:55:21+0530', 'acne'])->display_values)
        ->toBe(['22 Aug 2026, 03:55 AM', 'Acne']);
});

it('renders an empty answer as no values at all', function (): void {
    expect(answer('')->display_values)->toBe([]);
});
