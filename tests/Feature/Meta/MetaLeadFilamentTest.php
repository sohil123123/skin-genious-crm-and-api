<?php

declare(strict_types=1);

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MetaSyncStatus;
use App\Filament\Pages\MetaLeadSettings;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\MetaLeadSyncLogs\MetaLeadSyncLogResource;
use App\Filament\Resources\MetaLeadSyncLogs\Pages\ListMetaLeadSyncLogs;
use App\Filament\Resources\MetaPages\MetaPageResource;
use App\Filament\Resources\MetaPages\Pages\ListMetaPages;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\MetaLeadSyncLog;
use App\Models\MetaPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * What the Meta integration adds to the Filament UI.
 *
 * The access-token assertion is the one that matters most: the token grants
 * read access to every lead a Page has collected, so it must never reach a
 * rendered page.
 */
uses(RefreshDatabase::class);

/**
 * Shield names permissions {Action}:{Model}, as App\Policies\LeadPolicy shows.
 */
const META_TEST_PERMISSIONS = [
    'ViewAny:Lead',
    'View:Lead',
    'Update:Lead',
    'ViewAny:MetaPage',
    'View:MetaPage',
    'Update:MetaPage',
    'ViewAny:MetaLeadSyncLog',
    'View:MetaLeadSyncLog',
    'View:MetaLeadSettings',
];

