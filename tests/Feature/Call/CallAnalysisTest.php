<?php

declare(strict_types=1);

use App\Enums\Call\CallAnalysisStatus;
use App\Enums\Call\CallSentiment;
use App\Jobs\Call\AnalyzeCallJob;
use App\Models\{Call, CallAnalysis, CallTranscription, Setting};
use App\Services\Call\Analysis\{NullCallAnalysisService, OpenAiCallAnalysisService};
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reading a transcript and extracting what the clinic acts on.
 *
 * The rule under test throughout: a model asked for twenty judgements about a
 * short call will invent them, and invented purchase intent is worse than none
 * — it looks like data, gets filtered on, and sends follow-up to the wrong
 * people. So every field is coerced, clamped, and allowed to be null.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushRuntimeCache();

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'an-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'started_at' => now(),
        'talk_duration_seconds' => 180,
        'transcription_status' => 'completed',
        'crm_outcome' => 'Appointment booked',
        'crm_note' => 'Booked for Tuesday.',
    ]);

    $this->transcription = CallTranscription::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'openai',
        'transcript' => 'नमस्ते, मैं HydraFacial के बारे में पूछना चाहती थी. कितना खर्च आएगा? '
            . 'पैकेज पैंतालीस हज़ार का है. थोड़ा महँगा है, मैं सोचकर बताती हूँ. ठीक है धन्यवाद.',
        'status' => 'completed',
        'is_current' => true,
    ]);
});

function enableAnalysis(): void
{
    Setting::setValue('call_analysis_driver', 'openai');
    Setting::setValue('call_analysis_enabled', '1');
    Setting::setValue('call_transcription_api_key', 'sk-test');
    Setting::flushRuntimeCache();
    app()->forgetInstance(CallAnalysisServiceInterface::class);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeAnalysis(array $overrides = []): void
{
    Http::fake(['*/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => json_encode(array_merge([
            'summary' => 'Caller asked about HydraFacial pricing and found it expensive.',
            'customer_intent' => 'price enquiry',
            'call_reason' => 'treatment pricing',
            'outcome' => 'will think about it',
            'sentiment' => 'mixed',
            'sentiment_score' => 0.1,
            'urgency' => 'medium',
            'lead_temperature' => 'warm',
            'purchase_intent' => 55,
            'objection' => 'Price felt high',
            'treatment_interest' => 'HydraFacial',
            'price_discussed' => true,
            'appointment_discussed' => false,
            'appointment_booked' => false,
            'follow_up_required' => true,
            'follow_up_reason' => 'Wanted to think about the price',
            'next_best_action' => 'Call back in three days with a package option.',
            'next_best_action_priority' => 70,
            'confidence' => 0.8,
        ], $overrides))]]],
        'usage' => ['prompt_tokens' => 420, 'completion_tokens' => 130],
    ])]);
}

// ──────────────── The driver ────────────────

it('binds the null analyser until a provider is chosen', function (): void {
    expect(app(CallAnalysisServiceInterface::class))->toBeInstanceOf(NullCallAnalysisService::class);
});

it('binds the real analyser from the settings screen', function (): void {
    enableAnalysis();

    expect(app(CallAnalysisServiceInterface::class))->toBeInstanceOf(OpenAiCallAnalysisService::class)
        ->and(app(CallAnalysisServiceInterface::class)->isEnabled())->toBeTrue();
});

// ──────────────── A full pass ────────────────

it('analyses a transcript and stores the result', function (): void {
    enableAnalysis();
    fakeAnalysis();

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $analysis = CallAnalysis::where('call_id', $this->call->getKey())->first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->sentiment)->toBe(CallSentiment::Mixed)
        ->and($analysis->purchase_intent)->toBe(55)
        ->and($analysis->treatment_interest)->toBe('HydraFacial')
        ->and($analysis->price_discussed)->toBeTrue()
        ->and($analysis->appointment_booked)->toBeFalse()
        ->and($analysis->is_current)->toBeTrue()
        ->and($analysis->input_tokens)->toBe(420)
        // The whole model response, so a prompt change can be replayed against
        // what an earlier version actually returned.
        ->and($analysis->structured_result)->toHaveKey('next_best_action');

    expect($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::Completed);
});

/**
 * The separation the whole schema is built around: a model must never overwrite
 * what a staff member typed.
 */
