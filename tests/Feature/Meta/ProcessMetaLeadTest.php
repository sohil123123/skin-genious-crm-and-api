<?php

declare(strict_types=1);

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MetaSyncStatus;
use App\Enums\PhoneStatus;
use App\Jobs\Lead\ProcessMetaLeadJob;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadCustomField;
use App\Models\MetaLeadSyncLog;
use App\Models\MetaPage;
use App\Models\Setting;
use App\Services\Meta\MetaLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Turning a Meta lead into a CRM lead.
 *
 * The two properties worth defending here are that the same lead can be
 * processed any number of times without producing a second CRM record, and that
 * two forms asking completely different questions both work without a schema
 * change.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Without this a stub we forgot to register becomes a real call to
    // graph.facebook.com that hangs through three retries. Failing loudly is
    // far more useful than a two-minute timeout.
    Http::preventStrayRequests();

    Setting::setValue('meta_access_token', 'system-token');

    $this->clinic = Clinic::create([
        'name' => 'Test Clinic',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    $this->page = MetaPage::create([
        'page_id' => '1122334455',
        'page_name' => 'Skin Genious',
        'clinic_id' => $this->clinic->getKey(),
        'access_token' => 'page-token',
        'is_active' => true,
    ]);
});

function syncLog(string $leadgenId = '1001'): MetaLeadSyncLog
{
    return MetaLeadSyncLog::create([
        'leadgen_id' => $leadgenId,
        'meta_page_id' => test()->page->getKey(),
        'status' => MetaSyncStatus::Pending,
        'payload' => ['leadgen_id' => $leadgenId],
    ]);
}

/**
 * A Graph API lead node, shaped exactly as Meta returns one.
 *
 * @param  array<int, array{name: string, values: array<int, string>}>  $fieldData
 */
function fakeGraph(array $fieldData, array $overrides = [], ?string $formName = 'Skin Concern Form'): void
{
    $lead = array_merge([
        'id' => '1001',
        'created_time' => '2026-08-05T00:58:53-07:00',
        'field_data' => $fieldData,
        'ad_id' => '5544332211',
        'ad_name' => 'Dullness Video Ad',
        'adset_id' => '4433221100',
        'adset_name' => 'Mumbai 25-45',
        'campaign_id' => '3322110099',
        'campaign_name' => 'August Facials',
        'form_id' => '9988776655',
        'is_organic' => false,
        'platform' => 'ig',
    ], $overrides);

    $leadId = (string) $lead['id'];
    $formId = (string) $lead['form_id'];

    // Stubs are keyed to the specific id rather than a catch-all, because
    // Http::fake() merges successive calls instead of replacing them — a
    // catch-all registered first would keep answering for every later lead.
    Http::fake([
        '*/' . $leadId . '?*' => Http::response($lead),
        // The form name is a separate call; the lead node never carries it.
        '*/' . $formId . '?*' => Http::response(
            $formName === null ? ['id' => $formId] : ['id' => $formId, 'name' => $formName]
        ),
        // The Page node serves two purposes: deriving a Page access token from
        // the system token, and resolving the Page's name.
        '*/' . test()->page->page_id . '?*' => Http::response([
            'id' => test()->page->page_id,
            'name' => 'Skin Genious',
            'access_token' => 'derived-page-token',
        ]),
    ]);
}

// ─────────────── The happy path ───────────────

