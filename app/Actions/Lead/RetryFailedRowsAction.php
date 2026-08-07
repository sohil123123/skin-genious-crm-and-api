<?php

declare(strict_types=1);

namespace App\Actions\Lead;

use App\Jobs\Lead\RetryFailedLeadRowsJob;
use App\Models\LeadImport;

/**
 * Guards and dispatches a retry of an import's failed rows.
 *
 * The guard lives here rather than in the Filament action so the same rule
 * applies however the retry is triggered — table action, detail page, or a
 * future scheduled sweep.
 */
class RetryFailedRowsAction
{
    /**
     * @return int Number of rows that will be retried.
     */
    public function execute(LeadImport $import): int
    {
        $pending = $import->failures()->unresolved()->count();

        if ($pending === 0) {
            return 0;
        }

        RetryFailedLeadRowsJob::dispatch($import->getKey());

        return $pending;
    }

    public function canRun(LeadImport $import): bool
    {
        return $import->canRetryFailures();
    }
}