it('never overwrites what a person recorded on the call', function (): void {
    enableAnalysis();
    fakeAnalysis();

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $call = $this->call->fresh();

    expect($call->crm_outcome)->toBe('Appointment booked')
        ->and($call->crm_note)->toBe('Booked for Tuesday.')
        // The analysis said a follow-up was needed; the call's own flag stays
        // the clinic's to set.
        ->and($call->follow_up_required)->toBeFalse()
        // Only the summary is denormalised onto the call, for the list view.
        ->and($call->ai_summary)->toContain('HydraFacial');
});

// ──────────────── Untrusted output ────────────────

/**
 * The model is instructed to return a shape, not guaranteed to. A string where
 * an integer belongs would otherwise reach the database as a silent 0.
 */
it('clamps and coerces whatever the model returns', function (): void {
    enableAnalysis();
    fakeAnalysis([
        'purchase_intent' => 999,
        'sentiment_score' => 7.5,
        'confidence' => 'very sure',
        'sentiment' => 'ecstatic',
        'urgency' => 'immediately',
    ]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $analysis = CallAnalysis::where('call_id', $this->call->getKey())->first();

    expect($analysis->purchase_intent)->toBe(100)
        ->and((float) $analysis->sentiment_score)->toBe(1.0)
        // Not a number, so not stored as one.
        ->and($analysis->ai_confidence)->toBeNull()
        // Values outside the allowed set fall back rather than being written.
        ->and($analysis->sentiment)->toBe(CallSentiment::Unknown)
        ->and($analysis->urgency)->toBeNull();
});

/**
 * A transcript of hold music is not a sales enquiry. Storing an empty analysis
 * would put a blank AI panel in front of staff and teach them the feature does
 * not work.
 */
it('stores nothing when the model had nothing to say', function (): void {
    enableAnalysis();
    fakeAnalysis(['summary' => null, 'customer_intent' => null]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect(CallAnalysis::count())->toBe(0)
        ->and($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::NotAvailable);
});

it('does not analyse a call with no transcript', function (): void {
    enableAnalysis();
    Http::fake();

    $bare = Call::create([
        'uuid' => (string) Str::uuid(),
        'provider' => 'exotel',
        'provider_call_id' => 'an-2',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'started_at' => now(),
    ]);

    (new AnalyzeCallJob($bare->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect($bare->fresh()->analysis_status)->toBe(CallAnalysisStatus::NotAvailable);
    Http::assertNothingSent();
});

// ──────────────── Reaching existing calls ────────────────

it('refuses to queue anything while analysis is off', function (): void {
    $this->artisan('calls:analyse')
        ->expectsOutputToContain('Call analysis is not enabled')
        ->assertFailed();
});

it('skips calls settled while analysis was off, unless asked', function (): void {
    enableAnalysis();
    $this->call->forceFill(['analysis_status' => 'not_available'])->save();

    $this->artisan('calls:analyse')->expectsOutputToContain('Nothing to analyse')->assertSuccessful();
    $this->artisan('calls:analyse', ['--all' => true])->expectsOutputToContain('Queued 1')->assertSuccessful();
});

// ──────────────── Reaching it from the UI ────────────────

/**
 * The section used to hide itself when there was no analysis, which left no
 * answer to "where would the result show up?" — the exact question someone asks
 * after switching the feature on and seeing nothing change.
 */
it('shows the AI section with a next step even before anything is analysed', function (): void {
    $admin = analysisAdmin();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)->toContain('AI analysis')
        ->toContain('Not analysed yet');
});

it('explains why there is nothing to analyse when the call has no transcript', function (): void {
    $admin = analysisAdmin();
    $this->call->forceFill(['transcription_status' => 'not_available'])->save();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    // The copy moved into an EmptyState heading + description; what matters is
    // that it still says why, and offers no button it cannot honour.
    expect($html)
        ->toContain('Nothing to analyse yet')
        ->toContain('transcribed first')
        ->not->toContain('Analyse with AI');
});

/**
 * Telling someone to go and find a menu is worse than the button itself - the
 * empty state is exactly where a person is standing when they want this.
 */
it('puts an Analyse button inside the empty AI section', function (): void {
    $admin = analysisAdmin();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)
        ->toContain('Not analysed yet')
        ->toContain('analyseFromEmptyState')
        ->toContain('Analyse with AI');
});

it('offers an Analyse action on a call that has a transcript', function (): void {
    $admin = analysisAdmin();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)->toContain('Analyse with AI');
});

// ──────────────── A guess is not a fact ────────────────

/**
 * The bug that prompted these: appointment_booked was a NOT NULL column with a
 * default of false, so "the transcript does not say" was stored as a definite
 * no - and when the model did guess yes, the panel drew the same green tick it
 * uses for facts the clinic recorded.
 */
it('keeps "the transcript does not say" out of the false column', function (): void {
    enableAnalysis();
    fakeAnalysis([
        'appointment_booked' => null,
        'appointment_discussed' => 'maybe',
        'price_discussed' => true,
    ]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $analysis = CallAnalysis::where('call_id', $this->call->getKey())->first();

    expect($analysis->appointment_booked)->toBeNull()
        // Not a boolean the model could have meant, so not stored as one.
        ->and($analysis->appointment_discussed)->toBeNull()
        // A real answer still lands as a real answer.
        ->and($analysis->price_discussed)->toBeTrue();
});

it('does not present an AI booking guess as a recorded booking', function (): void {
    $admin = analysisAdmin();
    enableAnalysis();
    fakeAnalysis(['appointment_booked' => true]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)
        // Attributed in the label, so it cannot be read as the diary.
        ->toContain('Appointment (per AI)')
        ->toContain('Booked on the call')
        ->toContain('not a booking in the diary')
        // And the whole section is marked as inference, not just this field.
        ->toContain('not a clinic record');
});

it('distinguishes discussed-but-not-booked from never-mentioned', function (): void {
    $admin = analysisAdmin();
    enableAnalysis();
    fakeAnalysis(['appointment_booked' => false, 'appointment_discussed' => true]);

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)->toContain('Discussed, not booked');
});

// ──────────────── The transcript section, same treatment ────────────────

it('puts a Transcribe button inside the empty Transcript section', function (): void {
    $admin = analysisAdmin();

    // No transcript, but audio on file - so transcribing is a thing that can
    // actually be done from here.
    $this->transcription->delete();
    $this->call->forceFill(['transcription_status' => 'pending'])->save();

    \App\Models\CallRecording::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'exotel',
        'storage_status' => 'stored',
        'storage_disk' => 'call_recordings',
        'storage_path' => 'calls/an-1.mp3',
    ]);

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call->fresh()])
    )->getContent();

    expect($html)
        ->toContain('Not transcribed yet')
        ->toContain('transcribeFromEmptyState')
        ->toContain('Transcribe with AI');
});

