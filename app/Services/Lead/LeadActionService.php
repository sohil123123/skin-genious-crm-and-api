<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Enums\LeadStatus;
use App\Models\AiActionLog;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Lead;
use App\Enums\Call\CallSignalKey;
use App\Models\CallInsightSignal;
use App\Models\LeadActionLog;
use App\Models\LeadCustomField;
use App\Models\LeadFieldValue;
use App\Services\Concerns\CalculatesActionPriority;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the daily Next Best Action queue for imported Meta leads.
 *
 * Structured as a sibling of AiActionService and scored through the same
 * shared formula, but reading from `leads` rather than `users`. It never
 * touches the patient engine.
 *
 * The governing insight is that a paid lead's value decays far faster than a
 * patient's. A patient overdue for a package session is worth roughly the same
 * today as tomorrow; a lead who wrote "I want to visit today or tomorrow" is
 * worth a fraction tomorrow of what they are worth this morning. The scoring
 * below is therefore driven by the lead's own answers, not by a flat rule.
 */
class LeadActionService
{
    use CalculatesActionPriority;

    // What was said on the phone, read the same way by both engines.
    use \App\Services\Concerns\AppliesCallSignals;

    /** Key of the lead-form question that reveals visit urgency. */
    protected const VISIT_TIMING_KEY = 'when_would_you_like_to_visit';

    protected const CONCERN_KEY = 'what_is_your_main_skin_concern';

    protected const SESSION_INTEREST_KEY = 'which_session_are_you_interested_in';

    /**
     * Intent weight per visit-timing answer.
     *
     * These are the exact values Meta's forms return for this account, so the
     * mapping is against real data rather than invented categories.
     */
    protected const TIMING_INTENT = [
        'today_/_tomorrow' => 0.95,
        'this_week' => 0.90,
        'this_weekend' => 0.75,
        'later,_just_exploring' => 0.45,
        'just_enquiring' => 0.45,
    ];

    /** Answers that qualify a lead as "wants to visit now". */
    protected const HOT_TIMINGS = ['today_/_tomorrow', 'this_week'];

    /** How long a lead may sit uncontacted before it is considered aging. */
    protected int $agingDays = 7;

    /**
     * Score at or above which a lead action counts as high priority.
     *
     * Lower than the patient queue's 70 by design. The shared formula
     * multiplies five sub-1.0 factors, so its output lives in roughly the 0-60
     * band; on real data 40+ isolates exactly the declared-intent and
     * existing-patient actions, which is what "high priority" should mean. The
     * patient queue keeps its own 70 threshold untouched.
     */
    public const HIGH_PRIORITY_THRESHOLD = 40;

    /**
     * Trigger weighting applied after the shared formula.
     *
     * The shared formula multiplies five sub-1.0 factors, which compresses its
     * output into roughly the 0-60 band — a same-day lead scoring perfectly on
     * every axis still only reaches 77. That behaviour is deliberately left
     * alone so the patient engine's scores do not move, but it means the raw
     * score cannot express "drop everything and call this person".
     *
     * These multipliers restore the intended spread across the lead queue:
     * declared same-day intent lands at the top of the range, a month-old
     * "just enquiring" sits at the bottom, and the ordering reflects what the
     * lead actually told the form.
     */
    protected const TRIGGER_WEIGHT = [
        LeadActionLog::TRIGGER_HOT_INTENT => 1.55,
        LeadActionLog::TRIGGER_EXISTING_PATIENT => 1.45,
        LeadActionLog::TRIGGER_STALLED => 1.30,
        LeadActionLog::TRIGGER_NEVER_CONTACTED => 1.15,
        // Above hot intent: a form said what they wanted, a call is them
        // saying it, and asking twice is how a warm lead goes cold.
        LeadActionLog::TRIGGER_CALL_COMMITMENT => 1.65,
    ];

    /**
     * Ceiling for a weighted score.
     *
     * Held below 100 so the hot band cannot saturate. At 1.85 the top three
     * fresh leads all clamped to exactly 100 and lost their relative order,
     * which defeats the point of a ranked queue on the busiest morning.
     */
    protected const MAX_SCORE = 97;

    /**
     * Triggers that stop making sense the moment the lead books.
     *
     * Everything except the call commitment: each of the others exists to
     * argue for getting this person into the diary, and a promise made on the
     * phone is the one thing an appointment does not discharge.
     *
     * One list, read both when the queue is built and when a later booking
     * reconciles it, so the two cannot disagree.
     */
    public const TRIGGERS_SUPERSEDED_BY_BOOKING = [
        LeadActionLog::TRIGGER_HOT_INTENT,
        LeadActionLog::TRIGGER_EXISTING_PATIENT,
        LeadActionLog::TRIGGER_NEVER_CONTACTED,
        LeadActionLog::TRIGGER_STALLED,
    ];

    /** Statuses that are finished, one way or another. */
    protected const CLOSED_STATUSES = [
        LeadStatus::Junk->value,
        LeadStatus::Lost->value,
        LeadStatus::Won->value,
        LeadStatus::Unqualified->value,
    ];

    /**
     * Eligible leads per clinic, for the length of one generation pass.
     *
     * @var array<int, \Illuminate\Support\Collection<int, Lead>>
     */
    protected array $eligibleLeadCache = [];

