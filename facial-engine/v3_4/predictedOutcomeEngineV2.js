import { deriveCalibratedParameterV36, CALIBRATION_VERSION_V36 } from './clinicalCalibrationV36.js'
import {
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  FACE_ZONE_ATLAS_V2,
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'
import {
  DERIVED_REPORT_FORMULAS_V2,
  CORE_FEATURE_FORMULAS_V2,
} from './skinStateScoreMapperV2.js'
import {
  MODALITY_RESPONSE_LIBRARY_V2,
} from './modalityResponseLibraryV2.js'
import {
  PREDICTED_OUTCOME_SCHEMA_VERSION,
  OUTCOME_HORIZONS_V2,
  OUTCOME_HORIZON_IDS_V2,
  SCORE_DIRECTION_V2,
  validatePredictedOutcomeV2,
} from './predictedOutcomeV2.schema.js'
import {
  PREDICTED_OUTCOME_RULES_VERSION,
  HORIZON_POINT_CAPS_V2,
  INTENSITY_RESPONSE_MULTIPLIER_V2,
  ROLE_RESPONSE_MULTIPLIER_V2,
  RESPONSE_CONFIDENCE_MULTIPLIER_V2,
  EVIDENCE_TIER_MULTIPLIER_V2,
  SCORE_RELIABILITY_MULTIPLIER_V2,
  ONSET_HORIZON_BLEND_V2,
  FEATURE_HORIZON_MULTIPLIER_V2,
  MULTI_MODALITY_SATURATION_V2,
  PREDICTION_RANGE_WIDTH_V2,
  TRANSIENT_EFFECT_PROFILES_V2,
  MODALITY_TRANSIENT_OVERRIDES_V2,
  CONFIDENCE_SCORE_THRESHOLDS_V2,
} from './predictedOutcomeRulesV2.js'
import {
  calculateDeliveredTreatmentDoseV2,
  findActionDoseV2,
} from './calculateDeliveredTreatmentDoseV2.js'

export const PREDICTED_OUTCOME_ENGINE_VERSION =
  'aia_predicted_outcome_engine_v2.0.0'

const clamp = (value, min = 0, max = 1) =>
  Math.max(min, Math.min(max, Number(value)))

const clampScore = (value) =>
  Math.max(1, Math.min(100, Math.round(Number(value))))

const round = (value, digits = 2) =>
  Number(Number(value).toFixed(digits))

const unique = (values) =>
  [...new Set((values ?? []).filter(Boolean))]

function normaliseOptimizerResult(input) {
  if (input?.plan?.modality_actions) return input
  if (input?.optimiser_plan?.plan?.modality_actions) {
    return input.optimiser_plan
  }
  throw new Error(
    'Optimizer result with plan.modality_actions is required.',
  )
}

function baselineZoneScore(
  skinState,
  featureId,
  zoneId,
) {
  const value =
    skinState?.core_features?.[featureId]
      ?.zone_scores_1_to_100?.[zoneId]
  return Number.isFinite(Number(value))
    ? Number(value)
    : null
}

function baselineGlobalScore(
  skinState,
  featureId,
) {
  const value =
    skinState?.core_features?.[featureId]
      ?.global_burden_score_1_to_100
  return Number.isFinite(Number(value))
    ? Number(value)
    : null
}

function scoreReliability(
  skinState,
  featureId,
) {
  const reliability =
    skinState?.core_features?.[featureId]
      ?.score_reliability

  const numeric =
    reliability?.score_1_to_100 ??
    reliability?.score ??
    reliability?.reliability_score_1_to_100
  if (Number.isFinite(Number(numeric))) {
    return clamp(Number(numeric) / 100, 0.35, 1)
  }

  return (
    SCORE_RELIABILITY_MULTIPLIER_V2[
      String(reliability?.tier ?? 'medium')
        .toLowerCase()
    ] ?? 0.78
  )
}

function intensityForActionZone(action, zoneId) {
  return (
    action?.intensity_by_zone?.[zoneId] ??
    action?.zone_delivery?.find(
      (zone) => zone.zone_id === zoneId,
    )?.maximum_intensity ??
    action?.eligibility_summary
      ?.maximum_intensity ??
    'medium'
  )
}

function actionTargetsFeatureInZone(
  action,
  featureId,
  zoneId,
) {
  return Boolean(
    action?.zone_delivery?.some(
      (zone) =>
        zone.zone_id === zoneId &&
        zone.targets?.some(
          (target) =>
            target.feature_id === featureId,
        ),
    ) ||
      action?.target_features?.some(
        (feature) =>
          feature.feature_id === featureId &&
          (
            !feature.zones?.length ||
            feature.zones.includes(zoneId)
          ),
      ),
  )
}

function onsetBlend(
  expectedOnset,
  horizonId,
) {
  const key = String(expectedOnset ?? 'none')
    .trim()
    .toLowerCase()
  return (
    ONSET_HORIZON_BLEND_V2[key]?.[horizonId] ??
    ONSET_HORIZON_BLEND_V2.none[horizonId]
  )
}

function responseStrengthAtHorizon(
  response,
  featureId,
  horizonId,
) {
  const blend = onsetBlend(
    response.expected_onset,
    horizonId,
  )
  const strength = Math.min(
    5,
    Number(response.immediate_strength_0_to_5 ?? 0) *
      blend.immediate +
      Number(response.course_strength_0_to_5 ?? 0) *
        blend.course,
  )
  const featureMultiplier =
    FEATURE_HORIZON_MULTIPLIER_V2[
      featureId
    ]?.[horizonId] ?? 1

  return clamp(
    (strength / 5) * featureMultiplier,
    0,
    1,
  )
}

function actionDoseForZone({
  action,
  zoneId,
  predictionType,
  deliveredDose,
}) {
  if (predictionType === 'pre_session_plan') {
    return {
      dose_fraction_0_to_1: 1,
      evidence_quality: 'planned_full_delivery',
    }
  }

  const actionDose = findActionDoseV2(
    deliveredDose,
    action.modality_id,
  )
  if (!actionDose) {
    return {
      dose_fraction_0_to_1: 0,
      evidence_quality: 'missing',
    }
  }
  const zone =
    actionDose.zone_dose?.[zoneId]
  return {
    dose_fraction_0_to_1:
      zone?.dose_fraction_0_to_1 ??
      actionDose.overall_dose_fraction_0_to_1 ??
      0,
    evidence_quality:
      actionDose.evidence_quality,
  }
}

function doseEvidenceMultiplier(quality) {
  return {
    planned_full_delivery: 0.94,
    high: 1,
    medium_high: 0.94,
    medium: 0.84,
    low: 0.66,
    missing: 0.35,
  }[quality] ?? 0.75
}

function actionFeatureContribution({
  action,
  featureId,
  zoneId,
  baselineScore,
  horizonId,
  predictionType,
  deliveredDose,
  reliability,
}) {
  const entry =
    MODALITY_RESPONSE_LIBRARY_V2[
      action.modality_id
    ]
  const response =
    entry?.feature_response?.[featureId]

  if (
    !entry ||
    !response ||
    !actionTargetsFeatureInZone(
      action,
      featureId,
      zoneId,
    )
  ) {
    return null
  }

  const horizonStrength =
    responseStrengthAtHorizon(
      response,
      featureId,
      horizonId,
    )
  if (horizonStrength <= 0) return null

  const dose = actionDoseForZone({
    action,
    zoneId,
    predictionType,
    deliveredDose,
  })
  if (dose.dose_fraction_0_to_1 <= 0) {
    return null
  }

  const intensity =
    INTENSITY_RESPONSE_MULTIPLIER_V2[
      intensityForActionZone(action, zoneId)
    ] ?? 0.72
  const role =
    ROLE_RESPONSE_MULTIPLIER_V2[
      response.clinical_role
    ] ?? 0.5
  const responseConfidence =
    RESPONSE_CONFIDENCE_MULTIPLIER_V2[
      response.confidence
    ] ?? 0.64
  const evidence =
    EVIDENCE_TIER_MULTIPLIER_V2[
      entry.evidence?.overall_tier
    ] ?? 0.62
  const burdenResponsiveness =
    0.52 + 0.48 * clamp(baselineScore / 100)

  const points =
    HORIZON_POINT_CAPS_V2[horizonId] *
    horizonStrength *
    intensity *
    role *
    responseConfidence *
    evidence *
    reliability *
    burdenResponsiveness *
    dose.dose_fraction_0_to_1

  const confidenceScore =
    reliability *
    responseConfidence *
    evidence *
    doseEvidenceMultiplier(
      dose.evidence_quality,
    )

  return {
    modality_id: action.modality_id,
    modality_name: action.modality_name,
    plan_role: action.plan_role,
    feature_id: featureId,
    zone_id: zoneId,
    expected_onset:
      response.expected_onset,
    clinical_role:
      response.clinical_role,
    immediate_strength_0_to_5:
      response.immediate_strength_0_to_5,
    course_strength_0_to_5:
      response.course_strength_0_to_5,
    horizon_response_fraction:
      round(horizonStrength, 4),
    delivered_dose_fraction_0_to_1:
      round(
        dose.dose_fraction_0_to_1,
        4,
      ),
    intensity:
      intensityForActionZone(action, zoneId),
    expected_improvement_points:
      round(points, 3),
    confidence_score_0_to_1:
      round(confidenceScore, 4),
    response_notes: response.notes,
  }
}

function combineTherapeuticContributions(
  contributions,
  baselineScore,
) {
  const sorted = [...contributions].sort(
    (a, b) =>
      b.expected_improvement_points -
        a.expected_improvement_points ||
      a.modality_id.localeCompare(b.modality_id),
  )
  const multipliers = [
    1,
    MULTI_MODALITY_SATURATION_V2
      .second_contribution_multiplier,
    MULTI_MODALITY_SATURATION_V2
      .third_contribution_multiplier,
  ]

  const combined = sorted.reduce(
    (sum, contribution, index) =>
      sum +
      contribution.expected_improvement_points *
        (
          multipliers[index] ??
          MULTI_MODALITY_SATURATION_V2
            .later_contribution_multiplier
        ),
    0,
  )

  return Math.min(
    combined,
    baselineScore *
      MULTI_MODALITY_SATURATION_V2
        .maximum_single_session_reduction_fraction,
  )
}

function transientPointsForAction({
  action,
  featureId,
  zoneId,
  horizonId,
  predictionType,
  deliveredDose,
}) {
  if (
    !(action.selected_zones ?? []).includes(zoneId)
  ) {
    return null
  }

  const entry =
    MODALITY_RESPONSE_LIBRARY_V2[
      action.modality_id
    ]
  if (!entry) return null

  const categoryPoints =
    TRANSIENT_EFFECT_PROFILES_V2[
      action.category
    ]?.[featureId]?.[horizonId] ?? 0
  const modalityPoints =
    MODALITY_TRANSIENT_OVERRIDES_V2[
      action.modality_id
    ]?.[featureId]?.[horizonId] ?? 0
  const basePoints =
    categoryPoints + modalityPoints
  if (basePoints <= 0) return null

  const dose = actionDoseForZone({
    action,
    zoneId,
    predictionType,
    deliveredDose,
  })
  const intensity =
    INTENSITY_RESPONSE_MULTIPLIER_V2[
      intensityForActionZone(action, zoneId)
    ] ?? 0.72

  return {
    modality_id: action.modality_id,
    points: round(
      basePoints *
        intensity *
        dose.dose_fraction_0_to_1,
      3,
    ),
    type:
      'expected_short_lived_treatment_effect',
  }
}

function combineTransientContributions(
  contributions,
) {
  const sorted = [...contributions].sort(
    (a, b) =>
      b.points - a.points ||
      a.modality_id.localeCompare(b.modality_id),
  )
  return Math.min(
    18,
    sorted.reduce(
      (sum, contribution, index) =>
        sum +
        contribution.points *
          (index === 0
            ? 1
            : index === 1
              ? 0.7
              : 0.45),
      0,
    ),
  )
}

function predictionConfidence({
  contributions,
  transientContributions,
  reliability,
}) {
  if (
    reliability < 0.45 ||
    (
      contributions.length === 0 &&
      transientContributions.length === 0
    )
  ) {
    return contributions.length === 0 &&
      transientContributions.length === 0
      ? {
          score: reliability,
          label: 'medium',
        }
      : {
          score: reliability,
          label:
            'not_reliably_predictable',
        }
  }

  const contributionScore =
    contributions.length > 0
      ? contributions.reduce(
          (sum, contribution) =>
            sum +
            contribution.confidence_score_0_to_1,
          0,
        ) / contributions.length
      : reliability * 0.8

  const score = clamp(
    0.55 * contributionScore +
      0.45 * reliability,
  )

  const label =
    score >=
    CONFIDENCE_SCORE_THRESHOLDS_V2.high
      ? 'high'
      : score >=
          CONFIDENCE_SCORE_THRESHOLDS_V2
            .medium_high
        ? 'medium_high'
        : score >=
            CONFIDENCE_SCORE_THRESHOLDS_V2
              .medium
          ? 'medium'
          : score >=
              CONFIDENCE_SCORE_THRESHOLDS_V2
                .low
            ? 'low'
            : 'not_reliably_predictable'

  return {
    score: round(score, 4),
    label,
  }
}

function interpretationForNetChange(
  expectedImprovement,
) {
  if (expectedImprovement >= 10) {
    return 'strong_visible_improvement_predicted'
  }
  if (expectedImprovement >= 5) {
    return 'visible_improvement_predicted'
  }
  if (expectedImprovement >= 1.5) {
    return 'subtle_visible_improvement_predicted'
  }
  if (expectedImprovement <= -4) {
    return 'temporary_visible_worsening_possible'
  }
  if (expectedImprovement <= -1.5) {
    return 'minor_temporary_worsening_possible'
  }
  return 'no_material_visible_change_predicted'
}

function buildHorizonResult({
  baselineScore,
  therapeuticPoints,
  transientPoints,
  confidence,
  contributors,
  transientContributors,
}) {
  const range =
    PREDICTION_RANGE_WIDTH_V2[
      confidence.label
    ] ??
    PREDICTION_RANGE_WIDTH_V2.medium

  const therapeuticLow =
    therapeuticPoints * range.lower
  const therapeuticHigh =
    therapeuticPoints * range.upper
  const transientLow =
    transientPoints * 0.75
  const transientHigh =
    transientPoints * 1.3

  const expectedNet =
    therapeuticPoints - transientPoints
  const bestNet =
    therapeuticHigh - transientLow
  const conservativeNet =
    therapeuticLow - transientHigh

  return {
    baseline_burden_score_1_to_100:
      baselineScore,
    predicted_burden_score_1_to_100: {
      best_case: clampScore(
        baselineScore - bestNet,
      ),
      expected: clampScore(
        baselineScore - expectedNet,
      ),
      conservative: clampScore(
        baselineScore - conservativeNet,
      ),
    },
    predicted_net_change_points: {
      best_case_improvement:
        round(bestNet, 1),
      expected_improvement:
        round(expectedNet, 1),
      conservative_improvement:
        round(conservativeNet, 1),
    },
    therapeutic_improvement_points: {
      expected:
        round(therapeuticPoints, 1),
    },
    transient_burden_adjustment_points: {
      expected: round(transientPoints, 1),
      direction:
        transientPoints > 0
          ? 'temporarily_increases_burden'
          : 'none',
    },
    interpretation:
      interpretationForNetChange(expectedNet),
    visible_score_shift_is_included: true,
    confidence,
    contributing_modalities: contributors.map(
      (item) => ({
        modality_id: item.modality_id,
        expected_improvement_points:
          item.expected_improvement_points,
        clinical_role: item.clinical_role,
      }),
    ),
    transient_contributors:
      transientContributors,
  }
}

function zonePrediction({
  baselineSkinState,
  optimizerResult,
  featureId,
  zoneId,
  predictionType,
  deliveredDose,
}) {
  const baselineScore = baselineZoneScore(
    baselineSkinState,
    featureId,
    zoneId,
  )
  if (baselineScore === null) return null

  const reliability = scoreReliability(
    baselineSkinState,
    featureId,
  )
  const horizons = {}

  for (const horizonId of OUTCOME_HORIZON_IDS_V2) {
    const contributions =
      optimizerResult.plan.modality_actions
        .map((action) =>
          actionFeatureContribution({
            action,
            featureId,
            zoneId,
            baselineScore,
            horizonId,
            predictionType,
            deliveredDose,
            reliability,
          }),
        )
        .filter(Boolean)

    const transientContributions =
      optimizerResult.plan.modality_actions
        .map((action) =>
          transientPointsForAction({
            action,
            featureId,
            zoneId,
            horizonId,
            predictionType,
            deliveredDose,
          }),
        )
        .filter(Boolean)
        .filter(
          (item) => item.points > 0,
        )

    const therapeuticPoints =
      combineTherapeuticContributions(
        contributions,
        baselineScore,
      )
    const transientPoints =
      combineTransientContributions(
        transientContributions,
      )
    const confidence =
      predictionConfidence({
        contributions,
        transientContributions,
        reliability,
      })

    horizons[horizonId] =
      buildHorizonResult({
        baselineScore,
        therapeuticPoints,
        transientPoints,
        confidence,
        contributors: contributions,
        transientContributors:
          transientContributions,
      })
  }

  return {
    zone_id: zoneId,
    zone_label:
      FACE_ZONE_ATLAS_V2.zones[zoneId]?.label ??
      zoneId,
    baseline_burden_score_1_to_100:
      baselineScore,
    score_reliability_0_to_1:
      round(reliability, 4),
    horizons,
  }
}

function weightedMean(values) {
  const totalWeight = values.reduce(
    (sum, item) => sum + item.weight,
    0,
  )
  if (totalWeight <= 0) return 0
  return (
    values.reduce(
      (sum, item) =>
        sum + item.value * item.weight,
      0,
    ) / totalWeight
  )
}

function aggregateZoneNetChange(
  zonePredictions,
  horizonId,
  rangeField,
) {
  const items = Object.values(
    zonePredictions,
  ).map((zone) => {
    const horizon =
      zone.horizons[horizonId]
    const predicted =
      horizon.predicted_burden_score_1_to_100[
        rangeField
      ]
    return {
      value:
        zone.baseline_burden_score_1_to_100 -
        predicted,
      weight:
        FACE_ZONE_ATLAS_V2.zones[zone.zone_id]
          ?.default_area_weight ?? 0.04,
    }
  })

  if (!items.length) return 0
  const mean = weightedMean(items)
  const top = [...items]
    .sort((a, b) => b.value - a.value)
    .slice(0, Math.min(3, items.length))
  const topMean =
    top.reduce(
      (sum, item) => sum + item.value,
      0,
    ) / top.length

  return 0.75 * mean + 0.25 * topMean
}

function aggregateGlobalPrediction({
  baselineSkinState,
  featureId,
  zonePredictions,
}) {
  const baselineScore = baselineGlobalScore(
    baselineSkinState,
    featureId,
  )
  const horizons = {}

  for (const horizonId of OUTCOME_HORIZON_IDS_V2) {
    const bestChange =
      aggregateZoneNetChange(
        zonePredictions,
        horizonId,
        'best_case',
      )
    const expectedChange =
      aggregateZoneNetChange(
        zonePredictions,
        horizonId,
        'expected',
      )
    const conservativeChange =
      aggregateZoneNetChange(
        zonePredictions,
        horizonId,
        'conservative',
      )

    const zoneHorizons = Object.values(
      zonePredictions,
    ).map(
      (zone) => zone.horizons[horizonId],
    )
    const therapeutic =
      zoneHorizons.length > 0
        ? zoneHorizons.reduce(
            (sum, horizon) =>
              sum +
              horizon
                .therapeutic_improvement_points
                .expected,
            0,
          ) / zoneHorizons.length
        : 0
    const transient =
      zoneHorizons.length > 0
        ? zoneHorizons.reduce(
            (sum, horizon) =>
              sum +
              horizon
                .transient_burden_adjustment_points
                .expected,
            0,
          ) / zoneHorizons.length
        : 0

    const confidenceScores =
      zoneHorizons.map(
        (horizon) =>
          horizon.confidence.score,
      )
    const confidenceScore =
      confidenceScores.length
        ? confidenceScores.reduce(
            (sum, value) => sum + value,
            0,
          ) / confidenceScores.length
        : scoreReliability(
            baselineSkinState,
            featureId,
          )
    const confidence =
      predictionConfidence({
        contributions: [
          {
            confidence_score_0_to_1:
              confidenceScore,
          },
        ],
        transientContributions: [],
        reliability: scoreReliability(
          baselineSkinState,
          featureId,
        ),
      })

    horizons[horizonId] = {
      baseline_burden_score_1_to_100:
        baselineScore,
      predicted_burden_score_1_to_100: {
        best_case: clampScore(
          baselineScore - bestChange,
        ),
        expected: clampScore(
          baselineScore - expectedChange,
        ),
        conservative: clampScore(
          baselineScore -
            conservativeChange,
        ),
      },
      predicted_net_change_points: {
        best_case_improvement:
          round(bestChange, 1),
        expected_improvement:
          round(expectedChange, 1),
        conservative_improvement:
          round(conservativeChange, 1),
      },
      therapeutic_improvement_points: {
        expected: round(therapeutic, 1),
      },
      transient_burden_adjustment_points: {
        expected: round(transient, 1),
        direction:
          transient > 0
            ? 'temporarily_increases_burden'
            : 'none',
      },
      interpretation:
        interpretationForNetChange(
          expectedChange,
        ),
      visible_score_shift_is_included: true,
      confidence,
    }
  }

  return {
    baseline_burden_score_1_to_100:
      baselineScore,
    horizons,
  }
}

function predictedCoreForCalibration(baseline, features, horizonId, rangeField) {
  const out = structuredClone(baseline.core_features)
  for (const [id, f] of Object.entries(out)) {
    const prediction = features[id]
    const next = prediction?.global_prediction?.horizons?.[horizonId]?.predicted_burden_score_1_to_100?.[rangeField] ?? f.global_burden_score_1_to_100
    const delta = (next - f.global_burden_score_1_to_100) / 99
    f.guarded_normalized_burden_0_to_1 = Math.max(0, Math.min(1, (f.guarded_normalized_burden_0_to_1 ?? (f.global_burden_score_1_to_100-1)/99) + delta))
    f.global_burden_score_1_to_100 = next
    f.zone_normalized_burdens_0_to_1 ??= {}
    for (const [zoneId, before] of Object.entries(f.zone_scores_1_to_100 ?? {})) {
      const after = prediction?.zone_predictions?.[zoneId]?.horizons?.[horizonId]?.predicted_burden_score_1_to_100?.[rangeField] ?? before
      f.zone_normalized_burdens_0_to_1[zoneId] = Math.max(0, Math.min(1, (f.zone_normalized_burdens_0_to_1[zoneId] ?? (before-1)/99) + (after-before)/99))
      f.zone_scores_1_to_100[zoneId] = after
    }
  }
  return out
}

function deriveReportParameters({
  baselineSkinState,
  features,
}) {
  const result = {}

  for (const [
    parameterId,
    definition,
  ] of Object.entries(
    DERIVED_REPORT_FORMULAS_V2,
  CORE_FEATURE_FORMULAS_V2,
  )) {
    const parameter = {
      parameter_id: parameterId,
      display_polarity:
        definition.display_polarity,
      source_components:
        definition.components,
      horizons: {},
    }

    for (const horizonId of OUTCOME_HORIZON_IDS_V2) {
      const burdenByRange = {}
      for (const rangeField of [
        'best_case',
        'expected',
        'conservative',
      ]) {
        burdenByRange[rangeField] =
          clampScore(
            Object.entries(
              definition.components,
            ).reduce(
              (sum, [featureId, weight]) =>
                sum +
                (
                  features[featureId]
                    ?.global_prediction
                    ?.horizons?.[horizonId]
                    ?.predicted_burden_score_1_to_100?.[
                    rangeField
                  ] ??
                  baselineGlobalScore(
                    baselineSkinState,
                    featureId,
                  ) ??
                  1
                ) *
                  weight,
              0,
            ),
          )
      }

      if (baselineSkinState.scoring_execution?.calibration_version === CALIBRATION_VERSION_V36) {
        const ranges = ['best_case', 'expected', 'conservative']
        const cores = Object.fromEntries(ranges.map(r => [r, predictedCoreForCalibration(baselineSkinState, features, horizonId, r)]))
        const calibrated = core => deriveCalibratedParameterV36(parameterId, core, definition, CORE_FEATURE_FORMULAS_V2)
        const samples = ranges.map(r => calibrated(cores[r]))
        burdenByRange.expected = samples[1].internal_burden_score_1_to_100
        if (parameterId === 'skin_sebum') {
          // Oil balance is nonlinear. Enumerate oil/dryness corners instead of
          // assuming that less oil is always the best case.
          for (const oil of ranges) for (const dry of ranges) {
            samples.push(calibrated({...cores.expected, oiliness: cores[oil].oiliness, visual_dehydration: cores[dry].visual_dehydration}))
          }
          const oilStates=samples.map(p=>p.calibration.oil_state_0_to_1)
          if (Math.min(...oilStates)<=.5 && Math.max(...oilStates)>=.5) {
            samples.push({internal_burden_score_1_to_100: 2}) // target lies inside the uncertainty envelope; capped health 99
          }
        }
        burdenByRange.best_case = Math.min(...samples.map(p=>p.internal_burden_score_1_to_100))
        burdenByRange.conservative = Math.max(...samples.map(p=>p.internal_burden_score_1_to_100))
        parameter.calibration_version = CALIBRATION_VERSION_V36
      }

      const display = Object.fromEntries(
        Object.entries(burdenByRange).map(
          ([rangeField, burden]) => [
            rangeField,
            definition.display_polarity ===
            'higher_is_better'
              ? 101 - burden
              : burden,
          ],
        ),
      )

      parameter.horizons[horizonId] = {
        predicted_internal_burden_score_1_to_100:
          burdenByRange,
        predicted_display_score_1_to_100:
          display,
      }
    }

    result[parameterId] = parameter
  }

  result.skin_type = {
    parameter_id: 'skin_type',
    display_polarity: 'label_only',
    baseline_label:
      baselineSkinState.skin_type?.label ??
      baselineSkinState
        .derived_report_parameters?.skin_type
        ?.display_label ??
      null,
    prediction_policy:
      'Skin type label is not changed by a single-session outcome prediction.',
  }

  return result
}

function horizonSummary(features, horizonId) {
  const featureChanges = Object.entries(
    features,
  )
    .map(([featureId, feature]) => {
      const horizon =
        feature.global_prediction.horizons[
          horizonId
        ]
      return {
        feature_id: featureId,
        expected_improvement_points:
          horizon.predicted_net_change_points
            .expected_improvement,
        predicted_score_1_to_100:
          horizon.predicted_burden_score_1_to_100
            .expected,
        transient_adjustment_points:
          horizon
            .transient_burden_adjustment_points
            .expected,
        interpretation:
          horizon.interpretation,
      }
    })
    .sort(
      (a, b) =>
        b.expected_improvement_points -
          a.expected_improvement_points ||
        a.feature_id.localeCompare(b.feature_id),
    )

  return {
    horizon_id: horizonId,
    label:
      OUTCOME_HORIZONS_V2.find(
        (item) => item.id === horizonId,
      )?.label ?? horizonId,
    top_expected_visible_improvements:
      featureChanges
        .filter(
          (item) =>
            item.expected_improvement_points >=
            1.5,
        )
        .slice(0, 6),
    temporary_effects_to_explain:
      featureChanges
        .filter(
          (item) =>
            item.transient_adjustment_points >=
            1.5,
        )
        .slice(0, 6),
    all_feature_changes: featureChanges,
  }
}

function buildPrediction({
  baselineSkinState,
  optimizerResult: rawOptimizerResult,
  predictionType,
  executionRecord = null,
}) {
  if (!baselineSkinState?.core_features) {
    throw new Error(
      'Baseline Skin State V2 is required.',
    )
  }

  const optimizerResult =
    normaliseOptimizerResult(
      rawOptimizerResult,
    )
  const deliveredDose =
    predictionType ===
    'delivered_treatment'
      ? calculateDeliveredTreatmentDoseV2({
          optimizerResult,
          executionRecord,
        })
      : null

  const features = {}

  for (const featureId of CORE_FEATURE_IDS) {
    const zonePredictions = {}
    const baselineZones =
      baselineSkinState.core_features?.[
        featureId
      ]?.zone_scores_1_to_100 ?? {}

    for (const zoneId of Object.keys(
      baselineZones,
    )) {
      if (!FACE_ZONE_IDS.includes(zoneId)) {
        continue
      }
      const prediction = zonePrediction({
        baselineSkinState,
        optimizerResult,
        featureId,
        zoneId,
        predictionType,
        deliveredDose,
      })
      if (prediction) {
        zonePredictions[zoneId] =
          prediction
      }
    }

    features[featureId] = {
      feature_id: featureId,
      score_direction:
        SCORE_DIRECTION_V2,
      global_prediction:
        aggregateGlobalPrediction({
          baselineSkinState,
          featureId,
          zonePredictions,
        }),
      zone_predictions:
        zonePredictions,
      dominant_predicted_change_zones:
        Object.values(zonePredictions)
          .map((zone) => ({
            zone_id: zone.zone_id,
            immediate_expected_improvement:
              zone.horizons.immediate_post
                .predicted_net_change_points
                .expected_improvement,
            day_7_expected_improvement:
              zone.horizons.day_7
                .predicted_net_change_points
                .expected_improvement,
            day_28_expected_improvement:
              zone.horizons.day_28
                .predicted_net_change_points
                .expected_improvement,
          }))
          .sort(
            (a, b) =>
              b.day_7_expected_improvement -
                a.day_7_expected_improvement ||
              a.zone_id.localeCompare(
                b.zone_id,
              ),
          )
          .slice(0, 6),
    }
  }

  const prediction = {
    schema_version:
      PREDICTED_OUTCOME_SCHEMA_VERSION,
    engine_version:
      PREDICTED_OUTCOME_ENGINE_VERSION,
    rules_version:
      PREDICTED_OUTCOME_RULES_VERSION,
    prediction_type:
      predictionType,
    scan_id:
      baselineSkinState.scan?.scan_id ??
      null,
    session_id:
      executionRecord?.session_id ??
      null,
    treatment_mode:
      optimizerResult.plan
        .treatment_mode ??
      optimizerResult.input_summary
        ?.treatment_mode ??
      null,
    score_direction:
      SCORE_DIRECTION_V2,
    score_change_policy: {
      immediate_visible_changes_change_the_score:
        true,
      hydration_related_fine_line_change_is_scored:
        true,
      congestion_or_hydration_related_pore_visibility_change_is_scored:
        true,
      permanence_is_not_required_for_a_visible_score_change:
        true,
      persistence_is_shown_through_separate_time_horizons:
        true,
    },
    horizons:
      OUTCOME_HORIZONS_V2,
    features,
    predicted_report_parameters:
      deriveReportParameters({
        baselineSkinState,
        features,
      }),
    patient_timeline_summary:
      Object.fromEntries(
        OUTCOME_HORIZON_IDS_V2.map(
          (horizonId) => [
            horizonId,
            horizonSummary(
              features,
              horizonId,
            ),
          ],
        ),
      ),
    delivered_treatment_dose:
      deliveredDose,
    assumptions_and_boundaries: [
      'Scores describe visible and scan-detectable skin appearance at each time point.',
      'Immediate hydration-related softening of fine lines is a real score change and is included.',
      'Immediate reduction in congestion-related pore visibility is a real score change and is included.',
      'The 48-hour, 1-week and 3–4-week horizons show whether the visible benefit settles, develops or fades.',
      'Temporary redness, dryness, swelling or pigment darkening is added separately rather than hidden.',
      'Ranges are deterministic engineering priors and require calibration against doctor-reviewed clinic outcomes.',
      predictionType ===
      'pre_session_plan'
        ? 'This prediction assumes the selected treatment is delivered as planned.'
        : 'This prediction is adjusted to what was recorded as actually delivered.',
    ],
    downstream_handoff: {
      client_outcome_report_ready: true,
      reassessment_comparison_ready: true,
      compare_actual_scan_against:
        'delivered_treatment_prediction',
      actual_post_scan_horizons_supported:
        OUTCOME_HORIZON_IDS_V2,
    },
  }

  const validation =
    validatePredictedOutcomeV2(
      prediction,
    )
  if (!validation.valid) {
    throw new Error(
      `Predicted outcome validation failed: ${validation.errors.join(' ')}`,
    )
  }

  return {
    ...prediction,
    validation,
  }
}

export function buildPreSessionPredictionV2({
  baselineSkinState,
  optimizerResult,
} = {}) {
  return buildPrediction({
    baselineSkinState,
    optimizerResult,
    predictionType:
      'pre_session_plan',
  })
}

export function buildDeliveredTreatmentPredictionV2({
  baselineSkinState,
  optimizerResult,
  executionRecord,
} = {}) {
  return buildPrediction({
    baselineSkinState,
    optimizerResult,
    predictionType:
      'delivered_treatment',
    executionRecord,
  })
}
