<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Models\MetaPage;
use App\Models\Setting;
use App\Services\Meta\Exceptions\MetaApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place in the application that talks to the Meta Graph API.
 *
 * Everything version-, transport- and error-shaped lives here so that callers
 * deal in arrays and exceptions rather than HTTP. That matters most for the
 * distinction this class draws between two kinds of failure: a transient one
 * (timeout, 500, rate limit) which the caller should retry, and a permanent one
 * (expired token, deleted lead, missing permission) which will fail identically
 * forever and must not be retried.
 */
class MetaGraphApiService
{
    /**
     * Meta error codes that will never succeed on retry.
     *
     * 100 invalid parameter / unknown field, 190 invalid or expired token,
     * 200 and 10 permission denied, 803 object does not exist.
     */
    protected const PERMANENT_ERROR_CODES = [10, 100, 190, 200, 803];

    /**
     * Fetch one lead by its leadgen id.
     *
     * The requested field list goes beyond what Meta returns by default; the
     * ad-level attribution in it depends on the token's permissions, so fields
     * that come back absent are treated as unknown rather than as an error.
     *
     * @return array<string, mixed>
     *
     * @throws MetaApiException
     */
    public function getLead(string $leadgenId, string $accessToken): array
    {
        return $this->get($leadgenId, $accessToken, [
            'fields' => implode(',', (array) config('meta.lead_fields')),
        ]);
    }

