<?php

declare(strict_types=1);

use App\Models\{Clinic, Lead, Permission, Role, User};
use App\Services\Lead\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Which enquiries turned into clients, and the tint that shows it.
 *
 * Derived rather than stored. Lead::matched_user_id looks like the answer and
 * is the opposite one: it is written at import, when the matcher looks for a
 * client who already exists, so it marks the people who were already ours when
 * an ad reached them again.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->service = app(LeadConversionService::class);
});

function convLead(string $phone, \Carbon\Carbon $at): Lead
{
    $lead = Lead::create([
        'clinic_id' => test()->clinic->getKey(),
        'first_name' => 'Lead',
        'full_name' => 'Lead ' . substr($phone, -4),
        'phone' => $phone,
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);

    $lead->forceFill(['created_at' => $at])->save();

    return $lead;
}

function convClient(string $mobile, \Carbon\Carbon $at): User
{
    $user = User::create([
        'clinic_id' => test()->clinic->getKey(), 'first_name' => 'Client', 'last_name' => 'X',
        'mobile' => $mobile, 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $user->assignRole(Role::firstOrCreate(['name' => config('project.roles.client'), 'guard_name' => 'web']));

    $user->forceFill(['created_at' => $at])->save();

    return $user;
}

it('marks a lead whose enquiry produced a client', function (): void {
    $lead = convLead('+919610003186', now()->subDays(5));
    convClient('9610003186', now()->subDays(2));

    expect($this->service->leadBecameClient($lead))->toBeTrue();
});

it('does not mark a lead from somebody who was already a client', function (): void {
    convClient('9610003186', now()->subDays(30));
    $lead = convLead('+919610003186', now()->subDays(5));

    expect($this->service->leadBecameClient($lead))->toBeFalse();
});

/**
 * Somebody who converted, then filled in another ad form, has two rows. Only
 * the enquiry that actually brought them in should take the credit — the later
 * one is a re-enquiry from an existing client.
 */
it('credits the enquiry that produced the client, not the one after it', function (): void {
    $first = convLead('+919610003186', now()->subDays(30));
    convClient('9610003186', now()->subDays(20));
    $second = convLead('+919610003186', now()->subDays(2));

    expect($this->service->leadBecameClient($first))->toBeTrue()
        ->and($this->service->leadBecameClient($second))->toBeFalse();
});

it('leaves a lead who never came in unmarked', function (): void {
    expect($this->service->leadBecameClient(convLead('+919610003186', now()->subDays(5))))->toBeFalse();
});

/**
 * Counted per person. Three ad forms and one visit is one client, not three.
 */
it('counts a repeat enquirer once', function (): void {
    convLead('+919610003186', now()->subDays(30));
    convLead('+919610003186', now()->subDays(20));
    convLead('+919610003186', now()->subDays(10));
    convClient('9610003186', now()->subDays(5));

    expect($this->service->countClientsFromLeads())->toBe(1);
});

/**
 * The stat on the page and the tint in the list read the same source, so they
 * cannot disagree with each other in front of a user.
 */
it('agrees with the number of tinted rows', function (): void {
    convLead('+919610003186', now()->subDays(10));
    convClient('9610003186', now()->subDays(5));

    convLead('+919829000002', now()->subDays(10));

    convClient('9829000003', now()->subDays(30));
    convLead('+919829000003', now()->subDays(10));

    $tinted = Lead::all()->filter(fn (Lead $lead): bool => $this->service->leadBecameClient($lead))->count();

    expect($tinted)->toBe(1)
        ->and($this->service->countClientsFromLeads())->toBe(1);
});

it('draws the tint on the leads table', function (): void {
    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Lead', 'View:Lead'] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $admin = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111116', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $admin->assignRole($role);

    convLead('+919610003186', now()->subDays(10));
    convClient('9610003186', now()->subDays(5));

    $page = $this->actingAs($admin)
        ->get(\App\Filament\Resources\Leads\LeadResource::getUrl('index'))
        ->getContent();

    // The class is on the row, and the stylesheet that gives it meaning
    // reached the page. Without the second half the row renders untinted and
    // nothing fails.
    expect($page)->toContain('fi-row-lead-converted')
        ->toContain('.fi-row-lead-converted');
});

/**
 * The service runs while the Leads table renders. An environment where the
 * client role has never been seeded should mean "no clients matched", not a
 * 500 on the page — Spatie's role() scope throws RoleDoesNotExist there, which
 * took out two unrelated Meta tests before this was changed.
 */
it('survives a database where the client role was never seeded', function (): void {
    Role::where('name', config('project.roles.client'))->delete();

    convLead('+919610003186', now()->subDays(10));

    expect(fn (): int => $this->service->countClientsFromLeads())->not->toThrow(\Throwable::class)
        ->and(app(LeadConversionService::class)->countClientsFromLeads())->toBe(0);
});

// ──────────── The Client column ────────────

it('badges an enquiry that produced a client as Converted', function (): void {
    $lead = convLead('+919610003186', now()->subDays(5));
    convClient('9610003186', now()->subDays(2));

    expect($this->service->statusFor($lead))->toBe(\App\Enums\LeadClientStatus::Converted);
});

/**
 * The only state the column could previously show, and the reason it was
 * misleading: matched_user_id marks exactly this population and nothing else.
 */
it('badges an enquiry from somebody already ours as Existing', function (): void {
    convClient('9610003186', now()->subDays(30));
    $lead = convLead('+919610003186', now()->subDays(5));

    expect($this->service->statusFor($lead))->toBe(\App\Enums\LeadClientStatus::Existing);
});

it('badges nothing for a lead who never came in', function (): void {
    $lead = convLead('+919610003186', now()->subDays(5));

    expect($this->service->statusFor($lead))->toBeNull();
});

/**
 * Somebody who enquired, came in, then filled in a second form reads as
 * Existing on the second row. They converted once, on the enquiry that brought
 * them in, and a later form should not claim the credit again.
 */
it('does not let a repeat enquiry claim the conversion twice', function (): void {
    $first = convLead('+919610003186', now()->subDays(30));
    convClient('9610003186', now()->subDays(20));
    $second = convLead('+919610003186', now()->subDays(2));

    expect($this->service->statusFor($first))->toBe(\App\Enums\LeadClientStatus::Converted)
        ->and($this->service->statusFor($second))->toBe(\App\Enums\LeadClientStatus::Existing);
});

/**
 * The badge links to the client, which matched_user_id cannot supply: it is
 * null on exactly the converted leads.
 */
it('links a converted lead to the client record it produced', function (): void {
    $lead = convLead('+919610003186', now()->subDays(5));
    $client = convClient('9610003186', now()->subDays(2));

    expect($lead->matched_user_id)->toBeNull()
        ->and($this->service->clientFor($lead)['id'])->toBe($client->getKey());
});

it('shows both badges in the leads table', function (): void {
    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Lead', 'View:Lead'] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $admin = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111120', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $admin->assignRole($role);

    convLead('+919610003186', now()->subDays(5));
    convClient('9610003186', now()->subDays(2));

    convClient('9829000055', now()->subDays(40));
    convLead('+919829000055', now()->subDays(5));

    $html = \Livewire\Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\Leads\Pages\ListLeads::class)
        ->call('loadTable')
        ->html();

    expect($html)->toContain('Converted')
        ->toContain('Existing')
        // And the tint is only on the one that converted.
        ->toContain('fi-row-lead-converted');
});
