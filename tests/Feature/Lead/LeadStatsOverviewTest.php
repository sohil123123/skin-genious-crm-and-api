<?php

declare(strict_types=1);

use App\Filament\Widgets\LeadStatsOverview;
use App\Models\{Clinic, Lead, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * "Patients from leads" on the Leads page.
 *
 * It replaced "Already patients", which counted Lead::matched_user_id — a field
 * written during import, when the matcher looks for a patient who already
 * exists. That captures the opposite population: people who were patients
 * before an ad reached them again. A lead who enquires and is then created as a
 * patient never gets it set, because at import time there was no patient to
 * match, so the stat that mattered commercially was invisible.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->clientRole = Role::firstOrCreate([
        'name' => config('project.roles.client'), 'guard_name' => 'web',
    ]);
});

function statLead(string $phone, ?\Carbon\Carbon $at = null): Lead
{
    $lead = Lead::create([
        'clinic_id' => test()->clinic->getKey(),
        'first_name' => 'Lead',
        'phone' => $phone,
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);

    if ($at !== null) {
        $lead->forceFill(['created_at' => $at])->save();
    }

    return $lead;
}

function statPatient(string $mobile, ?\Carbon\Carbon $at = null): User
{
    $user = User::create([
        'clinic_id' => test()->clinic->getKey(), 'first_name' => 'P', 'last_name' => 'X',
        'mobile' => $mobile, 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $user->assignRole(test()->clientRole);

    if ($at !== null) {
        $user->forceFill(['created_at' => $at])->save();
    }

    return $user;
}

function convertedCount(): int
{
    $widget = new LeadStatsOverview();
    $method = (new ReflectionClass($widget))->getMethod('patientsCreatedFromLeads');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

/**
 * The number stored differently on each side is the same number: leads keep
 * "+919610003186", patients keep "9610003186".
 */
it('counts a patient created after their enquiry, across the two phone formats', function (): void {
    statLead('+919610003186', now()->subDays(5));
    statPatient('9610003186', now()->subDays(2));

    expect(convertedCount())->toBe(1);
});

/**
 * Somebody who was already a patient when the ad reached them is not a
 * conversion — that is the population the old stat counted.
 */
it('does not count a patient who existed before the enquiry', function (): void {
    statPatient('9610003186', now()->subDays(30));
    statLead('+919610003186', now()->subDays(5));

    expect(convertedCount())->toBe(0);
});

it('ignores a lead who never became a patient', function (): void {
    statLead('+919610003186', now()->subDays(5));

    expect(convertedCount())->toBe(0);
});

it('ignores a patient whose number matches no enquiry', function (): void {
    statLead('+919610003186', now()->subDays(5));
    statPatient('9829000099', now()->subDays(2));

    expect(convertedCount())->toBe(0);
});

/**
 * Somebody who filled in two ad forms is judged against the first. Measuring
 * against the second would make anybody who re-enquired after becoming a
 * patient look like a fresh conversion.
 */
it('judges a repeat enquirer against their first enquiry', function (): void {
    statLead('+919610003186', now()->subDays(30));
    statPatient('9610003186', now()->subDays(20));
    statLead('+919610003186', now()->subDays(2));

    expect(convertedCount())->toBe(1);
});

/**
 * Staff are users too. Counting them would inflate the figure with whoever
 * happens to share a number with a lead.
 */
it('counts only patients, not staff', function (): void {
    statLead('+919610003186', now()->subDays(5));

    $staff = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Therapist', 'last_name' => 'X',
        'mobile' => '9610003186', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $staff->assignRole(Role::firstOrCreate([
        'name' => config('project.roles.therapist'), 'guard_name' => 'web',
    ]));

    $staff->forceFill(['created_at' => now()->subDays(2)])->save();

    expect(convertedCount())->toBe(0);
});

it('reports nothing rather than dividing by zero when there are no leads', function (): void {
    $widget = new LeadStatsOverview();
    $method = (new ReflectionClass($widget))->getMethod('getStats');
    $method->setAccessible(true);

    expect($method->invoke($widget))->toHaveCount(2);
});

// ──────────── The three periods on the Total leads card ────────────

function statsDescriptionHtml(int $index = 0): string
{
    $widget = new LeadStatsOverview();
    $method = (new ReflectionClass($widget))->getMethod('getStats');
    $method->setAccessible(true);

    $stat = $method->invoke($widget)[$index];
    $property = (new ReflectionClass($stat))->getProperty('description');
    $property->setAccessible(true);

    $description = $property->getValue($stat);

    return $description instanceof \Illuminate\Contracts\Support\Htmlable
        ? $description->toHtml()
        : (string) $description;
}

/**
 * The badge text alone, with the markup and its whitespace collapsed away.
 */
function statsDescription(int $index = 0): string
{
    return trim((string) preg_replace('/\s+/', ' ', strip_tags(statsDescriptionHtml($index))));
}

/**
 * Replaced "48 added in the last 7 days", which cannot answer the question
 * anybody actually asks in the morning: did the ads bring anything in today,
 * and is that better or worse than yesterday.
 */
it('counts today, yesterday and this month separately', function (): void {
    statLead('+919000000001', now()->startOfDay()->addHours(9));
    statLead('+919000000002', now()->startOfDay()->addHours(14));
    statLead('+919000000003', now()->startOfDay()->subHours(3));
    statLead('+919000000004', now()->startOfMonth()->addDay());

    expect(statsDescription())->toBe('2 today 1 yesterday 4 this month');
});

/**
 * The boundary is midnight, not a rolling 24 hours. A lead that arrived at
 * 11:50pm last night belongs to yesterday, and counting it as "today" would
 * make the morning figure disagree with the list underneath it.
 */
it('draws the day boundary at midnight', function (): void {
    statLead('+919000000005', now()->startOfDay()->subMinutes(10));
    statLead('+919000000006', now()->startOfDay()->addMinutes(10));

    expect(statsDescription())->toStartWith('1 today 1 yesterday');
});

it('counts nothing rather than failing on a clinic with no leads', function (): void {
    expect(statsDescription())->toBe('0 today 0 yesterday 0 this month');
});

/**
 * Filament prints a stat description with {{ }}, which escapes a plain string
 * and leaves an Htmlable alone. Returning a string here would put the literal
 * markup on screen, so the type is the feature.
 */
it('renders the periods as badges rather than escaped markup', function (): void {
    statLead('+919000000007', now()->startOfDay()->addHour());

    $html = statsDescriptionHtml();

    expect($html)->toContain('fi-badge')
        ->not->toContain('&lt;');
});

/**
 * A green zero reads as "all good" on the morning the ads stopped delivering,
 * which is the one morning this card has to be read correctly.
 */
it('does not colour today green when nothing came in', function (): void {
    statLead('+919000000008', now()->startOfDay()->subHours(2));

    expect(statsDescription())->toStartWith('0 today')
        ->and(statsDescriptionHtml())->not->toContain('fi-color-success');
});