    /**
     * Look up a lead form's name.
     *
     * The lead node carries form_id but never the form's name, so this is a
     * second round trip. One campaign can deliver thousands of leads from a
     * single form and the name effectively never changes, so it is cached.
     */
    public function getFormName(string $formId, string $accessToken): ?string
    {
        $key = config('meta.cache.prefix') . ':form_name:' . $formId;

        // A failed lookup caches nothing, so a transient error does not pin a
        // null name in place for a day.
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        try {
            $form = $this->get($formId, $accessToken, ['fields' => 'id,name']);
            $name = $form['name'] ?? null;
        } catch (MetaApiException $exception) {
            // A missing form name costs nothing — the id is already stored.
            Log::channel('meta_leads')->info('Could not resolve Meta form name.', [
                'form_id' => $formId,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        Cache::put($key, $name ?? '', (int) config('meta.cache.form_ttl'));

        return $name;
    }

    /**
     * Fetch a Page's name, used when connecting a Page in the settings screen.
     */
    public function getPageName(string $pageId, string $accessToken): ?string
    {
        try {
            return $this->get($pageId, $accessToken, ['fields' => 'id,name'])['name'] ?? null;
        } catch (MetaApiException) {
            return null;
        }
    }

    /**
     * Derive a Page access token from the system token.
     *
     * Meta wants a Page token to read a Page's leads. Asking an administrator
     * to paste one in per Page is exactly the manual step this integration is
     * meant to remove, so it is fetched instead: a User token with
     * pages_show_list can read `access_token` off any Page it administers.
     *
     * Returns null when the system token is already a Page token, or lacks the
     * permission — the caller then falls back to using it directly, which is
     * the correct behaviour in both cases.
     */
    public function getDerivedPageToken(string $pageId, string $systemToken): ?string
    {
        $key = config('meta.cache.prefix') . ':page_token:' . $pageId;

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        try {
            $token = $this->get($pageId, $systemToken, ['fields' => 'access_token'])['access_token'] ?? null;
        } catch (MetaApiException $exception) {
            Log::channel('meta_leads')->info('Could not derive a Page token; falling back to the system token.', [
                'page_id' => $pageId,
                'reason' => $exception->getMessage(),
            ]);

            $token = null;
        }

        // A short TTL rather than a long one: a revoked token should stop being
        // served within the hour without anyone clearing a cache.
        Cache::put($key, $token ?? '', (int) config('meta.cache.page_token_ttl', 3600));

        return $token;
    }

    /**
     * Forget a derived Page token, so the next call fetches a fresh one.
     */
    public function forgetDerivedPageToken(string $pageId): void
    {
        Cache::forget(config('meta.cache.prefix') . ':page_token:' . $pageId);
    }

    /**
     * The token every Page-scoped call should use.
     *
     * One place decides this so the settings screen, the connection test and
     * the lead importer can never disagree about which credential is in play.
     *
     * Order: a token saved on the Page, then one derived from the system token,
     * then the system token itself — which is already correct when it is a Page
     * token, or a User token carrying leads_retrieval.
     */
    public function tokenForPage(MetaPage $page): ?string
    {
        if (filled($page->access_token)) {
            return (string) $page->access_token;
        }

        $systemToken = MetaPage::systemAccessToken();

        if ($systemToken === null) {
            return null;
        }

        return $this->getDerivedPageToken($page->page_id, $systemToken) ?? $systemToken;
    }

    /**
     * Subscribe a Page to leadgen notifications.
     *
     * Without this the app receives nothing, no matter how the webhook is
     * configured in the App Dashboard — the subscription is per Page.
     *
     * @throws MetaApiException
     */
    public function subscribePageToLeadgen(MetaPage $page): bool
    {
        $response = $this->post($page->page_id . '/subscribed_apps', $this->tokenForPage($page), [
            'subscribed_fields' => config('meta.webhook.field', 'leadgen'),
        ]);

        return (bool) ($response['success'] ?? false);
    }

    /**
     * Which fields a Page is currently subscribed to, for the connection test.
     *
     * @return array<int, string>
     *
     * @throws MetaApiException
     */
    public function getSubscribedFields(MetaPage $page): array
    {
        $response = $this->get($page->page_id . '/subscribed_apps', $this->tokenForPage($page), [
            'fields' => 'subscribed_fields',
        ]);

        return $response['data'][0]['subscribed_fields'] ?? [];
    }

    /**
     * Confirm a Page's token works and report what it can reach.
     *
     * Returns a result rather than throwing, because the settings screen wants
     * to show the user why a connection failed, not a stack trace.
     *
     * @return array{ok: bool, page_name: ?string, subscribed: bool, message: string}
     */
    public function testConnection(MetaPage $page): array
    {
        $token = $this->tokenForPage($page);

        if ($token === null) {
            return [
                'ok' => false,
                'page_name' => null,
                'subscribed' => false,
                'message' => 'No Meta access token is configured. Add one on the Meta Lead Settings screen.',
            ];
        }

        try {
            $name = $this->getPageName($page->page_id, $token);

            if ($name === null) {
                return [
                    'ok' => false,
                    'page_name' => null,
                    'subscribed' => false,
                    'message' => 'The token was rejected, or it does not have access to this Page.',
                ];
            }

            $fields = $this->getSubscribedFields($page);
            $subscribed = in_array((string) config('meta.webhook.field', 'leadgen'), $fields, true);

            return [
                'ok' => true,
                'page_name' => $name,
                'subscribed' => $subscribed,
                'message' => $subscribed
                    ? sprintf('Connected to "%s" and subscribed to leadgen.', $name)
                    : sprintf('Connected to "%s", but it is not subscribed to leadgen yet.', $name),
            ];
        } catch (MetaApiException $exception) {
            return [
                'ok' => false,
                'page_name' => null,
                'subscribed' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    // ──────────────── Transport ────────────────

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws MetaApiException
     */
    protected function get(string $path, ?string $accessToken, array $query = []): array
    {
        return $this->send('get', $path, $accessToken, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws MetaApiException
     */
    protected function post(string $path, ?string $accessToken, array $payload = []): array
    {
        return $this->send('post', $path, $accessToken, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws MetaApiException
     */
    protected function send(string $method, string $path, ?string $accessToken, array $data): array
    {
        if (blank($accessToken)) {
            throw MetaApiException::permanent('No Meta access token is configured for this request.');
        }

        $url = $this->url($path);

        try {
            $response = $this->client($accessToken)->{$method}($url, $data);
        } catch (Throwable $exception) {
            // Connection refused, DNS failure, timeout — all worth retrying.
            throw MetaApiException::transient(
                'Could not reach the Meta Graph API: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->errorFrom($response);
    }

    protected function client(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->timeout((int) config('meta.api.timeout', 20))
            ->connectTimeout((int) config('meta.api.connect_timeout', 10))
            ->acceptJson()
            // Only transport-level failures are retried here. HTTP errors are
            // classified below, because retrying an expired token is pointless
            // and retrying a rate limit needs a longer wait than this.
            ->retry(
                (int) config('meta.api.retry_times', 3),
                (int) config('meta.api.retry_sleep_ms', 500),
                throw: false,
            );
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('meta.api.base_url'), '/')
            . '/' . trim($this->apiVersion(), '/')
            . '/' . ltrim($path, '/');
    }

    /**
     * The Graph version to call.
     *
     * Overridable from settings so a version bump does not need a deploy, but
     * pinned by config otherwise — an unpinned version would start failing on
     * Meta's release schedule rather than ours.
     */
    public function apiVersion(): string
    {
        $configured = Setting::getValue('meta_api_version');

        return filled($configured) ? (string) $configured : (string) config('meta.api.version');
    }

    /**
     * Turn a Graph API error body into a typed exception.
     *
     * Meta reports failures as HTTP 400 with a structured error object far more
     * often than it uses distinct status codes, so the body is what decides
     * whether a retry has any chance.
     */
    protected function errorFrom(Response $response): MetaApiException
    {
        $error = (array) ($response->json('error') ?? []);
        $code = (int) ($error['code'] ?? 0);
        $subcode = $error['error_subcode'] ?? null;

        $message = sprintf(
            'Meta Graph API error %d%s: %s',
            $code,
            $subcode !== null ? '/' . $subcode : '',
            $error['message'] ?? ('HTTP ' . $response->status()),
        );

        // Never log the token; the message above is deliberately built from the
        // error body alone.
        Log::channel('meta_leads')->warning('Meta Graph API request failed.', [
            'status' => $response->status(),
            'code' => $code,
            'subcode' => $subcode,
            'type' => $error['type'] ?? null,
        ]);

        if (in_array($code, self::PERMANENT_ERROR_CODES, true)) {
            return MetaApiException::permanent($message);
        }

        // 4 and 17 are Meta's rate limits, 613 a throttle: all worth retrying
        // after the job's backoff.
        return MetaApiException::transient($message);
    }
}