    /**
     * Next appointment per phone key per clinic, for one generation pass.
     *
     * @var array<int, Collection<string, Carbon>>
     */
    protected array $upcomingAppointmentCache = [];

    public function __construct(
        protected PhoneNormalizerService $phoneNormalizer,
    ) {}

    /**
     * Generate lead actions for today across the given clinics.
     */
    public function generateForToday(?array $clinicIds = null): int
    {
        $clinics = Clinic::where('is_active', true)
            ->when($clinicIds, fn ($q) => $q->whereIn('id', $clinicIds))
            ->get();

        $total = 0;

        foreach ($clinics as $clinic) {
            $total += $this->generateForClinic($clinic);
        }

        return $total;
    }

    /**
     * Generate lead actions for a single clinic.
     */
    public function generateForClinic(Clinic $clinic): int
    {
        // Yesterday's unworked actions are retired rather than deleted, so the
        // history of what was suggested survives for later analysis.
        LeadActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', '<', Carbon::today())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Today's untouched actions are rebuilt from scratch; anything a staff
        // member has already acted on is left exactly as it is.
        LeadActionLog::where('clinic_id', $clinic->id)
            ->where('generated_date', Carbon::today())
            ->whereNull('staff_outcome')
            ->delete();

        $actions = collect()
            ->merge($this->findHotIntent($clinic))
            ->merge($this->findExistingPatientLeads($clinic))
            ->merge($this->findNeverContacted($clinic))
            ->merge($this->findStalled($clinic))
            ->merge($this->findOpenCallCommitments($clinic));

        // Somebody with an appointment already in the diary does not need
        // chasing to make one. Every trigger above except the call commitment
        // is an argument for getting this person booked, and reading one of
        // them out to a lead who is coming in at four this afternoon is the
        // most visible way for this queue to look broken.
        //
        // The call commitment survives on purpose: it is a promise the clinic
        // made — a price list, a callback — and having an appointment does not
        // discharge it. The patient engine draws the same line.
        $booked = $this->upcomingAppointmentsByPhoneKey($clinic);

        if ($booked->isNotEmpty()) {
            $actions = $actions->reject(function (array $action) use ($booked): bool {
                if (! in_array($action['action_trigger'], self::TRIGGERS_SUPERSEDED_BY_BOOKING, true)) {
                    return false;
                }

                $key = $this->phoneNormalizer->matchKey($action['_phone'] ?? null);

                return $key !== null && $booked->has($key);
            })->values();
        }

        // What the lead actually said on the phone: can raise or lower a score,
        // and drops the action entirely for somebody who declined or booked.
        $actions = $this->decorateWithCallSignals($actions);

        // One action per lead: the triggers overlap by design (a hot lead is
        // also an uncontacted one), so the strongest reason wins.
        $deduplicated = $actions
            ->groupBy('lead_id')
            ->map(fn (Collection $group) => $group->sortByDesc('priority_score')->first())
            ->values();

        // Leads whose phone already has a patient action today are dropped, so
        // nobody is called twice in one morning from two different queues.
        $suppressed = $this->phoneNumbersWithPatientActionToday($clinic);

        $created = 0;

        foreach ($deduplicated as $action) {
            if ($suppressed->isNotEmpty()) {
                $key = $this->phoneNormalizer->matchKey($action['_phone'] ?? null);

                if ($key !== null && $suppressed->contains($key)) {
                    continue;
                }
            }

            unset($action['_phone']);

            // The unique (lead_id, generated_date) index is the real guarantee;
            // updateOrCreate keeps a repeated Regenerate from throwing.
            LeadActionLog::updateOrCreate(
                [
                    'lead_id' => $action['lead_id'],
                    'generated_date' => $action['generated_date'],
                ],
                $action
            );

            $created++;
        }

        return $created;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 1: Wants to visit now
    // ──────────────────────────────────────────────────────────────

    /**
     * Leads who told us on the form that they want to come in imminently.
     *
     * This is the most perishable opportunity in the whole CRM: the patient has
     * declared intent and the clinic has already paid to acquire them. Recency
     * decays over three days rather than the usual weeks, so a same-day lead
     * that has gone a week cold stops outranking everything else.
     */
    /**
     * Leads who asked for something on the phone and have not had it.
     *
     * The mirror of the patient engine's trigger, and the reason both queues
     * needed this: a lead who said "send me the price" is the warmest thing in
     * the list, and until now the engine could only see that somebody rang them
     * — not what was said. "Contacted three days ago" and "asked for a quote
     * three days ago" led to the same recommendation.
     *
     * Closed leads are excluded, as everywhere else here: a lead marked Won,
     * Lost, Junk or Unqualified is finished, whatever the last call suggested.
     */
    protected function findOpenCallCommitments(Clinic $clinic): Collection
    {
        $reader = $this->callSignals();

        $grouped = CallInsightSignal::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotNull('lead_id')
            // The same set the patient engine fires on, from the enum. When
            // each engine kept its own list they drifted immediately — this one
            // listened for high intent and buying signals, the patient one did
            // not; that one listened for an unresolved issue, this one did not;
            // and neither listened for "staff followup required" at all.
            ->whereIn('signal_key', CallSignalKey::followUpValues())
            ->recent($reader->windowDays())
            ->confident($reader->confidenceThreshold())
            ->with('lead')
            ->orderByDesc('occurred_at')
            ->get()
            ->groupBy('lead_id');

        $actions = collect();

        foreach ($grouped as $leadId => $group) {
            $lead = $group->first()->lead;

            if (! $lead || in_array($lead->status?->value, self::CLOSED_STATUSES, true)) {
                continue;
            }

            $subjectSignals = $reader->forLead((int) $leadId);

            if ($this->callSignalsSuppress($subjectSignals)) {
                continue;
            }

            $latest = $group->first();

            // Hours, not calendar days — see the note on the patient engine's
            // copy of this. The old rule compared against midnight and so could
            // not see yesterday's calls in this morning's queue.
            if (! $reader->isDue($latest->occurred_at)) {
                continue;
            }

            $daysSince = (int) max(0, $latest->occurred_at?->diffInDays(now()) ?? 0);

            // A promise survives a booking; the urgency behind it does not.
            //
            // Somebody who asked for treatment details on Tuesday and booked a
            // consultation on Wednesday is still owed the details, so this
            // action stays — but the thing it was pushing towards has already
            // happened, and it should not outrank a lead nobody has managed to
            // book at all.
            $booked = $this->nextAppointmentFor($clinic, $lead);

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.95,
                'recency' => max(0.3, 1 - ($daysSince / 14)),
                'treatment_fit' => 0.8,
                'urgency' => $booked !== null ? 0.5 : 0.9,
                'slot_availability' => 0.8,
                'fatigue_penalty' => $this->contactFatiguePenaltyFor(LeadActionLog::class, 'lead_id', (int) $leadId),
            ]), LeadActionLog::TRIGGER_CALL_COMMITMENT);

