<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MetaSyncStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Lead\ProcessMetaLeadJob;
use App\Models\MetaLeadSyncLog;
use App\Models\MetaPage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Meta Lead Ads notifications.
 *
 * The controller's only job is to prove the request came from Meta, write down
 * that the lead exists, and hand off. Everything expensive happens on the
 * queue: Meta treats a slow response as a delivery failure and resends, so a
 * Graph API call in here would create the very duplicates the rest of this
 * integration works to prevent.
 */
class MetaLeadWebhookController extends Controller
{
    /**
     * Meta's subscription handshake.
     *
     * Called once when the callback URL is saved in the App Dashboard, and
     * again whenever the subscription is edited.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = $this->verifyToken();

        if ($mode === 'subscribe' && filled($verifyToken) && hash_equals((string) $verifyToken, (string) $token)) {
            Log::channel('meta_leads')->info('Meta webhook verified.');

            // Meta requires the raw challenge echoed back as plain text.
            return response((string) $challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::channel('meta_leads')->warning('Meta webhook verification rejected.', [
            'mode' => $mode,
            'token_configured' => filled($verifyToken),
        ]);

        return response('Invalid verify token', 403)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Handle a leadgen notification.
     *
     * Always answers 200 once the signature checks out, even for a payload it
     * cannot use. A non-200 makes Meta retry the same notification for hours,
     * and an unknown Page or malformed entry will not become usable on the
     * fifth delivery — it needs an administrator, so it is logged instead.
     */
    public function handle(Request $request): Response|JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            Log::channel('meta_leads')->warning('Meta webhook rejected: signature mismatch.');

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();

        if (($payload['object'] ?? null) !== config('meta.webhook.object', 'page')) {
            return response()->json(['error' => 'Unsupported object'], 404);
        }

        $queued = 0;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== config('meta.webhook.field', 'leadgen')) {
                    continue;
                }

                if ($this->queueLead((array) ($change['value'] ?? []))) {
                    $queued++;
                }
            }
        }

        Log::channel('meta_leads')->info('Meta leadgen webhook received.', ['queued' => $queued]);

        return response('EVENT_RECEIVED', 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Record one lead and dispatch it.
     *
     * firstOrCreate against the unique leadgen_id is the first duplicate
     * defence: a redelivered notification finds the existing row and stops
     * here, never reaching the queue.
     *
     * @param  array<string, mixed>  $value
     */
    protected function queueLead(array $value): bool
    {
        $leadgenId = trim((string) ($value['leadgen_id'] ?? ''));

        if ($leadgenId === '') {
            Log::channel('meta_leads')->warning('Meta leadgen change carried no leadgen_id.', ['value' => $value]);

            return false;
        }

        // A Page the CRM has never seen registers itself here rather than
        // having its lead rejected. Only the id is stored — resolving the name
        // needs a Graph call, which belongs on the queue.
        $page = MetaPage::resolveFromWebhook(isset($value['page_id']) ? (string) $value['page_id'] : null);

        if ($page === null) {
            // Reached only when the Page was deliberately deactivated, or the
            // payload carried no page_id at all. Recorded either way, so there
            // is a trace rather than silence.
            MetaLeadSyncLog::firstOrCreate(
                ['leadgen_id' => $leadgenId],
                [
                    'status' => MetaSyncStatus::Failed,
                    'payload' => $value,
                    'error_message' => sprintf(
                        'Meta Page %s is not accepting leads.',
                        $value['page_id'] ?? 'unknown',
                    ),
                    'processed_at' => now(),
                ],
            );

            Log::channel('meta_leads')->warning('Meta lead from an inactive or unidentified Page.', [
                'page_id' => $value['page_id'] ?? null,
                'leadgen_id' => $leadgenId,
            ]);

            return false;
        }

        if ($page->wasRecentlyCreated) {
            Log::channel('meta_leads')->info('Registered a new Meta Page from its first lead.', [
                'page_id' => $page->page_id,
            ]);
        }

        $syncLog = MetaLeadSyncLog::firstOrCreate(
            ['leadgen_id' => $leadgenId],
            [
                'meta_page_id' => $page->getKey(),
                'status' => MetaSyncStatus::Pending,
                'payload' => $value,
            ],
        );

        if (! $syncLog->wasRecentlyCreated) {
            Log::channel('meta_leads')->info('Meta webhook redelivered; already recorded.', [
                'leadgen_id' => $leadgenId,
                'status' => $syncLog->status->value,
            ]);

            return false;
        }

        ProcessMetaLeadJob::dispatch($syncLog->getKey());

        return true;
    }

    /**
     * Validate Meta's HMAC of the raw request body.
     *
     * The raw body must be used rather than the decoded array, because any
     * re-encoding changes the bytes and therefore the signature.
     */
    protected function signatureIsValid(Request $request): bool
    {
        if (! config('meta.webhook.verify_signature', true)) {
            return true;
        }

        $appSecret = Setting::getValue('meta_app_secret', config('meta.credentials.app_secret'));

        if (blank($appSecret)) {
            // Refuse rather than wave it through: an unsigned endpoint would
            // accept a lead from anyone who guessed the URL.
            Log::channel('meta_leads')->error('Meta webhook cannot verify signatures: no app secret is configured.');

            return false;
        }

        $header = (string) $request->header((string) config('meta.webhook.signature_header', 'X-Hub-Signature-256'), '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), (string) $appSecret);

        return hash_equals($expected, $header);
    }

    protected function verifyToken(): ?string
    {
        return Setting::getValue('meta_verify_token', config('meta.credentials.verify_token'));
    }
}
