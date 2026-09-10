<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\AssessmentStatus;
use App\Enums\Call\CallSignalKey;
use App\Models\CallInsightSignal;
use App\Models\AiActionLog;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserPackageUsage;
use App\Models\TreatmentSession;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiActionService
{
    // The scoring formula and fatigue calculation live in the trait so the lead
    // engine scores identically. $maxContactAttempts comes from there too.
    use \App\Services\Concerns\CalculatesActionPriority;

    // What was said on the phone, read the same way by both engines.
    use \App\Services\Concerns\AppliesCallSignals;

    /** Default number of days to look back for recent events */
    protected int $lookbackDays = 60;

    /** Default package session overdue threshold in days */
    protected int $packageOverdueDays = 21;

    /** Maintenance due window: completed treatment X days ago */
    protected int $maintenanceDueMinDays = 28;
    protected int $maintenanceDueMaxDays = 56;

    /**
     * Invoice statuses that do not represent real commercial activity.
     *
     * A cancelled invoice is not a conversion; a draft is not yet one. Every
     * other status — paid, unpaid, partial — means the clinic committed to a
     * treatment, so it must count as evidence that the patient converted.
     */
    protected const VOID_INVOICE_STATUSES = ['draft', 'cancelled'];

    /**
     * Score at or above which an action counts as high priority.
     *
     * The shared formula multiplies five sub-1.0 factors, so its raw output
     * lives in roughly the 0-35 band on real data — the old 70 threshold could
     * never be met by anything, which is why the dashboard permanently reported
     * zero high-priority actions. Triggers are weighted below to restore the
     * spread, and 55 is where the weighted scores separate genuine "do this
     * first" work from routine follow-up.
     */
    public const HIGH_PRIORITY_THRESHOLD = 55;

    /**
     * Trigger weighting applied after the shared formula.
     *
     * Mirrors the lead engine, which already had to solve this: the raw formula
     * ranks correctly within one trigger but cannot express that a package
     * patient who has paid and stopped attending is worth more attention than a
     * cold maintenance nudge. Weighting is applied per trigger so the ordering
     * across categories reflects how close the money already is.
     */
    protected const TRIGGER_WEIGHT = [
        'package_overdue' => 2.20,
        'treatment_plan_dropoff' => 2.10,
        'scan_no_treatment' => 2.00,
        'consultation_no_treatment' => 1.90,
        'cancelled_not_rebooked' => 1.80,
        'package_nearing_exhaustion' => 1.60,
        'no_show_not_rebooked' => 1.50,
        'same_day_slot_fill' => 1.50,
        'maintenance_due' => 1.40,
        // Somebody who asked for something on the phone and has not had it is
        // the most concrete promise in this list, and the only one where the
        // patient is expecting the call.
        'call_commitment_open' => 2.15,
        // Deliberately unweighted: its score is already a 0-100 risk figure,
        // not an output of the multiplicative formula.
        'tomorrows_risk_list' => 1.00,
    ];

    /** Ceiling for a weighted score, held below 100 so the top band cannot saturate. */
    protected const MAX_SCORE = 97;

    /**
     * Triggers that stop making sense the moment the patient books.
     *
     * Exactly the ones whose finder calls hasFutureAppointment(), listed here
     * so the queue can be reconciled when a booking arrives after it was
     * built. Kept as a constant rather than re-derived, because the alternative
     * is a second copy of this list somewhere else that silently disagrees.
     *
     * same_day_slot_fill, package_nearing_exhaustion and tomorrows_risk_list
     * are absent on purpose: none of them is an argument for making a booking,
     * and the last is about an appointment that already exists.
     */
    public const TRIGGERS_SUPERSEDED_BY_BOOKING = [
        'cancelled_not_rebooked',
        'no_show_not_rebooked',
        'scan_no_treatment',
        'consultation_no_treatment',
        'package_overdue',
        'treatment_plan_dropoff',
        'maintenance_due',
    ];

    /**
     * Bring today's queue back in line after a booking is made.
     *
     * The queue is built once each morning and is a snapshot of what was true
     * then. A patient who books at noon leaves cards behind that tell staff to
     * ring and book them, and those cards stay wrong until tomorrow — which is
     * how a queue loses the trust it needs to be worth reading.
     *
     * Two effects. Cards that exist only to chase a booking are deleted, which
     * is precisely what regeneration would do to them. The call-commitment card
     * survives, because a promise made on the phone is not discharged by an
     * appointment, but it is told about the booking so it stops telling staff
     * to sell one.
     *
     * Anything a staff member has already acted on is untouched: that is
     * history, not a suggestion.
     */
    public function reconcileWithBooking(int $userId, Carbon $startsAt): void
    {
        $today = AiActionLog::where('user_id', $userId)
            ->where('generated_date', Carbon::today())
            ->whereNull('staff_outcome');

        (clone $today)
            ->whereIn('action_trigger', self::TRIGGERS_SUPERSEDED_BY_BOOKING)
            ->delete();

        (clone $today)
            ->where('action_trigger', 'call_commitment_open')
            ->get()
            ->each(function (AiActionLog $action) use ($startsAt): void {
                $reason = $this->withBookingNote($action->reason ?? '', $startsAt);

                if ($reason === $action->reason) {
                    return;
                }

                $action->forceFill([
                    'reason' => $reason,
                    'goal' => 'Send what was promised before they come in',
                    'avoid_notes' => 'Do not pitch an appointment — they already have one. Just send what was promised.',
                ])->save();
            });
    }

    /**
     * Generate all AI actions for today across specified clinics.
     * If no clinicIds provided, generates for all active clinics.
     */
    public function generateForToday(?array $clinicIds = null): int
    {
        $clinics = Clinic::where('is_active', true)
            ->when($clinicIds, fn ($q) => $q->whereIn('id', $clinicIds))
            ->get();

        $totalActions = 0;

        foreach ($clinics as $clinic) {
            $totalActions += $this->generateForClinic($clinic);
        }

        return $totalActions;
    }

    /**
     * Generate AI actions for a single clinic.
     */
    public function generateForClinic(Clinic $clinic): int
    {
        // Deactivate previous day's remaining active actions for this clinic
        AiActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', '<', Carbon::today())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Also deactivate today's existing actions (regeneration scenario)
        AiActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', Carbon::today())
            ->whereNull('staff_outcome')
            ->delete();

        $actions = collect();

        // ── A. Rescue Actions ──
        $actions = $actions->merge($this->findCancelledNotRebooked($clinic));
        $actions = $actions->merge($this->findNoShowNotRebooked($clinic));
        $actions = $actions->merge($this->findSameDaySlotFill($clinic));

        // ── B. Conversion Actions ──
        $actions = $actions->merge($this->findScanNoTreatment($clinic));
        $actions = $actions->merge($this->findConsultationNoTreatment($clinic));

        // ── C. Retention Actions ──
        $actions = $actions->merge($this->findPackageOverdue($clinic));
        $actions = $actions->merge($this->findTreatmentPlanDropoff($clinic));
        $actions = $actions->merge($this->findPackageNearingExhaustion($clinic));

        // ── D. Capacity Actions ──
        $actions = $actions->merge($this->findTomorrowsRiskList($clinic));
        $actions = $actions->merge($this->findMaintenanceDue($clinic));

        // ── E. What recent calls said ──
        $actions = $actions->merge($this->findOpenCallCommitments($clinic));

        // Every trigger above is scored from CRM records alone. This pass folds
        // in what the patient actually said on the phone: it can raise or lower
        // a score, drop an action entirely for somebody who declined or has
        // just booked, and add the sentence a staff member needs before ringing.
        //
        // Applied here rather than inside each trigger so all eleven benefit
        // without eleven copies of the same lookup, and so the signals are
        // fetched once per clinic instead of once per patient.
        $actions = $this->decorateWithCallSignals($actions);

        // Deduplicate by user_id — keep highest priority action per patient
        $deduplicated = $actions->groupBy('user_id')->map(function (Collection $group) {
            return $group->sortByDesc('priority_score')->first();
        })->values();

        // Actions a staff member has already worked survive regeneration (they
        // are not deleted above), so re-inserting those patients would put the
        // same person in the queue twice — once done, once outstanding.
        $alreadyWorked = AiActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', Carbon::today())
            ->whereNotNull('staff_outcome')
            ->pluck('user_id')
            ->all();

        $created = 0;

        foreach ($deduplicated as $action) {
            if (in_array($action['user_id'], $alreadyWorked, true)) {
                continue;
            }

            AiActionLog::create($action);
            $created++;
        }

        return $created;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 11: Something promised on a call and not yet delivered
    // ──────────────────────────────────────────────────────────────

    /**
     * Patients who asked for something on the phone and have not had it.
     *
     * The only trigger in this service where the patient is expecting to hear
     * back. Every other one infers a reason to make contact from dates and
     * bookings; this one is a request the clinic has already received and not
     * answered, which is why it outranks them.
     *
     * Deliberately narrow. It fires on the asking signals — a callback, some
     * information, an appointment — and not on objections or sentiment, because
     * "sounded hesitant" is not a promise, and chasing it as one is how a queue
     * fills with actions nobody can complete.
     */
    protected function findOpenCallCommitments(Clinic $clinic): Collection
    {
        $reader = $this->callSignals();

        $grouped = CallInsightSignal::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotNull('customer_user_id')
            // Taken from the enum rather than listed here. The lead engine asks
            // the same question, and when both kept their own list the two
            // disagreed about which signals count — including agreeing to
            // ignore "staff followup required", which is the vocabulary's
            // plainest statement that somebody has to do something.
            ->whereIn('signal_key', CallSignalKey::followUpValues())
            ->recent($reader->windowDays())
            ->confident($reader->confidenceThreshold())
            ->with('customer')
            ->orderByDesc('occurred_at')
            ->get()
            ->groupBy('customer_user_id');

        $actions = collect();

        foreach ($grouped as $userId => $group) {
            $client = $group->first()->customer;

            if (! $client || ! $this->isClientRole($client)) {
                continue;
            }

            $subjectSignals = $reader->forPatient((int) $userId);

            // Already dealt with: they booked, or they told us not to.
            if ($this->callSignalsSuppress($subjectSignals)) {
                continue;
            }

            $latest = $group->first();

            // Left with the person who took the call for a few hours before it
            // counts as anybody else's work. Measured in hours rather than
            // calendar days, which is the correction: comparing against
            // midnight meant a call at 10:29 yesterday morning read as zero
            // days old this morning and was skipped, and so was every other
            // call until it was nearly two days old. The queue is built once at
            // 07:00, so that rule could never see yesterday at all.
            if (! $reader->isDue($latest->occurred_at)) {
                continue;
            }

            $daysSince = (int) max(0, $latest->occurred_at?->diffInDays(now()) ?? 0);

            // A promise survives a booking; the urgency behind it does not.
            // This is the one trigger here that is not gated by
            // hasFutureAppointment(), because what was asked for on the phone
            // is still owed whether or not the patient is coming in.
            $booked = $this->nextAppointmentAt((int) $userId);

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.9,
                'recency' => max(0.3, 1 - ($daysSince / 14)),
                'treatment_fit' => 0.8,
                'urgency' => $booked !== null ? 0.5 : 0.85,
                'slot_availability' => 0.8,
                'fatigue_penalty' => $this->contactFatiguePenaltyFor(AiActionLog::class, 'user_id', (int) $userId),
            ]), 'call_commitment_open');

            $wantsWriting = $group->contains(
                fn (CallInsightSignal $signal): bool => $signal->signal_key === CallSignalKey::InformationRequested
            );

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => (int) $userId,
                'action_category' => AiActionLog::CATEGORY_CONVERSION,
                'action_trigger' => 'call_commitment_open',
                'priority_score' => $priority,
                // Somebody who asked for information wants it in writing. Ringing
                // them back to read a price list aloud answers a question they
                // did not ask.
                'recommended_channel' => $wantsWriting ? 'whatsapp' : 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => $this->withCallReason(
                    $this->withBookingNote(
                        sprintf(
                            'Asked for something on a call %s and has not had it.',
                            // "0 day(s) ago" for this morning's call, which is
                            // what the day count produced. diffForHumans says
                            // "5 hours ago" and needs no same-day special case.
                            $latest->occurred_at?->diffForHumans() ?? 'recently',
                        ),
                        $booked,
                    ),
                    $reader->explain($subjectSignals),
                ),
                // Opened from what was actually said, with the generic line as
                // a fallback for a call that established nothing sayable.
                'suggested_message' => $this->callAwareScript($client->first_name, $subjectSignals)
                    ?? sprintf(
                        'Hi %s, following up on your call — sending across what you asked about.',
                        $client->first_name,
                    ),
                'goal' => $booked !== null
                    ? 'Send what was promised before they come in'
                    : 'Close the loop on what was promised',
                'slots_to_offer' => null,
                'avoid_notes' => $booked !== null
                    ? 'Do not pitch an appointment — they already have one. Just send what was promised.'
                    : 'They already said what they want. Lead with that, not with a pitch.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'related_call_id' => $latest->call_id,
                'call_signals' => $reader->toBasis($subjectSignals),
                'expires_at' => Carbon::today()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    /**
     * Fold call insight into the actions the existing triggers produced.
     *
     * Three effects, in order of how much they matter.
     *
     * Suppression removes the action entirely. Somebody who refused, or who
     * booked on the phone this morning, should not be in today's queue at all —
     * and the booking case matters most in the hours before it reaches the
     * diary, which is exactly the window where hasFutureAppointment() cannot
     * help.
     *
     * The score modifier tilts the ranking, bounded, and only when switched on.
     *
     * The reason gains a sentence about what was actually said, which happens
     * regardless: it costs nothing, and it is the difference between "cancelled
     * three weeks ago" and "cancelled three weeks ago; on the call last Tuesday
     * they raised the cost".
     *
     * @param  Collection<int, array<string, mixed>>  $actions
     * @return Collection<int, array<string, mixed>>
     */
    protected function decorateWithCallSignals(Collection $actions): Collection
    {
        if ($actions->isEmpty()) {
            return $actions;
        }

        // One query for the whole clinic. Asking per patient would put a query
        // inside a loop over every outstanding action.
        $userIds = $actions->pluck('user_id')->filter()->all();

        $signalsByUser = $this->callSignals()->forPatients($userIds);

        // The script is spoken to the patient, so it needs their name. One
        // pluck rather than touching all eleven triggers to carry it along.
        $names = User::whereIn('id', $userIds)->pluck('first_name', 'id');

        return $actions
            ->map(function (array $action) use ($signalsByUser, $names): ?array {
                $signals = $signalsByUser->get($action['user_id']) ?? collect();

                if ($signals->isEmpty()) {
                    return $action;
                }

                // The call-driven trigger is already built from these
                // signals: it carries its own basis, its own related call, a
                // reason that already names what was said and a script drawn
                // from it. Running it through this pass appended the same
                // sentence a second time — "on the call 3 hours ago they asked
                // for more information" twice in one reason — and applied the
                // score modifier on top of arithmetic that had already
                // accounted for the conversation.
                if ($action['action_trigger'] === 'call_commitment_open') {
                    return $action;
                }

                if ($this->callSignalsSuppress($signals)) {
                    return null;
                }

                $applied = $this->applyCallSignals((int) $action['priority_score'], $signals);

                $action['priority_score'] = $applied['score'];
                $action['reason'] = $this->withCallReason($action['reason'], $applied['note']);
                $action['call_signals'] ??= ($applied['basis'] ?: null);
                $action['related_call_id'] ??= $signals->first()?->call_id;

                // Opened from the conversation instead of from the record. The
                // generic script greets somebody nobody has spoken to, and read
                // out to a patient the clinic rang yesterday it tells them they
                // were not listened to.
                $script = $this->callAwareScript($names->get($action['user_id']), $signals);

                if ($script !== null) {
                    $action['suggested_message'] = $script;
                }

                return $action;
            })
            ->filter()
            ->values();
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 1: Cancelled appointments not rebooked
    // ──────────────────────────────────────────────────────────────

    protected function findCancelledNotRebooked(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $cancelledAppointments = Appointment::where('clinic_id', $clinic->id)
            ->where('status', AppointmentStatus::Cancelled)
            ->where('start_datetime', '>=', $cutoffDate)
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($cancelledAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            // "Not rebooked" previously meant only "has nothing booked ahead",
            // so a patient who cancelled and then walked in a week later was
            // still chased about the cancellation. Recovery is only outstanding
            // if nothing has happened since. The window opens on the day of the
            // cancellation, not the day after: dropping a morning slot and
            // coming in that same afternoon is an ordinary way for a clinic to
            // recover a cancellation, and it should not read as a lost patient.
            if ($this->hasConvertedSince($client->id, $appointment->start_datetime)) {
                continue;
            }

            $daysSinceCancellation = (int) round(Carbon::parse($appointment->start_datetime)->diffInDays(Carbon::today()));

            // Best recall window: 3-14 days after cancellation
            if ($daysSinceCancellation < 3 || $daysSinceCancellation > 30) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.7,
                'recency' => max(0, 1 - ($daysSinceCancellation / 30)),
                'treatment_fit' => 0.8,
                'urgency' => $daysSinceCancellation <= 7 ? 0.9 : 0.6,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'cancelled_not_rebooked');

            $typeLabel = $appointment->type instanceof AppointmentType
                ? $appointment->type->getLabel()
                : ucfirst((string) $appointment->type);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RESCUE,
                'action_trigger' => 'cancelled_not_rebooked',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => '12:30 - 2:00 PM',
                'reason' => "Cancelled {$typeLabel} appointment {$daysSinceCancellation} days ago on " .
                    Carbon::parse($appointment->start_datetime)->format('d M Y') .
                    ". No rebook found. Ideal recall window.",
                'suggested_message' => "Hi {$client->first_name}, we noticed your {$typeLabel} appointment was cancelled. We have some great slots available this week — would you like to reschedule?",
                'goal' => "Reschedule {$typeLabel} appointment",
                'slots_to_offer' => null,
                'avoid_notes' => 'Do not offer discount unless price was the original objection.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 2: No-show not rebooked
    // ──────────────────────────────────────────────────────────────

    protected function findNoShowNotRebooked(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $noShowAppointments = Appointment::where('clinic_id', $clinic->id)
            ->where('status', AppointmentStatus::NoShow)
            ->where('start_datetime', '>=', $cutoffDate)
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($noShowAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            // Same correction as the cancellation trigger: a patient who missed
            // one appointment and then attended — including later the same day —
            // has already been recovered.
            if ($this->hasConvertedSince($client->id, $appointment->start_datetime)) {
                continue;
            }

            $daysSinceNoShow = (int) round(Carbon::parse($appointment->start_datetime)->diffInDays(Carbon::today()));

            if ($daysSinceNoShow < 2 || $daysSinceNoShow > 21) {
                continue;
            }

            // Check repeated no-shows — reduce priority
            $previousNoShows = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::NoShow)
                ->count();

            $intentPenalty = min(0.3, $previousNoShows * 0.1);

            $priority = $this->weighted($this->calculatePriority([
                'intent' => max(0.3, 0.6 - $intentPenalty),
                'recency' => max(0, 1 - ($daysSinceNoShow / 21)),
                'treatment_fit' => 0.7,
                'urgency' => $daysSinceNoShow <= 5 ? 0.8 : 0.5,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'no_show_not_rebooked');

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RESCUE,
                'action_trigger' => 'no_show_not_rebooked',
                'priority_score' => $priority,
                'recommended_channel' => $previousNoShows >= 2 ? 'whatsapp' : 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => "No-show for appointment on " .
                    Carbon::parse($appointment->start_datetime)->format('d M Y') .
                    " ({$daysSinceNoShow} days ago). " .
                    ($previousNoShows > 1 ? "Has {$previousNoShows} previous no-shows — use gentle tone." : "First no-show — likely recoverable."),
                'suggested_message' => "Hi {$client->first_name}, we missed you at your last appointment. No worries — would you like to reschedule at a time that works better for you?",
                'goal' => 'Rebook missed appointment',
                'slots_to_offer' => null,
                'avoid_notes' => $previousNoShows >= 2
                    ? 'Consider requiring confirmation or deposit for next booking.'
                    : 'Use non-judgmental tone. Do not reference the no-show directly.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 3: Same-day slot fill
    // ──────────────────────────────────────────────────────────────

    protected function findSameDaySlotFill(Clinic $clinic): Collection
    {
        // Count today's total appointment capacity vs booked
        $todayBooked = Appointment::where('clinic_id', $clinic->id)
            ->whereDate('start_datetime', Carbon::today())
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            ->count();

        $totalSlots = $this->estimateDailySlots($clinic);
        $emptySlots = max(0, $totalSlots - $todayBooked);

        if ($emptySlots < 2) {
            return collect();
        }

        // Find patients who are overdue and could fill today
        $candidates = User::whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->whereHas('appointments', function ($q) {
                $q->where('status', AppointmentStatus::Completed)
                    ->where('start_datetime', '>=', Carbon::today()->subDays(60));
            })
            ->whereDoesntHave('appointments', function ($q) {
                $q->where('start_datetime', '>=', Carbon::today())
                    ->whereNotIn('status', [
                        AppointmentStatus::Cancelled,
                        AppointmentStatus::NoShow,
                    ]);
            })
            // No limit here: the "last visit ≥ 14 days ago" test below is what
            // decides eligibility, and capping the query first meant the five
            // rows the database happened to return were often all rejected,
            // producing an empty slot-fill list on a day with real candidates.
            ->get();

        $actions = collect();

        foreach ($candidates as $client) {
            if ($actions->count() >= 5) {
                break;
            }

            $lastVisit = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::Completed)
                ->orderByDesc('start_datetime')
                ->first();

            if (!$lastVisit) {
                continue;
            }

            $daysSinceVisit = (int) round(Carbon::parse($lastVisit->start_datetime)->diffInDays(Carbon::today()));

            if ($daysSinceVisit < 14) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.5,
                'recency' => max(0, 1 - ($daysSinceVisit / 60)),
                'treatment_fit' => 0.6,
                'urgency' => 0.9, // Same-day is always urgent
                'slot_availability' => 1.0,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'same_day_slot_fill');

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CAPACITY,
                'action_trigger' => 'same_day_slot_fill',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => 'ASAP — before 11:00 AM',
                'reason' => "{$emptySlots} empty slots today. Last visit was {$daysSinceVisit} days ago. Patient is due for follow-up.",
                'suggested_message' => "Hi {$client->first_name}! We have a slot available today — would you like to come in for your next session?",
                'goal' => 'Fill empty slot today',
                'slots_to_offer' => null,
                'avoid_notes' => 'Only contact if patient has responded well to short-notice requests before.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->setHour(17),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 4: Scan completed, no treatment
    // ──────────────────────────────────────────────────────────────

    protected function findScanNoTreatment(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        // Only the patient's most recent scan is a candidate. Every scan used to
        // be evaluated independently, so a patient who scanned, was treated, and
        // was re-scanned afterwards generated an action against that second scan
        // — the follow-up scan has no treatment of its own yet, so it looked
        // like an unconverted enquiry. That re-scan is evidence of a *completed*
        // conversion, not a missed one.
        $assessments = Assessment::where('clinic_id', $clinic->id)
            ->where('status', AssessmentStatus::Completed)
            ->where('created_at', '>=', $cutoffDate)
            ->with(['user', 'user.roles', 'treatmentSessions'])
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->sortByDesc('created_at')->first())
            ->values();

        $actions = collect();

        foreach ($assessments as $assessment) {
            $client = $assessment->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Anything at all happening on or after the scan day — payment,
            // package purchase, session redemption, completed treatment, or a
            // visit — means the scan converted.
            if ($this->hasConvertedSince($client->id, $assessment->created_at)) {
                continue;
            }

            // A later scan of any status, including one still in progress, is
            // itself proof the patient came back: the images cannot be captured
            // without them in the chair.
            $hasLaterScan = Assessment::where('user_id', $client->id)
                ->where('created_at', '>', $assessment->created_at)
                ->exists();

            if ($hasLaterScan) {
                continue;
            }

            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            $daysSinceScan = (int) round(Carbon::parse($assessment->created_at)->diffInDays(Carbon::today()));

            if ($daysSinceScan < 3) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.85, // High — they came in and did a scan
                'recency' => max(0, 1 - ($daysSinceScan / $this->lookbackDays)),
                'treatment_fit' => 0.9,
                'urgency' => $daysSinceScan <= 14 ? 0.9 : 0.6,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'scan_no_treatment');

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CONVERSION,
                'action_trigger' => 'scan_no_treatment',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => '10:00 AM - 12:00 PM',
                'reason' => "Skin scan completed {$daysSinceScan} days ago on " .
                    Carbon::parse($assessment->created_at)->format('d M Y') .
                    ". No treatment session or invoice found. Patient invested time — high conversion potential.",
                'suggested_message' => "Hi {$client->first_name}, your AI skin analysis from " .
                    Carbon::parse($assessment->created_at)->format('d M') .
                    " identified some key areas we can work on. Would you like to book your customised facial this week?",
                'goal' => 'Convert scan to treatment appointment',
                'slots_to_offer' => null,
                'avoid_notes' => 'Remind of scan findings. Do not discount unless price was discussed.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => $assessment->id,
                'expires_at' => Carbon::today()->addDays(2)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 5: Consultation completed, no treatment
    // ──────────────────────────────────────────────────────────────

    protected function findConsultationNoTreatment(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays($this->lookbackDays);

        $consultAppointments = Appointment::where('clinic_id', $clinic->id)
            ->where('type', AppointmentType::Consult)
            ->where('status', AppointmentStatus::Completed)
            ->where('start_datetime', '>=', $cutoffDate)
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($consultAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Check if a treatment appointment followed the consult
            $hasTreatmentAfterConsult = Appointment::where('user_id', $client->id)
                ->where('type', AppointmentType::Treatment)
                ->where('start_datetime', '>=', $appointment->start_datetime)
                ->whereIn('status', [
                    AppointmentStatus::Completed,
                    AppointmentStatus::InProgress,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::Pending,
                ])
                ->exists();

            if ($hasTreatmentAfterConsult) {
                continue;
            }

            // A booked treatment appointment is not the only way a consult
            // converts. Paying, buying a package or redeeming a session all
            // mean the plan started — the appointment may simply not be in the
            // diary yet.
            if ($this->hasConvertedSince($client->id, $appointment->start_datetime)) {
                continue;
            }

            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            $daysSinceConsult = (int) round(Carbon::parse($appointment->start_datetime)->diffInDays(Carbon::today()));

            if ($daysSinceConsult < 3) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.75,
                'recency' => max(0, 1 - ($daysSinceConsult / $this->lookbackDays)),
                'treatment_fit' => 0.85,
                'urgency' => $daysSinceConsult <= 10 ? 0.85 : 0.55,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'consultation_no_treatment');

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CONVERSION,
                'action_trigger' => 'consultation_no_treatment',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => "Consult completed {$daysSinceConsult} days ago on " .
                    Carbon::parse($appointment->start_datetime)->format('d M Y') .
                    ". No treatment appointment followed. Patient may need a nudge.",
                'suggested_message' => "Hi {$client->first_name}, after your consultation, our doctor recommended a personalised treatment plan. Would you like to schedule your first session?",
                'goal' => 'Start treatment plan after consultation',
                'slots_to_offer' => null,
                'avoid_notes' => 'Address actual barrier — ask if they have questions about the plan.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(2)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 6: Package patient overdue
    // ──────────────────────────────────────────────────────────────

    protected function findPackageOverdue(Clinic $clinic): Collection
    {
        $activePackages = UserPackage::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->with(['user', 'user.roles', 'items', 'usages'])
            ->get();

        $actions = collect();

        foreach ($activePackages as $package) {
            $client = $package->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $remainingSessions = $package->getTotalRemainingSessions();

            if ($remainingSessions <= 0) {
                continue;
            }

            // Find last usage date
            $lastUsage = $package->usages()->latest()->first();
            $lastUsageDate = $lastUsage ? Carbon::parse($lastUsage->created_at) : Carbon::parse($package->created_at);

            // A redemption may be logged late or not at all, so the package
            // ledger on its own overstates how long a patient has been away.
            // The real question is when they were last in the clinic — take the
            // later of the two, otherwise a patient treated last week is
            // reported as a month overdue.
            $lastActivity = $this->lastActivityAt($client->id);

            if ($lastActivity !== null && $lastActivity->greaterThan($lastUsageDate)) {
                $lastUsageDate = $lastActivity;
            }

            $daysSinceLastUsage = (int) round($lastUsageDate->diffInDays(Carbon::today()));

            if ($daysSinceLastUsage < $this->packageOverdueDays) {
                continue;
            }

            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.8, // They bought a package — strong intent
                'recency' => max(0, 1 - ($daysSinceLastUsage / 90)),
                'treatment_fit' => 1.0,
                'urgency' => $daysSinceLastUsage >= 42 ? 0.95 : 0.7,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'package_overdue');

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'package_overdue',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => '10:00 AM - 12:00 PM',
                'reason' => "Package '{$package->package_name}' has {$remainingSessions} sessions remaining. " .
                    "Last activity was {$daysSinceLastUsage} days ago on " .
                    $lastUsageDate->format('d M Y') . ". Overdue for next session.",
                'suggested_message' => "Hi {$client->first_name}, you have {$remainingSessions} sessions remaining in your {$package->package_name} package. It's been a while since your last visit — shall we book your next session?",
                'goal' => 'Book next package session',
                'slots_to_offer' => null,
                'avoid_notes' => 'Emphasise continuity of care, not urgency. Do not pressure.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => $package->id,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(3)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 7: Treatment plan drop-off
    // ──────────────────────────────────────────────────────────────

    protected function findTreatmentPlanDropoff(Clinic $clinic): Collection
    {
        $cutoffDate = Carbon::today()->subDays(90);

        // Assessments with treatment sessions where only session 1 completed
        $assessments = Assessment::where('clinic_id', $clinic->id)
            ->where('status', AssessmentStatus::Completed)
            ->where('created_at', '>=', $cutoffDate)
            ->has('treatmentSessions', '>=', 2)
            ->with(['user', 'user.roles', 'treatmentSessions'])
            ->get();

        $actions = collect();

        foreach ($assessments as $assessment) {
            $client = $assessment->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $sessions = TreatmentSession::where('assessment_id', $assessment->id)->get();
            $completedSessions = $sessions->where('status', 'completed')->count();
            $totalSessions = $sessions->count();

            // Only flag if they started (≥1 completed) but didn't finish all
            if ($completedSessions === 0 || $completedSessions >= $totalSessions) {
                continue;
            }

            $lastCompletedSession = $sessions->where('status', 'completed')
                ->sortByDesc('updated_at')
                ->first();

            if (!$lastCompletedSession) {
                continue;
            }

            $lastSessionDate = Carbon::parse($lastCompletedSession->updated_at);

            // The plan row only knows about its own sessions. A patient can look
            // abandoned here while having been invoiced or treated last week
            // under a different plan or package — measure the gap from whatever
            // they actually did most recently.
            $lastActivity = $this->lastActivityAt($client->id);

            if ($lastActivity !== null && $lastActivity->greaterThan($lastSessionDate)) {
                $lastSessionDate = $lastActivity;
            }

            $daysSinceLastSession = (int) round($lastSessionDate->diffInDays(Carbon::today()));

            if ($daysSinceLastSession < 14) {
                continue;
            }

            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.75,
                'recency' => max(0, 1 - ($daysSinceLastSession / 90)),
                'treatment_fit' => 0.95,
                'urgency' => $daysSinceLastSession >= 30 ? 0.85 : 0.65,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'treatment_plan_dropoff');

            $remaining = $totalSessions - $completedSessions;

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'treatment_plan_dropoff',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => '11:00 AM - 1:00 PM',
                'reason' => "Completed {$completedSessions}/{$totalSessions} treatment sessions. " .
                    "Last activity was {$daysSinceLastSession} days ago on " .
                    $lastSessionDate->format('d M Y') . ". {$remaining} sessions remaining in plan.",
                'suggested_message' => "Hi {$client->first_name}, you've made great progress with {$completedSessions} sessions completed! You have {$remaining} more sessions in your plan. Shall we schedule your next one?",
                'goal' => 'Resume treatment plan',
                'slots_to_offer' => null,
                'avoid_notes' => 'Highlight results achieved so far. Do not create anxiety about drop-off.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => null,
                'related_assessment_id' => $assessment->id,
                'expires_at' => Carbon::today()->addDays(3)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 8: Package nearing exhaustion
    // ──────────────────────────────────────────────────────────────

    protected function findPackageNearingExhaustion(Clinic $clinic): Collection
    {
        $activePackages = UserPackage::where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->with(['user', 'user.roles', 'items'])
            ->get();

        $actions = collect();

        foreach ($activePackages as $package) {
            $client = $package->user;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            $remaining = $package->getTotalRemainingSessions();
            $total = $package->getTotalSessions();

            // Only flag if exactly 1 session remaining and total > 1
            if ($remaining !== 1 || $total <= 1) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.6,
                'recency' => 0.8,
                'treatment_fit' => 0.85,
                'urgency' => 0.7,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'package_nearing_exhaustion');

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'package_nearing_exhaustion',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => 'During or after final session',
                'reason' => "Package '{$package->package_name}' has only 1 session remaining out of {$total}. " .
                    "Staff should discuss renewal or next steps before the final session.",
                'suggested_message' => null,
                'goal' => 'Discuss package renewal or maintenance plan',
                'slots_to_offer' => null,
                'avoid_notes' => 'Discuss during the visit — not as a sales call. Recommend reassessment before renewal if appropriate.',
                'assigned_to' => null,
                'related_appointment_id' => null,
                'related_package_id' => $package->id,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(7)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 9: Tomorrow's appointment risk list
    // ──────────────────────────────────────────────────────────────

    protected function findTomorrowsRiskList(Clinic $clinic): Collection
    {
        $tomorrow = Carbon::tomorrow();

        $tomorrowAppointments = Appointment::where('clinic_id', $clinic->id)
            ->whereDate('start_datetime', $tomorrow)
            ->whereIn('status', [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ])
            ->with(['client', 'client.roles'])
            ->get();

        $actions = collect();

        foreach ($tomorrowAppointments as $appointment) {
            $client = $appointment->client;

            if (!$client || !$this->isClientRole($client)) {
                continue;
            }

            // Calculate risk signals
            $previousNoShows = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::NoShow)
                ->count();

            $previousCancellations = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::Cancelled)
                ->count();

            $bookedDaysAgo = (int) round(Carbon::parse($appointment->created_at)->diffInDays(Carbon::today()));
            $isUnconfirmed = $appointment->status === AppointmentStatus::Pending;

            // Risk score
            $riskScore = 0;
            $riskSignals = [];

            if ($previousNoShows > 0) {
                $riskScore += min(30, $previousNoShows * 15);
                $riskSignals[] = "{$previousNoShows} previous no-show(s)";
            }

            if ($previousCancellations > 1) {
                $riskScore += min(20, $previousCancellations * 5);
                $riskSignals[] = "{$previousCancellations} previous cancellation(s)";
            }

            if ($bookedDaysAgo > 14) {
                $riskScore += 15;
                $riskSignals[] = "Booked {$bookedDaysAgo} days ago";
            }

            if ($isUnconfirmed) {
                $riskScore += 25;
                $riskSignals[] = "Still unconfirmed";
            }

            // Only flag if meaningful risk
            if ($riskScore < 25) {
                continue;
            }

            $priority = min(100, $riskScore);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_CAPACITY,
                'action_trigger' => 'tomorrows_risk_list',
                'priority_score' => $priority,
                'recommended_channel' => 'call',
                'recommended_time' => 'This evening or early tomorrow',
                'reason' => "Tomorrow's appointment at " .
                    Carbon::parse($appointment->start_datetime)->format('g:i A') .
                    " — at-risk. Signals: " . implode('; ', $riskSignals) . ".",
                'suggested_message' => "Hi {$client->first_name}, just confirming your appointment tomorrow at " .
                    Carbon::parse($appointment->start_datetime)->format('g:i A') . ". We look forward to seeing you!",
                'goal' => 'Confirm appointment and reduce no-show risk',
                'slots_to_offer' => null,
                'avoid_notes' => 'Personal confirmation call is more effective than automated reminder for at-risk appointments.',
                'assigned_to' => null,
                'related_appointment_id' => $appointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => $tomorrow->copy()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 10: Maintenance due
    // ──────────────────────────────────────────────────────────────

    protected function findMaintenanceDue(Clinic $clinic): Collection
    {
        // Both bounds are taken at day boundaries. Comparing a datetime column
        // against a midnight Carbon excluded any visit later in the day on the
        // window's edge, so patients silently fell out of the window.
        $minDate = Carbon::today()->subDays($this->maintenanceDueMaxDays)->startOfDay();
        $maxDate = Carbon::today()->subDays($this->maintenanceDueMinDays)->endOfDay();

        // Clients whose last completed appointment was 28-56 days ago
        $clientIds = Appointment::where('clinic_id', $clinic->id)
            ->where('status', AppointmentStatus::Completed)
            ->select('user_id', DB::raw('MAX(start_datetime) as last_visit'))
            ->groupBy('user_id')
            ->havingRaw('MAX(start_datetime) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->pluck('user_id');

        if ($clientIds->isEmpty()) {
            return collect();
        }

        $clients = User::whereIn('id', $clientIds)
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->where('is_active', true)
            ->get();

        $actions = collect();

        foreach ($clients as $client) {
            if ($this->hasFutureAppointment($client->id)) {
                continue;
            }

            // A maintenance nudge only makes sense if the patient really has
            // gone quiet. The window is derived from appointments alone, so a
            // patient invoiced or treated since their last booked visit would
            // otherwise be told it has been two months.
            if ($this->hasConvertedSince($client->id, Carbon::today()->subDays($this->maintenanceDueMinDays))) {
                continue;
            }

            // Patients with an unfinished package belong to the package trigger,
            // which carries the sessions they have already paid for.
            $hasUnusedPackage = UserPackage::where('user_id', $client->id)
                ->where('is_active', true)
                ->get()
                ->contains(fn (UserPackage $package) => $package->getTotalRemainingSessions() > 0);

            if ($hasUnusedPackage) {
                continue;
            }

            $lastAppointment = Appointment::where('user_id', $client->id)
                ->where('status', AppointmentStatus::Completed)
                ->orderByDesc('start_datetime')
                ->first();

            if (!$lastAppointment) {
                continue;
            }

            $daysSinceVisit = (int) round(Carbon::parse($lastAppointment->start_datetime)->diffInDays(Carbon::today()));

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.5,
                'recency' => max(0, 1 - ($daysSinceVisit / $this->maintenanceDueMaxDays)),
                'treatment_fit' => 0.7,
                'urgency' => 0.5,
                'slot_availability' => 0.7,
                'fatigue_penalty' => $this->getContactFatiguePenalty($client->id),
            ]), 'maintenance_due');

            $typeLabel = $lastAppointment->type instanceof AppointmentType
                ? $lastAppointment->type->getLabel()
                : ucfirst((string) $lastAppointment->type);

            $actions->push([
                'clinic_id' => $clinic->id,
                'user_id' => $client->id,
                'action_category' => AiActionLog::CATEGORY_RETENTION,
                'action_trigger' => 'maintenance_due',
                'priority_score' => $priority,
                'recommended_channel' => 'whatsapp',
                'recommended_time' => '10:00 AM - 12:00 PM',
                'reason' => "Last visit ({$typeLabel}) was {$daysSinceVisit} days ago on " .
                    Carbon::parse($lastAppointment->start_datetime)->format('d M Y') .
                    ". Due for maintenance visit.",
                'suggested_message' => "Hi {$client->first_name}, it's been {$daysSinceVisit} days since your last session. To maintain your results, we recommend scheduling a follow-up. Would you like to book?",
                'goal' => 'Schedule maintenance appointment',
                'slots_to_offer' => null,
                'avoid_notes' => 'Position as continuity of care, not sales. Do not push if client completed full course recently.',
                'assigned_to' => null,
                'related_appointment_id' => $lastAppointment->id,
                'related_package_id' => null,
                'related_assessment_id' => null,
                'expires_at' => Carbon::today()->addDays(5)->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
            ]);
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  Priority Scoring Engine
    // ──────────────────────────────────────────────────────────────

    /**
     * Calculate contact fatigue penalty (0.0 to 1.0).
     * Based on number of AI action logs created for this user in the last 7 days.
     *
     * The implementation now lives in CalculatesActionPriority; this signature
     * is kept so the ten existing call sites are untouched.
     */
    protected function getContactFatiguePenalty(int $userId): float
    {
        return $this->contactFatiguePenaltyFor(AiActionLog::class, 'user_id', $userId);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Check if a user has the 'client' role.
     */
    protected function isClientRole(User $user): bool
    {
        return $user->roles->contains('name', 'client');
    }

    // ──────────────────────────────────────────────────────────────
    //  Patient activity evidence
    // ──────────────────────────────────────────────────────────────

    /**
     * Normalise a moment to the start of its calendar day.
     *
     * Every "did anything happen after X?" question in this service compares a
     * staff-entered business date against a system timestamp. `invoice_date` is
     * chosen by whoever raised the invoice and is routinely earlier in the day
     * than the record that triggered it — a scan uploaded at 13:30 against an
     * invoice dated 09:19 the same morning. Comparing those two at second
     * precision reports "no invoice found" for a patient who paid that day,
     * which is exactly the false positive this fixes. The clinic's unit of
     * truth is the day, so comparisons are made at day boundaries.
     */
    protected function dayFloor(Carbon|string $moment): Carbon
    {
        return Carbon::parse($moment)->startOfDay();
    }

    /**
     * Any invoice that represents real commercial activity on or after $since.
     *
     * Package purchases are included. They were previously excluded, which
     * inverted the intent: a patient who scanned and then bought a package is
     * the single strongest conversion in the CRM, and was being reported every
     * morning as someone who never converted.
     */
    protected function hasInvoiceSince(int $userId, Carbon|string $since): bool
    {
        return Invoice::where('user_id', $userId)
            ->whereNotIn('status', self::VOID_INVOICE_STATUSES)
            ->where('invoice_date', '>=', $this->dayFloor($since))
            ->exists();
    }

    /**
     * A package bought on or after $since.
     */
    protected function hasPackagePurchaseSince(int $userId, Carbon|string $since): bool
    {
        return UserPackage::where('user_id', $userId)
            ->where('created_at', '>=', $this->dayFloor($since))
            ->exists();
    }

    /**
     * A package session redeemed on or after $since.
     *
     * Redemption is the attendance signal for package patients: the session may
     * generate a zero-value invoice or no invoice at all, so invoice presence
     * alone would miss it.
     */
    protected function hasPackageRedemptionSince(int $userId, Carbon|string $since): bool
    {
        return UserPackageUsage::whereIn(
            'user_package_id',
            UserPackage::where('user_id', $userId)->select('id')
        )
            ->where('created_at', '>=', $this->dayFloor($since))
            ->exists();
    }

    /**
     * A treatment session marked completed on or after $since.
     */
    protected function hasCompletedTreatmentSince(int $userId, Carbon|string $since): bool
    {
        return TreatmentSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $this->dayFloor($since))
            ->exists();
    }

    /**
     * An appointment actually attended on or after $since.
     */
    protected function hasAttendedVisitSince(int $userId, Carbon|string $since): bool
    {
        return Appointment::where('user_id', $userId)
            ->whereIn('status', [
                AppointmentStatus::Completed,
                AppointmentStatus::InProgress,
            ])
            ->where('start_datetime', '>=', $this->dayFloor($since))
            ->exists();
    }

    /**
     * Did the patient convert — in any form — on or after $since?
     *
     * This is the single gate every conversion and rescue trigger must pass. A
     * patient counts as converted if they paid, bought a package, redeemed a
     * session, completed a treatment, or simply turned up. Each trigger
     * previously tested one of these in isolation, which is why a patient could
     * satisfy four of the five and still be chased.
     */
    protected function hasConvertedSince(int $userId, Carbon|string $since): bool
    {
        return $this->hasInvoiceSince($userId, $since)
            || $this->hasPackagePurchaseSince($userId, $since)
            || $this->hasPackageRedemptionSince($userId, $since)
            || $this->hasCompletedTreatmentSince($userId, $since)
            || $this->hasAttendedVisitSince($userId, $since);
    }

    /**
     * Is there an appointment still ahead of this patient?
     */
    /**
     * When this patient is next due in, if they are.
     *
     * The date as well as the fact, because the call-commitment card keeps a
     * promise alive for somebody who has booked and has to say when they are
     * coming: "send this before Monday" is a job, "they have booked" is not.
     */
    protected function nextAppointmentAt(int $userId): ?Carbon
    {
        return Appointment::where('user_id', $userId)
            ->countsAsBooked()
            ->orderBy('start_datetime')
            ->value('start_datetime');
    }

    /**
     * Whether this patient is already coming in, or has been in today.
     *
     * The seven triggers that argue for getting somebody booked all skip on
     * this. The conditions live on Appointment::scopeCountsAsBooked() so they
     * cannot drift from the lead engine's copy of the same question — which is
     * exactly what happened, and what put a patient back in the queue while she
     * was in the chair.
     */
    protected function hasFutureAppointment(int $userId): bool
    {
        return Appointment::where('user_id', $userId)
            ->countsAsBooked()
            ->exists();
    }

    /**
     * The most recent date on which this patient did anything at all.
     *
     * Used by the retention triggers, where "days since last session" read from
     * a single table is misleading: a treatment-plan row can look abandoned
     * while the patient was invoiced yesterday under a different plan.
     */
    protected function lastActivityAt(int $userId): ?Carbon
    {
        $candidates = [];

        $lastVisit = Appointment::where('user_id', $userId)
            ->whereIn('status', [AppointmentStatus::Completed, AppointmentStatus::InProgress])
            ->max('start_datetime');

        $lastInvoice = Invoice::where('user_id', $userId)
            ->whereNotIn('status', self::VOID_INVOICE_STATUSES)
            ->max('invoice_date');

        $lastSession = TreatmentSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->max('updated_at');

        $lastRedemption = UserPackageUsage::whereIn(
            'user_package_id',
            UserPackage::where('user_id', $userId)->select('id')
        )->max('created_at');

        foreach ([$lastVisit, $lastInvoice, $lastSession, $lastRedemption] as $moment) {
            if ($moment !== null) {
                $candidates[] = Carbon::parse($moment);
            }
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortDesc()->first();
    }

    /**
     * Apply the trigger weighting to a raw score from the shared formula.
     *
     * Kept out of calculatePriority() so the shared trait stays byte-identical
     * for the lead engine, which applies its own weights.
     */
    protected function weighted(int $rawScore, string $trigger): int
    {
        return (int) min(
            self::MAX_SCORE,
            round($rawScore * (self::TRIGGER_WEIGHT[$trigger] ?? 1.0))
        );
    }

    /**
     * Estimate daily appointment slots for a clinic based on operating hours.
     * Each slot = 90 minutes. Returns approximate slot count.
     */
    protected function estimateDailySlots(Clinic $clinic): int
    {
        if (!$clinic->start_time || !$clinic->end_time) {
            return 5; // Default: ~8 hrs / 1.5 hrs = 5 slots
        }

        $start = Carbon::parse($clinic->start_time);
        $end = Carbon::parse($clinic->end_time);
        $minutes = max(90, $start->diffInMinutes($end));

        return (int) floor($minutes / 90);
    }

    /**
     * Get summary statistics for a clinic on a given date.
     */
    public function getSummaryStats(?int $clinicId = null, ?string $date = null): array
    {
        $date = $date ?? Carbon::today()->toDateString();

        $query = AiActionLog::where('generated_date', $date)
            ->where('is_active', true);

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        $totalActions = (clone $query)->count();
        $highPriority = (clone $query)->where('priority_score', '>=', self::HIGH_PRIORITY_THRESHOLD)->count();
        $rescueCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_RESCUE)->count();
        $conversionCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_CONVERSION)->count();
        $retentionCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_RETENTION)->count();
        $capacityCount = (clone $query)->where('action_category', AiActionLog::CATEGORY_CAPACITY)->count();
        $completedCount = AiActionLog::where('generated_date', $date)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereNotNull('staff_outcome')
            ->count();

        // Today's empty slots across clinics or for specific clinic
        $todayBooked = Appointment::whereDate('start_datetime', Carbon::today())
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->count();

        $clinicQuery = Clinic::where('is_active', true);
        if ($clinicId) {
            $clinicQuery->where('id', $clinicId);
        }

        $totalDailySlots = 0;
        foreach ($clinicQuery->get() as $clinic) {
            $totalDailySlots += $this->estimateDailySlots($clinic);
        }

        $emptySlots = max(0, $totalDailySlots - $todayBooked);

        // Estimated potential incremental appointments
        $estimatedPotentialMin = (int) round($highPriority * 0.15);
        $estimatedPotentialMax = (int) round($highPriority * 0.35);

        return [
            'total_actions' => $totalActions,
            'high_priority' => $highPriority,
            'rescue_count' => $rescueCount,
            'conversion_count' => $conversionCount,
            'retention_count' => $retentionCount,
            'capacity_count' => $capacityCount,
            'completed_count' => $completedCount,
            'empty_slots_today' => $emptySlots,
            'estimated_potential_min' => max(1, $estimatedPotentialMin),
            'estimated_potential_max' => max(2, $estimatedPotentialMax),
        ];
    }
}
