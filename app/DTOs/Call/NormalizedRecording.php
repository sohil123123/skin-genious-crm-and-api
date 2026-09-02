<?php

declare(strict_types=1);

namespace App\DTOs\Call;

/**
 * A recording as a provider described it, before anything has been downloaded.
 *
 * Carries only what the provider actually said. Everything about the local
 * copy — disk, path, checksum, size — is discovered during the download and
 * belongs to the CallRecording row, not here.
 */
final readonly class NormalizedRecording
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceUrl,
        public ?string $providerRecordingId = null,
        public ?int $durationSeconds = null,
        public ?string $mimeType = null,
        public array $metadata = [],
    ) {}

    /**
     * The stable identity of this recording, used to stop the same audio being
     * attached to a call twice when the provider resends it.
     *
     * Hashed on the URL rather than compared directly because recording URLs
     * routinely carry expiring signature parameters, and are long enough that
     * indexing them whole would be wasteful.
     */
    public function urlHash(): string
    {
        return hash('sha256', $this->sourceUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_url' => $this->sourceUrl,
            'source_url_hash' => $this->urlHash(),
            'provider_recording_id' => $this->providerRecordingId,
            'duration_seconds' => $this->durationSeconds,
            'mime_type' => $this->mimeType,
            'metadata' => $this->metadata ?: null,
        ];
    }
}