            $wantsWriting = $group->contains(
                fn (CallInsightSignal $signal): bool => $signal->signal_key === CallSignalKey::InformationRequested
            );

            $actions->push([
                'clinic_id' => $clinic->id,
                'lead_id' => (int) $leadId,
                'matched_user_id' => null,
                'action_category' => LeadActionLog::CATEGORY_CONVERSION,
                'action_trigger' => LeadActionLog::TRIGGER_CALL_COMMITMENT,
                'priority_score' => $priority,
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
                'suggested_message' => $this->callAwareScript($lead->first_name, $subjectSignals)
                    ?? sprintf(
                        'Hi %s, following up on your call — sending across what you asked about.',
                        $lead->first_name ?: 'there',
                    ),
                // The goal changes once they are booked. "Move to a
                // consultation" is work already done, and a card that asks for
                // it sends staff to sell an appointment the lead already has.
                'goal' => $booked !== null
                    ? 'Send what was promised before they come in'
                    : 'Answer what they asked for and move to a consultation',
                'avoid_notes' => $booked !== null
                    ? 'Do not pitch the consultation — it is already booked. Just send what was promised.'
                    : 'They already told us what they want. Do not restart the pitch.',
                'assigned_to' => null,
                'related_call_id' => $latest->call_id,
                'call_signals' => $reader->toBasis($subjectSignals),
                'expires_at' => Carbon::today()->endOfDay(),
                'generated_date' => Carbon::today(),
                'is_active' => true,
                '_phone' => $lead->phone,
            ]);
        }

        return $actions;
    }

    /**
     * Fold call insight into the actions the existing lead triggers produced.
     *
     * Identical in shape to the patient engine's pass, and deliberately so:
     * staff read the two queues as one worklist, and a lead who said "not
     * interested" must drop out of it exactly as a patient would.
     *
     * @param  Collection<int, array<string, mixed>>  $actions
     * @return Collection<int, array<string, mixed>>
     */
    protected function decorateWithCallSignals(Collection $actions): Collection
    {
        if ($actions->isEmpty()) {
            return $actions;
        }

        $leadIds = $actions->pluck('lead_id')->filter()->all();

        $signalsByLead = $this->callSignals()->forLeads($leadIds);

        // The script is spoken to the lead, so it needs their name.
        $names = Lead::whereIn('id', $leadIds)->pluck('first_name', 'id');

        return $actions
            ->map(function (array $action) use ($signalsByLead, $names): ?array {
                $signals = $signalsByLead->get($action['lead_id']) ?? collect();

                if ($signals->isEmpty()) {
                    return $action;
                }

                // Already built from these signals; see the patient engine's
                // copy of this. Decorating it again duplicated the sentence in
                // the reason and double-counted the call in the score.
                if ($action['action_trigger'] === LeadActionLog::TRIGGER_CALL_COMMITMENT) {
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

                // The form script greets a stranger. Once somebody has been on
                // the phone, opening with "thank you for your enquiry" tells
                // them the call they had did not register anywhere.
                $script = $this->callAwareScript($names->get($action['lead_id']), $signals);

                if ($script !== null) {
                    $action['suggested_message'] = $script;
                }

                return $action;
            })
            ->filter()
            ->values();
    }

    protected function findHotIntent(Clinic $clinic): Collection
    {
        $actions = collect();

        foreach ($this->eligibleLeads($clinic) as $lead) {
            $intent = $this->visitIntent($lead);

            if ($intent === null || ! $intent['hot']) {
                continue;
            }

            $ageDays = $this->ageInDays($lead);

            $priority = $this->weighted($this->calculatePriority([
                'intent' => $intent['weight'],
                // Two-day half-life: declared same-day intent is the fastest
                // decaying signal the clinic has.
                'recency' => $this->decay($ageDays, 2),
                'treatment_fit' => 0.95,
                'urgency' => $ageDays <= 1 ? 1.0 : 0.9,
                'slot_availability' => 0.95,
                'fatigue_penalty' => $this->fatigueFor($lead),
            ]), LeadActionLog::TRIGGER_HOT_INTENT);

            $actions->push($this->buildAction(
                lead: $lead,
                clinic: $clinic,
                category: LeadActionLog::CATEGORY_CONVERSION,
                trigger: LeadActionLog::TRIGGER_HOT_INTENT,
                priority: $priority,
                channel: 'call',
                time: 'Within the next 2 hours',
                reason: sprintf(
                    'Asked to visit %s and enquired %s. Paid lead with declared intent — value drops sharply each day.',
                    $intent['phrase'],
                    $this->agePhrase($ageDays),
                ),
                goal: 'Book an appointment today or tomorrow',
                avoid: 'Do not leave this to WhatsApp alone — call first. Do not discount; they have not raised price.',
                // A same-day lead is worthless by tomorrow morning.
                expiresAt: Carbon::today()->endOfDay(),
            ));
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 2: Lead is an existing patient
    // ──────────────────────────────────────────────────────────────

    /**
     * Leads whose phone matched a patient record during import.
     *
     * These need a completely different opening: the person already knows the
     * clinic, so treating them as a cold enquiry is jarring and wastes the
     * relationship. The reason line names their history so staff can open with
     * it.
     */
    protected function findExistingPatientLeads(Clinic $clinic): Collection
    {
        $actions = collect();

        foreach ($this->eligibleLeads($clinic)->whereNotNull('matched_user_id') as $lead) {
            $ageDays = $this->ageInDays($lead);
            $intent = $this->visitIntent($lead);

            $lastVisit = Appointment::where('user_id', $lead->matched_user_id)
                ->where('status', \App\Enums\AppointmentStatus::Completed)
                ->orderByDesc('start_datetime')
                ->first();

            $historyNote = $lastVisit !== null
                ? sprintf('Last visited %s.', Carbon::parse($lastVisit->start_datetime)->format('d M Y'))
                : 'Registered patient with no completed visit yet.';

            $priority = $this->weighted($this->calculatePriority([
                'intent' => 0.85,
                // Decays more slowly: an existing patient's relationship does
                // not evaporate the way a cold enquiry does.
                'recency' => $this->decay($ageDays, 10),
                // They are a known quantity, so treatment fit is high.
                'treatment_fit' => 0.95,
                'urgency' => ($intent['hot'] ?? false) ? 0.95 : 0.8,
                'slot_availability' => 0.9,
                'fatigue_penalty' => $this->fatigueFor($lead),
            ]), LeadActionLog::TRIGGER_EXISTING_PATIENT);

            $actions->push($this->buildAction(
                lead: $lead,
                clinic: $clinic,
                category: LeadActionLog::CATEGORY_RETENTION,
                trigger: LeadActionLog::TRIGGER_EXISTING_PATIENT,
                priority: $priority,
                channel: 'call',
                time: '11:00 AM - 1:00 PM',
                reason: sprintf(
                    'Existing patient responded to a Facebook ad %s. %s Treat as a returning patient, not a new enquiry.',
                    $this->agePhrase($ageDays),
                    $historyNote,
                ),
                goal: 'Rebook a known patient',
                avoid: 'Do not read a cold-lead script. Acknowledge that they have been here before.',
                expiresAt: Carbon::today()->addDays(2)->endOfDay(),
            ));
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 3: New lead, never contacted
    // ──────────────────────────────────────────────────────────────

    /**
     * Leads still sitting at "new" with no outreach recorded.
     *
     * Intent is taken from what they actually said about timing rather than
     * being flat, so a "just enquiring" lead does not outrank a patient who is
     * overdue for treatment and far more likely to book.
     */
    protected function findNeverContacted(Clinic $clinic): Collection
    {
        $actions = collect();

        // Leads whose number the clinic has actually rung, whatever record the
        // call ended up attached to.
        //
        // Matched on the phone key rather than on lead_id, because a person can
        // exist twice — once as a lead from an ad, once as a patient — and the
        // call matcher attaches to the patient. That is the case that produced
        // a card reading "has never been contacted" nine hours after somebody
        // had contacted them, which is the kind of thing that stops staff
        // trusting the whole queue.
        $called = $this->recentCallsByPhoneKey($clinic);

        foreach ($this->eligibleLeads($clinic) as $lead) {
            if ($lead->status !== LeadStatus::New) {
                continue;
            }

            $lastCall = $called->get($this->phoneNormalizer->matchKey($lead->phone) ?? '_');

            $ageDays = $this->ageInDays($lead);

            // Same-day leads are deliberately included. They used to be skipped
            // on the assumption that the hot-intent trigger would claim them,
            // but that only holds when the lead declared a near visit date — an
            // overnight enquiry that left the timing question blank was claimed
            // by nothing and never appeared in the 7:10 AM queue at all. The
            // deduplication below still lets hot intent win where it applies.
            $intent = $this->visitIntent($lead);

            $priority = $this->weighted($this->calculatePriority([
                'intent' => $intent['weight'] ?? 0.55,
                'recency' => $this->decay($ageDays, 7),
                'treatment_fit' => 0.8,
                // Urgency rises as the lead ages: an untouched enquiry becomes
                // more embarrassing, not less, the longer it sits.
                'urgency' => $ageDays >= $this->agingDays ? 0.85 : 0.7,
                'slot_availability' => 0.85,
                'fatigue_penalty' => $this->fatigueFor($lead),
            ]), LeadActionLog::TRIGGER_NEVER_CONTACTED);

            $actions->push($this->buildAction(
                lead: $lead,
                clinic: $clinic,
                category: LeadActionLog::CATEGORY_CONVERSION,
                trigger: LeadActionLog::TRIGGER_NEVER_CONTACTED,
                priority: $priority,
                channel: $ageDays >= $this->agingDays ? 'whatsapp' : 'call',
                time: '10:00 AM - 12:00 PM',
                // Two different sentences, because they describe two different
                // situations. A lead nobody has rung needs a first call. A lead
                // somebody rang, who is still sitting at New, needs the outcome
                // of that call recording — and telling staff to make first
                // contact would have them repeat a conversation that already
                // happened.
                reason: $lastCall !== null
                    ? sprintf(
                        'Enquired %s and was called %s, but is still marked New — the outcome was never recorded.',
                        $this->agePhrase($ageDays),
                        $lastCall->started_at?->diffForHumans() ?? 'recently',
                    )
                    : sprintf(
                        'Enquired %s and has never been contacted.%s',
                        $this->agePhrase($ageDays),
                        $ageDays >= $this->agingDays
                            ? ' Over a week old — try WhatsApp, calls are unlikely to land now.'
                            : '',
                    ),
                goal: $lastCall !== null
                    ? 'Record what came of the call and move the lead on'
                    : 'Make first contact and qualify the enquiry',
                avoid: match (true) {
                    $lastCall !== null => 'They have already been spoken to. Do not open as a first call.',
                    $ageDays >= $this->agingDays => 'Do not apologise for the delay — it draws attention to it.',
                    default => 'Do not open with a price. Ask about their concern first.',
                },
                expiresAt: Carbon::today()->addDays(3)->endOfDay(),
            ));
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  TRIGGER 4: Contacted but never booked
    // ──────────────────────────────────────────────────────────────

    /**
     * Leads someone has spoken to, where the conversation then died.
     *
     * This is where ad spend is most often wasted: the hard part (making
     * contact) is already done and nobody closed the loop.
     */
    protected function findStalled(Clinic $clinic): Collection
    {
        $actions = collect();

        foreach ($this->eligibleLeads($clinic) as $lead) {
            if (! in_array($lead->status, [LeadStatus::Contacted, LeadStatus::Qualified], true)) {
                continue;
            }

            $stalledDays = $this->calendarDaysSince($lead->updated_at);

            if ($stalledDays < 3) {
                continue;
            }

            $priority = $this->weighted($this->calculatePriority([
                'intent' => $lead->status === LeadStatus::Qualified ? 0.85 : 0.70,
                'recency' => $this->decay($stalledDays, 10),
                'treatment_fit' => 0.85,
                'urgency' => $stalledDays >= 7 ? 0.9 : 0.7,
                'slot_availability' => 0.85,
                'fatigue_penalty' => $this->fatigueFor($lead),
            ]), LeadActionLog::TRIGGER_STALLED);

            $actions->push($this->buildAction(
                lead: $lead,
                clinic: $clinic,
                category: LeadActionLog::CATEGORY_CONVERSION,
                trigger: LeadActionLog::TRIGGER_STALLED,
                priority: $priority,
                channel: 'whatsapp',
                time: '12:00 PM - 2:00 PM',
                reason: sprintf(
                    'Marked "%s" %d days ago but never booked. Contact was already made — the conversation just stopped.',
                    $lead->status?->getLabel() ?? 'contacted',
                    $stalledDays,
                ),
                goal: 'Close the loop and secure a booking',
                avoid: 'Do not restart the conversation from scratch. Reference what was already discussed.',
                expiresAt: Carbon::today()->addDays(3)->endOfDay(),
            ));
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────
    //  Shared construction
    // ──────────────────────────────────────────────────────────────

    /**
     * Assemble one action row, including the drafted script.
     *
     * @return array<string, mixed>
     */
    protected function buildAction(
        Lead $lead,
        Clinic $clinic,
        string $category,
        string $trigger,
        int $priority,
        string $channel,
        string $time,
        string $reason,
        string $goal,
        string $avoid,
        Carbon $expiresAt,
    ): array {
        return [
            'clinic_id' => $clinic->id,
            'lead_id' => $lead->id,
            'matched_user_id' => $lead->matched_user_id,
            'action_category' => $category,
            'action_trigger' => $trigger,
            'priority_score' => $priority,
            'recommended_channel' => $channel,
            'recommended_time' => $time,
            'reason' => $reason,
            'suggested_message' => $this->draftMessage($lead, $trigger),
            'goal' => $goal,
            'avoid_notes' => $avoid,
            'assigned_to' => $lead->assigned_to,
            'expires_at' => $expiresAt,
            'generated_date' => Carbon::today(),
            'is_active' => true,
            // Carried only as far as the phone-suppression check, then removed.
            '_phone' => $lead->phone,
        ];
    }

    /**
     * Draft an opening message from what the lead actually told the form.
     *
     * A generic "would you like to book?" wastes the single biggest advantage
     * of a Meta lead: they have already stated their concern and their timing.
     */
    protected function draftMessage(Lead $lead, string $trigger): string
    {
        $name = $lead->first_name ?: ($lead->full_name ?: 'there');
        $concern = $this->answer($lead, self::CONCERN_KEY);
        $session = $this->answer($lead, self::SESSION_INTEREST_KEY);
        $intent = $this->visitIntent($lead);

        if ($trigger === LeadActionLog::TRIGGER_EXISTING_PATIENT) {
            return sprintf(
                'Hi %s, lovely to hear from you again! We saw your enquiry%s. Shall we get you booked in?',
                $name,
                $concern !== null ? ' about ' . $this->humanize($concern) : '',
            );
        }

        $opening = sprintf('Hi %s, thank you for your enquiry with AI Aesthetics', $name);

        if ($concern !== null) {
            $opening .= sprintf(' about %s', $this->humanize($concern));
        }

        $opening .= '. ';

        if ($intent !== null && $intent['hot']) {
            $opening .= 'You mentioned you would like to visit ' . $intent['phrase']
                . ' — I have a couple of slots I can hold for you. Which suits better?';
        } elseif ($session !== null) {
            $opening .= sprintf(
                'You were interested in %s. Would you like me to explain what it involves and check availability?',
                $this->humanize($session),
            );
        } else {
            $opening .= 'Would you like to book a skin analysis so we can recommend the right treatment for you?';
        }

        return $opening;
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Leads eligible for any action today.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Lead>
     */
    protected function eligibleLeads(Clinic $clinic)
    {
        // Memoised per clinic: all four triggers walk the same set, and
        // re-querying it four times would quadruple the work for no gain.
        //
        // On the instance, not in a static. A static local lives as long as the
        // PHP process, so under a queue worker — or Octane, or a test run — the
        // second generation for a clinic would score the leads as they were the
        // first time, silently, including leads since deleted. The memoisation
        // is only meant to last one pass.
        if (isset($this->eligibleLeadCache[$clinic->id])) {
            return $this->eligibleLeadCache[$clinic->id];
        }

        return $this->eligibleLeadCache[$clinic->id] = Lead::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->whereNotNull('phone')
            ->with(['fieldValues.customField', 'matchedUser'])
            ->get();
    }

    /**
     * Read one of the lead's form answers by question key.
     *
     * Returns the raw stored value, so scoring compares against Meta's exact
     * strings and display humanises separately.
     */
    protected function answer(Lead $lead, string $key): ?string
    {
        foreach ($lead->fieldValues as $value) {
            if ($value->customField?->key === $key) {
                $raw = trim((string) $value->value);

                return $raw === '' ? null : $raw;
            }
        }

        return null;
    }

    /**
     * What the lead told us about when they want to come in.
     *
     * The visit-timing question comes back in two completely different shapes
     * depending on how the form was built, and only one of them was ever read:
     *
     *   - A multiple-choice form answers with Meta's own slugs, e.g.
     *     "today_/_tomorrow" — these are the keys in TIMING_INTENT.
     *   - An appointment-request form answers with a real date and time, e.g.
     *     "2026-09-08T11:25:00+0530" or "Sep 8, 2026 at 11:25 AM IST".
     *
     * Every live form on this account is the second kind, so matching only
     * against the slug list meant the hot-intent trigger could never fire for a
     * single lead, and the strongest buying signal in the CRM went unread.
     *
     * @return array{weight: float, phrase: string, hot: bool}|null
     */
    protected function visitIntent(Lead $lead): ?array
    {
        $answer = $this->answer($lead, self::VISIT_TIMING_KEY);

        if ($answer === null) {
            return null;
        }

        if (array_key_exists($answer, self::TIMING_INTENT)) {
            return [
                'weight' => self::TIMING_INTENT[$answer],
                'phrase' => '"' . $this->humanize($answer) . '"',
                'hot' => in_array($answer, self::HOT_TIMINGS, true),
            ];
        }

        $date = LeadFieldValue::parseAnswerDate($answer);

        if ($date === null) {
            return null;
        }

        $daysAway = (int) Carbon::today()->diffInDays($date->copy()->startOfDay());

        return match (true) {
            // The slot they asked for has already gone by. That is a missed
            // appointment request rather than a cold lead, but it is no longer
            // time-critical the way an upcoming date is, so it is handed to the
            // ageing triggers instead of the hot one.
            $daysAway < 0 => [
                'weight' => 0.55,
                'phrase' => 'on ' . $date->format('d M'),
                'hot' => false,
            ],
            $daysAway <= 1 => [
                'weight' => 0.95,
                'phrase' => $daysAway === 0 ? 'today' : 'tomorrow',
                'hot' => true,
            ],
            $daysAway <= 6 => [
                'weight' => 0.90,
                'phrase' => 'on ' . $date->format('l d M'),
                'hot' => true,
            ],
            $daysAway <= 14 => [
                'weight' => 0.75,
                'phrase' => 'on ' . $date->format('d M'),
                'hot' => false,
            ],
            default => [
                'weight' => 0.55,
                'phrase' => 'on ' . $date->format('d M Y'),
                'hot' => false,
            ],
        };
    }

    protected function humanize(?string $value): string
    {
        return LeadCustomField::humanizeValue($value);
    }

    protected function ageInDays(Lead $lead): int
    {
        $submitted = $lead->fb_created_time ?? $lead->created_at;

        return $this->calendarDaysSince($submitted);
    }

    /**
     * Whole calendar days between a moment and this morning.
     *
     * Deliberately compares midnights rather than the raw instants. Carbon 3's
     * diffInDays() returns a fraction, so rounding it made a lead's age depend
     * on the clock time it arrived at: an enquiry submitted at 10:51 AM
     * yesterday rounded to 1 day old, while one submitted at 5:01 PM the same
     * afternoon rounded to 0 and was then discarded by the "skip same-day
     * leads" guard. Every lead that came in after roughly midday simply never
     * reached the queue the following morning.
     */
    protected function calendarDaysSince(mixed $moment): int
    {
        $days = Carbon::parse($moment)->startOfDay()->diffInDays(Carbon::today());

        // Negative only for a future-dated import; such a lead is "today" as
        // far as the queue is concerned.
        return max(0, (int) $days);
    }

    protected function agePhrase(int $days): string
    {
        return match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'yesterday',
            $days < 7 => "{$days} days ago",
            $days < 14 => 'over a week ago',
            $days < 30 => 'over two weeks ago',
            default => 'over a month ago',
        };
    }

    protected function fatigueFor(Lead $lead): float
    {
        return $this->contactFatiguePenaltyFor(LeadActionLog::class, 'lead_id', $lead->id);
    }

    /**
     * Apply the trigger weighting to a raw score from the shared formula.
     *
     * Kept separate from calculatePriority() so the shared formula stays
     * byte-identical for the patient engine.
     */
    protected function weighted(int $rawScore, string $trigger): int
    {
        $weight = self::TRIGGER_WEIGHT[$trigger] ?? 1.0;

        return (int) min(self::MAX_SCORE, round($rawScore * $weight));
    }

    /**
     * Recency decay tuned to how leads actually arrive.
     *
     * A linear decay over a short window drives every lead in a backfilled
     * import to zero, because they are all weeks old. This decays fast at
     * first — capturing that the first 48 hours matter most — then flattens,
     * so a three-week-old lead still carries a usable, comparable score
     * instead of collapsing into an undifferentiated floor.
     */
    protected function decay(int $ageDays, float $halfLifeDays): float
    {
        return max(0.18, 1 / (1 + ($ageDays / max($halfLifeDays, 0.5))));
    }

    /**
     * Phone match keys for patients who already have an action today.
     *
     * Uses the same last-national-digits key the importer uses, so a lead
     * stored as "+919876543210" matches a patient record saved years ago as
     * "09876543210".
     *
     * @return Collection<int, string>
     */
    /**
     * Bring today's queue back in line after a booking is made.
     *
     * The lead engine's copy of the patient engine's method, and the same
     * reasoning: the queue is a snapshot taken at 07:10, and a lead who books
     * at noon leaves behind a card telling staff to ring and book them.
     *
     * Leads are found by phone number rather than by Lead::matched_user_id,
     * which is null on almost every lead that has actually booked — the
     * booking creates a patient record and nothing links the two.
     */
    public function reconcileWithBooking(string $phone, Carbon $startsAt): void
    {
        $key = $this->phoneNormalizer->matchKey($phone);

        if ($key === null) {
            return;
        }

        $leadIds = Lead::where('phone', 'like', '%' . $key)->pluck('id');

        if ($leadIds->isEmpty()) {
            return;
        }

        $today = LeadActionLog::whereIn('lead_id', $leadIds)
            ->where('generated_date', Carbon::today())
            ->whereNull('staff_outcome');

        (clone $today)
            ->whereIn('action_trigger', self::TRIGGERS_SUPERSEDED_BY_BOOKING)
            ->delete();

        (clone $today)
            ->where('action_trigger', LeadActionLog::TRIGGER_CALL_COMMITMENT)
            ->get()
            ->each(function (LeadActionLog $action) use ($startsAt): void {
                $reason = $this->withBookingNote($action->reason ?? '', $startsAt);

                if ($reason === $action->reason) {
                    return;
                }

                $action->forceFill([
                    'reason' => $reason,
                    'goal' => 'Send what was promised before they come in',
                    'avoid_notes' => 'Do not pitch the consultation — it is already booked. Just send what was promised.',
                ])->save();
            });
    }

    /**
     * The most recent connected call to each number this clinic has rung.
     *
     * Keyed by phone key rather than by lead, so it answers the question the
     * triggers actually ask — "has this person been spoken to" — for a person
     * who exists as both a lead and a patient, or whose call arrived before
     * either record did. The Call model's own scopeForCustomer() is built on
     * the same reasoning.
     *
     * One query for the clinic, not one per lead.
     *
     * @return Collection<string, \App\Models\Call>
     */
    protected function recentCallsByPhoneKey(Clinic $clinic): Collection
    {
        return \App\Models\Call::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotNull('client_phone_key')
            // A missed call is not a contact. Somebody whose phone rang out has
            // still never been spoken to, and telling staff otherwise would
            // suppress the first real conversation.
            ->where('is_connected', true)
            ->where('started_at', '>=', Carbon::today()->subDays($this->callSignals()->windowDays()))
            ->orderByDesc('started_at')
            ->get()
            ->keyBy('client_phone_key');
    }

    /**
     * Phone numbers with an appointment still ahead of them.
     *
     * Matched on the number rather than on Lead::matched_user_id, because that
     * column is null far more often than not — a lead who booked is usually
     * created as a patient by whoever took the booking, and nothing links the
     * two records. Neha Gaur is the case: a lead marked New, a confirmed
     * appointment at four this afternoon under a patient record with the same
     * number, and a queue telling staff to ring her and make first contact.
     *
     * The patient engine has asked this question about its own subjects from
     * the beginning (hasFutureAppointment). The lead engine could not ask it at
     * all, which is why the two queues disagreed about the same person.
     *
     * Carries the date, not just the fact, because a card that keeps a
     * promise alive for somebody who has booked has to say when they are
     * coming in — "send this before Monday" is actionable, "they have booked"
     * is not.
     *
     * Memoised: the generation pass asks twice, once to drop the
     * booking-chasing triggers and once inside the call trigger.
     *
     * @return Collection<string, Carbon>
     */
    protected function upcomingAppointmentsByPhoneKey(Clinic $clinic): Collection
    {
        if (isset($this->upcomingAppointmentCache[$clinic->id])) {
            return $this->upcomingAppointmentCache[$clinic->id];
        }

        $rows = Appointment::query()
            ->where('appointments.clinic_id', $clinic->id)
            // Same definition the patient engine uses, from the model. Keeping
            // a second copy here is how the two came to disagree.
            ->countsAsBooked()
            ->join('users', 'users.id', '=', 'appointments.user_id')
            ->orderBy('appointments.start_datetime')
            ->get(['users.mobile', 'appointments.start_datetime']);

        $map = collect();

        foreach ($rows as $row) {
            $key = $this->phoneNormalizer->matchKey((string) $row->mobile);

            // Ordered ascending, so the first one wins: the next appointment
            // is the one that matters, not the furthest away.
            if ($key !== null && ! $map->has($key)) {
                $map->put($key, Carbon::parse($row->start_datetime));
            }
        }

        return $this->upcomingAppointmentCache[$clinic->id] = $map;
    }

    /**
     * When this lead is next coming in, if they are.
     */
    protected function nextAppointmentFor(Clinic $clinic, Lead $lead): ?Carbon
    {
        $key = $this->phoneNormalizer->matchKey($lead->phone);

        return $key === null ? null : $this->upcomingAppointmentsByPhoneKey($clinic)->get($key);
    }

    protected function phoneNumbersWithPatientActionToday(Clinic $clinic): Collection
    {
        return AiActionLog::query()
            // Every column is table-qualified: users also has clinic_id and
            // is_active, so an unqualified name is ambiguous after the join.
            ->where('ai_action_logs.clinic_id', $clinic->id)
            ->where('ai_action_logs.generated_date', Carbon::today())
            ->where('ai_action_logs.is_active', true)
            ->join('users', 'users.id', '=', 'ai_action_logs.user_id')
            ->pluck('users.mobile')
            ->map(fn ($mobile): ?string => $this->phoneNormalizer->matchKey((string) $mobile))
            ->filter()
            ->unique()
            ->values();
    }

    // ──────────────────────────────────────────────────────────────
    //  Summary
    // ──────────────────────────────────────────────────────────────

    /**
     * Headline numbers for the Leads tab.
     *
     * @return array<string, int>
     */
    public function getSummaryStats(?int $clinicId = null, ?string $date = null): array
    {
        $date = $date ?? Carbon::today()->toDateString();

        $actions = fn () => LeadActionLog::query()
            ->where('generated_date', $date)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        $leads = fn () => Lead::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        return [
            'total_actions' => (clone $actions())->where('is_active', true)->count(),
            'high_priority' => (clone $actions())->where('is_active', true)
                ->where('priority_score', '>=', self::HIGH_PRIORITY_THRESHOLD)->count(),
            'hot_now' => (clone $actions())->where('is_active', true)
                ->where('action_trigger', LeadActionLog::TRIGGER_HOT_INTENT)->count(),
            'never_contacted' => (clone $actions())->where('is_active', true)
                ->where('action_trigger', LeadActionLog::TRIGGER_NEVER_CONTACTED)->count(),
            'existing_patients' => (clone $actions())->where('is_active', true)
                ->where('action_trigger', LeadActionLog::TRIGGER_EXISTING_PATIENT)->count(),
            'completed_count' => (clone $actions())->whereNotNull('staff_outcome')->count(),
            // Deliberately measured against leads, not actions: this is the
            // number that shows work being left undone.
            //
            // Age is the enquiry date, not created_at — created_at is when the
            // CSV was imported, which for a backfilled export is today and
            // would report every stale lead as brand new.
            'aging_uncontacted' => (clone $leads())
                ->where('status', LeadStatus::New->value)
                ->whereRaw(
                    'COALESCE(fb_created_time, created_at) <= ?',
                    [Carbon::today()->subDays($this->agingDays)]
                )
                ->count(),
            'total_open_leads' => (clone $leads())->whereNotIn('status', self::CLOSED_STATUSES)->count(),
        ];
    }
}
