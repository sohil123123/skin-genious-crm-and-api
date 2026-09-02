<?php

declare(strict_types=1);

namespace App\Services\Call\Analysis;

use App\DTOs\Call\CallAnalysisResult;
use App\Models\Call;
use App\Models\CallTranscription;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;

/**
 * The analyser that does nothing, and is the default.
 *
 * Binding a null implementation rather than leaving the interface unbound
 * matters: an unbound interface fails at the point of use, months later, inside
 * a queue worker. This fails immediately and legibly instead.
 */
class NullCallAnalysisService implements CallAnalysisServiceInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }

    public function version(): string
    {
        return 'none';
    }

    public function analyse(Call $call, CallTranscription $transcription): ?CallAnalysisResult
    {
        return null;
    }
}
