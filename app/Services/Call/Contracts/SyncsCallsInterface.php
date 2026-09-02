<?php

declare(strict_types=1);

namespace App\Services\Call\Contracts;

use App\Models\CallSyncRun;
use Illuminate\Support\Carbon;

/**
 * Implemented by providers that also expose a pull API.
 *
 * Separate from CallProviderInterface because not every provider has one.
 * Exotel pushes and is never polled; Callyzer does both. Folding sync into the
 * base interface would force a meaningless implementation onto the provider
 * that does not need it, which is how an interface stops describing anything.
 */
interface SyncsCallsInterface
{
    /**
     * Whether pulling calls from this provider is switched on.
     *
     * Independent of isEnabled(): an installation can legitimately accept
     * webhooks while leaving the polling sync off.
     */
    public function isSyncEnabled(): bool;

    /**
     * Pull calls for a window and write them through the ingestion service.
     *
     * The run row is passed in rather than created here so a manual "Sync Now"
     * and the scheduled job share one record of what happened, and so the
     * counters survive a crash mid-run.
     *
     * @param  array<string, mixed>  $filters  employee, client, call types
     */
    public function syncCalls(
        CallSyncRun $run,
        Carbon $from,
        Carbon $to,
        array $filters = [],
    ): CallSyncRun;
}
