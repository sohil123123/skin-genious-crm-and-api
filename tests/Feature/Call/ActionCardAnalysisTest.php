<?php

declare(strict_types=1);

use App\Models\{AiActionLog, Call, CallAnalysis, Clinic, LeadActionLog, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * The call's AI analysis, on the card in the action queue.
 *
 * Staff work the queue top to bottom without opening anything. A card that says
 * "asked for something on a call" and makes them go and find the call to learn
 * what was asked is a page load in the middle of that pass — so the summary,
 * the signals the engine actually scored on, and the model's suggested next
 * step are on the card itself.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->role = Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach ([
        'ViewAny:Call', 'View:Call',
        'widget_AiActionQueue', 'widget_LeadActionQueue',
    ] as $permission) {
        $this->role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->staff = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111115', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->staff->assignRole($this->role);

    $this->patient = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Rohit', 'last_name' => 'Singh',
        'mobile' => '9700052005', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $this->clinic->getKey(),
        'provider' => 'callyzer',
        'provider_call_id' => 'card-analysis-1',
        'source' => 'webhook',
        'direction' => 'outgoing',
        'call_status' => 'completed',
        'customer_user_id' => $this->patient->getKey(),
        'started_at' => now()->subHours(9),
        'talk_duration_seconds' => 83,
    ]);

    CallAnalysis::create([
        'call_id' => $this->call->getKey(),
        'is_current' => true,
        'summary' => 'The clinic called to confirm an appointment; the customer could not make the time.',
        'next_best_action' => 'Follow up to confirm a new appointment time.',
    ]);

    $this->basis = [
        ['key' => 'appointment_intent', 'type' => 'intent', 'confidence' => 0.9],
        ['key' => 'staff_followup_required', 'type' => 'follow_up', 'confidence' => 0.9],
    ];
});

it('shows the call analysis on a patient action card', function (): void {
    AiActionLog::create([
        'clinic_id' => $this->clinic->getKey(),
        'user_id' => $this->patient->getKey(),
        'action_category' => AiActionLog::CATEGORY_CONVERSION,
        'action_trigger' => 'call_commitment_open',
        'priority_score' => 97,
        'recommended_channel' => 'call',
        'reason' => 'Asked for something on a call 9 hours ago and has not had it.',
        'related_call_id' => $this->call->getKey(),
        'call_signals' => $this->basis,
        'generated_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $html = Livewire::actingAs($this->staff)
        ->test(\App\Filament\Widgets\AiActionQueue::class)
        ->html();

    expect($html)->toContain('AI analysis of the call')
        ->toContain('could not make the time')
        ->toContain('Follow up to confirm a new appointment time.')
        // The signals the engine scored on, named the way the call page names
        // them rather than as raw keys.
        ->toContain('Appointment intent')
        ->toContain('Staff followup required')
        // And a way through to the recording without hunting for the call.
        ->toContain('Open call');
});

it('shows the same block on a lead action card', function (): void {
    $lead = \App\Models\Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Rohit',
        'full_name' => 'Rohit Singh',
        'phone' => '9700052005',
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);

    LeadActionLog::create([
        'clinic_id' => $this->clinic->getKey(),
        'lead_id' => $lead->getKey(),
        'action_category' => LeadActionLog::CATEGORY_CONVERSION,
        'action_trigger' => LeadActionLog::TRIGGER_CALL_COMMITMENT,
        'priority_score' => 90,
        'recommended_channel' => 'call',
        'reason' => 'Asked for something on a call 9 hours ago and has not had it.',
        'related_call_id' => $this->call->getKey(),
        'call_signals' => $this->basis,
        'generated_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $html = Livewire::actingAs($this->staff)
        ->test(\App\Filament\Widgets\LeadActionQueue::class)
        ->html();

    expect($html)->toContain('AI analysis of the call')
        ->toContain('could not make the time')
        ->toContain('Appointment intent');
});

/**
 * Most cards are built from appointment dates and package history and have no
 * call behind them. They must look exactly as they did.
 */
it('adds nothing to a card no call raised', function (): void {
    AiActionLog::create([
        'clinic_id' => $this->clinic->getKey(),
        'user_id' => $this->patient->getKey(),
        'action_category' => AiActionLog::CATEGORY_RETENTION,
        'action_trigger' => 'package_overdue',
        'priority_score' => 80,
        'recommended_channel' => 'call',
        'reason' => 'Package sessions unused for three weeks.',
        'generated_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $html = Livewire::actingAs($this->staff)
        ->test(\App\Filament\Widgets\AiActionQueue::class)
        ->html();

    expect($html)->toContain('Package sessions unused')
        ->not->toContain('AI analysis of the call');
});

/**
 * The badges must show the basis the engine scored on at generation time, not
 * whatever the call says now. A re-analysis that changed the signals would
 * otherwise silently rewrite the explanation for a decision already made.
 */
it('shows the basis stored on the action, not the call current signals', function (): void {
    AiActionLog::create([
        'clinic_id' => $this->clinic->getKey(),
        'user_id' => $this->patient->getKey(),
        'action_category' => AiActionLog::CATEGORY_CONVERSION,
        'action_trigger' => 'call_commitment_open',
        'priority_score' => 97,
        'recommended_channel' => 'call',
        'reason' => 'Asked for something on a call 9 hours ago and has not had it.',
        'related_call_id' => $this->call->getKey(),
        'call_signals' => [['key' => 'price_objection', 'type' => 'objection', 'confidence' => 0.8]],
        'generated_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $html = Livewire::actingAs($this->staff)
        ->test(\App\Filament\Widgets\AiActionQueue::class)
        ->html();

    expect($html)->toContain('Price objection')
        ->not->toContain('Appointment intent');
});