/**
 * A call the provider never recorded cannot be transcribed by pressing
 * anything, so the section must say so rather than offer a button that would
 * queue nothing.
 */
it('offers no Transcribe button when there is no audio at all', function (): void {
    $admin = analysisAdmin();

    $this->transcription->delete();
    $this->call->forceFill(['transcription_status' => 'not_available'])->save();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call->fresh()])
    )->getContent();

    expect($html)
        ->toContain('No recording for this call')
        ->not->toContain('transcribeFromEmptyState');
});

function analysisAdmin(): \App\Models\User
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    config(['app.env' => 'local']);

    foreach (config('project.roles') as $roleName) {
        \App\Models\Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    $role = \App\Models\Role::firstOrCreate(['name' => config('project.roles.super_admin'), 'guard_name' => 'web']);

    foreach (['ViewAny:Call', 'View:Call', 'Update:Call', 'PlayRecording:Call', 'ViewTranscript:Call'] as $permission) {
        $role->givePermissionTo(\App\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    $clinic = \App\Models\Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $user = \App\Models\User::create([
        'clinic_id' => $clinic->getKey(),
        'first_name' => 'Super', 'last_name' => 'Admin',
        'mobile' => '9111111111', 'password' => bcrypt('x'), 'is_active' => true,
    ]);

    $user->assignRole($role);

    test()->call->forceFill(['clinic_id' => $clinic->getKey()])->save();

    return $user;
}

// ──────────────── A length rule, not an alphabet rule ────────────────

use App\Services\Call\Transcription\TranscriptWordCounter;

it('counts words the same in every script', function (): void {
    $hindi = 'हां जी, क्या हाल है? टेस्टिंग कर रहे? हो गया मेरा टेस्ट? रख दो फोन.';
    $english = 'Yes hello, how are you? Just testing. Is my test done? Please hang up now.';

    expect(TranscriptWordCounter::count($hindi))->toBe(15)
        ->and(TranscriptWordCounter::count($english))->toBe(15)
        ->and(TranscriptWordCounter::count(''))->toBe(0)
        ->and(TranscriptWordCounter::count(null))->toBe(0)
        // Collapsed whitespace must not inflate the count, or the floor becomes
        // a formatting rule.
        ->and(TranscriptWordCounter::count("  two\n\n  words  "))->toBe(2);
});

/**
 * The bug this pins: the floor was str_word_count() plus a space count.
 * str_word_count() returns 0 for Devanagari, so the spaces carried the whole
 * tally and a Hindi call needed about twice the words of an English one to be
 * analysed at all. Most of this clinic's calls are in Hindi, so the feature was
 * effectively switched off for them - silently, with the call left sitting at
 * "not available" and nothing logged.
 */
it('analyses a short Hindi call that clears the floor', function (): void {
    enableAnalysis();
    fakeAnalysis();

    $this->transcription->forceFill([
        'transcript' => 'हां जी, क्या हाल है? टेस्टिंग कर रहे? हो गया मेरा टेस्ट? रख दो फोन.',
    ])->save();

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::Completed)
        ->and(CallAnalysis::where('call_id', $this->call->getKey())->exists())->toBeTrue();
});

/**
 * The floor still has to hold. "Hello? Wrong number." is not a sales enquiry,
 * and paying a model to say so is waste.
 */
it('still refuses a transcript that is genuinely too short', function (): void {
    enableAnalysis();
    Http::fake();

    $this->transcription->forceFill(['transcript' => 'हैलो? रॉन्ग नंबर.'])->save();

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::NotAvailable);
    Http::assertNothingSent();
});

