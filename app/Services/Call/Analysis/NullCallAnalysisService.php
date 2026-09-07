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

    /**
     * The configured floor, even though nothing here reads a transcript.
     *
     * The screens ask the bound service what the minimum is so they can explain
     * a refusal. Returning 0 while no provider is chosen would have the call
     * page announce that every transcript is long enough, moments before the
     * analyser declines to run at all — two different reasons for the same
     * blank panel, and the wrong one shown.
     */
    public function minimumWords(): int
    {
        return max(1, (int) config('calls.analysis.min_words', 15));
    }
}
