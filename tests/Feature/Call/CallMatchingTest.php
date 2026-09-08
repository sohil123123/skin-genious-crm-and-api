<?php

declare(strict_types=1);

use App\Enums\Call\CallMatchingMethod;
use App\Enums\Call\CallMatchingStatus;
use App\Models\Call;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\Call\CallIngestionService;
use App\Services\Call\PhoneNumberNormalizer;
use App\Services\Call\Providers\Exotel\ExotelCallMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * Working out who was on the other end of a call.
 *
 * The governing rule under test throughout: a wrong match is worse than no
 * match. Attaching a conversation to the wrong patient writes someone else's
 * medical discussion into their file, and nothing downstream would ever flag
 * it — the call would simply appear in their history looking legitimate. So
 * ambiguity must stop the matcher, not be resolved by picking a favourite.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Queue::fake();

    $this->clinic = Clinic::create([
        'name' => 'Test Clinic',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    $this->ingestion = app(CallIngestionService::class);
    $this->mapper = app(ExotelCallMapper::class);
});

function makePatient(string $mobile, string $first = 'Anita', ?int $clinicId = null): User
{
    return User::create([
        'clinic_id' => $clinicId,
        'first_name' => $first,
        'last_name' => 'Shah',
        'mobile' => $mobile,
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);
}

function incomingFrom(string $number, string $sid = 'sid-match'): ?Call
{
    return test()->ingestion->ingest(test()->mapper->map([
        'CallSid' => $sid,
        'From' => $number,
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => '2026-08-29 10:15:00',
        'DialCallDuration' => '120',
    ]));
}

// ──────────────── The happy path ────────────────

it('attaches a call to the one patient whose number matches', function (): void {
    $patient = makePatient('9876543210', clinicId: $this->clinic->getKey());

    $call = incomingFrom('+919876543210');

    expect($call->customer_user_id)->toBe($patient->getKey())
        ->and($call->matching_status)->toBe(CallMatchingStatus::Matched)
        ->and($call->matching_method)->toBe(CallMatchingMethod::Phone)
        ->and($call->matched_at)->not->toBeNull()
        // The clinic follows the patient, so the call lands where staff can see it.
        ->and($call->clinic_id)->toBe($this->clinic->getKey());
});

/**
 * users.mobile predates any normalisation in this application: the same person
 * exists as "9876543210", "+919876543210" and "0 9876543210" depending on who
 * typed them in and when. Matching only on an exact string would leave years of
 * patients unreachable by their own calls.
 */
it('matches a patient however their number was originally stored', function (string $stored): void {
    $patient = makePatient($stored);

    $call = incomingFrom('+919876543210');

    expect($call->customer_user_id)->toBe($patient->getKey());
})->with([
    'national' => '9876543210',
    'with country code' => '919876543210',
    'e164' => '+919876543210',
    'trunk zero' => '09876543210',
]);

it('falls back to a lead when no patient matches', function (): void {
    $lead = Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'full_name' => 'Priya Desai',
        'phone' => '+919812345678',
        'source' => 'facebook',
        'status' => 'new',
    ]);

    $call = incomingFrom('+919812345678');

    expect($call->lead_id)->toBe($lead->getKey())
        ->and($call->customer_user_id)->toBeNull()
        ->and($call->matching_status)->toBe(CallMatchingStatus::Matched);
});

/**
 * A lead who became a patient is the patient from then on, and the call has to
 * land on the timeline staff actually look at.
 */
it('carries a converted lead through to the patient', function (): void {
    $patient = makePatient('9812345678', 'Priya');

    Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'full_name' => 'Priya Desai',
        'phone' => '+919812345678',
        'matched_user_id' => $patient->getKey(),
        'source' => 'facebook',
        'status' => 'new',
    ]);

    $call = incomingFrom('+919812345678');

    expect($call->customer_user_id)->toBe($patient->getKey());
});

// ──────────────── Refusing to guess ────────────────

it('marks a call ambiguous when the number matches two patients', function (): void {
    makePatient('9876543210', 'Anita');
    makePatient('+919876543210', 'Anita Duplicate');

    $call = incomingFrom('+919876543210');

    expect($call->matching_status)->toBe(CallMatchingStatus::Ambiguous)
        ->and($call->customer_user_id)->toBeNull()
        ->and($call->lead_id)->toBeNull()
        // The candidates are recorded so a human can choose rather than search.
        ->and($call->match_candidates)->toHaveCount(2);
});

it('leaves an unknown caller unmatched without inventing a patient', function (): void {
    $call = incomingFrom('+919999888877');

    expect($call->matching_status)->toBe(CallMatchingStatus::Unmatched)
        ->and($call->customer_user_id)->toBeNull()
        ->and(User::count())->toBe(0)
        ->and(Lead::count())->toBe(0);
});

/**
 * A four-digit short code matches thousands of people and must never attach a
 * call to any of them.
 */
