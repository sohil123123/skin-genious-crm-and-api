<?php

declare(strict_types=1);

namespace App\Services\Call\Contracts;

use App\DTOs\Call\CallAnalysisResult;
use App\Models\Call;
use App\Models\CallTranscription;

/**
 * What the CRM needs from an AI analyser.
 *
 * Takes a transcript and returns judgements. Deliberately knows nothing about
 * telephony: which company carried the call has no bearing on what was said in
 * it, and coupling the two would mean re-implementing analysis for every new
 * provider.
 */
interface CallAnalysisServiceInterface
{
    /**
     * Whether this driver is configured and switched on.
     *
     * Checked before a job is dispatched as well as inside it, so a disabled
     * analyser does not fill the queue with work that will be discarded.
     */
    public function isEnabled(): bool;

    public function name(): string;

    /**
     * The prompt and pipeline version, stored against each analysis so a later
     * version can be compared against this one before it is trusted.
     */
    public function version(): string;

    /**
     * Analyse one call from its transcript.
     *
     * Must throw on a transient failure so the job's retry and backoff apply,
     * and return null only when this call can never be analysed — a transcript
     * too short to carry meaning, or a response with nothing in it. The
     * distinction decides whether the pipeline retries or gives up, and getting
     * it wrong means either a permanent retry loop or a silently dropped call.
     */
    public function analyse(Call $call, CallTranscription $transcription): ?CallAnalysisResult;

    /**
     * The shortest transcript this driver will accept.
     *
     * On the contract rather than inside the driver because the screens need
     * it: a transcript below the floor is refused silently otherwise, which
     * looks identical to analysis being broken. The call page says why, and the
     * button says so before queueing work that would be discarded.
     */
    public function minimumWords(): int;
}
