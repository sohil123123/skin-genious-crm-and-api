<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AppointmentStatus;
use App\Jobs\RegenerateActionQueuesJob;
use App\Models\Appointment;
use App\Services\AiActionService;
use App\Services\Lead\LeadActionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Keeps today's action queues honest when an appointment changes.
 *
 * Both queues are built once each morning and are a snapshot of what was true
 * then. Everything they say stays on screen until tomorrow, including the
 * things that stopped being true at noon — a card reading "make first contact
 * and book them" for a patient who booked three hours ago, or "move them to a
 * consultation" for one who is coming in on Monday.
 *
 * Two responses, deliberately, because they answer different needs.
 *
 * The reconcile is immediate and touches only the person who booked. It runs
 * inside the save, costs two small queries, and means the card is right the
 * moment anybody reloads the page — including when no queue worker is running.
 *
 * The rebuild is the full pass over the clinic, queued so it cannot slow down
 * the receptionist saving the appointment. It catches everything the targeted
 * pass cannot: a lead who now has a patient action and should drop out of the
 * lead queue, a cancelled appointment putting somebody back into it, the
 * ordering across the whole list.
 *
 * Failures are logged and swallowed. Tidying a work queue must never be the
 * reason an appointment cannot be saved.
 */
class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        $this->reconcile($appointment);
        $this->rebuild($appointment);
    }

    /**
     * A booking moved, confirmed or cancelled changes what the queues should
     * say — a cancellation just as much as a booking, since the person is back
     * to needing the call the queue had stopped suggesting.
     */
    public function updated(Appointment $appointment): void
    {
        if ($appointment->wasChanged(['status', 'start_datetime', 'user_id'])) {
            $this->reconcile($appointment);
            $this->rebuild($appointment);
        }
    }

    public function deleted(Appointment $appointment): void
    {
        $this->rebuild($appointment);
    }

    /**
     * Rebuild both queues for the clinic this appointment belongs to.
     */
    protected function rebuild(Appointment $appointment): void
    {
        if ($appointment->clinic_id === null) {
            return;
        }

        try {
            RegenerateActionQueuesJob::dispatch((int) $appointment->clinic_id);
        } catch (\Throwable $exception) {
            Log::warning('Could not queue an action-queue rebuild for an appointment.', [
                'appointment_id' => $appointment->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Correct this one person's cards straight away.
     */
    protected function reconcile(Appointment $appointment): void
    {
        // Only a booking that is actually ahead of them. A cancelled or
        // no-show appointment leaves the person needing exactly the call the
        // queue is suggesting, and the rebuild will put it back.
        // The same boundary Appointment::scopeCountsAsBooked() draws: the start
        // of today, not the current moment. isPast() meant an appointment
        // stopped counting the instant it began, so a booking made for 12:30
        // and saved at 12:31 reconciled nothing.
        if ($appointment->start_datetime === null
            || $appointment->start_datetime->lt(Carbon::today())
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
            //
            // client(), not user() — the relation is named for the role, since
            // "user" in this application means staff as well.
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