// ──────────────── The floor, and saying so ────────────────

it('takes the minimum word count from settings', function (): void {
    enableAnalysis();

    expect(app(CallAnalysisServiceInterface::class)->minimumWords())->toBe(15);

    Setting::setValue('call_analysis_min_words', '40');
    Setting::flushRuntimeCache();

    expect(app(CallAnalysisServiceInterface::class)->minimumWords())->toBe(40);
});

/**
 * A floor of zero would send every fragment of hold music to a paid endpoint,
 * so a nonsensical setting falls back rather than being obeyed.
 */
it('refuses a minimum below one', function (): void {
    enableAnalysis();

    Setting::setValue('call_analysis_min_words', '0');
    Setting::flushRuntimeCache();

    expect(app(CallAnalysisServiceInterface::class)->minimumWords())->toBe(1);
});

it('honours a raised minimum by skipping a call it used to analyse', function (): void {
    enableAnalysis();
    Http::fake();

    // Comfortably over the default 15, well under 40.
    Setting::setValue('call_analysis_min_words', '40');
    Setting::flushRuntimeCache();

    (new AnalyzeCallJob($this->call->getKey()))->handle(app(CallAnalysisServiceInterface::class));

    expect($this->call->fresh()->analysis_status)->toBe(CallAnalysisStatus::NotAvailable);
    Http::assertNothingSent();
});

/**
 * The call used to sit at "Not analysed yet" for ever, with the button doing
 * nothing visible. A transcript below the floor will never be analysed however
 * many times it is pressed, so the section has to say so rather than imply
 * patience is all that is needed.
 */
it('warns on the call page when a transcript is below the floor', function (): void {
    $admin = analysisAdmin();
    enableAnalysis();

    Setting::setValue('call_analysis_min_words', '40');
    Setting::flushRuntimeCache();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)->toContain('too short to analyse')
        // The numbers on both sides, so the reader can judge the setting.
        ->toContain('and the minimum is 40')
        ->not->toContain('Not analysed yet');
});

it('leaves the ordinary empty state alone when the transcript is long enough', function (): void {
    $admin = analysisAdmin();
    enableAnalysis();

    $html = $this->actingAs($admin)->get(
        \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $this->call])
    )->getContent();

    expect($html)->toContain('Not analysed yet')
        ->not->toContain('too short to analyse');
});
