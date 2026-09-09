<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AiActionService;
use App\Services\Lead\LeadActionService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps today's action queues honest when somebody books.
 *
 * Both queues are built once each morning and are a snapshot of what was true
 * then. Everything they say stays on screen until tomorrow, including the
 * things that stopped being true at noon — a card reading "make first contact
 * and book them" for a patient who booked three hours ago, or "move them to a
 * consultation" for one who is coming in on Monday.
 *
 * Regenerating the whole clinic on every booking would fix it and cost more
 * than it is worth: it rewrites and reorders every card in the queue while
 * staff are working through it. So this reconciles only the person who booked,
 * which is the only person whose cards changed.
 *
 * Failures are logged and swallowed. Tidying a work queue must never be the
 * reason an appointment cannot be saved.
 */
class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        $this->reconcile($appointment);
    }

    /**
     * A cancelled appointment being reinstated, or moved to a new date, is a
     * booking arriving as far as the queues are concerned.
     */
    public function updated(Appointment $appointment): void
    {
        if ($appointment->wasChanged(['status', 'start_datetime'])) {
            $this->reconcile($appointment);
        }
    }

    protected function reconcile(Appointment $appointment): void
    {
        // Only a booking that is actually ahead of them. A cancelled or
        // no-show appointment leaves the person needing exactly the call the
        // queue is suggesting.
        if ($appointment->start_datetime === null
            || $appointment->start_datetime->isPast()
            || in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
            return;
        }

        try {
            app(AiActionService::class)->reconcileWithBooking(
                (int) $appointment->user_id,
                $appointment->start_datetime,
            );

            // The lead side is reached through the phone number: a lead who
            // books is created as a patient by whoever takes the booking, and
            // nothing links the two records.
            // client(), not user() — the relation is named for the role,
            // since "user" in this application means staff as well.
            $mobile = $appointment->client?->mobile;

            if (filled($mobile)) {
                app(LeadActionService::class)->reconcileWithBooking(
                    (string) $mobile,
                    $appointment->start_datetime,
                );
            }
        } catch (\Throwable $exception) {
            Log::warning('Could not reconcile the action queues with a new booking.', [
                'appointment_id' => $appointment->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
