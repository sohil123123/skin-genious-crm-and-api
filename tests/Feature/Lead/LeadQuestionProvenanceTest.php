<?php

declare(strict_types=1);

use App\Enums\LeadFieldType;
use App\Models\Clinic;
use App\Models\LeadCustomField;
use App\Services\Lead\LeadFieldResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A question must always be able to say how it was really asked.
 *
 * label doubles as the wording shown across the CRM and is openly editable, so
 * on its own it cannot be trusted to describe what a given customer saw. Once
 * someone rewords a question, the lead detail screen would otherwise attribute
 * wording to a person who was never shown it.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::create([
        'name' => 'Test Clinic',
        'address_line1' => '1 Test Street',
        'city' => 'Mumbai',
        'pincode' => '400001',
    ]);

    $this->resolver = app(LeadFieldResolverService::class);
});

it('records the original wording when a question is first seen', function (): void {
    $field = $this->resolver->resolve(
        label: 'when_would_you_like_to_visit_for_your_salmon_facial?',
        clinicId: $this->clinic->getKey(),
    );

    expect($field->source_label)->toBe('when_would_you_like_to_visit_for_your_salmon_facial?')
        ->and($field->labelWasRewritten())->toBeFalse();
});

it('keeps the original after the display wording is rewritten', function (): void {
    // Exactly what happened to the salmon facial question: someone tidied the
    // label on the Lead Questions screen and the real question vanished.
    $field = $this->resolver->resolve(
        label: 'when_would_you_like_to_visit_for_your_salmon_facial?',
        clinicId: $this->clinic->getKey(),
    );

    $field->update(['label' => 'Which session are you interested in?']);
    $field->refresh();

    expect($field->label)->toBe('Which session are you interested in?')
        ->and($field->source_label)->toBe('when_would_you_like_to_visit_for_your_salmon_facial?')
        ->and($field->source_question)->toBe('when_would_you_like_to_visit_for_your_salmon_facial?')
        ->and($field->labelWasRewritten())->toBeTrue();
});

it('does not treat tidying underscores as a rewrite', function (): void {
    // Humanising "when_would_you_like_to_visit?" into readable prose is the
    // same question, so the card must not clutter itself saying so.
    $field = $this->resolver->resolve(
        label: 'when_would_you_like_to_visit?',
        clinicId: $this->clinic->getKey(),
    );

    $field->update(['label' => 'When would you like to visit?']);

    expect($field->refresh()->labelWasRewritten())->toBeFalse();
});

it('never overwrites the original on a later sighting', function (): void {
    $first = $this->resolver->resolve(
        label: 'when_would_you_like_to_visit_for_your_salmon_facial?',
        clinicId: $this->clinic->getKey(),
    );

    $first->update(['label' => 'Which session are you interested in?']);
    $this->resolver->flush();

    // A second lead arrives asking the same question.
    $again = $this->resolver->resolve(
        label: 'when_would_you_like_to_visit_for_your_salmon_facial?',
        clinicId: $this->clinic->getKey(),
    );

    expect($again->is($first))->toBeTrue()
        ->and($again->source_label)->toBe('when_would_you_like_to_visit_for_your_salmon_facial?')
        ->and($again->label)->toBe('Which session are you interested in?');
});

it('adopts the incoming wording for a question stored before this was tracked', function (): void {
    $legacy = LeadCustomField::create([
        'clinic_id' => $this->clinic->getKey(),
        'key' => LeadCustomField::makeKey('do_you_have_acne?'),
        'label' => 'Do you have acne?',
        'source_label' => null,
        'type' => LeadFieldType::Text->value,
        'is_active' => true,
    ]);

    $this->resolver->resolve(label: 'do_you_have_acne?', clinicId: $this->clinic->getKey());

    expect($legacy->refresh()->source_label)->toBe('do_you_have_acne?');
});

it('answers with the label when no original was ever recorded', function (): void {
    $field = LeadCustomField::create([
        'clinic_id' => $this->clinic->getKey(),
        'key' => 'legacy_question',
        'label' => 'A legacy question',
        'source_label' => null,
        'type' => LeadFieldType::Text->value,
        'is_active' => true,
    ]);

    expect($field->source_question)->toBe('A legacy question')
        ->and($field->labelWasRewritten())->toBeFalse();
});
