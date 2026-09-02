<?php

declare(strict_types=1);

namespace App\Services\Call\Exceptions;

use RuntimeException;

/**
 * A provider API call failed.
 *
 * Carries whether the failure is worth retrying, because the caller cannot tell
 * from the message and guessing wrong is expensive in both directions: retrying
 * a rejected API token hammers the provider for nothing and buries the real
 * problem, while giving up on a rate limit silently loses the window's calls.
 */
class CallProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function permanent(string $message, ?int $status = null): self
    {
        return new self($message, retryable: false, httpStatus: $status);
    }

    public static function transient(string $message, ?int $status = null, ?int $retryAfter = null): self
    {
        return new self($message, retryable: true, httpStatus: $status, retryAfterSeconds: $retryAfter);
    }

    /**
     * A 429. Separated out because the correct response is to wait the stated
     * period rather than to apply the generic backoff.
     */
    public static function rateLimited(string $message, ?int $retryAfter = null): self
    {
        return new self($message, retryable: true, httpStatus: 429, retryAfterSeconds: $retryAfter);
    }
}