it('creates a CRM lead from a Meta lead', function (): void {
    fakeGraph([
        ['name' => 'full_name', 'values' => ['priya meena']],
        ['name' => 'phone_number', 'values' => ['+919887127755']],
        ['name' => 'city', 'values' => ['Mumbai']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    $lead = Lead::first();

    expect($lead)->not->toBeNull()
        ->and($lead->full_name)->toBe('Priya Meena')
        ->and($lead->first_name)->toBe('Priya')
        ->and($lead->last_name)->toBe('Meena')
        ->and($lead->phone)->toBe('+919887127755')
        ->and($lead->city)->toBe('Mumbai')
        ->and($lead->clinic_id)->toBe($this->clinic->getKey())
        ->and($lead->status)->toBe(LeadStatus::New)
        // platform "ig" is more precise than a blanket "facebook".
        ->and($lead->source)->toBe(LeadSource::Instagram)
        ->and($lead->fb_lead_id)->toBe('1001')
        // A webhook lead belongs to no import batch.
        ->and($lead->lead_import_id)->toBeNull();
});

it('stores the full Meta attribution chain', function (): void {
    fakeGraph([
        ['name' => 'phone_number', 'values' => ['+919887127755']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    $lead = Lead::first();

    expect($lead->campaign_id)->toBe('3322110099')
        ->and($lead->campaign_name)->toBe('August Facials')
        ->and($lead->adset_id)->toBe('4433221100')
        ->and($lead->adset_name)->toBe('Mumbai 25-45')
        ->and($lead->ad_id)->toBe('5544332211')
        ->and($lead->ad_name)->toBe('Dullness Video Ad')
        ->and($lead->form_id)->toBe('9988776655')
        ->and($lead->form_name)->toBe('Skin Concern Form')
        ->and($lead->page_name)->toBe('Skin Genious')
        ->and($lead->is_organic)->toBeFalse();
});

it('keeps the ids when the token cannot resolve the names', function (): void {
    // An access token without ads permissions gets ids but no names. The lead
    // must still be able to answer "which campaign was this?".
    fakeGraph(
        [['name' => 'phone_number', 'values' => ['+919887127755']]],
        overrides: ['campaign_name' => null, 'adset_name' => null, 'ad_name' => null],
        formName: null,
    );

    app(MetaLeadService::class)->process(syncLog());

    $lead = Lead::first();

    expect($lead->campaign_id)->toBe('3322110099')
        ->and($lead->campaign_name)->toBeNull()
        ->and($lead->form_id)->toBe('9988776655')
        ->and($lead->form_name)->toBeNull();
});

it('marks the sync log as imported and links the lead', function (): void {
    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    $log = syncLog();
    app(MetaLeadService::class)->process($log);

    $log->refresh();

    expect($log->status)->toBe(MetaSyncStatus::Success)
        ->and($log->lead_id)->toBe(Lead::first()->getKey())
        ->and($log->attempts)->toBe(1)
        ->and($log->processed_at)->not->toBeNull();
});

// ─────────────── Dynamic form questions ───────────────

it('stores answers to questions it has never seen before', function (): void {
    fakeGraph([
        ['name' => 'phone_number', 'values' => ['+919887127755']],
        ['name' => 'what_is_your_main_skin_concern?', 'values' => ['dullness_/_tanning']],
        ['name' => 'age', 'values' => ['28']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    $answers = Lead::first()->custom_answers;

    // humanize() is sentence case, not title case — "dullness_/_tanning" reads
    // as "Dullness / tanning" while the raw value stays exact for filtering.
    expect($answers)->toHaveCount(2)
        ->and(collect($answers)->pluck('value')->all())
        ->toContain('Dullness / tanning', '28');
});

it('supports two forms with completely different questions and no schema change', function (): void {
    // Form A — skin.
    fakeGraph([
        ['name' => 'phone_number', 'values' => ['+919887127755']],
        ['name' => 'skin_type', 'values' => ['Oily']],
        ['name' => 'skin_problem', 'values' => ['Acne']],
    ]);
    app(MetaLeadService::class)->process(syncLog('1001'));

    // Form B — hair. Same table, same code, different questions.
    fakeGraph(
        [
            ['name' => 'phone_number', 'values' => ['+919887127756']],
            ['name' => 'hair_problem', 'values' => ['Hair Fall']],
            ['name' => 'preferred_time', 'values' => ['Evening']],
            ['name' => 'budget', 'values' => ['5000']],
        ],
        overrides: ['id' => '1002'],
    );
    app(MetaLeadService::class)->process(syncLog('1002'));

    $skinLead = Lead::where('fb_lead_id', '1001')->first();
    $hairLead = Lead::where('fb_lead_id', '1002')->first();

    expect(array_keys($skinLead->custom_answers))->toEqualCanonicalizing(['skin_type', 'skin_problem'])
        ->and(array_keys($hairLead->custom_answers))->toEqualCanonicalizing(['hair_problem', 'preferred_time', 'budget'])
        ->and(Lead::count())->toBe(2);
});

it('folds a question onto the custom field an earlier import already created', function (): void {
    // This is the point of routing questions through ColumnAutoMapperService:
    // without it a webhook lead would create a second, near-identical question
    // and the two would filter separately.
    $existing = LeadCustomField::create([
        'clinic_id' => $this->clinic->getKey(),
        'key' => 'skin_type',
        'label' => 'skin_type',
        'type' => 'select',
        'is_active' => true,
    ]);

    fakeGraph([
        ['name' => 'phone_number', 'values' => ['+919887127755']],
        ['name' => 'skin_type', 'values' => ['Oily']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    expect(LeadCustomField::where('key', 'skin_type')->count())->toBe(1)
        ->and(Lead::first()->fieldValues->first()->lead_custom_field_id)->toBe($existing->getKey());
});

it('joins a multi-answer question the same way the CSV importer does', function (): void {
    fakeGraph([
        ['name' => 'phone_number', 'values' => ['+919887127755']],
        ['name' => 'concerns', 'values' => ['Acne', 'Pigmentation']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    $value = Lead::first()->fieldValues->first();

    expect($value->value)->toBe('Acne|Pigmentation')
        ->and($value->value_json)->toBe(['Acne', 'Pigmentation']);
});

it('never lets a form question overwrite Meta attribution', function (): void {
    // A question innocently named "campaign" must not be able to rewrite the
    // campaign this lead actually came from.
    fakeGraph([
        ['name' => 'phone_number', 'values' => ['+919887127755']],
        ['name' => 'campaign', 'values' => ['Something the user typed']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    expect(Lead::first()->campaign_name)->toBe('August Facials');
});

// ─────────────── Duplicates and retries ───────────────

it('does not create a second lead when the same lead is processed twice', function (): void {
    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    $log = syncLog();

    app(MetaLeadService::class)->process($log);
    app(MetaLeadService::class)->process($log->refresh());

    expect(Lead::count())->toBe(1);
});

it('skips a lead the CRM already imported from a CSV', function (): void {
    // The same Meta lead may already have arrived in an export of the same
    // campaign. Matching is on fb_lead_id, which is unique per submission.
    $existing = Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'phone' => '+919887127755',
        'fb_lead_id' => '1001',
        'source' => LeadSource::Facebook->value,
        'status' => LeadStatus::New->value,
    ]);

    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    $log = syncLog();
    app(MetaLeadService::class)->process($log);

    expect(Lead::count())->toBe(1)
        ->and($log->refresh()->status)->toBe(MetaSyncStatus::Skipped)
        ->and($log->lead_id)->toBe($existing->getKey());
});

it('imports two different submissions that share a phone number', function (): void {
    // The same person legitimately submits several forms. Phone must never be
    // the Meta duplicate key.
    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);
    app(MetaLeadService::class)->process(syncLog('1001'));

    fakeGraph(
        [['name' => 'phone_number', 'values' => ['+919887127755']]],
        overrides: ['id' => '2002'],
    );
    app(MetaLeadService::class)->process(syncLog('2002'));

    expect(Lead::count())->toBe(2)
        ->and(Lead::pluck('phone')->unique()->all())->toBe(['+919887127755']);
});

it('does nothing when asked to reprocess a settled record', function (): void {
    Http::fake();

    $log = syncLog();
    $log->forceFill(['status' => MetaSyncStatus::Success])->save();

    app(MetaLeadService::class)->process($log);

    Http::assertNothingSent();
    expect(Lead::count())->toBe(0);
});

// ─────────────── Failure handling ───────────────

it('retries a transient Graph API failure', function (): void {
    Http::fake(['*' => Http::response(['error' => ['code' => 2, 'message' => 'Temporary issue']], 500)]);

    $log = syncLog();

    expect(fn () => app(MetaLeadService::class)->process($log))
        ->toThrow(App\Services\Meta\Exceptions\MetaApiException::class);

    expect($log->refresh()->status)->toBe(MetaSyncStatus::Failed)
        ->and($log->error_message)->toContain('Temporary issue');
});

it('does not retry an expired access token', function (): void {
    // Error 190 will fail identically forever; burning five attempts on it only
    // buries the message that explains what to fix.
    Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Access token has expired']], 400)]);

    $log = syncLog();

    app(MetaLeadService::class)->process($log);

    expect($log->refresh()->status)->toBe(MetaSyncStatus::Failed)
        ->and($log->error_message)->toContain('expired')
        ->and(Lead::count())->toBe(0);
});

it('fails cleanly when no access token is configured anywhere', function (): void {
    Http::fake();
    $this->page->forceFill(['access_token' => null])->save();
    Setting::setValue('meta_access_token', '');

    $log = syncLog();
    app(MetaLeadService::class)->process($log->refresh());

    expect($log->refresh()->status)->toBe(MetaSyncStatus::Failed)
        ->and($log->error_message)->toContain('Meta Lead Settings');

    Http::assertNothingSent();
});

it('keeps a lead whose phone number is unusable, flagged for review', function (): void {
    fakeGraph([
        ['name' => 'full_name', 'values' => ['No Phone Person']],
        ['name' => 'email', 'values' => ['someone@example.com']],
    ]);

    app(MetaLeadService::class)->process(syncLog());

    $lead = Lead::first();

    // Worth keeping: it still carries attribution and answers.
    expect($lead)->not->toBeNull()
        ->and($lead->phone)->toBeNull()
        ->and($lead->phone_status)->toBe(PhoneStatus::Invalid)
        ->and($lead->email)->toBe('someone@example.com');
});

// ─────────────── Automatic Page discovery ───────────────

it('files a lead from a self-registered Page using the default clinic', function (): void {
    // The Page arrived from a webhook knowing only its own id — no clinic, no
    // name, no token. It must still produce a normal CRM lead.
    $this->page->forceFill([
        'clinic_id' => null,
        'page_name' => null,
        'access_token' => null,
        'last_synced_at' => null,
    ])->save();

    Setting::setValue('meta_default_clinic_id', (string) $this->clinic->getKey());

    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    app(MetaLeadService::class)->process(syncLog());

    $lead = Lead::first();

    expect($lead)->not->toBeNull()
        ->and($lead->clinic_id)->toBe($this->clinic->getKey())
        ->and($lead->page_id)->toBe('1122334455');
});

it('fills in the Page name from Meta on first use', function (): void {
    $this->page->forceFill(['page_name' => null, 'last_synced_at' => null])->save();

    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    app(MetaLeadService::class)->process(syncLog());

    $this->page->refresh();

    expect($this->page->page_name)->toBe('Skin Genious')
        ->and($this->page->last_synced_at)->not->toBeNull()
        ->and(Lead::first()->page_name)->toBe('Skin Genious');
});

it('falls back to the first clinic when no default is configured', function (): void {
    $this->page->forceFill(['clinic_id' => null])->save();
    Setting::setValue('meta_default_clinic_id', '');

    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    app(MetaLeadService::class)->process(syncLog());

    expect(Lead::first()->clinic_id)->toBe($this->clinic->getKey());
});

it('uses the system token when the Page has none of its own', function (): void {
    $this->page->forceFill(['access_token' => null])->save();

    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    app(MetaLeadService::class)->process(syncLog());

    // The Page token is derived from the system token rather than typed in.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '1122334455')
        && str_contains((string) $request->header('Authorization')[0], 'system-token'));

    // ...and the lead itself is then fetched with the derived Page token.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/1001')
        && str_contains((string) $request->header('Authorization')[0], 'derived-page-token'));

    expect(Lead::count())->toBe(1);
});

it('prefers a token saved on the Page over the system token', function (): void {
    $this->page->forceFill(['access_token' => 'page-specific-token'])->save();

    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    app(MetaLeadService::class)->process(syncLog());

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/1001')
        && str_contains((string) $request->header('Authorization')[0], 'page-specific-token'));
});

// ─────────────── The job wrapper ───────────────

it('processes the lead through the queued job', function (): void {
    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    $log = syncLog();

    (new ProcessMetaLeadJob($log->getKey()))->handle(app(MetaLeadService::class));

    expect(Lead::count())->toBe(1)
        ->and($log->refresh()->status)->toBe(MetaSyncStatus::Success);
});

it('running the job twice still produces one lead', function (): void {
    fakeGraph([['name' => 'phone_number', 'values' => ['+919887127755']]]);

    $log = syncLog();

    (new ProcessMetaLeadJob($log->getKey()))->handle(app(MetaLeadService::class));
    (new ProcessMetaLeadJob($log->getKey()))->handle(app(MetaLeadService::class));

    expect(Lead::count())->toBe(1);
});

it('survives the sync log having been deleted', function (): void {
    Http::fake();

    (new ProcessMetaLeadJob(9999))->handle(app(MetaLeadService::class));

    expect(Lead::count())->toBe(0);
});
