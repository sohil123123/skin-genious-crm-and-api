<?php

declare(strict_types=1);

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallProvider;
use App\Enums\Call\CallSource;
use App\Enums\Call\CallStatus;
use App\Models\Call;
use App\Models\Setting;
use App\Services\Call\CallIngestionService;
use App\Services\Call\Providers\Callyzer\CallyzerCallMapper;
use App\Services\Call\Providers\Exotel\ExotelCallMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * The rule the whole system rests on: one real call is one row.
 *
 * The hard part is not insertion but update. A call arrives in pieces —
 * ringing, answered, completed, then a recording some minutes later — and those
 * pieces can arrive out of order, because a provider retrying a slow "ringing"
 * delivery will happily deliver it after "completed" already succeeded. These
 * tests pin the merge rules that make that safe.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();
    Queue::fake();

    $this->ingestion = app(CallIngestionService::class);
    $this->exotel = app(ExotelCallMapper::class);
    $this->callyzer = app(CallyzerCallMapper::class);
});

function exotelEvent(array $overrides = []): array
{
    return array_merge([
        'CallSid' => 'sid-100',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => '2026-08-29 10:15:00',
        'DialCallDuration' => '272',
        'ConversationDuration' => '245',
    ], $overrides);
}

// ──────────────── One call, one row ────────────────

it('creates one call from a provider event', function (): void {
    $call = $this->ingestion->ingest($this->exotel->map(exotelEvent()));

    expect(Call::count())->toBe(1)
        ->and($call->provider)->toBe(CallProvider::Exotel)
        ->and($call->provider_call_id)->toBe('sid-100')
        ->and($call->direction)->toBe(CallDirection::Incoming)
        ->and($call->call_status)->toBe(CallStatus::Completed)
        ->and($call->is_connected)->toBeTrue()
        ->and($call->uuid)->not->toBeEmpty();
});

it('updates the same row when the provider sends the call again', function (): void {
    $this->ingestion->ingest($this->exotel->map(exotelEvent()));
    $this->ingestion->ingest($this->exotel->map(exotelEvent()));

    expect(Call::count())->toBe(1)
        ->and(Call::first()->event_count)->toBe(2);
});

/**
 * The overlap between the live webhook and the overnight sync is the normal
 * case, not an edge case, and it must update one row.
 */
it('does not duplicate a call that arrives by webhook and again by sync', function (): void {
    $this->ingestion->ingest($this->callyzer->map(callyzerRecord(), CallSource::Webhook));
    $this->ingestion->ingest($this->callyzer->map(callyzerRecord(), CallSource::ApiSync));

    expect(Call::count())->toBe(1);

    $call = Call::first();

    expect($call->source)->toBe(CallSource::Webhook)
        // Where it came from first and what last touched it are different
        // questions, and both are worth being able to answer.
        ->and($call->last_source)->toBe(CallSource::ApiSync);
});

/**
 * The same id from two different providers is two different calls. Merging them
 * would splice two conversations into one record.
 */
it('keeps calls from different providers apart even with the same id', function (): void {
    $this->ingestion->ingest($this->exotel->map(exotelEvent(['CallSid' => 'shared-id'])));
    $this->ingestion->ingest($this->callyzer->map(callyzerRecord(['id' => 'shared-id'])));

    expect(Call::count())->toBe(2);
});

// ──────────────── Staged updates ────────────────

it('advances a ringing call through to completed', function (): void {
    $this->ingestion->ingest($this->exotel->map(exotelEvent([
        'CallStatus' => 'ringing',
        'DialCallDuration' => null,
        'ConversationDuration' => null,
    ])));

    expect(Call::first()->call_status)->toBe(CallStatus::Ringing);

    $this->ingestion->ingest($this->exotel->map(exotelEvent()));

    $call = Call::first();

    expect($call->call_status)->toBe(CallStatus::Completed)
        ->and($call->duration_seconds)->toBe(272)
        ->and($call->talk_duration_seconds)->toBe(245);
});

/**
 * The failure this guards against: a provider retries a slow "ringing"
 * delivery, it lands after "completed" succeeded, and a naive upsert rolls a
 * finished call back to ringing and wipes its duration.
 */
