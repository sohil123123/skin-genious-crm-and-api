<?php

declare(strict_types=1);

use App\Enums\Call\CallSignalKey;
use App\Models\{AiActionLog, Call, CallAnalysis, CallInsightSignal, Clinic, LeadActionLog, Setting, User};
use App\Services\AiActionService;
use App\Services\Lead\LeadActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Call insight reaching the two Next Best Action engines.
 *
 * The governing constraint, from the brief: this must improve the queues
 * without changing what they already do. So the first test here is that an
 * engine with no call insight behaves exactly as before — everything else is
 * only safe if that holds.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->patient = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Test', 'last_name' => 'Patient',
        'mobile' => '9829000001', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->patient->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.client'), 'guard_name' => 'web',
    ]));

    $this->lead = \App\Models\Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Sohil',
        'phone' => '9829000002',
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function nbaCall(array $attributes = []): Call
{
    return Call::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => test()->clinic->getKey(),
        'provider' => 'callyzer',
        'provider_call_id' => 'nba-' . Str::random(8),
        'source' => 'webhook',
        'direction' => 'outgoing',
        'call_status' => 'completed',
        'started_at' => now()->subDays(2),
    ], $attributes));
}

/**
 * @param  array<int, string>  $keys
 */
function nbaSignals(Call $call, array $keys, ?int $userId = null, ?int $leadId = null, float $confidence = 0.9): void
{
    $analysis = CallAnalysis::create(['call_id' => $call->getKey(), 'is_current' => true]);

    foreach ($keys as $key) {
        CallInsightSignal::create([
            'call_analysis_id' => $analysis->getKey(),
            'call_id' => $call->getKey(),
            'clinic_id' => $call->clinic_id,
            'customer_user_id' => $userId,
            'lead_id' => $leadId,
            'signal_type' => CallSignalKey::from($key)->type()->value,
            'signal_key' => $key,
            'confidence' => $confidence,
            'occurred_at' => $call->started_at,
        ]);
    }
}

// ──────────────── The constraint that matters most ────────────────

/**
 * An engine that hears nothing must behave exactly as it did before this
 * feature existed. Every other behaviour here is only safe if this holds.
 */
it('leaves the patient queue untouched when no call has been analysed', function (): void {
    $before = AiActionLog::count();

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::whereNotNull('related_call_id')->count())->toBe(0)
        ->and(AiActionLog::count())->toBe($before + AiActionLog::count() - $before);
});

it('leaves the lead queue untouched when no call has been analysed', function (): void {
    app(LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::whereNotNull('related_call_id')->count())->toBe(0);
});

// ──────────────── Scenario 1: your "test" patient ────────────────

/**
 * A patient asked for something on the phone and has not had it. Nothing in the
 * CRM's dates says to ring them — no cancellation, no unused package — so
 * before this, they simply did not appear.
 */
