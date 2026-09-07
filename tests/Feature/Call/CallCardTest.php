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

/**
 * The repairs used to live only on the call page. The list is where somebody
 * actually notices a row with no recording or an unmatched caller, so opening
 * the call to press one button was a page load per repair.
 *
 * Both surfaces now build from the same action classes, which is what stops
 * them drifting: a guard tightened in one place cannot quietly stay loose in
 * the other.
 */
it('offers the same repairs in the row menu as on the call page', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call', 'PlayRecording:Call', 'ViewTranscript:Call'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $clinic = Clinic::create(['name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true]);

    $admin = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Super', 'last_name' => 'Admin', 'mobile' => '9111111113', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->assignRole($role);

    $call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $clinic->id,
        'provider' => 'exotel',
        'provider_call_id' => 'repairs-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'client_phone_normalized' => '+919687784381',
        'client_phone_key' => '9687784381',
        'matching_status' => 'unmatched',
        'started_at' => now()->subHour(),
    ]);

    // Retry download only appears when there is audio to fetch.
    \App\Models\CallRecording::create([
        'call_id' => $call->getKey(), 'provider' => 'exotel',
        'storage_status' => 'remote_only', 'storage_path' => 'calls/repairs-1.mp3',
    ]);

    $this->actingAs($admin);

    $rows = \Livewire\Livewire::test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    foreach (['Refresh from provider', 'Retry download', 'Re-run customer matching'] as $label) {
        expect($rows)->toContain($label);
    }

    // And the call page still offers them, from the same definitions.
    $view = $this->get(\App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $call]))->getContent();

    foreach (['Refresh from provider', 'Retry download', 'Re-run customer matching'] as $label) {
        expect($view)->toContain($label);
    }
});

/**
 * A manual call has no provider to ask, and a match a person decided by hand
 * outranks a phone-number guess — so neither button should be offered.
 */
it('hides repairs that cannot do anything for the record', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $clinic = Clinic::create(['name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true]);

    $admin = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Super', 'last_name' => 'Admin', 'mobile' => '9111111114', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->assignRole($role);

    $call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $clinic->id,
        'provider' => 'manual',
        'provider_call_id' => 'manual-1',
        'source' => 'manual',
        'direction' => 'outgoing',
        'call_status' => 'completed',
        'matching_status' => 'manually_matched',
        'started_at' => now(),
    ]);

    $view = $this->actingAs($admin)
        ->get(\App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $call]))
        ->getContent();

    expect($view)->not->toContain('Refresh from provider')
        // No recordings, so nothing to download.
        ->and($view)->not->toContain('Retry download')
        ->and($view)->not->toContain('Re-run customer matching');
});

/**
 * The Text and AI badges announced that something existed without offering it,
 * and the thing they announce is exactly what somebody scanning the list wants
 * to read. Reaching it meant opening the call.
 */
it('opens the transcript and analysis from the card badges', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call', 'ViewTranscript:Call', 'PlayRecording:Call'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $clinic = Clinic::create(['name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true]);

    $admin = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Super', 'last_name' => 'Admin', 'mobile' => '9111111115', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->assignRole($role);

    $call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $clinic->id,
        'provider' => 'callyzer',
        'provider_call_id' => 'badges-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'transcription_status' => 'completed',
        'analysis_status' => 'completed',
        'client_name' => 'Sohil Shingala',
        'started_at' => now()->subHour(),
    ]);

    \App\Models\CallTranscription::create([
        'call_id' => $call->getKey(), 'provider' => 'openai',
        'transcript' => 'हां जी, टेस्टिंग कर रहे थे. पैकेज कितने का है?',
        'status' => 'completed', 'is_current' => true,
    ]);

    \App\Models\CallAnalysis::create([
        'call_id' => $call->getKey(),
        'summary' => 'Caller asked about package pricing.',
        'customer_intent' => 'price enquiry',
        'analysis_version' => 'v2',
        'model' => 'gpt-4o',
        'is_current' => true,
    ]);

    $this->actingAs($admin);

    $rows = \Livewire\Livewire::test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    // Both badges carry a mount handler for their own action, scoped to this
    // row — without the record key the modal would open on the wrong call.
    expect($rows)->toContain("mountAction('viewTranscript'")
        ->toContain("mountAction('viewAnalysis'")
        ->toContain("recordKey: '" . $call->getKey() . "'");

    // Every column sits inside the row's own <a href>, so a badge without this
    // guard opens the call page as well as the modal — two things happening
    // from one click, one of them unasked for.
    expect(substr_count($rows, 'event.preventDefault(); event.stopPropagation();'))
        ->toBeGreaterThanOrEqual(4);
});

/**
 * Reading a transcript is a separate grant from seeing that a call happened, so
 * a badge that opens one must not appear for somebody who may not read it.
 */
it('does not offer the transcript to a user without that permission', function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'reception-badges', 'guard_name' => 'web']);

    // Deliberately without ViewTranscript:Call.
    foreach (['ViewAny:Call', 'View:Call'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $clinic = Clinic::create(['name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true]);

    $staff = User::create(['clinic_id' => $clinic->id, 'first_name' => 'Front', 'last_name' => 'Desk', 'mobile' => '9222222298', 'password' => bcrypt('x'), 'is_active' => true]);
    $staff->assignRole($role);

    $call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $clinic->id,
        'provider' => 'callyzer',
        'provider_call_id' => 'badges-2',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'transcription_status' => 'completed',
        'client_name' => 'Restricted Caller',
        'started_at' => now(),
    ]);

    \App\Models\CallTranscription::create([
        'call_id' => $call->getKey(), 'provider' => 'openai',
        'transcript' => 'Private medical detail.', 'status' => 'completed', 'is_current' => true,
    ]);

    $this->actingAs($staff);

    $rows = \Livewire\Livewire::test(\App\Filament\Resources\Calls\Pages\ListCalls::class)
        ->call('loadTable')
        ->html();

    // The row renders, and the badge is still drawn — the call was transcribed,
    // which is not a secret. What is withheld is the way to read it.
    expect($rows)->toContain('Restricted Caller')
        ->not->toContain("mountAction('viewTranscript'");
});
