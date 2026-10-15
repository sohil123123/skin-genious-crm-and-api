<?php

declare(strict_types=1);

namespace App\Jobs\Call;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs the calls:retry sweep on request from the panel.
 *
 * A job rather than an inline Artisan call: with a sync queue the sweep itself
 * downloads and transcribes, which is minutes of work a browser request would
 * time out on. Unique so an impatient second press does not start a second
 * sweep over the same rows.
 */
class RetryCallPipelineJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array<int, string>  $stages  Any of recordings, transcriptions, analyses, matching. Empty means every stage Call Settings allows.
     */
    public function __construct(
        public array $stages = [],
        public ?int $triggeredBy = null,
    ) {
        $this->onQueue((string) config('calls.queue.sync', 'sync'));

        if ($connection = config('calls.queue.connection')) {
            $this->onConnection($connection);
        }
    }

    public function uniqueId(): string
    {
        return 'call-pipeline-retry';
    }

    public function uniqueFor(): int
    {
        return $this->timeout;
    }

    public function handle(): void
    {
        // Under a sync queue this runs in the web worker after the response,
        // where max_execution_time would otherwise cut the sweep off halfway.
        @set_time_limit(0);

        $options = [];

        foreach (array_intersect($this->stages, ['recordings', 'transcriptions', 'analyses', 'matching']) as $stage) {
            $options['--' . $stage] = true;
        }

        Artisan::call('calls:retry', $options);

        Log::channel('calls')->info('Call pipeline retry run from the panel.', [
            'stages' => $this->stages ?: 'all',
            'triggered_by' => $this->triggeredBy,
            'output' => trim(Artisan::output()),
        ]);
    }
}
