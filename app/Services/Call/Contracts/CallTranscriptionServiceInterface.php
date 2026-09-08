<?php

declare(strict_types=1);

namespace App\Services\Call\Contracts;

use App\DTOs\Call\TranscriptionResult;
use App\Models\CallRecording;

/**
 * What the CRM needs from a speech-to-text service.
 *
 * Provider-independent by design, and deliberately independent of the telephony
 * providers too: which company recorded a call has nothing to do with which
 * company transcribes it, and coupling the two would mean re-implementing
 * transcription for every new telephony integration.
 */
interface CallTranscriptionServiceInterface
{
    /**
     * Whether this driver is configured and switched on.
     *
     * Checked before a job is dispatched as well as inside it, so a disabled
     * transcriber does not fill the queue with work that will be discarded.
     */
    public function isEnabled(): bool;

    public function name(): string;

    /**
     * Transcribe a stored recording.
     *
     * Implementations must throw on a transient failure so the job's retry and
     * backoff apply, and return null only when this recording can never be
     * transcribed — audio too short, or a format the service will not accept.
     * The distinction decides whether the pipeline retries or gives up, and
     * getting it wrong means either a permanent retry loop or a silently
     * dropped conversation.
     */
    public function transcribe(CallRecording $recording, ?string $language = null): ?TranscriptionResult;
}
