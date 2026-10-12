<?php

use App\Services\SebumBalanceReporting;

it('compares sebum by the same balance rules as the app', function (array $fixture) {
    $comparison = SebumBalanceReporting::compare($fixture['before'], $fixture['after'], $fixture['reference']);
    expect($comparison['result'])->toBe($fixture['result']);
    expect($comparison['status'])->toBe($fixture['status']);
    expect(abs($comparison['change_points'] - $fixture['gain']))->toBeLessThan(1e-9);
})->with(function () {
    $fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/sebumBalanceReporting.json'), true, 512, JSON_THROW_ON_ERROR);
    $dataset = [];
    foreach ($fixtures as $fixture) {
        $dataset[$fixture['name']] = [$fixture];
    }
    return $dataset;
});

it('projects historical sebum labels for both reports without modifying stored values or other parameters', function () {
    $baseline = ['diagnosis_report' => ['skin_sebum_index' => [
        'parameter_name' => 'Skin Sebum Index', 'score_or_label' => 25.15, 'target_single_session_score' => 50,
    ]]];
    $rows = [
        'skin_sebum_content' => ['before_treatment_score_or_label' => 40, 'post_treatment_score_or_label' => 45,
            'result' => 'declined', 'base_post_score_or_label_internal' => 99],
        'skin_hydration_score' => ['before_treatment_score_or_label' => 40, 'post_treatment_score_or_label' => 60, 'result' => 'improved'],
    ];
    $original = $rows;
    $result = SebumBalanceReporting::forReportRows($rows, $baseline);
    expect($result['skin_sebum_content']['result'])->toBe('improved');
    expect($result['skin_sebum_content']['before_treatment_score_or_label'])->toBe(25);
    expect($result['skin_sebum_content']['post_treatment_score_or_label'])->toBe(45);
    expect($result['skin_sebum_content']['base_post_score_or_label_internal'])->toBe(99);
    expect($result['skin_hydration_score'])->toBe($original['skin_hydration_score']);
    expect($rows)->toBe($original);
});

it('uses saved raw reported measurements rather than rounded or internal rescored values', function () {
    $row = ['before_treatment_score_or_label' => 40, 'post_treatment_score_or_label' => 40, 'result' => 'stable',
        'base_post_score_or_label_internal' => 99,
        '_facial_score_display' => ['raw' => ['before_treatment_score_or_label' => 40.1, 'post_treatment_score_or_label' => 40.4],
            'reporting_reference' => ['before' => 25, 'target' => 50]]];
    $rows = SebumBalanceReporting::forReportRows(['skin_sebum_content' => $row], []);
    expect($rows['skin_sebum_content']['result'])->toBe('improved');
    expect($rows['skin_sebum_content']['post_treatment_score_or_label'])->toBe(40);
    expect($rows['skin_sebum_content']['_facial_score_display'])->toBe($row['_facial_score_display']);
});

it('requires review for missing or conflicting targets instead of trusting an old improved label', function () {
    $row = ['before_treatment_score_or_label' => 25, 'post_treatment_score_or_label' => 40, 'result' => 'improved'];
    $rows = SebumBalanceReporting::forReportRows(['skin_sebum_content' => $row], []);
    expect($rows['skin_sebum_content']['result'])->toBe('stable');
    $baseline = [
        'diagnosis_report' => ['skin_sebum_content' => ['score_or_label' => 25, 'target_single_session_score' => 50]],
        'selected' => [['parameter' => 'Skin Sebum Index', 'target_score' => 70]],
    ];
    expect(SebumBalanceReporting::resolveReference($baseline))->toBe(['status' => 'review_required']);
    $rows = SebumBalanceReporting::forReportRows(['skin_sebum_content' => $row], $baseline);
    expect($rows['skin_sebum_content']['result'])->toBe('stable');
    expect($rows['skin_sebum_content']['report_comparison_status'])->toBe('review_required');
});

it('reuses only explicitly supplied score-scale ranges', function () {
    $baseline = ['diagnosis_report' => ['skin_sebum_content' => ['score_or_label' => 45, 'balance_target_range' => [40, 60]]]];
    $row = ['before_treatment_score_or_label' => 45, 'post_treatment_score_or_label' => 58, 'result' => 'declined'];
    $rows = SebumBalanceReporting::forReportRows(['skin_sebum_content' => $row], $baseline);
    expect($rows['skin_sebum_content']['result'])->toBe('stable');
    expect($rows['skin_sebum_content']['report_comparison_status'])->toBe('balanced');
});
