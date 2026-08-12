<?php

declare(strict_types=1);

use App\Enums\MetaSyncStatus;
use App\Jobs\Lead\ProcessMetaLeadJob;
use App\Models\Clinic;
use App\Models\MetaLeadSyncLog;
use App\Models\MetaPage;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * The webhook endpoint's contract with Meta.
 *
 * Two things matter here above all: an unsigned request must never be able to
 * inject a lead, and a redelivered notification must never queue a second job.
 * Meta redelivers whenever it does not see a prompt 200, so the second case is
 * routine traffic rather than an edge case.
 */
uses(RefreshDatabase::class);

const APP_SECRET = 'test-app-secret';

beforeEach(function (): void {
    Setting::setValue('meta_app_secret', APP_SECRET);
    Setting::setValue('meta_verify_token', 'test-verify-token');

    // Created directly rather than via a factory: the project has none for
    // Clinic, and the slug fills itself in from the name.
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

/**
 * Sign a payload the way Meta does, over the exact bytes sent.
 */
function signedPost(array $payload): \Illuminate\Testing\TestResponse
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return test()->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $body, APP_SECRET),
        ],
        $body,
    );
}

function leadgenPayload(string $leadgenId, string $pageId = '1122334455'): array
{
    return [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => 1755000000,
            'changes' => [[
                'field' => 'leadgen',
                'value' => [
                    'leadgen_id' => $leadgenId,
                    'page_id' => $pageId,
                    'form_id' => '9988776655',
                    'ad_id' => '5544332211',
                    'created_time' => 1755000000,
                ],
            ]],
        ]],
    ];
}

// ─────────────── Verification handshake ───────────────

it('echoes the challenge when the verify token matches', function (): void {
    $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=abc123')
        ->assertOk()
        ->assertSee('abc123');
});

it('rejects verification when the token is wrong', function (): void {
    $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=abc123')
        ->assertForbidden();
});

it('rejects verification when no token is configured', function (): void {
    Setting::setValue('meta_verify_token', '');

    $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=&hub_challenge=abc123')
        ->assertForbidden();
});

// ─────────────── Signature ───────────────

it('rejects a payload with no signature', function (): void {
    Queue::fake();

    $this->postJson('/api/webhooks/meta', leadgenPayload('1001'))
        ->assertForbidden();

    Queue::assertNothingPushed();
    expect(MetaLeadSyncLog::count())->toBe(0);
});

it('rejects a payload whose signature does not match the body', function (): void {
    Queue::fake();

    $body = json_encode(leadgenPayload('1001'));

    $this->call('POST', '/api/webhooks/meta', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', 'a different body', APP_SECRET),
    ], $body)->assertForbidden();

    Queue::assertNothingPushed();
});

it('refuses everything when no app secret is configured', function (): void {
    // Failing closed matters: an endpoint that cannot verify signatures would
    // otherwise accept a lead from anyone who guessed the URL.
    Setting::setValue('meta_app_secret', '');
    Queue::fake();

    signedPost(leadgenPayload('1001'))->assertForbidden();

    Queue::assertNothingPushed();
});

// ─────────────── Accepting a lead ───────────────

it('records the lead and queues it', function (): void {
    Queue::fake();

    signedPost(leadgenPayload('1001'))
        ->assertOk()
        ->assertSee('EVENT_RECEIVED');

    $log = MetaLeadSyncLog::first();

    expect($log)->not->toBeNull()
        ->and($log->leadgen_id)->toBe('1001')
        ->and($log->status)->toBe(MetaSyncStatus::Pending)
        ->and($log->meta_page_id)->toBe($this->page->getKey())
        ->and($log->payload['form_id'])->toBe('9988776655');

    Queue::assertPushed(ProcessMetaLeadJob::class, 1);
});

it('does not perform any Graph API call inside the request', function (): void {
    // Meta treats a slow response as a delivery failure and resends, so a Graph
    // round trip in the request cycle would actively manufacture duplicates.
    Queue::fake();
    Http::fake();

    signedPost(leadgenPayload('1001'))->assertOk();

    Http::assertNothingSent();
});

// ─────────────── Duplicate delivery ───────────────

it('queues only one job when Meta redelivers the same lead', function (): void {
    Queue::fake();

    signedPost(leadgenPayload('1001'))->assertOk();
    signedPost(leadgenPayload('1001'))->assertOk();
    signedPost(leadgenPayload('1001'))->assertOk();

    expect(MetaLeadSyncLog::where('leadgen_id', '1001')->count())->toBe(1);

    Queue::assertPushed(ProcessMetaLeadJob::class, 1);
});

it('still answers 200 to a redelivery so Meta stops retrying', function (): void {
    Queue::fake();

    signedPost(leadgenPayload('1001'));

    signedPost(leadgenPayload('1001'))->assertOk();
});

// ─────────────── Payloads it cannot use ───────────────

it('records a lead from an unconfigured Page without queueing it', function (): void {
    Queue::fake();

    signedPost(leadgenPayload('2002', pageId: '9999999999'))->assertOk();

    $log = MetaLeadSyncLog::where('leadgen_id', '2002')->first();

    expect($log->status)->toBe(MetaSyncStatus::Failed)
        ->and($log->meta_page_id)->toBeNull()
        ->and($log->error_message)->toContain('9999999999');

    Queue::assertNothingPushed();
});

it('ignores a Page that has been deactivated', function (): void {
    Queue::fake();
    $this->page->update(['is_active' => false]);

    signedPost(leadgenPayload('3003'))->assertOk();

    Queue::assertNothingPushed();
});

it('ignores changes that are not leadgen events', function (): void {
    Queue::fake();

    $payload = leadgenPayload('4004');
    $payload['entry'][0]['changes'][0]['field'] = 'feed';

    signedPost($payload)->assertOk();

    expect(MetaLeadSyncLog::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects an object it does not handle', function (): void {
    Queue::fake();

    $payload = leadgenPayload('5005');
    $payload['object'] = 'instagram';

    signedPost($payload)->assertNotFound();
});

it('survives a leadgen change with no leadgen_id', function (): void {
    Queue::fake();

    $payload = leadgenPayload('6006');
    unset($payload['entry'][0]['changes'][0]['value']['leadgen_id']);

    signedPost($payload)->assertOk();

    expect(MetaLeadSyncLog::count())->toBe(0);
});
