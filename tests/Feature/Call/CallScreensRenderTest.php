<?php

declare(strict_types=1);

use App\Filament\Pages\CallIntegrationHealth;
use App\Filament\Pages\CallSettings;
use App\Filament\Resources\CallProviderAgents\CallProviderAgentResource;
use App\Filament\Resources\Calls\CallResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Call;
use App\Models\CallProviderAgent;
use App\Models\Clinic;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every call screen renders.
 *
 * These exist because a Filament page can be perfectly valid PHP and still be
 * broken: `php -l` happily accepts an import of a class that does not exist,
 * and a method renamed between major versions only fails when the page is
 * actually built. Both faults reach production as a 500 on a screen nobody
 * opened during development.
 *
 * Deliberately shallow — this asserts the page builds, not what it shows.
 * Behaviour is covered by the other call tests; this is the smoke alarm.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();

    // The User model does not implement FilamentUser, so Filament's panel
    // middleware admits an authenticated user only when the environment is
    // "local" — which the test suite is not. This is a pre-existing app-level
    // gate unrelated to calls; without it every panel test 403s before
    // reaching the page it is meant to be checking.
    config(['app.env' => 'local']);

    $this->clinic = Clinic::create([
        'name' => 'Test Clinic',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    // Rendering a Filament page builds the whole sidebar, which asks every
    // resource for its navigation badge. UserResource's badge queries the
    // "client" role by name and Spatie throws if it does not exist, so the
    // seeded roles have to be present or an unrelated resource takes this
    // page down with it.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (config('project.roles') as $roleName) {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    // Shield is configured with define_via_gate = false, so the super admin
    // role grants nothing on its own — the permissions have to be real.
    $role = Role::firstOrCreate([
        'name' => config('project.roles.super_admin'),
        'guard_name' => 'web',
    ]);

    foreach ([
        'ViewAny:Call', 'View:Call', 'Update:Call',
        'PlayRecording:Call', 'ViewTranscript:Call',
        'ViewAny:CallProviderAgent', 'View:CallProviderAgent',
        'Create:CallProviderAgent', 'Update:CallProviderAgent',
        // Needed only to reach the patient profile, which is where the Calls
        // relation manager is mounted.
        'ViewAny:User', 'View:User', 'Update:User',
    ] as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]));
    }

    $this->admin = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'mobile' => '9111111111',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->admin->assignRole($role);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $this->clinic->getKey(),
        'provider' => 'exotel',
        'provider_call_id' => 'render-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'is_connected' => true,
        'client_phone' => '+919876543210',
        'client_phone_key' => '9876543210',
        'started_at' => now()->subHour(),
        'duration_seconds' => 272,
        'talk_duration_seconds' => 245,
    ]);
});

it('renders the call list', function (): void {
    $this->actingAs($this->admin)
        ->get(CallResource::getUrl('index'))
        ->assertSuccessful();
});

/**
 * The tabs are where the Filament v3 Tab class slipped in — valid PHP, missing
 * class, 500 on the one screen everybody opens first.
 */
it('renders every tab on the call list', function (string $tab): void {
    $this->actingAs($this->admin)
        ->get(CallResource::getUrl('index', ['activeTab' => $tab]))
        ->assertSuccessful();
})->with(['all', 'needs_attention', 'follow_up', 'incoming', 'outgoing', 'missed', 'today']);

it('renders the call detail page', function (): void {
    $this->actingAs($this->admin)
        ->get(CallResource::getUrl('view', ['record' => $this->call]))
        ->assertSuccessful();
});

