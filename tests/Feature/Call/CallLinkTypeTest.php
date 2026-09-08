<?php

declare(strict_types=1);

use App\Enums\Call\CallLinkType;
use App\Models\{Call, Clinic, Lead, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Telling a patient's call apart from a lead's, in the list.
 *
 * The name alone was ambiguous: a patient has a history, a package and a
 * diary; a lead has a sales conversation and none of those. They are opened in
 * different resources by different people, so a list showing only a name asked
 * everybody to remember which of the two each caller was.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (config('project.roles') as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call'] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->admin = User::create([
        'clinic_id' => $this->clinic->id, 'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111114', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->admin->assignRole($role);

    $this->patient = User::create([
        'clinic_id' => $this->clinic->id, 'first_name' => 'Anita', 'last_name' => 'Shah',
        'mobile' => '9687784381', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->lead = Lead::create([
        'clinic_id' => $this->clinic->id,
        'first_name' => 'Sohil',
        'full_name' => 'Sohil Mansuri',
        'phone' => '9829000002',
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function linkedCall(array $attributes = []): Call
{
    return Call::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => test()->clinic->id,
        'provider' => 'callyzer',
        'provider_call_id' => 'link-' . Str::random(8),
        'source' => 'webhook',
        'direction' => 'outgoing',
        'call_status' => 'completed',
        'matching_status' => 'matched',
        'started_at' => now()->subHour(),
    ], $attributes));
}

// ──────────────── The classification itself ────────────────

it('classifies a call by which record it is attached to', function (): void {
    expect(linkedCall(['customer_user_id' => $this->patient->id])->link_type)->toBe(CallLinkType::Patient)
        ->and(linkedCall(['lead_id' => $this->lead->id])->link_type)->toBe(CallLinkType::Lead)
        ->and(linkedCall(['matching_status' => 'unmatched'])->link_type)->toBe(CallLinkType::None);
});

/**
 * A converted lead keeps its old calls, and both columns end up set. The call
 * is history the patient now owns — filing it under the lead they used to be
 * would hide it from the record anyone actually opens.
 */
it('treats a converted lead as a patient', function (): void {
    $call = linkedCall([
        'customer_user_id' => $this->patient->id,
        'lead_id' => $this->lead->id,
    ]);

    expect($call->link_type)->toBe(CallLinkType::Patient);
});

// ──────────────── The filter ────────────────

it('filters to patient calls, lead calls, or neither', function (): void {
    $patientCall = linkedCall(['customer_user_id' => $this->patient->id]);
    $leadCall = linkedCall(['lead_id' => $this->lead->id]);
    $orphan = linkedCall(['matching_status' => 'unmatched']);

    expect(Call::linkedTo(CallLinkType::Patient)->pluck('id')->all())->toBe([$patientCall->id])
        ->and(Call::linkedTo(CallLinkType::Lead)->pluck('id')->all())->toBe([$leadCall->id])
        ->and(Call::linkedTo(CallLinkType::None)->pluck('id')->all())->toBe([$orphan->id]);
});

/**
 * The SQL and the accessor must agree, or the filter shows a row whose badge
 * says something else. A converted lead is the case where they could diverge.
 */
it('keeps a converted lead out of the lead filter', function (): void {
    linkedCall(['customer_user_id' => $this->patient->id, 'lead_id' => $this->lead->id]);

    expect(Call::linkedTo(CallLinkType::Lead)->count())->toBe(0)
        ->and(Call::linkedTo(CallLinkType::Patient)->count())->toBe(1);
});

// ──────────────── The list ────────────────

it('badges each row with the kind of record it belongs to', function (): void {
    config(['app.env' => 'local']);

    linkedCall(['customer_user_id' => $this->patient->id]);
    linkedCall(['lead_id' => $this->lead->id]);

    $rows = \Livewire\Livewire::actingAs($this->admin)
        ->test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    // Asserted on the hover text rather than on the words "Patient" and
    // "Lead", which now also appear in the filter options — a test that passed
    // on those would pass with no badge rendered at all.
    expect($rows)->toContain('An existing patient record')
        ->toContain('A lead, not yet a patient')
        // Both names still render, so the badge sits beside the name rather
        // than replacing it.
        ->toContain('Anita Shah')
        ->toContain('Sohil Mansuri');
});

/**
 * An unmatched call already carries a badge saying nobody is attached. A second
 * one saying the same thing in different words is noise on the rows that most
 * need reading.
 */
it('does not stack a not-linked badge on an unmatched row', function (): void {
    config(['app.env' => 'local']);

    linkedCall(['matching_status' => 'unmatched', 'client_name' => 'Unknown Caller']);

    $rows = \Livewire\Livewire::actingAs($this->admin)
        ->test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    expect($rows)->toContain('Unmatched')
        ->not->toContain('Not linked to a patient or a lead');
});
