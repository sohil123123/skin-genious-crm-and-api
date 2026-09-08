<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Enums\Call\CallProvider;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\Call\CallProviderManager;
use App\Services\Call\CallWebhookService;
use App\Services\Call\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Exotel call notifications.
 *
 * The controller proves the request is genuine, writes down that it happened,
 * and hands off. Nothing else. Exotel's Passthru applet can run synchronously
 * inside the call flow — the caller is on the line while this responds — so
 * anything slow here is heard as dead air by a real person, and a Passthru that
 * does not answer promptly is retried, which is how duplicate calls begin.
 *
 * The response codes are chosen for how Exotel reacts to them, not for
 * correctness in the abstract:
 *
 *   403  bad or missing secret. Worth telling the caller it was rejected.
 *   200  everything else, including payloads the CRM cannot use — because a
 *        non-2xx makes Exotel redeliver for hours, and a malformed payload will
 *        not become valid on the fifth attempt. It needs a person, so it is
 *        archived and surfaced rather than retried.
 */
class ExotelCallWebhookController extends Controller
{
    public function __construct(
        protected CallWebhookService $webhooks,
        protected CallProviderManager $providers,
    ) {}

    /**
     * Handle a Passthru or status callback delivery.
     *
     * Accepts GET and POST: the Passthru applet sends URL-encoded query
     * parameters over GET, while Exotel's status callbacks POST JSON.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $adapter = $this->providers->get(CallProvider::Exotel);

        if (! $adapter->validateWebhook($request)) {
            Log::channel('calls')->warning('Exotel webhook rejected: the shared secret did not match.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorised'], 403);
        }

        if (! $adapter->isEnabled()) {
            // Deliberately switched off. Answered 200 so Exotel does not
            // redeliver for hours against an integration nobody wants running.
            return response()->json(['status' => 'disabled'], 200);
        }

        // query() and input() are merged so one handler covers both the GET
        // Passthru and the POST callback without caring which arrived.
        $payload = array_merge($request->query(), $request->post(), (array) $request->json()->all());

        $result = $this->webhooks->receive(CallProvider::Exotel, $request, $payload);

        return response()->json([
            'status' => $result['duplicate'] ? 'duplicate' : ($result['accepted'] ? 'accepted' : 'ignored'),
        ], 200);
    }

    /**
     * The legacy screen-pop endpoint, kept working exactly as it was.
     *
     * The clinic's reception software calls this while the phone is ringing and
     * reads {"select":"existing"|"new"} to decide which screen to open. That
     * contract predates this system and must not change — so the answer is
     * computed first and returned unconditionally, and the unified ingestion is
     * a side effect wrapped in its own try/catch.
     *
     * If anything in the new call pipeline throws, the receptionist still gets
     * their screen pop. That is the right priority: a missing call record is
     * recoverable from the archived payload, a receptionist staring at a blank
     * screen while a patient waits is not.
     */
    public function screenPop(Request $request): JsonResponse
    {
        $payload = array_merge($request->query(), $request->post());

        $exists = $this->callerIsKnown($payload);

        try {
            $adapter = $this->providers->get(CallProvider::Exotel);

            if ($adapter->isEnabled() && $adapter->validateWebhook($request)) {
                $this->webhooks->receive(CallProvider::Exotel, $request, $payload);
            }
        } catch (\Throwable $exception) {
            // Swallowed on purpose. The screen pop is the contract; call
            // recording is the bonus.
            Log::channel('calls')->error('Screen-pop ingestion failed; the pop response was unaffected.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['select' => $exists ? 'existing' : 'new']);
    }

    /**
     * Whether the caller is already a patient.
     *
     * Matched on the last ten digits, which is how the same person survives
     * being stored as "+919876543210" by one system and "9876543210" by
     * another.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function callerIsKnown(array $payload): bool
    {
        // The CallSid and direction guards are carried over from the original
        // endpoint unchanged. They decide when the pop says "new", and the
        // reception software's behaviour depends on that answer.
        if (blank($payload['CallSid'] ?? null)) {
            return false;
        }

        $direction = strtolower((string) ($payload['Direction'] ?? ''));

        if (! str_contains($direction, 'incoming') && ! str_contains($direction, 'inbound')) {
            return false;
        }

        $mobile = $payload['CallFrom'] ?? $payload['From'] ?? null;

        if (blank($mobile)) {
            return false;
        }

        $key = app(PhoneNumberNormalizer::class)->matchKey((string) $mobile);

        if (blank($key)) {
            return false;
        }

        return User::query()
            ->active()
            ->where(fn ($query) => $query
                ->where('mobile', $key)
                ->orWhere('mobile', 'LIKE', '%' . $key))
            ->exists();
    }

    /**
     * The URL to configure in the Exotel flow, secret included.
     *
     * Built here rather than typed by hand so the secret in the URL and the one
     * the endpoint checks can never drift apart.
     */
    public static function webhookUrl(): string
    {
        $secret = Setting::getConfigured('exotel_webhook_secret', config('calls.exotel.webhook.secret'));
        $key = (string) config('calls.exotel.webhook.secret_query_key', 'token');

        $url = url('/api/webhooks/exotel/calls');

        return filled($secret)
            ? $url . '?' . $key . '=' . urlencode((string) $secret)
            : $url;
    }

    /**
     * The URL for the Passthru that runs at Call Start.
     *
     * Deliberately the legacy screen-pop endpoint rather than the unified one:
     * that applet's response drives the flow's Switch Case, so it must keep
     * returning {"select": ...}. Adding the secret is what lets the same
     * request also open the call record and announce it to the clinic's
     * screens — the popup exists because this endpoint fires while the phone
     * is still ringing, which no later Passthru does.
     */
    public static function screenPopUrl(): string
    {
        $secret = Setting::getConfigured('exotel_webhook_secret', config('calls.exotel.webhook.secret'));
        $key = (string) config('calls.exotel.webhook.secret_query_key', 'token');

        $url = url('/api/exotel/webhook');

        return filled($secret)
            ? $url . '?' . $key . '=' . urlencode((string) $secret)
            : $url;
    }
}
