@php
    $score = data_get($item, 'score_or_label');
    $semantics = strtolower(trim((string)data_get($item, 'score_semantics', '')));
    $polarity = strtolower(trim((string)data_get($item, 'score_polarity', '')));
    $direction = strtolower(trim((string)data_get($item, 'ideal_score_direction', '')));
    $comparison = strtolower(trim((string)data_get($item, 'comparison_mode', '')));
    $caption = data_get($item, 'patient_friendly_score_interpretation', data_get($item, 'score_interpretation', ''));

    // Direction must be explicit. "higher_is_worse" contains the word "higher",
    // so broad substring matching incorrectly labelled every severity score as higher-is-better.
    $isBalance = in_array($polarity, ['depends_on_target', 'target_based'], true)
        || in_array($direction, ['move_toward_target', 'maintain_target', 'target'], true)
        || str_contains($comparison, 'target_distance')
        || str_contains($semantics, 'state_spectrum');

    $isHigher = !$isBalance && (
        in_array($polarity, ['higher_is_better', 'higher_better'], true)
        || in_array($direction, ['increase', 'higher', 'increase_toward_ideal'], true)
        || ($semantics === 'health' && !in_array($polarity, ['higher_is_worse', 'lower_is_better'], true))
    );

    $isLower = !$isBalance && (
        in_array($polarity, ['higher_is_worse', 'lower_is_better', 'lower_better'], true)
        || in_array($direction, ['decrease', 'lower', 'decrease_toward_ideal'], true)
        || $semantics === 'severity'
    );

    // Conservative fallback: unknown numerical concern scores are displayed as lower-is-better,
    // while explicitly health-oriented scores remain higher-is-better.
    if (!$isBalance && !$isHigher && !$isLower) {
        $isHigher = $semantics === 'health';
        $isLower = !$isHigher;
    }

    $directionLabel = $isBalance ? 'Balance target' : ($isHigher ? 'Higher is better' : 'Lower is better');
    $dotClass = $isBalance ? 'score-dot-on-balance' : ($isHigher ? 'score-dot-on-health' : 'score-dot-on-severity');
    $numericScore = is_numeric($score) ? max(0, min(5, (int)$score)) : 0;
@endphp
<div class="score-number">{{ $score }}/100</div>
<table cellpadding="0" cellspacing="0" style="margin:1.3mm auto 0;">
<tr>
@for($i=1;$i<=5;$i++)<td class="score-dot {{ $i <= $numericScore ? $dotClass : '' }}" style="{{ $i>1?'border-left:1mm solid #0b2033;':'' }}"></td>@endfor
</tr>
</table>
@if($caption)<div class="score-caption">{{ $caption }}</div>@endif
<div class="score-direction">{{ $directionLabel }}</div>