it('does not match on a number too short to identify anyone', function (): void {
    makePatient('9876543210');

    $call = incomingFrom('1234');

    expect($call->matching_status)->toBe(CallMatchingStatus::Unmatched)
        ->and($call->customer_user_id)->toBeNull();
});

// ──────────────── Manual matching is final ────────────────

it('records a manual match and exempts it from automatic matching', function (): void {
    $call = incomingFrom('+919999888877');
    $patient = makePatient('9999888877');

    $this->ingestion->matchManually($call, $patient->getKey(), null, $patient->getKey());

    $call->refresh();

    expect($call->matching_status)->toBe(CallMatchingStatus::ManuallyMatched)
        ->and($call->customer_user_id)->toBe($patient->getKey());

    // A later provider event must not undo the decision, even though the
    // automatic matcher would now find the number ambiguous. (Stored in a
    // different form because users.mobile is unique on the exact string —
    // which is precisely why matching compares on the last ten digits.)
    makePatient('+919999888877', 'Someone Else');

    incomingFrom('+919999888877');

    $call->refresh();

    expect($call->matching_status)->toBe(CallMatchingStatus::ManuallyMatched)
        ->and($call->customer_user_id)->toBe($patient->getKey());
});

// ──────────────── Re-matching as the CRM changes ────────────────

/**
 * A caller who was a stranger on Monday is a patient by Friday, and their
 * earlier call belongs in the history built for them.
 */
it('attaches previously unmatched calls once the person exists', function (): void {
    incomingFrom('+919777666555', 'sid-a');
    incomingFrom('+919777666555', 'sid-b');

    expect(Call::query()->needsMatching()->count())->toBe(2);

    $patient = makePatient('9777666555');

    $matched = $this->ingestion->rematchUnmatched(
        app(PhoneNumberNormalizer::class)->matchKey('9777666555')
    );

    expect($matched)->toBe(2)
        ->and(Call::query()->needsMatching()->count())->toBe(0)
        ->and(Call::query()->where('customer_user_id', $patient->getKey())->count())->toBe(2);
});

// ──────────────── Filing an agent's calls under a clinic ────────────────

/**
 * A Callyzer employee who dials for a branch but holds no CRM login. The clinic
 * on their mapping used to be read only when a staff member was also linked, so
 * setting it alone did nothing and their calls were filed under whichever
 * clinic the customer belonged to - a different question, and often a different
 * answer.
 */
it('files an agent\'s calls under the clinic set on their mapping', function (): void {
    $branch = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    \App\Models\CallProviderAgent::create([
        'provider' => 'callyzer',
        'provider_employee_name' => 'Shivani',
        'provider_employee_number' => '8169308873',
        'provider_employee_key' => '8169308873',
        'user_id' => null,
        'clinic_id' => $branch->getKey(),
        'active' => true,
    ]);

    $call = app(\App\Services\Call\CallIngestionService::class)->ingest(
        app(\App\Services\Call\Providers\Callyzer\CallyzerCallMapper::class)->map([
            'id' => 'agent-clinic-1',
            'emp_name' => 'Shivani',
            'emp_number' => '8169308873',
            'emp_country_code' => '+91',
            'client_number' => '9891353586',
            'client_country_code' => '+91',
            'call_type' => 'Outgoing',
            'duration' => '48',
            'call_date' => '2026-09-02',
            'call_time' => '15:05:00',
        ])
    );

    expect($call->clinic_id)->toBe($branch->getKey())
        // Attributed to the agent identity without inventing a staff member.
        ->and($call->agent_user_id)->toBeNull();
});

/**
 * The mapping states the clinic deliberately, so it must beat the customer's
 * own clinic rather than the other way round.
 */
it('prefers the mapping clinic over the matched customer clinic', function (): void {
    $branch = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $other = \App\Models\Clinic::create([
        'name' => 'Mumbai', 'address_line1' => '2', 'city' => 'M', 'pincode' => '400001', 'is_active' => true,
    ]);

    \App\Models\User::create([
        'clinic_id' => $other->getKey(), 'first_name' => 'Bhawna', 'last_name' => 'Solanki',
        'mobile' => '9891353586', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    \App\Models\CallProviderAgent::create([
        'provider' => 'callyzer',
        'provider_employee_name' => 'Shivani',
        'provider_employee_number' => '8169308873',
        'provider_employee_key' => '8169308873',
        'user_id' => null,
        'clinic_id' => $branch->getKey(),
        'active' => true,
    ]);

    $call = app(\App\Services\Call\CallIngestionService::class)->ingest(
        app(\App\Services\Call\Providers\Callyzer\CallyzerCallMapper::class)->map([
            'id' => 'agent-clinic-2',
            'emp_name' => 'Shivani',
            'emp_number' => '8169308873',
            'emp_country_code' => '+91',
            'client_number' => '9891353586',
            'client_country_code' => '+91',
            'call_type' => 'Outgoing',
            'duration' => '48',
            'call_date' => '2026-09-02',
            'call_time' => '15:05:00',
        ])
    );

    expect($call->clinic_id)->toBe($branch->getKey());
});
