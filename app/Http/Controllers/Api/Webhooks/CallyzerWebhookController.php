<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Enums\Call\CallProvider;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Call\CallProviderManager;
use App\Services\Call\Providers\Callyzer\CallyzerCallMapper;
use App\Services\Call\CallWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Callyzer call notifications.
 *
 * Callyzer posts a call log, or a batch of them, as JSON. A batch is unwrapped
 * and each record is claimed independently, so one unusable record in a batch
 * of fifty cannot cost the clinic the other forty-nine.
 *
 * As with Exotel, everything past authentication answers 2xx: a non-2xx makes
 * Callyzer redeliver, and redelivering a payload the CRM could not parse
 * produces the same result every time while adding load.
 */
class CallyzerWebhookController extends Controller
{
    /**
     * Sanity bound on one delivery. A payload claiming thousands of calls is a
     * misconfiguration or an attempt to exhaust the queue, not a normal batch.
     */
    protected const MAX_BATCH = 500;

    public function __construct(
        protected CallWebhookService $webhooks,
        protected CallProviderManager $providers,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $adapter = $this->providers->get(CallProvider::Callyzer);

        if (! $adapter->validateWebhook($request)) {
            Log::channel('calls')->warning('Callyzer webhook rejected: the shared secret did not match.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorised'], 403);
        }

        if (! $adapter->isEnabled()) {
            return response()->json(['status' => 'disabled'], 200);
        }

        $records = $this->records($request);

        if ($records === []) {
            return response()->json(['status' => 'ignored', 'accepted' => 0], 200);
        }

        $accepted = 0;
        $duplicates = 0;

        foreach (array_slice($records, 0, self::MAX_BATCH) as $record) {
            $result = $this->webhooks->receive(CallProvider::Callyzer, $request, $record);

            if ($result['duplicate']) {
                $duplicates++;
            } elseif ($result['accepted']) {
                $accepted++;
            }
        }

        Log::channel('calls')->info('Callyzer webhook received.', [
            'records' => count($records),
            'accepted' => $accepted,
            'duplicates' => $duplicates,
        ]);

        return response()->json([
            'status' => 'accepted',
            'accepted' => $accepted,
            'duplicates' => $duplicates,
        ], 200);
    }

    /**
     * Pull the call records out of whatever envelope Callyzer used.
     *
     * Callyzer has posted a bare object, a bare array and several envelope keys
     * across versions, so all of them are accepted. Probing rather than
     * insisting means a change to their payload shape is not an outage.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function records(Request $request): array
    {
        // The body alone, never Request::all().
        //
        // Callyzer authenticates by putting ?secret=... in the URL, and
        // Request::all() folds query parameters in beside the body. A JSON list
        // of employees therefore arrives as [0 => employee, 'secret' => '...'],
        // which is no longer a list - so every list check below failed and the
        // whole thing, secret included, was handed to the mapper as one call.
        // That is why real deliveries were acknowledged with a 200 and then
        // silently dropped, while every test here passed: the tests posted a
        // flat object, where the stray key was harmless.
        $payload = $request->isJson()
            ? (array) $request->json()->all()
            : $request->all();

        // Belt and braces for the form-encoded case, where the body and query
        // genuinely do share one bag.
        unset($payload['secret'], $payload['token']);

        if ($payload === []) {
            return [];
        }

        foreach (['data', 'result', 'records', 'call_logs', 'callLogs', 'calls'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $candidate = $payload[$key];

                // A single record delivered under an envelope key.
                if (! array_is_list($candidate)) {
                    return CallyzerCallMapper::flatten([$candidate]);
                }

                return CallyzerCallMapper::flatten($candidate);
            }
        }

        if (array_is_list($payload)) {
            return CallyzerCallMapper::flatten($payload);
        }

        // A bare single record - either one call log, or one employee carrying
        // several. flatten() tells them apart.
        return CallyzerCallMapper::flatten([$payload]);
    }

    /**
     * The URL to paste into Callyzer's webhook configuration.
     */
    public static function webhookUrl(): string
    {
        $secret = Setting::getConfigured('callyzer_webhook_secret', config('calls.callyzer.webhook_secret'));

        $url = url('/api/webhooks/callyzer/calls');

        return filled($secret) ? $url . '?secret=' . urlencode((string) $secret) : $url;
    }
}
