<?php

declare(strict_types=1);

use App\Models\{AiActionLog, Appointment, Call, CallAnalysis, CallInsightSignal, Clinic, Lead, LeadActionLog, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The queues are built once each morning and go stale the moment anything
 * happens.
 *
 * A patient who books at noon leaves behind a card telling staff to ring and
 * book them, and it stays wrong until the next day's run. This is the fix for
 * that one case — the person who booked, and only them, because regenerating
 * the clinic would reorder every card while staff are working through it.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The immediate, targeted pass is what these tests are about. Booking also
    // queues a full rebuild of the clinic, which under the sync driver would
    // run inline and replace the hand-built cards below with whatever the
    // engines produce — testing the rebuild instead of the reconcile. That path
    // has its own file.
    Queue::fake();

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->patient = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Gaurav', 'last_name' => 'Singh',
        'mobile' => '9610003186', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->patient->assignRole(Role::firstOrCreate([
        'name' => config('project.roles.client'), 'guard_name' => 'web',
    ]));

    $this->lead = Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Gaurav',
        'full_name' => 'Gaurav Singh Mahuwa',
        'phone' => '+919610003186',
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function bookFor(User $patient, array $attributes = []): Appointment
{
    return Appointment::create(array_merge([
        'type' => \App\Enums\AppointmentType::Consult->value,
        'clinic_id' => test()->clinic->getKey(),
        'user_id' => $patient->getKey(),
        'therapist_id' => $patient->getKey(),
        'start_datetime' => now()->addDays(5),
        'end_datetime' => now()->addDays(5)->addHour(),
        'duration_minutes' => 90,
        'status' => \App\Enums\AppointmentStatus::Confirmed->value,
        'created_by' => $patient->getKey(),
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function leadCard(array $attributes = []): LeadActionLog
{
    return LeadActionLog::create(array_merge([
        'clinic_id' => test()->clinic->getKey(),
        'lead_id' => test()->lead->getKey(),
        'action_category' => LeadActionLog::CATEGORY_CONVERSION,
        'action_trigger' => LeadActionLog::TRIGGER_NEVER_CONTACTED,
        'priority_score' => 26,
        'recommended_channel' => 'call',
        'reason' => 'Enquired yesterday and has never been contacted.',
        'generated_date' => now()->toDateString(),
        'is_active' => true,
    ], $attributes));
}

it('drops a lead card that only existed to get them booked', function (): void {
    leadCard();

    bookFor($this->patient);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

it('drops a patient card that only existed to get them booked', function (): void {
    AiActionLog::create([
        'clinic_id' => $this->clinic->getKey(),
        'user_id' => $this->patient->getKey(),
        'action_category' => AiActionLog::CATEGORY_RETENTION,
        'action_trigger' => 'package_overdue',
        'priority_score' => 80,
        'recommended_channel' => 'call',
        'reason' => 'Package sessions unused for three weeks.',
        'generated_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    bookFor($this->patient);

    expect(AiActionLog::where('user_id', $this->patient->getKey())->count())->toBe(0);
});

/**
 * A promise made on the phone is not discharged by an appointment. The card
 * stays and is told about the booking, so it stops telling staff to sell a
 * consultation the lead already has.
 */
it('tells the call commitment card about the booking rather than dropping it', function (): void {
    $card = leadCard([
        'action_trigger' => LeadActionLog::TRIGGER_CALL_COMMITMENT,
        'reason' => 'Asked for something on a call 3 hours ago and has not had it.',
        'goal' => 'Answer what they asked for and move to a consultation',
        'avoid_notes' => 'They already told us what they want. Do not restart the pitch.',
    ]);

    bookFor($this->patient, ['start_datetime' => now()->addDays(5)->setTime(13, 45)]);

    $card->refresh();

    expect($card->exists)->toBeTrue()
        ->and($card->reason)->toContain('already booked in for')
        ->and($card->goal)->toBe('Send what was promised before they come in')
        ->and($card->avoid_notes)->toContain('already booked');
});

it('does not say the same booking twice when the appointment is edited', function (): void {
    $card = leadCard([
        'action_trigger' => LeadActionLog::TRIGGER_CALL_COMMITMENT,
        'reason' => 'Asked for something on a call 3 hours ago and has not had it.',
    ]);

    $appointment = bookFor($this->patient);
    $appointment->update(['status' => \App\Enums\AppointmentStatus::Pending->value]);

    expect(substr_count($card->refresh()->reason, 'already booked in for'))->toBe(1);
});

/**
 * A cancelled appointment leaves the person needing exactly the call the queue
 * is suggesting.
 */
it('leaves the queue alone for a cancelled appointment', function (): void {
    leadCard();

    bookFor($this->patient, ['status' => \App\Enums\AppointmentStatus::Cancelled->value]);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(1);
});

it('leaves the queue alone for an appointment in the past', function (): void {
    leadCard();

    bookFor($this->patient, [
        'start_datetime' => now()->subDays(2),
        'end_datetime' => now()->subDays(2)->addHour(),
    ]);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(1);
});

/**
 * A card somebody has already worked is history, not a suggestion. Deleting it
 * would erase what a staff member recorded.
 */
it('never touches a card a staff member has already acted on', function (): void {
    leadCard(['staff_outcome' => 'called_no_answer']);

    bookFor($this->patient);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(1);
});

/**
 * Tidying a work queue must never be the reason a booking cannot be saved.
 */
it('saves the appointment even when the queue cannot be reconciled', function (): void {
    $orphan = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'No', 'last_name' => 'Phone',
        // Not a number anything can be matched on, so the lead lookup finds
        // nothing to reconcile.
        'mobile' => '-', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    expect(bookFor($orphan)->exists)->toBeTrue()
        ->and(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

// ──────────── An appointment that has already started ────────────

/**
 * Pallavi Bhatnagar, in production: booked at 12:30 and 12:56, and at 13:01 —
 * while she was in the chair — a regeneration put her back on the lead queue at
 * 97/100 as somebody who "wants to visit now" and should be rung today.
 *
 * The window was `start_datetime >= now()`, so an appointment stopped counting
 * the instant it began. The queue is read all day; it must not start chasing
 * people the moment their appointment starts.
 */
it('does not chase a lead whose appointment is happening right now', function (): void {
    $this->lead->forceFill(['created_at' => now()->subDays(3)])->save();

    // $this->patient already shares the lead's number, which is the whole
    // point: one person, two records, nothing linking them.
    bookFor($this->patient, [
        'start_datetime' => now()->subMinutes(30),
        'end_datetime' => now()->addMinutes(30),
    ]);

    app(\App\Services\Lead\LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

/**
 * And somebody seen earlier this morning is in the same position: they came in,
 * so the card telling staff to get them in is spent.
 */
it('does not chase a lead who was seen earlier today', function (): void {
    $this->lead->forceFill(['created_at' => now()->subDays(3)])->save();

    // $this->patient already shares the lead's number, which is the whole
    // point: one person, two records, nothing linking them.
    bookFor($this->patient, [
        'start_datetime' => now()->startOfDay()->addHours(9),
        'end_datetime' => now()->startOfDay()->addHours(10),
    ]);

    app(\App\Services\Lead\LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(0);
});

/**
 * Yesterday is outside the window on purpose. Chasing somebody after a visit is
 * what the retention triggers are for, and a lead who came in once and went
 * quiet still needs following up.
 */
it('still chases a lead whose only visit was yesterday', function (): void {
    $this->lead->forceFill(['created_at' => now()->subDays(3)])->save();

    // $this->patient already shares the lead's number, which is the whole
    // point: one person, two records, nothing linking them.
    bookFor($this->patient, [
        'start_datetime' => now()->startOfDay()->subDay()->addHours(11),
        'end_datetime' => now()->startOfDay()->subDay()->addHours(12),
    ]);

    app(\App\Services\Lead\LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $this->lead->getKey())->count())->toBe(1);
});

/**
 * The patient engine asks the same question and must give the same answer, or
 * a person in the chair drops out of one queue and into the other.
 */
it('agrees with the patient engine about an appointment in progress', function (): void {
    $patient = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Pallavi', 'last_name' => 'B',
        'mobile' => '9829000077', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $patient->assignRole(\App\Models\Role::firstOrCreate([
        'name' => config('project.roles.client'), 'guard_name' => 'web',
    ]));

    bookFor($patient, [
        'start_datetime' => now()->subMinutes(30),
        'end_datetime' => now()->addMinutes(30),
    ]);

    $service = app(\App\Services\AiActionService::class);
    $method = (new ReflectionClass($service))->getMethod('hasFutureAppointment');
    $method->setAccessible(true);

    expect($method->invoke($service, $patient->getKey()))->toBeTrue();
});