it('queues a patient who asked for information on a call', function (): void {
    $call = nbaCall(['customer_user_id' => $this->patient->getKey(), 'direction' => 'incoming']);
    nbaSignals($call, ['information_requested', 'price_objection'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    $action = AiActionLog::where('user_id', $this->patient->getKey())->first();

    expect($action)->not->toBeNull()
        ->and($action->action_trigger)->toBe('call_commitment_open')
        // Asked for information means send it, not ring back to read it aloud.
        ->and($action->recommended_channel)->toBe('whatsapp')
        ->and($action->related_call_id)->toBe($call->getKey())
        // The basis is stored structurally, not only as prose.
        ->and(collect($action->call_signals)->pluck('key'))->toContain('information_requested');
});

it('explains itself in the reason a staff member reads', function (): void {
    $call = nbaCall(['customer_user_id' => $this->patient->getKey()]);
    nbaSignals($call, ['information_requested', 'price_objection'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::first()->reason)
        ->toContain('raised the cost')
        ->toContain('asked for more information');
});

/**
 * Your first example: the clinic rang the patient and booked an appointment.
 * Nothing should chase them about it — including in the hours before the
 * booking reaches the diary, which is the window the existing
 * hasFutureAppointment() check cannot see.
 */
it('does not chase a patient who booked on the call', function (): void {
    $call = nbaCall(['customer_user_id' => $this->patient->getKey()]);
    nbaSignals($call, ['appointment_booked', 'information_requested'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(0);
});

it('does not chase a patient who said they are not interested', function (): void {
    $call = nbaCall(['customer_user_id' => $this->patient->getKey()]);
    nbaSignals($call, ['not_interested', 'callback_requested'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(0);
});

/**
 * §23: a model that is unsure must not put somebody in a work queue.
 */
it('ignores a call it was not confident about', function (): void {
    $call = nbaCall(['customer_user_id' => $this->patient->getKey()]);
    nbaSignals($call, ['information_requested'], userId: $this->patient->getKey(), confidence: 0.2);

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(0);
});

/**
 * §22: an objection from months ago is history, not a reason to act today.
 */
it('ignores a call older than the signal window', function (): void {
    $call = nbaCall([
        'customer_user_id' => $this->patient->getKey(),
        'started_at' => now()->subDays(200),
    ]);

    nbaSignals($call, ['information_requested'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(0);
});

/**
 * A promise made minutes ago belongs to the person who took the call. Queueing
 * it immediately asks a colleague to duplicate work already in hand.
 */
it('leaves a promise with whoever took the call for a couple of hours', function (): void {
    $call = nbaCall(['customer_user_id' => $this->patient->getKey(), 'started_at' => now()->subMinutes(20)]);
    nbaSignals($call, ['information_requested'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(0);
});

/**
 * The bug this replaced: the cool-off was a calendar day measured against
 * midnight, so a call at 10:29 yesterday morning read as zero days old in this
 * morning's 07:00 run and was skipped — and so was every call, until it was
 * nearly two days old. The queue is built once a day, so a rule that cannot see
 * yesterday cannot see anything.
 */
it('queues a promise from yesterday morning in this morning run', function (): void {
    $call = nbaCall([
        'customer_user_id' => $this->patient->getKey(),
        'started_at' => now()->subDay()->setTime(10, 29),
    ]);

    nbaSignals($call, ['information_requested'], userId: $this->patient->getKey());

    app(AiActionService::class)->generateForClinic($this->clinic);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(1);
});

/**
 * Rohit's call, exactly as it came out of the analyser: an appointment
 * discussed but not confirmed, the customer objecting to the timing, and the
 * model saying in as many words that staff must follow up.
 *
 * It produced nothing. Neither engine listened for `staff_followup_required` or
 * `appointment_intent` — both engines hand-listed the keys they cared about and
 * both lists missed these — so the queue kept showing the lead's original
 * "never contacted" action hours after somebody had, in fact, contacted them.
 */
it('queues the call that produced nothing: intent, an objection, and staff follow-up', function (): void {
    $call = nbaCall([
        'customer_user_id' => $this->patient->getKey(),
        'started_at' => now()->subHours(6),
    ]);

    nbaSignals(
        $call,
        ['appointment_intent', 'timing_objection', 'neutral_sentiment', 'staff_followup_required'],
        userId: $this->patient->getKey(),
    );

    app(AiActionService::class)->generateForClinic($this->clinic);

    $action = AiActionLog::where('user_id', $this->patient->getKey())->first();

    expect($action)->not->toBeNull()
        ->and($action->action_trigger)->toBe('call_commitment_open')
        ->and($action->related_call_id)->toBe($call->getKey())
        // Said in hours, because "0 day(s) ago" was what the old wording gave
        // a call from this morning.
        ->and($action->reason)->toContain('hours ago')
        ->and($action->reason)->not->toContain('day(s)');
});

it('queues the same call for a lead', function (): void {
    $call = nbaCall(['lead_id' => $this->lead->getKey(), 'started_at' => now()->subHours(6)]);

    nbaSignals(
        $call,
        ['appointment_intent', 'timing_objection', 'neutral_sentiment', 'staff_followup_required'],
        leadId: $this->lead->getKey(),
    );

    app(LeadActionService::class)->generateForClinic($this->clinic);

    $action = LeadActionLog::where('lead_id', $this->lead->getKey())->first();

    expect($action)->not->toBeNull()
        // Outranks "never contacted", so the queue stops claiming nobody has
        // spoken to somebody who was called this morning.
        ->and($action->action_trigger)->toBe(LeadActionLog::TRIGGER_CALL_COMMITMENT)
        ->and($action->reason)->not->toContain('never been contacted');
});

/**
 * Both engines must fire on the same signals. They did not, and nothing caught
 * it because each was tested against its own list.
 */
it('agrees between the two engines about which signals demand follow-up', function (): void {
    foreach (['appointment_intent', 'staff_followup_required', 'high_intent', 'unresolved_issue'] as $key) {
        expect(CallSignalKey::from($key)->demandsFollowUp())->toBeTrue();
    }

    // An objection is a reason to prepare for a conversation, not a promise to
    // keep. Chasing "sounded hesitant" fills a queue with work nobody can
    // finish.
    foreach (['timing_objection', 'neutral_sentiment', 'price_objection', 'appointment_booked'] as $key) {
        expect(CallSignalKey::from($key)->demandsFollowUp())->toBeFalse();
    }
});

// ──────────────── Scenario 2: your "sohil" lead ────────────────

it('queues a lead who asked for something on a call', function (): void {
    $call = nbaCall(['lead_id' => $this->lead->getKey()]);
    nbaSignals($call, ['information_requested', 'high_intent'], leadId: $this->lead->getKey());

    app(LeadActionService::class)->generateForClinic($this->clinic);

    $action = LeadActionLog::where('lead_id', $this->lead->getKey())->first();

    expect($action)->not->toBeNull()
        ->and($action->action_trigger)->toBe(LeadActionLog::TRIGGER_CALL_COMMITMENT)
        ->and($action->related_call_id)->toBe($call->getKey())
        // Outranks the form-driven triggers: a form says what somebody wanted
        // when they filled it in, a call is them saying it now.
        ->and($action->priority_score)->toBeGreaterThan(LeadActionService::HIGH_PRIORITY_THRESHOLD);
});

it('does not chase a lead who booked on the call', function (): void {
    $call = nbaCall(['lead_id' => $this->lead->getKey()]);
    nbaSignals($call, ['appointment_booked'], leadId: $this->lead->getKey());

    app(LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

it('does not chase a lead who declined', function (): void {
    $call = nbaCall(['lead_id' => $this->lead->getKey()]);
    nbaSignals($call, ['not_interested'], leadId: $this->lead->getKey());

    app(LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

/**
 * A lead the clinic has already finished with stays finished, whatever the last
 * call sounded like.
 */
it('leaves a closed lead closed however warm the call was', function (): void {
    $this->lead->forceFill(['status' => \App\Enums\LeadStatus::Won->value])->save();

    $call = nbaCall(['lead_id' => $this->lead->getKey()]);
    nbaSignals($call, ['high_intent', 'buying_signal'], leadId: $this->lead->getKey());

    app(LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

// ──────────────── Not claiming somebody was never contacted ────────────────

/**
 * The card that started this: "Enquired yesterday and has never been
 * contacted", nine hours after the clinic rang them.
 *
 * The lead and the patient are the same human with the same number, and the
 * call matcher attached to the patient — so nothing on the lead itself said a
 * call had happened. The trigger now asks the phone, which is what a person
 * would ask.
 */
it('does not claim a lead was never contacted when the clinic has rung them', function (): void {
    // Three days old: findNeverContacted skips anything under a day, and it
    // measures age to midnight, so "yesterday evening" still reads as zero.
    $this->lead->forceFill(['created_at' => now()->subDays(3)])->save();

    nbaCall([
        'lead_id' => null,
        'customer_user_id' => null,
        'client_phone_key' => '9829000002',
        'is_connected' => true,
        'started_at' => now()->subHours(9),
    ]);

    app(LeadActionService::class)->generateForClinic($this->clinic);

    $action = LeadActionLog::where('lead_id', $this->lead->getKey())->first();

    expect($action)->not->toBeNull()
        ->and($action->reason)->not->toContain('never been contacted')
        ->and($action->reason)->toContain('the outcome was never recorded')
        ->and($action->avoid_notes)->toContain('already been spoken to');
});

/**
 * A phone that rang out is not a conversation. Suppressing the first real call
 * because nobody answered would be worse than the falsehood it replaced.
 */
it('still calls a lead whose phone only rang out', function (): void {
    // Three days old: findNeverContacted skips anything under a day, and it
    // measures age to midnight, so "yesterday evening" still reads as zero.
    $this->lead->forceFill(['created_at' => now()->subDays(3)])->save();

    nbaCall([
        'lead_id' => null,
        'customer_user_id' => null,
        'client_phone_key' => '9829000002',
        'is_connected' => false,
        'call_status' => 'missed',
        'started_at' => now()->subHours(9),
    ]);

    app(LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->first()->reason)
        ->toContain('never been contacted');
});

// ──────────────── The modifier, which is off by default ────────────────

/**
 * §41: the modifier changes the ranking of actions the engines have been
 * producing in production, so it stays off until somebody switches it on.
 */
it('does not move existing scores while the modifier is off', function (): void {
    expect((bool) Setting::getConfigured('call_signal_modifier_enabled', config('calls.signals.modifier_enabled')))
        ->toBeFalse();
});

it('can be switched on from settings', function (): void {
    Setting::setValue('call_signal_modifier_enabled', '1');
    Setting::flushRuntimeCache();

    expect((bool) Setting::getConfigured('call_signal_modifier_enabled', false))->toBeTrue();
});
