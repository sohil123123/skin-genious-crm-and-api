<?php

declare(strict_types=1);

use App\Jobs\RegenerateActionQueuesJob;
use App\Models\{Appointment, Clinic, Lead, LeadActionLog, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * Booking somebody rebuilds both queues for their clinic.
 *
 * The queues are otherwise a 07:00 snapshot, and everything that happens during
 * the day leaves them asserting things that stopped being true.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
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
});

/**
 * @param  array<string, mixed>  $attributes
 */
function appointmentFor(User $patient, array $attributes = []): Appointment
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

it('rebuilds both queues when an appointment is created', function (): void {
    Queue::fake();

    appointmentFor($this->patient);

    Queue::assertPushed(
        RegenerateActionQueuesJob::class,
        fn (RegenerateActionQueuesJob $job): bool => $job->clinicId === $this->clinic->getKey()
    );
});

/**
 * A lead who has just been created as a patient and booked is the case this
 * exists for: nothing links the two records, so only a rebuild moves them out
 * of the lead queue.
 */
it('rebuilds when a brand new lead-turned-patient books', function (): void {
    Queue::fake();

    $newPatient = User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Neha', 'last_name' => 'Gaur',
        'mobile' => '7023727700', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    appointmentFor($newPatient);

    Queue::assertPushed(RegenerateActionQueuesJob::class);
});

/**
 * A cancellation puts somebody back into the queue they had dropped out of, so
 * it needs the rebuild as much as a booking does.
 */
it('rebuilds when an appointment is cancelled', function (): void {
    $appointment = appointmentFor($this->patient);

    Queue::fake();

    $appointment->update(['status' => \App\Enums\AppointmentStatus::Cancelled->value]);

    Queue::assertPushed(RegenerateActionQueuesJob::class);
});

it('does not rebuild for a change that cannot affect the queues', function (): void {
    $appointment = appointmentFor($this->patient);

    Queue::fake();

    $appointment->update(['notes' => 'Bring previous reports.']);

    Queue::assertNothingPushed();
});

/**
 * The rebuild runs the patient engine before the lead engine, the same order
 * the scheduler uses: the lead engine drops any lead whose number already has a
 * patient action today, so running it first would let one person be rung from
 * both queues.
 */
it('leaves a booked lead out of the lead queue once the rebuild runs', function (): void {
    $lead = Lead::create([
        'clinic_id' => $this->clinic->getKey(),
        'first_name' => 'Gaurav',
        'full_name' => 'Gaurav Singh Mahuwa',
        'phone' => '+919610003186',
        'status' => \App\Enums\LeadStatus::New->value,
        'source' => \App\Enums\LeadSource::Manual->value,
    ]);

    $lead->forceFill(['created_at' => now()->subDays(3)])->save();

    // Before: the lead queue wants somebody to ring and book them.
    app(\App\Services\Lead\LeadActionService::class)->generateForClinic($this->clinic);

    expect(LeadActionLog::where('lead_id', $lead->getKey())->count())->toBe(1);

    appointmentFor($this->patient);

    (new RegenerateActionQueuesJob($this->clinic->getKey()))->handle(
        app(\App\Services\AiActionService::class),
        app(\App\Services\Lead\LeadActionService::class),
    );

    expect(LeadActionLog::where('lead_id', $lead->getKey())->count())->toBe(0);
});

it('does nothing for a clinic that no longer exists', function (): void {
    (new RegenerateActionQueuesJob(999999))->handle(
        app(\App\Services\AiActionService::class),
        app(\App\Services\Lead\LeadActionService::class),
    );

    expect(LeadActionLog::count())->toBe(0);
});
