<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\Enums\Call\CallProvider;
use App\Enums\Call\RecordingStorageStatus;
use App\Models\CallRecording;
use App\Models\Setting;
use App\Services\Call\Exceptions\CallProviderException;
use App\Services\Call\Providers\Exotel\ExotelClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fetches call audio from a provider and stores it where the clinic owns it.
 *
 * The reason this exists at all: a provider recording URL is a loan, not a
 * possession. Exotel and Callyzer both expire theirs, so a CRM that stores only
 * the URL has a call history that quietly turns into dead links — and nobody
 * notices until someone needs the one call that mattered.
 *
 * Never called from a webhook. A 40 MB download inside a request would blow
 * past the provider's response deadline, and a provider that thinks a delivery
 * failed retries it, which is how duplicate calls get created.
 *
 * Every download is validated before it is kept. An expired provider URL
 * typically answers 200 with an HTML error page, so a naive save would write a
 * page of markup to disk under an .mp3 extension and report success.
 */
class CallRecordingService
{
    public function __construct(
        protected ExotelClient $exotel,
    ) {}

    /**
     * Download one recording and store it.
     *
     * Returns false for a permanent failure — the recording is marked so
     * nothing retries it. Throws for a transient one, so the job's backoff
     * applies. That distinction is the whole contract: get it wrong and either
     * a dead URL is retried forever, or a provider blip loses the audio.
     */
    public function download(CallRecording $recording): bool
    {
        if (! (bool) config('calls.recording.enabled', true)) {
            $recording->markSkipped('Recording downloads are switched off.');

            return false;
        }

        if (blank($recording->source_url)) {
            $recording->markSkipped('The provider published no recording URL.');

            return false;
        }

        if ($recording->isStored() && $recording->fileExists()) {
            return true;
        }

        $recording->markDownloading();

        try {
            [$body, $headers] = $this->fetch($recording);
        } catch (CallProviderException $exception) {
            if (! $exception->retryable) {
                $recording->markSkipped($exception->getMessage());

                return false;
            }

            $recording->markDownloadFailed($exception->getMessage());

            throw $exception;
        }

        $contentType = $this->headerValue($headers, 'content-type');

        if (! $this->looksLikeAudio($contentType, $body)) {
            // An expired provider URL usually answers 200 with an HTML error
            // page. Storing that would leave a call showing a playable
            // recording that plays nothing.
            $recording->markSkipped(sprintf(
                'The response was not audio (content type "%s"); the provider URL has most likely expired.',
                $contentType ?? 'unknown',
            ));

            return false;
        }

        $maxBytes = (int) config('calls.recording.max_bytes', 104857600);

        if (strlen($body) > $maxBytes) {
            $recording->markSkipped(sprintf(
                'The recording is %s, which exceeds the %s limit.',
                $this->humanBytes(strlen($body)),
                $this->humanBytes($maxBytes),
            ));

            return false;
        }

        if (strlen($body) === 0) {
            $recording->markSkipped('The provider returned an empty file.');

            return false;
        }

        $extension = $this->extensionFor($contentType, $recording->source_url);
        $path = $this->buildPath($recording, $extension);
        $disk = $this->disk();

        if (! Storage::disk($disk)->put($path, $body)) {
            // A disk failure is worth retrying: it is usually a full volume or
            // a transient permissions problem, not a bad recording.
            $recording->markDownloadFailed('Could not write the recording to storage.');

            throw CallProviderException::transient('Could not write the recording to storage.');
        }

        $recording->markDownloaded([
            'storage_disk' => $disk,
            'storage_path' => $path,
            'original_filename' => basename(parse_url($recording->source_url, PHP_URL_PATH) ?: '') ?: null,
            'mime_type' => $contentType,
            'extension' => $extension,
            'file_size' => strlen($body),
            // Kept so a later re-download can be verified against the original,
            // and so silent corruption on disk is detectable at all.
            'checksum' => hash('sha256', $body),
        ]);

        $recording->call?->refreshPipelineFlags();

        Log::channel('calls')->info('Call recording stored.', [
            'recording_id' => $recording->getKey(),
            'call_id' => $recording->call_id,
            'bytes' => strlen($body),
        ]);

        return true;
    }

