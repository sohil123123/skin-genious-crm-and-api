<?php

declare(strict_types=1);

use App\Enums\Call\CallSignalKey;
use App\Enums\Call\CallSignalType;
use App\Jobs\Call\AnalyzeCallJob;
use App\Models\{Call, CallInsightSignal, CallTranscription, Setting};
use App\Services\Call\CallSignalReader;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Turning a conversation into something the action engines can act on.
 *
 * The rule under test throughout: a signal is an instruction to telephone a
 * patient, so anything uncertain, unrecognised or stale must not become one.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();

    Setting::setValue('call_analysis_driver', 'openai');
    Setting::setValue('call_analysis_enabled', '1');
    Setting::setValue('call_transcription_api_key', 'sk-test');
    Setting::flushRuntimeCache();
    app()->forgetInstance(CallAnalysisServiceInterface::class);

    $this->clinic = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->patient = \App\Models\User::create([
        'clinic_id' => $this->clinic->getKey(), 'first_name' => 'Anita', 'last_name' => 'Shah',
        'mobile' => '9876543210', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'sig-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'clinic_id' => $this->clinic->getKey(),
        'started_at' => now()->subHours(2),
        'transcription_status' => 'completed',
    ]);

    $this->transcription = CallTranscription::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'openai',
        'transcript' => 'नमस्ते, मैं HydraFacial के बारे में पूछना चाहती थी. कितना खर्च आएगा? '
            . 'पैकेज पैंतालीस हज़ार का है. थोड़ा महँगा है, WhatsApp पर भेज दीजिए.',
        'status' => 'completed',
        'is_current' => true,
    ]);
});

/**
 * @param  array<int, array<string, mixed>>  $signals
 */
function fakeAnalysisWithSignals(array $signals): void
{
    Http::fake(['*/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'summary' => 'Caller asked about HydraFacial pricing and wants it on WhatsApp.',
            'customer_intent' => 'price enquiry',
            'sentiment' => 'neutral',
            'signals' => $signals,
        ])]]],
        'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 120],
    ])]);
}

// ──────────────── Extraction ────────────────