it('renders the agent mapping list', function (): void {
    CallProviderAgent::create([
        'provider' => 'exotel',
        'provider_employee_key' => '9509407709',
        'provider_employee_number' => '9509407709',
        'active' => true,
        'auto_discovered' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(CallProviderAgentResource::getUrl('index'))
        ->assertSuccessful();
});

it('renders the call settings page', function (): void {
    $this->actingAs($this->admin)
        ->get(CallSettings::getUrl())
        ->assertSuccessful();
});

it('renders the integration health page', function (): void {
    $this->actingAs($this->admin)
        ->get(CallIntegrationHealth::getUrl())
        ->assertSuccessful();
});

/**
 * The Calls tab added to the patient profile — a relation manager that widens
 * its own relation, which is easy to get wrong in a way only rendering reveals.
 */
it('renders the patient profile with its calls tab', function (): void {
    $patient = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Anita',
        'last_name' => 'Shah',
        'mobile' => '9876543210',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(UserResource::getUrl('edit', ['record' => $patient]))
        ->assertSuccessful();
});

// ──────────────── The clinic an agent files calls under ────────────────

use App\Filament\Resources\CallProviderAgents\Schemas\CallProviderAgentForm;

/**
 * A closed branch is not somewhere to file tomorrow's calls. Offering one in
 * the dropdown invites a mistake nobody notices for weeks - the calls just stop
 * appearing where the staff are looking.
 */
it('offers only active clinics when filing an agent', function (): void {
    $open = \App\Models\Clinic::create([
        'name' => 'Jaipur Open', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $closed = \App\Models\Clinic::create([
        'name' => 'Closed Branch', 'address_line1' => '2', 'city' => 'M', 'pincode' => '400001', 'is_active' => false,
    ]);

    $options = CallProviderAgentForm::clinicOptions();

    expect($options)->toHaveKey($open->getKey())
        ->and($options)->not->toHaveKey($closed->getKey());
});

/**
 * Except on a mapping that already points at it: hiding it would render an
 * empty field, and saving that would quietly unfile the agent.
 */
it('keeps a since-deactivated clinic visible on the mapping that uses it', function (): void {
    $closed = \App\Models\Clinic::create([
        'name' => 'Closed Branch', 'address_line1' => '2', 'city' => 'M', 'pincode' => '400001', 'is_active' => false,
    ]);

    $mapping = \App\Models\CallProviderAgent::create([
        'provider' => 'callyzer',
        'provider_employee_name' => 'Shivani',
        'provider_employee_number' => '8169308873',
        'provider_employee_key' => '8169308873',
        'clinic_id' => $closed->getKey(),
        'active' => true,
    ]);

    $options = CallProviderAgentForm::clinicOptions($mapping);

    expect($options)->toHaveKey($closed->getKey())
        ->and($options[$closed->getKey()])->toContain('inactive');
});

// ──────────────── Narrowing the staff list by clinic ────────────────

/**
 * @param  array<string, mixed>  $attributes
 */
function staffAt(?int $clinicId, string $first, string $mobile, array $attributes = []): \App\Models\User
{
    $user = \App\Models\User::create(array_merge([
        'clinic_id' => $clinicId, 'first_name' => $first, 'last_name' => 'Staff',
        'mobile' => $mobile, 'password' => bcrypt('x'), 'is_active' => true,
    ], $attributes));

    $role = \App\Models\Role::firstOrCreate([
        'name' => config('project.roles.super_admin'), 'guard_name' => 'web',
    ]);

    $user->assignRole($role);

    return $user;
}

it('narrows the staff list to the chosen clinic', function (): void {
    $jaipur = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $mumbai = \App\Models\Clinic::create([
        'name' => 'Mumbai', 'address_line1' => '2', 'city' => 'M', 'pincode' => '400001', 'is_active' => true,
    ]);

    $here = staffAt($jaipur->getKey(), 'Priya', '9000000001');
    $elsewhere = staffAt($mumbai->getKey(), 'Ravi', '9000000002');
    // Nobody's branch: exactly who the clinic field exists to place, so they
    // must never be filtered out.
    $unplaced = staffAt(null, 'Shivani', '9000000003');

    $options = CallProviderAgentForm::staffOptions($jaipur->getKey());

    expect($options)->toHaveKey($here->getKey())
        ->and($options)->toHaveKey($unplaced->getKey())
        ->and($options)->not->toHaveKey($elsewhere->getKey());
});

/**
 * A filter that silently drops the saved value turns an unrelated edit - a
 * note, the active toggle - into an unmapping.
 */
it('keeps the already-mapped staff member listed whatever their clinic', function (): void {
    $jaipur = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $mumbai = \App\Models\Clinic::create([
        'name' => 'Mumbai', 'address_line1' => '2', 'city' => 'M', 'pincode' => '400001', 'is_active' => true,
    ]);

    $mapped = staffAt($mumbai->getKey(), 'Ravi', '9000000002');

    expect(CallProviderAgentForm::staffOptions($jaipur->getKey(), $mapped->getKey()))
        ->toHaveKey($mapped->getKey());
});

it('lists everyone when no clinic is chosen', function (): void {
    $mumbai = \App\Models\Clinic::create([
        'name' => 'Mumbai', 'address_line1' => '2', 'city' => 'M', 'pincode' => '400001', 'is_active' => true,
    ]);

    $ravi = staffAt($mumbai->getKey(), 'Ravi', '9000000002');

    expect(CallProviderAgentForm::staffOptions(null))->toHaveKey($ravi->getKey())
        // The clinic rides along in the label, so two people with one name in
        // different branches are not the same entry twice.
        ->and(CallProviderAgentForm::staffOptions(null)[$ravi->getKey()])->toContain('Mumbai');
});

/**
 * Patients hold a login in this CRM. Mapping an agent identity to one would
 * attribute the clinic's outgoing calls to a customer.
 */
it('never offers a patient as the agent behind a handset', function (): void {
    $clinic = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $patient = \App\Models\User::create([
        'clinic_id' => $clinic->getKey(), 'first_name' => 'Anita', 'last_name' => 'Shah',
        'mobile' => '9876543210', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $patient->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.client'), 'guard_name' => 'web',
    ]));

    expect(CallProviderAgentForm::staffOptions($clinic->getKey()))->not->toHaveKey($patient->getKey());
});

// ──────────────── The counts above the table ────────────────

/**
 * @return array<string, mixed>
 */
function callTabs(): array
{
    $page = new \App\Filament\Resources\Calls\Pages\ListCalls();

    $method = new ReflectionMethod($page, 'getTabs');
    $method->setAccessible(true);

    return $method->invoke($page);
}

function makeDirectedCall(string $id, string $direction, array $attributes = []): \App\Models\Call
{
    return \App\Models\Call::create(array_merge([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => $id,
        'source' => 'webhook',
        'direction' => $direction,
        'call_status' => 'completed',
        'started_at' => now(),
    ], $attributes));
}

it('counts incoming and outgoing calls on their tabs', function (): void {
    // Read as numbers now, not tabs to read later: the badge is a closure over
    // a live query, so holding the tab and calling getBadge() after the inserts
    // would measure the same moment twice.
    //
    // A delta rather than an absolute, because beforeEach already seeds a call
    // and this test does not care how many.
    $incomingBefore = (int) callTabs()['incoming']->getBadge();
    $outgoingBefore = (int) callTabs()['outgoing']->getBadge();

    makeDirectedCall('in-1', 'incoming');
    makeDirectedCall('in-2', 'incoming');
    makeDirectedCall('out-1', 'outgoing');

    // Filament renders badges as strings.
    expect((int) callTabs()['incoming']->getBadge() - $incomingBefore)->toBe(2)
        ->and((int) callTabs()['outgoing']->getBadge() - $outgoingBefore)->toBe(1);
});

/**
 * A badge must never disagree with the rows underneath it. The listing hides
 * deleted calls, so the count has to as well.
 */
it('leaves deleted calls out of the direction counts', function (): void {
    $before = (int) callTabs()['incoming']->getBadge();

    makeDirectedCall('in-1', 'incoming');
    makeDirectedCall('in-2', 'incoming')->delete();

    expect((int) callTabs()['incoming']->getBadge() - $before)->toBe(1);
});

// ──────────────── The popup's class names ────────────────

/**
 * A state modifier and a transition modifier must never share a name.
 *
 * "sgc-pop--out" once meant both "this call is outgoing" and "this card is
 * leaving". Every outgoing card therefore matched the exit animation the
 * instant it was inserted: it appeared and collapsed in a quarter of a second,
 * which looked like a broadcasting fault rather than a stylesheet one.
 *
 * Asserted against the source because the card is assembled in the browser -
 * there is no server-rendered markup to inspect, and the collision lives in the
 * names themselves.
 */
it('does not let a direction modifier double as the exit animation', function (): void {
    $popup = file_get_contents(resource_path('views/filament/incoming-call-popup.blade.php'));

    // The negative lookahead is the point: sgc-pop--outgoing is fine, a bare
    // sgc-pop--out is the bug.
    expect($popup)->not->toMatch('/sgc-pop--out(?![a-z])/')
        ->and($popup)->toContain('sgc-pop--leaving')
        ->and($popup)->toContain('sgc-pop--outgoing');
});

it('rings the phone icon only while a call is live', function (): void {
    $popup = file_get_contents(resource_path('views/filament/incoming-call-popup.blade.php'));

    // The shake is scoped to --live, so a finished call sits still. A card that
    // vibrates about a call nobody can answer is just noise.
    expect($popup)->toContain('.sgc-pop--live .sgc-pop__icon')
        ->and($popup)->toContain('sgc-pop-ring-shake')
        // And it stops entirely for anyone who asked their system to stop
        // moving things.
        ->and($popup)->toContain('prefers-reduced-motion');
});
