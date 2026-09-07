<?php

declare(strict_types=1);

namespace App\Services\Call\Providers\Exotel;

use App\Models\Setting;
use App\Services\Call\Exceptions\CallProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The only place in the application that calls the Exotel REST API.
 *
 * Exotel pushes calls at the CRM rather than being polled, so this exists for
 * the gaps in that push: fetching a call whose Passthru never arrived, and —
 * the common case — collecting a recording URL that was not yet published when
 * the last webhook fired. Exotel finalises recordings a short while after the
 * call ends, so a flow that hangs up promptly reports no RecordingUrl at all.
 *
 * Authentication is HTTP Basic with the API key and token, which are held in
 * settings so they can be rotated without a deploy.
 */
class ExotelClient
{
    public function isConfigured(): bool
    {
        return filled($this->accountSid())
            && filled($this->apiKey())
            && filled($this->apiToken());
    }

    /**
     * Fetch one call's details by its CallSid.
     *
     * @return array<string, mixed>|null null when Exotel has no such call
     */
    public function getCall(string $callSid): ?array
    {
        $response = $this->http()->get($this->url('Calls/' . urlencode($callSid) . '.json'));

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            $this->throwForStatus($response->status(), $response->body());
        }

        $json = (array) $response->json();

        // Exotel wraps the record in a "Call" key on this endpoint.
        return (array) ($json['Call'] ?? $json) ?: null;
    }

    /**
     * The recording URL for a call, once Exotel has finalised it.
     *
     * Returns null rather than throwing when it is simply not ready: that is
     * the expected state for the first minute after a call ends, and treating
     * it as an error would fill the log with noise about normal behaviour.
     */
    public function getRecordingUrl(string $callSid): ?string
    {
        $call = $this->getCall($callSid);

        $url = $call['RecordingUrl'] ?? null;

        return filled($url) ? (string) $url : null;
    }

    /**
     * Download a recording from Exotel.
     *
     * Exotel's recording URLs require the same Basic credentials as the API, so
     * a plain unauthenticated fetch returns a 401 rather than audio. This is
     * why recording downloads ask the provider for credentials rather than
     * assuming a URL is public.
     *
     * @return array{0: string, 1: array<string, array<int, string>>}
     */
    public function downloadRecording(string $url): array
    {
        $response = Http::withBasicAuth((string) $this->apiKey(), (string) $this->apiToken())
            ->timeout((int) config('calls.recording.timeout', 120))
            ->connectTimeout((int) config('calls.recording.connect_timeout', 15))
            ->get($url);

        if ($response->failed()) {
            $this->throwForStatus($response->status(), 'Recording download failed.');
        }

        return [$response->body(), $response->headers()];
    }

    /**
     * Credentials for fetching a recording, for the generic downloader to use.
     *
     * @return array{0: string, 1: string}|null
     */
    public function recordingCredentials(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return [(string) $this->apiKey(), (string) $this->apiToken()];
    }

    protected function throwForStatus(int $status, string $body): never
    {
        $message = sprintf('Exotel returned HTTP %d: %s', $status, mb_substr($body, 0, 500));

        // Bad credentials and missing records fail identically on every retry.
        if (in_array($status, [400, 401, 403, 404, 422], true)) {
            throw CallProviderException::permanent($message, $status);
        }

        if ($status === 429) {
            throw CallProviderException::rateLimited($message);
        }

        throw CallProviderException::transient($message, $status);
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth((string) $this->apiKey(), (string) $this->apiToken())
            ->acceptJson()
            ->timeout((int) config('calls.exotel.http.timeout', 30))
            ->connectTimeout((int) config('calls.exotel.http.connect_timeout', 10))
            ->retry(
                (int) config('calls.exotel.http.retry_times', 2),
                (int) config('calls.exotel.http.retry_sleep_ms', 1000),
                throw: false,
            );
    }

    protected function url(string $path): string
    {
        return sprintf(
            'https://%s/v1/Accounts/%s/%s',
            trim((string) $this->subdomain(), '/'),
            $this->accountSid(),
            ltrim($path, '/'),
        );
    }

    protected function accountSid(): ?string
    {
        return $this->setting('exotel_account_sid', config('calls.exotel.account_sid'));
    }

    protected function apiKey(): ?string
    {
        return $this->setting('exotel_api_key', config('calls.exotel.api_key'));
    }

    protected function apiToken(): ?string
    {
        return $this->setting('exotel_api_token', config('calls.exotel.api_token'));
    }

    protected function subdomain(): ?string
    {
        return $this->setting('exotel_subdomain', config('calls.exotel.subdomain', 'api.exotel.com'));
    }

    protected function setting(string $key, mixed $fallback): ?string
    {
        $value = Setting::getConfigured($key, $fallback);

        return filled($value) ? (string) $value : null;
    }
}
