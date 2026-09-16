<?php

declare(strict_types=1);

use App\Filament\Widgets\LeadsByConcernChart;
use App\Models\{Clinic, Lead, LeadCustomField, LeadFieldValue};
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The dashboard's "Leads by Answer" doughnut.
 *
 * It drew an empty card on real data for two compounding reasons: it looked
 * only for questions typed `select` or `multiselect`, which Meta lead forms
 * never produce, and it decided whether to show itself using a looser rule than
 * the one it used to find data — so it appeared, announced itself, and had
 * nothing to draw.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);
});

function chartField(string $key, string $type, int $usage = 10): LeadCustomField
{
    return LeadCustomField::create([
        'clinic_id' => test()->clinic->getKey(),
        'key' => $key,
        'label' => str_replace('_', ' ', $key),
        'type' => $type,
        'is_active' => true,
        'usage_count' => $usage,
    ]);
}

/**
 * @param  array<int, string>  $answers
 */
function chartAnswers(LeadCustomField $field, array $answers): void
{
    foreach ($answers as $index => $answer) {
        $lead = Lead::create([
            'clinic_id' => test()->clinic->getKey(),
            'first_name' => 'Lead' . $index,
            'phone' => '98290000' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'status' => \App\Enums\LeadStatus::New->value,
            'source' => \App\Enums\LeadSource::Manual->value,
        ]);

        LeadFieldValue::create([
            'lead_id' => $lead->getKey(),
            'lead_custom_field_id' => $field->getKey(),
            'value' => $answer,
        ]);
    }
}

/**
 * The real shape of a Meta lead question: yes or no. Typed `boolean`, so the
 * old select/multiselect rule excluded the most chartable question there is.
 */
it('charts a yes or no question', function (): void {
    $field = chartField('have_you_visited_before', 'boolean');
    chartAnswers($field, ['yes', 'no', 'no', 'no', 'yes']);

    $data = (fn () => $this->getData())->call(new LeadsByConcernChart());

    expect($data['labels'])->toEqualCanonicalizing(['Yes', 'No'])
        ->and(array_sum($data['datasets'][0]['data']))->toBe(5);
});

/**
 * "When would you like to visit?" arrives as a timestamp, one distinct value
 * per lead. A doughnut of it is a hundred one-lead slices, not a distribution.
 */
it('leaves a question whose every answer is different out of the chart', function (): void {
    $field = chartField('when_would_you_like_to_visit', 'date');

    chartAnswers($field, [
        '2026-08-08T16:50:24+0530',
        '2026-08-09T08:15:01+0530',
        '2026-08-09T11:10:33+0530',
        '2026-08-10T13:50:47+0530',
    ]);

    expect(LeadsByConcernChart::canView())->toBeFalse();
});

/**
 * The bug that produced the blank card: the widget showed itself on a looser
 * test than the one it used to find data.
 */
it('hides itself rather than drawing an empty card', function (): void {
    $field = chartField('what_is_your_name', 'text');
    chartAnswers($field, ['Anita', 'Rohit', 'Neha', 'Gaurav']);

    expect(LeadsByConcernChart::canView())->toBeFalse();
});

it('shows itself as soon as one question has repeating answers', function (): void {
    chartAnswers(chartField('what_is_your_name', 'text'), ['Anita', 'Rohit']);
    chartAnswers(chartField('have_you_visited_before', 'boolean'), ['yes', 'no', 'yes', 'no']);

    expect(LeadsByConcernChart::canView())->toBeTrue();
});

/**
 * A declared select is trusted without counting: a multiselect stores the whole
 * combination pipe-joined in one cell, so distinct raw values over-counts badly
 * and would exclude exactly the question the widget was written for.
 */
it('trusts a declared select even when every combination is unique', function (): void {
    $field = chartField('concerns', 'multiselect');

    chartAnswers($field, [
        'acne|pigmentation',
        'acne|ageing|hair fall',
        'pigmentation|ageing|acne scars',
        'hair fall|dandruff',
    ]);

    expect(LeadsByConcernChart::canView())->toBeTrue();
});

it('draws nothing and stays hidden when no lead has answered anything', function (): void {
    chartField('have_you_visited_before', 'boolean', usage: 0);

    expect(LeadsByConcernChart::canView())->toBeFalse();
});
