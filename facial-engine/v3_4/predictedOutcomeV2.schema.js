import {
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'

export const PREDICTED_OUTCOME_SCHEMA_VERSION =
  'aia_predicted_outcome_v2.0.0'

export const PREDICTION_TYPES_V2 = Object.freeze([
  'pre_session_plan',
  'delivered_treatment',
  'course_projection',
])

export const OUTCOME_HORIZONS_V2 = Object.freeze([
  {
    id: 'immediate_post',
    label: 'Immediately after the session',
    hours_after_treatment: 0,
    patient_meaning:
      'The visible change the client may notice when the session is completed.',
  },
  {
    id: 'hours_48',
    label: 'Around 48 hours',
    hours_after_treatment: 48,
    patient_meaning:
      'The visible result after most short-lived treatment redness, swelling or product residue has begun to settle.',
  },
  {
    id: 'day_7',
    label: 'Around 1 week',
    hours_after_treatment: 168,
    patient_meaning:
      'The settled result expected during the first week.',
  },
  {
    id: 'day_28',
    label: 'Around 3–4 weeks',
    hours_after_treatment: 672,
    patient_meaning:
      'How much of the visible benefit is expected to remain or develop by the usual 3–4 week facial-results window.',
  },
])

export const OUTCOME_HORIZON_IDS_V2 =
  OUTCOME_HORIZONS_V2.map((item) => item.id)

export const PREDICTION_CONFIDENCE_VALUES_V2 =
  Object.freeze([
    'high',
    'medium_high',
    'medium',
    'low',
    'not_reliably_predictable',
  ])

export const SCORE_DIRECTION_V2 =
  'lower_burden_is_better'

export function createEmptyHorizonPredictionV2(
  baselineScore,
) {
  return {
    baseline_burden_score_1_to_100:
      baselineScore,
    predicted_burden_score_1_to_100: {
      best_case: baselineScore,
      expected: baselineScore,
      conservative: baselineScore,
    },
    predicted_net_change_points: {
      best_case_improvement: 0,
      expected_improvement: 0,
      conservative_improvement: 0,
    },
    therapeutic_improvement_points: {
      expected: 0,
    },
    transient_burden_adjustment_points: {
      expected: 0,
      direction:
        'none',
    },
    interpretation: 'no_material_change_predicted',
  }
}

export function validatePredictedOutcomeV2(
  prediction,
) {
  const errors = []

  if (
    prediction?.schema_version !==
    PREDICTED_OUTCOME_SCHEMA_VERSION
  ) {
    errors.push(
      'Invalid predicted-outcome schema version.',
    )
  }
  if (
    !PREDICTION_TYPES_V2.includes(
      prediction?.prediction_type,
    )
  ) {
    errors.push('Invalid prediction type.')
  }
  if (!prediction?.features) {
    errors.push('Prediction features are missing.')
  }

  for (const [featureId, feature] of Object.entries(
    prediction?.features ?? {},
  )) {
    if (!CORE_FEATURE_IDS.includes(featureId)) {
      errors.push(`Unknown feature: ${featureId}`)
      continue
    }

    for (const horizonId of OUTCOME_HORIZON_IDS_V2) {
      const horizon =
        feature.global_prediction?.horizons?.[
          horizonId
        ]
      if (!horizon) {
        errors.push(
          `${featureId} missing global horizon ${horizonId}.`,
        )
        continue
      }
      for (const value of Object.values(
        horizon.predicted_burden_score_1_to_100 ??
          {},
      )) {
        if (
          !Number.isFinite(Number(value)) ||
          Number(value) < 1 ||
          Number(value) > 100
        ) {
          errors.push(
            `${featureId}.${horizonId} contains an invalid predicted score.`,
          )
        }
      }
    }

    for (const [zoneId, zonePrediction] of Object.entries(
      feature.zone_predictions ?? {},
    )) {
      if (!FACE_ZONE_IDS.includes(zoneId)) {
        errors.push(
          `${featureId} has unknown zone ${zoneId}.`,
        )
      }
      for (const horizonId of OUTCOME_HORIZON_IDS_V2) {
        if (!zonePrediction.horizons?.[horizonId]) {
          errors.push(
            `${featureId}.${zoneId} missing ${horizonId}.`,
          )
        }
      }
    }
  }

  return {
    valid: errors.length === 0,
    errors,
  }
}
