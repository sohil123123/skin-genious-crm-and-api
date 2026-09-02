<?php

declare(strict_types=1);

namespace App\Services\Call\Transcription;

use App\DTOs\Call\TranscriptionResult;
use App\Models\CallRecording;
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;

/**
 * The transcriber that does nothing, and is the default.
 *
 * Exists so the entire pipeline — job dispatch, status transitions, the
 * transcript tab, the tests — is present and exercised before anyone signs a
 * contract with a speech provider. Binding a null implementation is better than
 * leaving the container unbound: an unbound interface fails at the point of
 * use, months later, in a queue worker.
 */
class NullTranscriptionService implements CallTranscriptionServiceInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }

    /**
     * Always null, which the pipeline reads as "this can never be transcribed"
     * and settles as NotAvailable rather than retrying forever.
     */
    public function transcribe(CallRecording $recording, ?string $language = null): ?TranscriptionResult
    {
        return null;
    }
}
