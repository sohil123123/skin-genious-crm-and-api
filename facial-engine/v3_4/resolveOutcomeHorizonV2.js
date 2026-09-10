import {
  OUTCOME_HORIZON_IDS_V2,
} from './predictedOutcomeV2.schema.js'

export const OUTCOME_HORIZON_RESOLVER_VERSION =
  'aia_outcome_horizon_resolver_v2.0.0'

export const OUTCOME_HORIZON_TIMING_V2 =
  Object.freeze({
    immediate_post: {
      target_hours: 0,
      exact_tolerance_hours: 6,
      usable_tolerance_hours: 24,
    },
    hours_48: {
      target_hours: 48,
      exact_tolerance_hours: 12,
      usable_tolerance_hours: 36,
    },
    day_7: {
      target_hours: 168,
      exact_tolerance_hours: 36,
      usable_tolerance_hours: 96,
    },
    day_28: {
      target_hours: 672,
      exact_tolerance_hours: 120,
      usable_tolerance_hours: 336,
    },
  })

function parseDate(value, fieldName) {
  if (!value) return null
  const timestamp = Date.parse(value)
  if (!Number.isFinite(timestamp)) {
    throw new Error(
      `${fieldName} must be a valid ISO date-time.`,
    )
  }
  return timestamp
}

function timingFit(distance, definition) {
  if (distance <= definition.exact_tolerance_hours) {
    return 'strong'
  }
  if (distance <= definition.usable_tolerance_hours) {
    return 'usable'
  }
  return 'approximate'
}

export function resolveOutcomeHorizonV2({
  explicitHorizonId = null,
  treatmentCompletedAtIso = null,
  scanCapturedAtIso = null,
  fallbackHorizonId = null,
} = {}) {
  if (explicitHorizonId) {
    if (
      !OUTCOME_HORIZON_IDS_V2.includes(
        explicitHorizonId,
      )
    ) {
      throw new Error(
        `Unknown outcome horizon: ${explicitHorizonId}`,
      )
    }
    return {
      version:
        OUTCOME_HORIZON_RESOLVER_VERSION,
      horizon_id: explicitHorizonId,
      resolution_method: 'explicit',
      hours_after_treatment: null,
      timing_distance_hours: null,
      timing_fit: 'explicit',
      comparison_supported: true,
    }
  }

  const treatmentTimestamp = parseDate(
    treatmentCompletedAtIso,
    'treatmentCompletedAtIso',
  )
  const scanTimestamp = parseDate(
    scanCapturedAtIso,
    'scanCapturedAtIso',
  )

  if (
    treatmentTimestamp !== null &&
    scanTimestamp !== null
  ) {
    const hoursAfterTreatment =
      (scanTimestamp - treatmentTimestamp) /
      (1000 * 60 * 60)

    if (hoursAfterTreatment < -1) {
      throw new Error(
        'Post scan cannot occur before treatment completion.',
      )
    }

    const candidates =
      OUTCOME_HORIZON_IDS_V2.map(
        (horizonId) => {
          const definition =
            OUTCOME_HORIZON_TIMING_V2[
              horizonId
            ]
          const distance = Math.abs(
            hoursAfterTreatment -
              definition.target_hours,
          )
          return {
            horizon_id: horizonId,
            distance,
            normalized_distance:
              distance /
              Math.max(
                1,
                definition
                  .usable_tolerance_hours,
              ),
            definition,
          }
        },
      ).sort(
        (a, b) =>
          a.normalized_distance -
            b.normalized_distance ||
          a.distance - b.distance,
      )

    const selected = candidates[0]
    const fit = timingFit(
      selected.distance,
      selected.definition,
    )

    return {
      version:
        OUTCOME_HORIZON_RESOLVER_VERSION,
      horizon_id: selected.horizon_id,
      resolution_method:
        'nearest_supported_timepoint',
      hours_after_treatment:
        Number(
          Math.max(
            0,
            hoursAfterTreatment,
          ).toFixed(2),
        ),
      timing_distance_hours: Number(
        selected.distance.toFixed(2),
      ),
      timing_fit: fit,
      comparison_supported:
        fit !== 'approximate',
      caution:
        fit === 'approximate'
          ? 'The scan timing is outside the preferred comparison window. Prediction comparison should be shown as approximate.'
          : null,
    }
  }

  if (fallbackHorizonId) {
    if (
      !OUTCOME_HORIZON_IDS_V2.includes(
        fallbackHorizonId,
      )
    ) {
      throw new Error(
        `Unknown fallback horizon: ${fallbackHorizonId}`,
      )
    }
    return {
      version:
        OUTCOME_HORIZON_RESOLVER_VERSION,
      horizon_id: fallbackHorizonId,
      resolution_method: 'fallback',
      hours_after_treatment: null,
      timing_distance_hours: null,
      timing_fit: 'approximate',
      comparison_supported: false,
      caution:
        'Treatment or scan timestamp was unavailable. The selected horizon is approximate.',
    }
  }

  return {
    version:
      OUTCOME_HORIZON_RESOLVER_VERSION,
    horizon_id: null,
    resolution_method: 'unresolved',
    hours_after_treatment: null,
    timing_distance_hours: null,
    timing_fit: 'unresolved',
    comparison_supported: false,
    caution:
      'A treatment completion time, scan time or explicit horizon is required for prediction comparison.',
  }
}
