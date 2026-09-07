<?php

declare(strict_types=1);

namespace App\Services\Call\Exceptions;

use RuntimeException;

/**
 * A transcription attempt failed for a reason that may not recur.
 *
 * Thrown only for transient failures. A permanent one — audio the service will
 * never accept, a recording too short to carry speech — is signalled by
 * returning null instead, because the two need opposite handling: one should be
 * retried with backoff, the other must stop the pipeline touching that
 * recording again.
 */
class CallTranscriptionException extends RuntimeException
{
}