beforeEach(function (): void {
    $this->clinic = Clinic::create([
        'name' => 'Test Clinic',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    // Shield is configured with define_via_gate = false, so even a super admin
    // holds explicit permissions. Granting the real ones — rather than
    // bypassing the gate — is what lets the denial test below mean anything.
    $role = Role::findOrCreate(config('project.roles.super_admin'), 'web');

    foreach (META_TEST_PERMISSIONS as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role->givePermissionTo(META_TEST_PERMISSIONS);

    $this->admin = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Test',
        'last_name' => 'Admin',
        'email' => 'admin@example.test',
        'mobile' => '+919999999999',
        'password' => bcrypt('password'),
    ]);

    $this->admin->assignRole(config('project.roles.super_admin'));

    $this->page = MetaPage::create([
        'page_id' => '1122334455',
        'page_name' => 'Skin Genious',
        'clinic_id' => $this->clinic->getKey(),
        'access_token' => 'super-secret-page-token',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);
});

function metaLead(array $overrides = []): Lead
{
    return Lead::create(array_merge([
        'clinic_id' => test()->clinic->getKey(),
        'full_name' => 'Priya Meena',
        'phone' => '+919887127755',
        'source' => LeadSource::Instagram->value,
        'status' => LeadStatus::New->value,
        'fb_lead_id' => '1001',
        'campaign_id' => '3322110099',
        'campaign_name' => 'August Facials',
        'form_id' => '9988776655',
        'page_name' => 'Skin Genious',
        'page_id' => '1122334455',
        'platform' => 'ig',
    ], $overrides));
}

it('shows a webhook lead in the existing lead list', function (): void {
    $lead = metaLead();

    Livewire::test(ListLeads::class)
        ->assertCanSeeTableRecords([$lead])
        ->assertSee('August Facials');
});

it('shows the Meta attribution on the lead detail screen', function (): void {
    $lead = metaLead();

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->assertSee('Meta attribution')
        ->assertSee('August Facials')
        ->assertSee('Skin Genious');
});

it('says a webhook lead arrived in real time rather than manually', function (): void {
    // Before this integration, a lead with no import batch always meant
    // "typed in by hand". That is no longer true.
    $lead = metaLead();

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->assertSee('Meta webhook (real time)');
});

it('falls back to the campaign id when the name was never resolved', function (): void {
    // Names need a token with ads permissions. Without one the id stands in as
    // the value, so "which campaign was this?" is still answerable.
    $lead = metaLead(['campaign_name' => null]);

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->assertSee('3322110099');
});

it('shows the page id alongside the page name', function (): void {
    $lead = metaLead();

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->assertSee('Skin Genious')
        ->assertSee('ID 1122334455');
});

it('filters the lead list down to real-time arrivals', function (): void {
    $realtime = metaLead();
    $manual = metaLead([
        'fb_lead_id' => null,
        'full_name' => 'Walk In Person',
        'campaign_name' => null,
        'source' => LeadSource::Manual->value,
    ]);

    Livewire::test(ListLeads::class)
        ->filterTable('arrival', 'realtime')
        ->assertCanSeeTableRecords([$realtime])
        ->assertCanNotSeeTableRecords([$manual]);
});

it('lists sync records for troubleshooting', function (): void {
    $log = MetaLeadSyncLog::create([
        'leadgen_id' => '1001',
        'meta_page_id' => $this->page->getKey(),
        'status' => MetaSyncStatus::Failed,
        'error_message' => 'Access token has expired',
        'attempts' => 3,
    ]);

    Livewire::test(ListMetaLeadSyncLogs::class)
        ->assertCanSeeTableRecords([$log])
        ->assertSee('Access token has expired');
});

it('never renders a Page access token', function (): void {
    Livewire::test(ListMetaPages::class)
        ->assertCanSeeTableRecords([$this->page])
        ->assertSee('Skin Genious')
        ->assertDontSee('super-secret-page-token');
});

it('renders the settings page with the callback URL and readiness summary', function (): void {
    Setting::setValue('meta_app_secret', 'a-secret');
    Setting::setValue('meta_verify_token', 'a-token');

    Livewire::test(MetaLeadSettings::class)
        ->assertOk()
        ->assertSee(url('/api/webhooks/meta'))
        ->assertSee('App secret saved')
        // The saved secret is a password field; its value must not be in the
        // rendered markup.
        ->assertDontSee('a-secret');
});

it('warns on the settings page when credentials are missing', function (): void {
    Setting::setValue('meta_app_secret', '');
    Setting::setValue('meta_verify_token', '');
    Setting::setValue('meta_access_token', '');

    Livewire::test(MetaLeadSettings::class)
        ->assertSee('incoming webhooks are being rejected')
        ->assertSee('No access token saved');
});

it('does not treat having no Pages as a problem', function (): void {
    // Zero Pages is the normal starting state, not misconfiguration — there is
    // nothing for anyone to go and create.
    $this->page->delete();

    Livewire::test(MetaLeadSettings::class)
        ->assertSee('register themselves')
        ->assertDontSee('No active Meta Pages connected');
});

it('offers no way to create a Meta Page by hand', function (): void {
    // Pages register themselves. A create form would invite a hand-typed page
    // id that does not match what Meta sends — configured-looking, but silently
    // receiving nothing.
    expect(MetaPageResource::canCreate())->toBeFalse();

    $this->page->delete();

    Livewire::test(ListMetaPages::class)
        ->assertOk()
        ->assertSee('Pages appear here on their own');
});

it('shows an auto-discovered Page as using the default clinic', function (): void {
    $discovered = MetaPage::create([
        'page_id' => '9999999999',
        'clinic_id' => null,
        'is_active' => true,
    ]);

    Livewire::test(ListMetaPages::class)
        ->assertCanSeeTableRecords([$discovered])
        ->assertSee('Default clinic');
});

it('lists sync records when a Page has not been named yet', function (): void {
    // A Page registers itself from the webhook with only its id, so page_name
    // is null until Meta resolves it. Using that as a select label crashed the
    // whole screen, and the Pages most in need of troubleshooting are exactly
    // the new ones.
    $unnamed = MetaPage::create([
        'page_id' => '9999999999',
        'clinic_id' => null,
        'is_active' => true,
    ]);

    $log = MetaLeadSyncLog::create([
        'leadgen_id' => '2002',
        'meta_page_id' => $unnamed->getKey(),
        'status' => MetaSyncStatus::Failed,
        'error_message' => 'Access token has expired',
        'attempts' => 1,
    ]);

    Livewire::test(ListMetaLeadSyncLogs::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$log])
        ->filterTable('meta_page_id', $unnamed->getKey())
        ->assertCanSeeTableRecords([$log]);
});

it('falls back to the page id when a Page has no name', function (): void {
    $unnamed = MetaPage::create([
        'page_id' => '9999999999',
        'clinic_id' => null,
        'is_active' => true,
    ]);

    expect($unnamed->displayName())->toBe('Page 9999999999')
        ->and(MetaPageResource::getRecordTitle($unnamed))->toBe('Page 9999999999')
        ->and($this->page->displayName())->toBe('Skin Genious');
});

it('denies the sync log to a user without the permission', function (): void {
    // The Meta screens go through the existing Shield permissions rather than
    // introducing an authorisation system of their own.
    $role = Role::findOrCreate('receptionist', 'web');

    $staff = User::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'No',
        'last_name' => 'Access',
        'email' => 'staff@example.test',
        'mobile' => '+919999999998',
        'password' => bcrypt('password'),
    ]);

    $staff->assignRole($role);

    $this->actingAs($staff);

    expect(MetaLeadSyncLogResource::canViewAny())->toBeFalse()
        ->and(MetaPageResource::canViewAny())->toBeFalse();
});
