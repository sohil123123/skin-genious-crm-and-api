<?php

use App\Models\{Call, Clinic, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The calls list draws two cards per row, not a strip of text columns.
 *
 * Worth a test of its own because the failure mode is silent: the cards are
 * Blade views styled by CSS injected from AdminPanelProvider, and if either the
 * view or the stylesheet stops reaching the page the table still renders — just
 * as an unstyled stack of text. That is exactly what the Integration Health
 * page did for a week before anyone noticed.
 */
uses(RefreshDatabase::class);

it('draws the call card and ships its styles', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call', 'PlayRecording:Call', 'ViewTranscript:Call'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $clinic = Clinic::create(['name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true]);

    $admin = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Super', 'last_name' => 'Admin', 'mobile' => '9111111111', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->assignRole($role);

    $agent = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Priya', 'last_name' => 'Nair', 'mobile' => '9167356935', 'password' => bcrypt('x'), 'is_active' => true]);

    $call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $clinic->id,
        'provider' => 'exotel',
        'provider_call_id' => 'card-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'is_connected' => true,
        'agent_user_id' => $agent->id,
        'employee_name' => 'Priya',
        'employee_code' => 'EMP-01',
        'employee_phone_normalized' => '+919167356935',
        'client_name' => 'Anita Shah',
        'client_phone_normalized' => '+919687784381',
        'client_phone_key' => '9687784381',
        'virtual_number_normalized' => '+911414937562',
        'crm_outcome' => 'Appointment booked',
        'started_at' => now()->subHour(),
        'duration_seconds' => 272,
        'talk_duration_seconds' => 245,
        'matching_status' => 'unmatched',
        'follow_up_required' => true,
    ]);

    $list = $this->actingAs($admin)->get(\App\Filament\Resources\Calls\CallResource::getUrl('index'))->getContent();

    // The table defers its query, so rows arrive on the Livewire request
    // rather than in the initial HTML. Render it the way the browser does.
    $rows = \Livewire\Livewire::test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    foreach (['sgc-glyph', 'sgc-name', 'sgc-own-avatar', 'Anita Shah', '+919687784381', 'Priya Nair', 'EMP-01'] as $needle) {
        expect($rows)->toContain($needle);
    }

    // The stylesheet the cards are written against reached the page.
    foreach (['.sgc-glyph--ok', '.sgc-own-avatar-primary', '.fi-row-call-unmatched'] as $rule) {
        expect($list)->toContain($rule);
    }

    // Unmatched rows are tinted so they stand out without reading a column.
    expect($list)->toContain('fi-row-call-unmatched');

    $view = $this->actingAs($admin)->get(\App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $call]))->getContent();

    // The two-column layout's sections are all present, not hidden behind tabs.
    //
    // "Outcome and notes" and "Follow-up" are absent on purpose: both sections
    // are commented out of the layout while those two features are temporarily
    // switched off. Restore them here when the sections come back, or this test
    // will pass without checking the thing it was written to check.
    foreach (['Participants', 'Timing', 'Provider and sync'] as $heading) {
        expect($view)->toContain($heading);
    }

    // And they really are gone, rather than merely unasserted.
    foreach (['Outcome and notes', 'Follow-up'] as $switchedOff) {
        expect($view)->not->toContain($switchedOff);
    }
});

/**
 * The provider used to be the fourth muted line inside the "Handled by" cell,
 * under a name, a warning and a phone number — which read as a footnote about
 * the agent rather than as the system that recorded the call. It has its own
 * badge column now.
 */
it('shows the provider as its own badge column', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $clinic = Clinic::create(['name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true]);

    $admin = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Super', 'last_name' => 'Admin', 'mobile' => '9111111112', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->assignRole($role);

    foreach ([['prov-exotel', 'exotel'], ['prov-callyzer', 'callyzer']] as [$id, $provider]) {
        Call::create([
            'uuid' => (string) Str::uuid(),
            'clinic_id' => $clinic->id,
            'provider' => $provider,
            'provider_call_id' => $id,
            'source' => 'webhook',
            'direction' => 'outgoing',
            'call_status' => 'completed',
            'employee_name' => 'soumyendro',
            'employee_code' => 'EMP-09',
            'started_at' => now()->subMinutes(5),
        ]);
    }

    $this->actingAs($admin);

    $rows = \Livewire\Livewire::test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    // Both providers named, and the column header present.
    foreach (['Provider', 'Exotel', 'Callyzer'] as $needle) {
        expect($rows)->toContain($needle);
    }

    // The employee code stays in the agent cell — it identifies the person in
    // the provider's app, so it belongs with the rest of their identity.
    expect($rows)->toContain('EMP-09');
});
