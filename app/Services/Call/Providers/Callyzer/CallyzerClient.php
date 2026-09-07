<?php

declare(strict_types=1);

namespace App\Services\Call\Providers\Callyzer;

use App\Models\Setting;
use App\Services\Call\Exceptions\CallProviderException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only place in the application that talks to the Callyzer API.
 *
 * Two things live here that would otherwise be scattered across every caller.
 *
 * The first is the rate limit. Callyzer allows roughly one request every two
 * seconds, and exceeding it returns 429s that are indistinguishable from an
 * outage — so the client paces itself through a cache lock rather than
 * discovering the limit by tripping over it. The lock is shared across
 * processes, which matters: two queue workers each politely waiting two seconds
 * still send two requests at once.
 *
 * The second is the retryable/permanent distinction. A 429 or a 503 should be
 * retried; a 401 will fail identically forever and must not be, because
 * retrying it wastes the job's attempts and hides the real problem — an expired
 * token — behind a wall of timeouts.
 *
 * Endpoint paths come from config because Callyzer has moved them between
 * versions, and a constant here would turn their next release into an outage.
 */
class CallyzerClient
{
    /**
     * Fetch a page of call history.
     *
     * @param  array<string, mixed>  $filters
     * @return array{records: array<int, array<string, mixed>>, has_more: bool, total: ?int, status: int}
     */
    public function callHistory(Carbon $from, Carbon $to, int $page = 1, array $filters = []): array
    {
        $dateFormat = (string) config('calls.callyzer.sync.date_format', 'Y-m-d');
        $pageSize = (int) config('calls.callyzer.sync.page_size', 100);

        $body = array_filter([
            'callStartDate' => $from->format($dateFormat),
            'callEndDate' => $to->format($dateFormat),
            'page' => $page,
            'pageSize' => $pageSize,
            'employeeNumbers' => $filters['employee_numbers'] ?? null,
            'clientNumbers' => $filters['client_numbers'] ?? null,
            'callTypes' => $filters['call_types'] ?? null,
            'excludeNumbers' => $filters['exclude_numbers'] ?? null,
            'recordFrom' => $filters['record_from'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $response = $this->request(
            (string) config('calls.callyzer.endpoints.call_history', '/admin/api/call/callHistory'),
            $body,
        );

        return $this->readCollection($response, $pageSize);
    }

    /**
     * Fetch specific calls by their Callyzer ids.
     *
     * Used to refresh a single call — most often to pick up a recording URL
     * that was not ready when the call first arrived.
     *
     * @param  array<int, string|int>  $ids
     * @return array{records: array<int, array<string, mixed>>, has_more: bool, total: ?int, status: int}
     */
    public function callHistoryByIds(array $ids): array
    {
        if ($ids === []) {
            return ['records' => [], 'has_more' => false, 'total' => 0, 'status' => 200];
        }

        $response = $this->request(
            (string) config('calls.callyzer.endpoints.call_history_by_ids', '/admin/api/call/callHistoryByIds'),
            ['ids' => array_values($ids)],
        );

        return $this->readCollection($response, count($ids));
    }

    /**
     * Whether the integration has everything it needs to make a request.
     */
    public function isConfigured(): bool
    {
        return filled($this->token());
    }

    /**
     * Send one request, paced to the provider's rate limit.
     *
     * @param  array<string, mixed>  $body
     */
    protected function request(string $path, array $body): Response
    {
        $token = $this->token();

        if (blank($token)) {
            throw CallProviderException::permanent('No Callyzer API token is configured.');
        }

        $this->waitForRateLimitSlot();

        try {
            $response = $this->http($token)->post($this->url($path), $body);
        } catch (ConnectionException $exception) {
            throw CallProviderException::transient('Could not reach Callyzer: ' . $exception->getMessage());
        }

        if ($response->successful()) {
            return $response;
        }

        $this->throwForStatus($response);
    }

    protected function throwForStatus(Response $response): never
    {
        $status = $response->status();
        $body = mb_substr($response->body(), 0, 500);

        if ($status === 429) {
            $retryAfter = (int) $response->header('Retry-After') ?: null;

            Log::channel('calls')->warning('Callyzer rate limit hit.', [
                'retry_after' => $retryAfter,
            ]);

            throw CallProviderException::rateLimited(
                'Callyzer rate limit exceeded. Slow the sync down or reduce its window.',
                $retryAfter,
            );
        }

        // 401 and 403 mean the token is wrong or revoked. No number of retries
        // fixes that, and pretending otherwise buries the real cause.
        if (in_array($status, [400, 401, 403, 404, 422], true)) {
            throw CallProviderException::permanent(
                sprintf('Callyzer rejected the request with HTTP %d: %s', $status, $body),
                $status,
            );
        }

        throw CallProviderException::transient(
            sprintf('Callyzer returned HTTP %d: %s', $status, $body),
            $status,
        );
    }

    /**
     * Read a page out of the response, whatever shape it arrives in.
     *
     * Callyzer has used several envelope keys across versions. Probing for the
     * first array of records rather than insisting on one key means an envelope
     * change costs nothing, which is the whole reason this integration reads
     * defensively.
     *
     * @return array{records: array<int, array<string, mixed>>, has_more: bool, total: ?int, status: int}
     */
    protected function readCollection(Response $response, int $pageSize): array
    {
        $json = (array) $response->json();

        $records = [];

        foreach (['result', 'data', 'records', 'call_logs', 'callLogs', 'calls'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $candidate = $json[$key];

                // Some versions nest one level deeper, e.g. data.records.
                if (isset($candidate['records']) && is_array($candidate['records'])) {
                    $candidate = $candidate['records'];
                }

                if (array_is_list($candidate)) {
                    $records = $candidate;
                    break;
                }
            }
        }

        // A bare list with no envelope at all.
        if ($records === [] && array_is_list($json)) {
            $records = $json;
        }

        $meta = (array) ($json['meta'] ?? $json['pagination'] ?? []);
        $total = isset($meta['total']) ? (int) $meta['total'] : null;

        // Callyzer nests calls inside the employee who made them; flatten()
        // unwraps that and merges the employee context down onto each call.
        $calls = CallyzerCallMapper::flatten($records);

        // Trusting a "has_more" flag that may not exist would silently stop the
        // sync after one page. A full page is the reliable signal that another
        // one may follow.
        //
        // Which unit Callyzer counts a page in - employees or calls - is not
        // documented and has differed between versions, so a full page by
        // either measure asks for another. Guessing wrong in this direction
        // costs one extra request; guessing wrong in the other silently drops
        // every call past the first page.
        $hasMore = array_key_exists('has_more', $meta)
            ? (bool) $meta['has_more']
            : (count($records) >= $pageSize || count($calls) >= $pageSize);

        return [
            'records' => $calls,
            'has_more' => $hasMore,
            'total' => $total,
            'status' => $response->status(),
        ];
    }

    /**
     * Block until this process may make a request.
     *
     * A cache lock rather than a sleep, because the limit is per account and
     * two workers sleeping independently still collide. Giving up after the
     * configured wait lets the job retry later instead of holding a worker
     * hostage behind a backlog.
     */
    protected function waitForRateLimitSlot(): void
    {
        $perSeconds = max(1, (int) config('calls.callyzer.rate_limit.per_seconds', 2));
        $maxWait = (int) config('calls.callyzer.rate_limit.wait_seconds', 30);
        $key = (string) config('calls.callyzer.rate_limit.lock_key', 'callyzer:api');

        $lock = Cache::lock($key . ':gate', $perSeconds);

        // block() without a callback, deliberately.
        //
        // Passing one inverts every part of this method's intent. Laravel runs
        // the callback, returns ITS value, and releases the lock in a finally.
        // So `block($maxWait, fn () => null)` returned null on success - which
        // is falsy, so the guard below fired every single time and the sync
        // reported a rate limit it had never actually hit. It also released the
        // lock immediately, destroying the interval this exists to enforce.
        // Meanwhile a real timeout throws LockTimeoutException, which nothing
        // caught, so the one case the guard was written for never reached it.
        //
        // Without a callback: true on acquisition, LockTimeoutException on
        // timeout, and no release - so the lock expires on its own after
        // $perSeconds, which is what spaces the requests out.
        try {
            $lock->block($maxWait);
        } catch (LockTimeoutException) {
            throw CallProviderException::rateLimited(
                'Timed out waiting for a Callyzer rate limit slot.',
            );
        }
    }

    protected function http(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('calls.callyzer.http.timeout', 30))
            ->connectTimeout((int) config('calls.callyzer.http.connect_timeout', 10))
            // Transport-level retries only, and spaced past the rate limit so a
            // retry does not immediately earn a 429 of its own.
            ->retry(
                (int) config('calls.callyzer.http.retry_times', 2),
                (int) config('calls.callyzer.http.retry_sleep_ms', 2000),
                throw: false,
            );
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    protected function baseUrl(): string
    {
        return (string) Setting::getConfigured('callyzer_base_url', config('calls.callyzer.base_url'));
    }

    /**
     * The API token, from settings first so it can be rotated without a deploy.
     */
    protected function token(): ?string
    {
        $token = Setting::getConfigured('callyzer_api_token', config('calls.callyzer.api_token'));

        return filled($token) ? (string) $token : null;
    }
}
