import {
  burdenToClientHealthScoreV2,
} from './clientOutcomeReportV2.schema.js'

export const ACTUAL_VS_PREDICTION_VERSION =
  'aia_actual_vs_prediction_v2.0.0'

const round = (value, digits = 1) =>
  Number(Number(value).toFixed(digits))

function unreliableOutcome(outcome) {
  if (!outcome) return true
  return [
    'contradicted',
    'pairwise_inconclusive',
    'numeric_change_not_visually_confirmed',
  ].includes(
    outcome.pairwise_validation_status,
  )
}

function measuredChangeStatus(outcome) {
  if (!outcome) {
    return 'not_reliably_measurable'
  }
  if (unreliableOutcome(outcome)) {
    return 'not_reliably_measurable'
  }
  if (
    outcome.pairwise_change_direction ===
    'mixed'
  ) {
    return 'mixed'
  }
  return (
    outcome.numeric_change_direction ??
    'not_reliably_measurable'
  )
}

function predictionRange(horizon) {
  if (!horizon) return null
  const score =
    horizon.predicted_burden_score_1_to_100
  const change =
    horizon.predicted_net_change_points

  return {
    burden_score_1_to_100: {
      best_case: score?.best_case ?? null,
      expected: score?.expected ?? null,
      conservative:
        score?.conservative ?? null,
    },
    client_health_score_1_to_100: {
      best_case:
        burdenToClientHealthScoreV2(
          score?.best_case,
        ),
      expected:
        burdenToClientHealthScoreV2(
          score?.expected,
        ),
      conservative:
        burdenToClientHealthScoreV2(
          score?.conservative,
        ),
    },
    improvement_points: {
      best_case:
        change?.best_case_improvement ??
        null,
      expected:
        change?.expected_improvement ??
        null,
      conservative:
        change?.conservative_improvement ??
        null,
    },
    expected_transient_burden_points:
      horizon
        .transient_burden_adjustment_points
        ?.expected ?? 0,
    confidence:
      horizon.confidence ?? null,
  }
}

function withinTolerance(
  actual,
  target,
  tolerance,
) {
  return Math.abs(actual - target) <= tolerance
}

function classifyAgainstPrediction({
  actualImprovement,
  predictedRange,
  horizonId,
  outcome,
}) {
  if (!predictedRange) {
    return 'prediction_not_available'
  }
  if (
    unreliableOutcome(outcome) ||
    outcome?.display_numeric_change === false
  ) {
    return 'not_reliably_measurable'
  }

  const best =
    predictedRange.improvement_points
      .best_case
  const expected =
    predictedRange.improvement_points
      .expected
  const conservative =
    predictedRange.improvement_points
      .conservative

  if (
    ![
      best,
      expected,
      conservative,
      actualImprovement,
    ].every((value) =>
      Number.isFinite(Number(value)),
    )
  ) {
    return 'prediction_not_available'
  }

  const transient =
    predictedRange
      .expected_transient_burden_points
  const earlyHorizon = [
    'immediate_post',
    'hours_48',
  ].includes(horizonId)

  if (
    earlyHorizon &&
    actualImprovement <
      conservative &&
    actualImprovement < 0 &&
    (
      transient >= 1.5 ||
      Boolean(
        outcome?.transient_reactivity_note,
      )
    )
  ) {
    return 'temporarily_obscured_by_expected_reactivity'
  }

  const tolerance = Math.max(
    1,
    Number(
      outcome?.minimum_detectable_change_points ??
        5,
    ) * 0.25,
  )

  if (actualImprovement > best + tolerance) {
    return 'above_predicted_range'
  }
  if (
    actualImprovement >= expected &&
    !withinTolerance(
      actualImprovement,
      expected,
      tolerance,
    )
  ) {
    return 'within_predicted_range_above_expected'
  }
  if (
    withinTolerance(
      actualImprovement,
      expected,
      tolerance,
    )
  ) {
    return 'within_predicted_range'
  }
  if (actualImprovement >= conservative) {
    return 'within_predicted_range_below_expected'
  }
  return 'below_predicted_range'
}