it('does not let a stale earlier event regress a finished call', function (): void {
    $this->ingestion->ingest($this->exotel->map(exotelEvent()));

    $this->ingestion->ingest($this->exotel->map(exotelEvent([
        'CallStatus' => 'ringing',
        'DialCallDuration' => null,
        'ConversationDuration' => null,
    ])));

    $call = Call::first();

    expect($call->call_status)->toBe(CallStatus::Completed)
        ->and($call->duration_seconds)->toBe(272)
        ->and($call->is_connected)->toBeTrue();
});

/**
 * Providers report 0 on interim events and the real figure at the end, so
 * accepting a smaller number would discard the only value that was ever true.
 */
it('never shrinks a duration', function (): void {
    $this->ingestion->ingest($this->exotel->map(exotelEvent()));
    $this->ingestion->ingest($this->exotel->map(exotelEvent(['DialCallDuration' => '5'])));

    expect(Call::first()->duration_seconds)->toBe(272);
});

it('accumulates provider extras across events rather than replacing them', function (): void {
    $this->ingestion->ingest($this->exotel->map(exotelEvent(['CustomField' => 'campaign-a'])));
    $this->ingestion->ingest($this->exotel->map(exotelEvent(['Legs' => '[]', 'digits' => '2'])));

    $data = Call::first()->provider_data;

    expect($data)->toHaveKey('CustomField')
        ->and($data)->toHaveKey('digits');
});

// ──────────────── The four layers stay separate ────────────────

/**
 * The scenario from the brief: Callyzer says "Interested", a staff member later
 * records "Appointment Booked", and a re-sync must not undo them.
 */
it('never lets a re-sync overwrite what a staff member recorded', function (): void {
    $this->ingestion->ingest($this->callyzer->map(callyzerRecord()));

    $call = Call::first();
    $call->forceFill([
        'crm_outcome' => 'Appointment Booked',
        'crm_note' => 'Booked for Tuesday.',
    ])->save();

    // The provider sends the call again with its own, older, view of things.
    $this->ingestion->ingest($this->callyzer->map(callyzerRecord([
        'crm_status' => 'Interested',
        'note' => 'Discussed the facial package',
    ]), CallSource::ApiSync));

    $call->refresh();

    expect($call->crm_outcome)->toBe('Appointment Booked')
        ->and($call->crm_note)->toBe('Booked for Tuesday.')
        // And the provider's own values survive alongside, untouched.
        ->and($call->provider_crm_status)->toBe('Interested')
        ->and($call->provider_note)->toBe('Discussed the facial package');
});

// ──────────────── Derived timing ────────────────

it('derives a duration from the provider timestamps when none was given', function (): void {
    $call = $this->ingestion->ingest($this->exotel->map([
        'CallSid' => 'sid-timing',
        'From' => '+919876543210',
        'To' => '08047122334',
        'Direction' => 'incoming',
        'CallStatus' => 'completed',
        'StartTime' => '2026-08-29 10:00:00',
        'EndTime' => '2026-08-29 10:03:00',
    ]));

    expect($call->duration_seconds)->toBe(180);
});

/**
 * Anything that carried talk time was answered, whatever the status word said.
 */
it('treats a call with talk time as connected', function (): void {
    $call = $this->ingestion->ingest($this->callyzer->map(callyzerRecord([
        'call_type' => 'Unknown',
        'duration' => '90',
    ])));

    expect($call->talk_duration_seconds)->toBe(90)
        ->and($call->is_connected)->toBeTrue();
});

// ──────────────── Refusals ────────────────

it('refuses an event with no provider call id', function (): void {
    expect($this->callyzer->map(callyzerRecord(['id' => ''])))->toBeNull();
});

/**
 * Helper: a representative Callyzer call log.
 */
function callyzerRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 'cz-100',
        'emp_name' => 'Priya',
        'emp_code' => 'EMP-01',
        'emp_country_code' => '+91',
        'emp_number' => '9000000001',
        'client_name' => 'Anita Shah',
        'client_country_code' => '+91',
        'client_number' => '9876543210',
        'duration' => '272',
        'call_type' => 'Outgoing',
        'call_date' => '2026-08-29',
        'call_time' => '10:15:00',
        'note' => 'Discussed the facial package',
        'crm_status' => 'Interested',
        'call_method' => 'sim',
        'call_mode' => 'manual',
        'modified_at' => '2026-08-29 10:20:00',
    ], $overrides);
}