    /**
     * Retrieve the bytes, authenticating where the provider requires it.
     *
     * @return array{0: string, 1: array<string, array<int, string>>}
     */
    protected function fetch(CallRecording $recording): array
    {
        // Exotel's recording URLs sit behind the same Basic credentials as its
        // API, so an unauthenticated GET returns a 401 rather than audio.
        if ($recording->provider === CallProvider::Exotel) {
            $credentials = $this->exotel->recordingCredentials();

            if ($credentials !== null) {
                return $this->exotel->downloadRecording($recording->source_url);
            }
        }

        try {
            $response = Http::timeout((int) config('calls.recording.timeout', 120))
                ->connectTimeout((int) config('calls.recording.connect_timeout', 15))
                ->withOptions(['stream' => false])
                ->get($recording->source_url);
        } catch (ConnectionException $exception) {
            throw CallProviderException::transient('Could not reach the recording URL: ' . $exception->getMessage());
        }

        if ($response->status() === 404 || $response->status() === 410) {
            // The provider has deleted it. There is nothing to come back for.
            throw CallProviderException::permanent('The provider no longer has this recording.', $response->status());
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw CallProviderException::permanent(
                'The recording URL rejected the request; the credentials or the link have expired.',
                $response->status(),
            );
        }

        if ($response->failed()) {
            throw CallProviderException::transient(
                sprintf('The recording URL returned HTTP %d.', $response->status()),
                $response->status(),
            );
        }

        return [$response->body(), $response->headers()];
    }

    /**
     * Decide whether what came back is really audio.
     *
     * The content type is checked first, then the bytes themselves — providers
     * serve audio as application/octet-stream often enough that rejecting on
     * the header alone would lose real recordings, and serve HTML error pages
     * with a 200 often enough that trusting it would keep fake ones.
     */
    protected function looksLikeAudio(?string $contentType, string $body): bool
    {
        $prefixes = (array) config('calls.recording.allowed_mime_prefixes', ['audio/']);

        $typeLooksRight = false;

        foreach ($prefixes as $prefix) {
            if ($contentType !== null && str_starts_with(strtolower($contentType), strtolower((string) $prefix))) {
                $typeLooksRight = true;
                break;
            }
        }

        if (! $typeLooksRight) {
            return false;
        }

        $head = substr($body, 0, 16);

        // Catch an HTML or XML error page served with a permissive content
        // type, which is the usual shape of an expired link.
        $trimmed = ltrim($head);

        if (str_starts_with($trimmed, '<') || str_starts_with($trimmed, '{')) {
            return false;
        }

        return strlen($body) > 128;
    }

    /**
     * Where this recording is stored.
     *
     * Grouped by month and then by call uuid, so a retention sweep can work a
     * directory at a time, and so the path leaks nothing about the patient. The
     * uuid rather than the id keeps call volumes out of a filename.
     */
    protected function buildPath(CallRecording $recording, string $extension): string
    {
        $call = $recording->call;
        $moment = $call?->started_at ?? $recording->created_at ?? now();

        $template = (string) config('calls.recording.path_template', 'call-recordings/{year}/{month}/{uuid}');

        $directory = strtr($template, [
            '{year}' => $moment->format('Y'),
            '{month}' => $moment->format('m'),
            '{uuid}' => $call?->uuid ?? 'orphan',
        ]);

        // A call can hold more than one recording, so the filename carries the
        // recording id — the two legs of a transferred call must not overwrite
        // each other.
        return sprintf('%s/%d-%s.%s', rtrim($directory, '/'), $recording->getKey(), Str::random(8), $extension);
    }

    protected function extensionFor(?string $contentType, string $url): string
    {
        $map = (array) config('calls.recording.extension_map', []);
        $normalized = strtolower(trim(explode(';', (string) $contentType)[0]));

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        // Fall back to whatever the URL claims, which is right more often than
        // a generic octet-stream content type is.
        $fromUrl = strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if ($fromUrl !== '' && preg_match('/^[a-z0-9]{2,5}$/', $fromUrl) === 1) {
            return $fromUrl;
        }

        return (string) config('calls.recording.default_extension', 'mp3');
    }

    protected function disk(): string
    {
        // getConfigured, not getValue: a blank setting must fall back to the
        // configured disk. Storage::disk('') throws.
        return (string) Setting::getConfigured(
            'call_recording_disk',
            config('calls.recording.disk', 'local'),
        );
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     */
    protected function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($values) ? ($values[0] ?? null) : (string) $values;
            }
        }

        return null;
    }

    /**
     * Delete the audio while keeping the record that it existed.
     *
     * Retention deletes a conversation, not the evidence that a conversation
     * happened. The row survives with storage_status Purged so the call history
     * stays honest and an audit can tell deliberate deletion from data loss.
     */
    public function purge(CallRecording $recording): bool
    {
        $disk = $recording->diskName();

        if ($disk !== null && filled($recording->storage_path)) {
            // Never let a stale disk name turn a retention purge into an
            // exception: the row still has to be marked purged either way.
            try {
                Storage::disk($disk)->delete($recording->storage_path);
            } catch (\Throwable $exception) {
                Log::channel('calls')->warning('Could not delete purged recording audio.', [
                    'recording_id' => $recording->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $recording->forceFill([
            'storage_status' => RecordingStorageStatus::Purged,
            'storage_path' => null,
            'purged_at' => now(),
        ])->save();

        $recording->call?->refreshPipelineFlags();

        return true;
    }

    protected function humanBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