export function compareActualOutcomeToPredictionV2({
  featureId,
  measuredOutcome,
  predictionFeature,
  horizonId,
  predictionBasis = null,
} = {}) {
  const baseline =
    measuredOutcome
      ?.before_burden_score_1_to_100 ??
    predictionFeature
      ?.global_prediction
      ?.baseline_burden_score_1_to_100 ??
    null
  const actual =
    measuredOutcome
      ?.after_burden_score_1_to_100 ??
    null
  const actualImprovement =
    measuredOutcome?.improvement_points ??
    (
      Number.isFinite(Number(baseline)) &&
      Number.isFinite(Number(actual))
        ? Number(baseline) - Number(actual)
        : null
    )

  const horizon =
    horizonId
      ? predictionFeature
          ?.global_prediction?.horizons?.[
          horizonId
        ] ?? null
      : null
  const predicted = predictionRange(horizon)
  const status =
    classifyAgainstPrediction({
      actualImprovement,
      predictedRange: predicted,
      horizonId,
      outcome: measuredOutcome,
    })

  const expectedImprovement =
    predicted?.improvement_points.expected
  const expectedGap =
    Number.isFinite(
      Number(actualImprovement),
    ) &&
    Number.isFinite(
      Number(expectedImprovement),
    )
      ? Number(actualImprovement) -
        Number(expectedImprovement)
      : null

  return {
    version:
      ACTUAL_VS_PREDICTION_VERSION,
    feature_id: featureId,
    horizon_id: horizonId,
    prediction_basis:
      predictionBasis,
    baseline: {
      burden_score_1_to_100:
        baseline,
      client_health_score_1_to_100:
        burdenToClientHealthScoreV2(
          baseline,
        ),
    },
    actual: {
      burden_score_1_to_100:
        actual,
      client_health_score_1_to_100:
        burdenToClientHealthScoreV2(
          actual,
        ),
      improvement_points:
        actualImprovement,
      change_status:
        measuredChangeStatus(
          measuredOutcome,
        ),
      minimum_detectable_change_points:
        measuredOutcome
          ?.minimum_detectable_change_points ??
        null,
      display_numeric_change:
        measuredOutcome
          ?.display_numeric_change ??
        false,
      pairwise_validation_status:
        measuredOutcome
          ?.pairwise_validation_status ??
        null,
      pairwise_summary:
        measuredOutcome
          ?.pairwise_summary ?? '',
      transient_reactivity_note:
        measuredOutcome
          ?.transient_reactivity_note ?? '',
    },
    predicted,
    outcome_vs_prediction_status:
      status,
    difference_from_expected_improvement_points:
      expectedGap === null
        ? null
        : round(expectedGap),
    actual_measurement_is_authoritative:
      true,
    prediction_is_comparison_only:
      true,
    numeric_display_policy:
      measuredOutcome
        ?.display_numeric_change
        ? 'show_actual_numeric_change'
        : 'suppress_change_number_and_show_measurement_status',
  }
}

export function compareZoneActualToPredictionV2({
  featureId,
  zoneId,
  measuredOutcome,
  predictionFeature,
  horizonId,
} = {}) {
  const measuredZone =
    measuredOutcome?.zone_changes?.[
      zoneId
    ]
  const predictedZone =
    predictionFeature?.zone_predictions?.[
      zoneId
    ]
  const predictedHorizon =
    predictedZone?.horizons?.[horizonId]

  if (
    !measuredZone ||
    !predictedHorizon
  ) {
    return {
      feature_id: featureId,
      zone_id: zoneId,
      comparable: false,
      outcome_vs_prediction_status:
        'prediction_not_available',
    }
  }

  const actualImprovement =
    measuredZone.improvement_points
  const predicted = predictionRange(
    predictedHorizon,
  )
  const pairwiseDirection =
    measuredOutcome?.zones_visibly_improved?.includes(
      zoneId,
    )
      ? 'improved'
      : measuredOutcome?.zones_visibly_worsened?.includes(
            zoneId,
          )
        ? 'worsened'
        : null

  const syntheticOutcome = {
    improvement_points:
      actualImprovement,
    minimum_detectable_change_points:
      measuredOutcome
        ?.minimum_detectable_change_points ??
      5,
    display_numeric_change:
      Math.abs(
        Number(actualImprovement ?? 0),
      ) >=
      Number(
        measuredOutcome
          ?.minimum_detectable_change_points ??
          5,
      ),
    numeric_change_direction:
      Number(actualImprovement) > 0
        ? 'improved'
        : Number(actualImprovement) < 0
          ? 'worsened'
          : 'stable',
    pairwise_change_direction:
      pairwiseDirection,
    pairwise_validation_status:
      measuredOutcome
        ?.pairwise_validation_status,
    transient_reactivity_note:
      measuredOutcome
        ?.transient_reactivity_note,
  }

  return {
    feature_id: featureId,
    zone_id: zoneId,
    comparable: true,
    baseline_burden_score_1_to_100:
      measuredZone
        .before_burden_score_1_to_100,
    actual_burden_score_1_to_100:
      measuredZone
        .after_burden_score_1_to_100,
    actual_improvement_points:
      actualImprovement,
    prediction_range: predicted,
    outcome_vs_prediction_status:
      classifyAgainstPrediction({
        actualImprovement,
        predictedRange: predicted,
        horizonId,
        outcome: syntheticOutcome,
      }),
    pairwise_direction:
      pairwiseDirection,
  }
}
