<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Clinic;
use App\Services\AiActionService;
use App\Services\Lead\LeadActionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds both action queues for one clinic.
 *
 * The queues are otherwise built once a day, at 07:00 and 07:10, and are a
 * snapshot of what was true then. Anything that happens afterwards leaves cards
 * behind that argue for work already done — most visibly a booking, after which
 * several triggers are still telling staff to ring and get this person in.
 *
 * Queued rather than run inline. A full rebuild walks every patient and every
 * lead in the clinic, and hanging that off the save of an appointment would
 * make booking somebody slower for the receptionist in front of them.
 *
 * Unique per clinic while it waits, so a morning of bookings does not stack up
 * a rebuild each. The queue is rebuilt from current data whenever the job does
 * run, so collapsing several requests into one loses nothing.
 */
class RegenerateActionQueuesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * How long the uniqueness lock is held.
     *
     * Long enough to absorb a burst of bookings, short enough that a crashed
     * worker cannot leave a clinic unable to regenerate for the rest of the
     * day.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public int $clinicId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->clinicId;
    }

    public function handle(AiActionService $patients, LeadActionService $leads): void
    {
        $clinic = Clinic::find($this->clinicId);

        if (! $clinic) {
            return;
        }

        try {
            // Patients first, then leads — the same order the scheduler uses at
            // 07:00 and 07:10, and for the same reason: the lead engine drops
            // any lead whose number already has a patient action today, so
            // running it first would let the same person be rung from both
            // queues.
            $patients->generateForClinic($clinic);
            $leads->generateForClinic($clinic);
        } catch (Throwable $exception) {
            Log::warning('Could not regenerate the action queues.', [
                'clinic_id' => $this->clinicId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
