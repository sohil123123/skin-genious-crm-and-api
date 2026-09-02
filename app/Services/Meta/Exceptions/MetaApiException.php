<?php

declare(strict_types=1);

namespace App\Services\Meta\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A Graph API call that did not succeed.
 *
 * The transient flag is the whole point of this class. Retrying a timeout is
 * correct; retrying an expired access token burns the job's remaining attempts
 * to arrive at the same failure five times, and buries the one error message
 * that would have told an administrator what to fix.
 */
class MetaApiException extends RuntimeException
{
    protected bool $transient = true;

    public static function transient(string $message, ?Throwable $previous = null): self
    {
        $exception = new self($message, 0, $previous);
        $exception->transient = true;

        return $exception;
    }

    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        $exception = new self($message, 0, $previous);
        $exception->transient = false;

        return $exception;
    }

    /**
     * Whether trying the same call again could plausibly succeed.
     */
    public function isTransient(): bool
    {
        return $this->transient;
    }
}