it('turns a call into signals the engines can query', function (): void {
    fakeAnalysisWithSignals([
        ['key' => 'price_objection', 'confidence' => 0.9, 'value' => null],
        ['key' => 'information_requested', 'confidence' => 0.95, 'value' => 'HydraFacial pricing'],
    ]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $signals = CallInsightSignal::where('call_id', $this->call->getKey())->get();

    expect($signals)->toHaveCount(2)
        ->and($signals->pluck('signal_key')->map->value->all())
        ->toEqualCanonicalizing(['price_objection', 'information_requested']);

    $objection = $signals->firstWhere('signal_key', CallSignalKey::PriceObjection);

    // The family is derived, not taken from the model: a model that mislabels
    // an objection as an opportunity would otherwise poison the rules that
    // match on family.
    expect($objection->signal_type)->toBe(CallSignalType::Objection)
        // Dated to the conversation, not to the analysis run — this is what
        // decays, and a backfill must not make last March look like today.
        ->and($objection->occurred_at->timestamp)->toBe($this->call->started_at->timestamp);
});

it('drops a key that is not in the vocabulary', function (): void {
    fakeAnalysisWithSignals([
        ['key' => 'cost_concern', 'confidence' => 0.9],
        ['key' => 'price_objection', 'confidence' => 0.9],
    ]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    // "cost_concern" is a reasonable phrase and a useless one: no rule matches
    // it, so storing it would put a row in the table that can never fire.
    expect(CallInsightSignal::pluck('signal_key')->map->value->all())->toBe(['price_objection']);
});

/**
 * How many times somebody has been rung is a fact about the CRM's records, not
 * something audible in a transcript. Offering it in the prompt would invite the
 * model to guess.
 */
it('refuses a signal the model cannot honestly have heard', function (): void {
    fakeAnalysisWithSignals([
        ['key' => 'repeated_calls', 'confidence' => 0.99],
        ['key' => 'high_intent', 'confidence' => 0.8],
    ]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect(CallInsightSignal::pluck('signal_key')->map->value->all())->toBe(['high_intent']);
});

it('reports the same signal once however often the model repeats it', function (): void {
    fakeAnalysisWithSignals([
        ['key' => 'price_objection', 'confidence' => 0.7],
        ['key' => 'price_objection', 'confidence' => 0.9],
    ]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect(CallInsightSignal::count())->toBe(1);
});

it('stores nothing when the call established nothing', function (): void {
    fakeAnalysisWithSignals([]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect(CallInsightSignal::count())->toBe(0);
});

it('clamps a confidence outside the possible range', function (): void {
    fakeAnalysisWithSignals([['key' => 'high_intent', 'confidence' => 7.5]]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect(CallInsightSignal::first()->confidence)->toBe(1.0);
});

// ──────────────── Reading ────────────────

/**
 * @param  array<string, mixed>  $attributes
 */
function signal(string $key, array $attributes = []): CallInsightSignal
{
    $call = test()->call;

    return CallInsightSignal::create(array_merge([
        'call_analysis_id' => \App\Models\CallAnalysis::create([
            'call_id' => $call->getKey(),
            'is_current' => false,
        ])->getKey(),
        'call_id' => $call->getKey(),
        'clinic_id' => $call->clinic_id,
        'customer_user_id' => null,
        'lead_id' => null,
        'signal_type' => CallSignalKey::from($key)->type()->value,
        'signal_key' => $key,
        'confidence' => 0.9,
        'occurred_at' => now()->subDay(),
    ], $attributes));
}

it('weighs a fresh signal above an identical stale one', function (): void {
    $reader = app(CallSignalReader::class);

    $fresh = signal('price_objection', ['occurred_at' => now()->subDay()]);
    $stale = signal('price_objection', ['occurred_at' => now()->subDays(40)]);

    expect($fresh->weight(45))->toBeGreaterThan($stale->weight(40 - 1));
});

/**
 * §22: a price objection from six months ago must not carry the weight of one
 * from yesterday. Outside the window it carries none.
 */
it('ignores a signal older than the window', function (): void {
    signal('price_objection', ['occurred_at' => now()->subDays(200), 'customer_user_id' => test()->patient->getKey()]);

    expect(app(CallSignalReader::class)->forPatient($this->patient->getKey()))->toBeEmpty();
});

/**
 * §23: the output is an instruction to telephone somebody, so a model that is
 * guessing should not be issuing one. The row is still stored as evidence.
 */
it('will not act on a signal below the confidence floor', function (): void {
    signal('price_objection', ['confidence' => 0.2, 'customer_user_id' => test()->patient->getKey()]);

    expect(CallInsightSignal::count())->toBe(1)
        ->and(app(CallSignalReader::class)->forPatient($this->patient->getKey()))->toBeEmpty();
});

it('reads a refusal as a reason to stop, not a weak negative', function (): void {
    signal('not_interested', ['customer_user_id' => test()->patient->getKey()]);
    signal('high_intent', ['customer_user_id' => test()->patient->getKey(), 'occurred_at' => now()->subDays(2)]);
    signal('buying_signal', ['customer_user_id' => test()->patient->getKey(), 'occurred_at' => now()->subDays(2)]);

    $reader = app(CallSignalReader::class);
    $signals = $reader->forPatient($this->patient->getKey());

    // The arithmetic alone does not save this. Two enthusiasms at +0.35 each
    // outweigh one refusal at -0.60, so pressure here is mildly positive — and
    // a queue that only summed pressures would ring somebody who just said no.
    expect($reader->pressure($signals))->toBeGreaterThan(0.0);

    // Which is the whole reason refusal is asked as its own question. The
    // engines check this before they look at any score, so it cannot be
    // outvoted by however many older reasons to call.
    expect($reader->hasRefused($signals))->toBeTrue();

    // And the refusal still drags the score down relative to the same signals
    // without it, so ranking degrades gracefully even where suppression is not
    // consulted.
    $withoutRefusal = $signals->reject(
        fn (CallInsightSignal $signal): bool => $signal->signal_key === CallSignalKey::NotInterested
    );

    expect($reader->pressure($signals))->toBeLessThan($reader->pressure($withoutRefusal));
});

/**
 * Every key must have a phrase. The phrases used to live behind a default arm
 * in the reader that fell back to the machine name, and the two keys Rohit's
 * call actually produced had no arm — so the reason read "they appointment
 * intent, staff followup required". An exhaustive match on the enum makes a
 * missing phrase fatal instead of merely embarrassing.
 */
it('has a human phrase for every signal in the vocabulary', function (): void {
    foreach (CallSignalKey::cases() as $key) {
        expect($key->phrase())
            ->toBeString()
            ->not->toContain('_');
    }
});

it('says what an appointment intent and a promised follow-up were, in words', function (): void {
    signal('appointment_intent', ['customer_user_id' => test()->patient->getKey()]);
    signal('staff_followup_required', ['customer_user_id' => test()->patient->getKey()]);
    signal('timing_objection', ['customer_user_id' => test()->patient->getKey()]);

    $reader = app(CallSignalReader::class);

    expect($reader->explain($reader->forPatient($this->patient->getKey())))
        ->toContain('wanted to arrange an appointment')
        ->toContain('were promised a follow-up')
        ->not->toContain('staff followup required');
});

/**
 * "They sounded neutral" is true and useless. It would take one of the three
 * slots in the reason without telling anybody anything they can act on.
 */
it('leaves sentiment that argues neither way out of the reason', function (): void {
    signal('neutral_sentiment', ['customer_user_id' => test()->patient->getKey()]);
    signal('price_objection', ['customer_user_id' => test()->patient->getKey()]);

    $reader = app(CallSignalReader::class);
    $explanation = $reader->explain($reader->forPatient($this->patient->getKey()));

    expect($explanation)->toContain('raised the cost')
        ->not->toContain('neutral');
});

it('explains itself in words a staff member can open a call with', function (): void {
    signal('price_objection', ['customer_user_id' => test()->patient->getKey()]);
    signal('information_requested', ['customer_user_id' => test()->patient->getKey()]);

    $reader = app(CallSignalReader::class);
    $explanation = $reader->explain($reader->forPatient($this->patient->getKey()));

    expect($explanation)->toContain('raised the cost')
        ->and($explanation)->toContain('asked for more information')
        // Not the machine names — somebody is about to speak to this person.
        ->and($explanation)->not->toContain('price_objection');
});
