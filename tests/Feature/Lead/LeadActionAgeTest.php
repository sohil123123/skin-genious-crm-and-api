<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadCustomField;
use App\Models\LeadFieldValue;
use App\Services\Lead\LeadActionService;
use Carbon\Carbon;

/**
 * How the lead action engine reads a lead's age and stated visit date.
 *
 * Both were silently dropping leads out of the morning queue:
 *
 *   - Age was a rounded fractional day diff, so whether a lead counted as one
 *     day old depended on the clock time it arrived at. Anything submitted
 *     after roughly midday rounded to zero and was then discarded by the
 *     "skip same-day leads" guard, which is why four of five leads imported on
 *     the same afternoon produced no action the next morning.
 *   - Visit intent was matched only against Meta's multiple-choice slugs, so an
 *     appointment-request form — the only kind this account runs — never once
 *     triggered the hot lane.
 *
 * No database is involved; both are pure functions of the lead's own values.
 */
function leadWith(string $submittedAt, ?string $visitAnswer = null): Lead
{
    $lead = Lead::make(['fb_created_time' => $submittedAt]);

    $field = LeadCustomField::make(['key' => 'when_would_you_like_to_visit']);

    $values = $visitAnswer === null
        ? collect()
        : collect([
            tap(LeadFieldValue::make(['value' => $visitAnswer]), fn ($v) => $v->setRelation('customField', $field)),
        ]);

    return $lead->setRelation('fieldValues', $values);
}

function callOnService(string $method, mixed ...$args): mixed
{
    $service = app(LeadActionService::class);
    $reflected = new ReflectionMethod($service, $method);
    $reflected->setAccessible(true);

    return $reflected->invoke($service, ...$args);
}

beforeEach(function (): void {
    // The exact morning the queue went wrong.
    Carbon::setTestNow(Carbon::parse('2026-09-07 07:10:00', 'Asia/Kolkata'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('ages a lead by calendar day, not by the hour it happened to arrive', function (string $submittedAt): void {
    expect(callOnService('ageInDays', leadWith($submittedAt)))->toBe(1);
})->with([
    'yesterday morning' => '2026-09-06 10:51:00',
    'yesterday afternoon' => '2026-09-06 15:32:00',
    'yesterday evening' => '2026-09-06 17:01:00',
    'yesterday just before midnight' => '2026-09-06 23:59:00',
]);

it('treats an enquiry from earlier the same day as zero days old', function (): void {
    expect(callOnService('ageInDays', leadWith('2026-09-07 02:48:00')))->toBe(0);
});

it('never reports a negative age for a future-dated import', function (): void {
    expect(callOnService('ageInDays', leadWith('2026-09-09 09:00:00')))->toBe(0);
});

it('reads a date-valued visit answer as declared intent', function (string $answer, bool $hot, string $phrase): void {
    $intent = callOnService('visitIntent', leadWith('2026-09-06 15:32:00', $answer));

    expect($intent)->not->toBeNull()
        ->and($intent['hot'])->toBe($hot)
        ->and($intent['phrase'])->toBe($phrase);
})->with([
    'later today' => ['2026-09-07T18:00:00+0530', true, 'today'],
    'tomorrow, written shape' => ['Sep 8, 2026 at 11:25 AM IST', true, 'tomorrow'],
    'later this week' => ['2026-09-11T14:00:00+0530', true, 'on Friday 11 Sep'],
    'a fortnight out' => ['2026-09-18T14:00:00+0530', false, 'on 18 Sep'],
    'a slot already missed' => ['2026-09-03T14:00:40+0530', false, 'on 03 Sep'],
]);

it('still understands the multiple-choice answers other forms send', function (): void {
    $intent = callOnService('visitIntent', leadWith('2026-09-06 15:32:00', 'today_/_tomorrow'));

    expect($intent['hot'])->toBeTrue()
        ->and($intent['weight'])->toBe(0.95);
});

it('reports no intent when the answer is not about timing at all', function (?string $answer): void {
    expect(callOnService('visitIntent', leadWith('2026-09-06 15:32:00', $answer)))->toBeNull();
})->with([
    'unanswered' => null,
    'a budget' => '5999',
    'a year' => '2026',
    'a concern' => 'dullness_/_tanning',
]);
