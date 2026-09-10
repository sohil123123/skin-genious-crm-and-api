export const PREDICTED_OUTCOME_RULES_VERSION =
  'aia_predicted_outcome_rules_v2.0.0'

/**
 * These are deterministic engineering priors, not clinically validated
 * promises. They are deliberately isolated so they can be calibrated against
 * doctor-reviewed Jaipur before/after outcomes without changing the engine.
 */

export const HORIZON_POINT_CAPS_V2 = Object.freeze({
  immediate_post: 30,
  hours_48: 34,
  day_7: 34,
  day_28: 30,
})

export const INTENSITY_RESPONSE_MULTIPLIER_V2 =
  Object.freeze({
    none: 0,
    very_low: 0.42,
    low: 0.58,
    low_medium: 0.72,
    medium: 0.84,
    medium_high: 0.93,
    high: 1,
  })

export const ROLE_RESPONSE_MULTIPLIER_V2 =
  Object.freeze({
    direct: 1,
    secondary: 0.74,
    supportive: 0.56,
    preventive: 0.28,
    conditional: 0.55,
    limited: 0.38,
    neutral: 0,
    avoid: 0,
    none: 0,
  })

export const RESPONSE_CONFIDENCE_MULTIPLIER_V2 =
  Object.freeze({
    high: 1,
    medium: 0.86,
    low: 0.66,
  })

export const EVIDENCE_TIER_MULTIPLIER_V2 =
  Object.freeze({
    A: 1,
    B: 0.92,
    C: 0.8,
    D: 0.65,
  })

export const SCORE_RELIABILITY_MULTIPLIER_V2 =
  Object.freeze({
    high: 1,
    medium: 0.84,
    low: 0.62,
    unreliable: 0.42,
  })

/**
 * The source response library has immediate strength, course strength and a
 * broad onset label. These curves translate that data into four patient-facing
 * time points.
 *
 * A visible immediate response is allowed to move the score immediately.
 * The later horizons show whether the benefit settles, grows or fades.
 */
export const ONSET_HORIZON_BLEND_V2 = Object.freeze({
  immediate: {
    immediate_post: { immediate: 1, course: 0.08 },
    hours_48: { immediate: 0.9, course: 0.2 },
    day_7: { immediate: 0.68, course: 0.28 },
    day_28: { immediate: 0.28, course: 0.38 },
  },
  immediate_to_days: {
    immediate_post: { immediate: 0.92, course: 0.08 },
    hours_48: { immediate: 1, course: 0.28 },
    day_7: { immediate: 0.78, course: 0.45 },
    day_28: { immediate: 0.34, course: 0.48 },
  },
  immediate_to_weeks: {
    immediate_post: { immediate: 0.84, course: 0.08 },
    hours_48: { immediate: 0.92, course: 0.32 },
    day_7: { immediate: 0.72, course: 0.68 },
    day_28: { immediate: 0.3, course: 0.92 },
  },
  days: {
    immediate_post: { immediate: 0.35, course: 0.04 },
    hours_48: { immediate: 0.82, course: 0.22 },
    day_7: { immediate: 0.9, course: 0.5 },
    day_28: { immediate: 0.36, course: 0.56 },
  },
  days_to_weeks: {
    immediate_post: { immediate: 0.28, course: 0.04 },
    hours_48: { immediate: 0.58, course: 0.2 },
    day_7: { immediate: 0.82, course: 0.62 },
    day_28: { immediate: 0.38, course: 1 },
  },
  weeks: {
    immediate_post: { immediate: 0.12, course: 0.02 },
    hours_48: { immediate: 0.22, course: 0.1 },
    day_7: { immediate: 0.42, course: 0.38 },
    day_28: { immediate: 0.28, course: 1 },
  },
  weeks_to_months: {
    immediate_post: { immediate: 0.08, course: 0.01 },
    hours_48: { immediate: 0.12, course: 0.06 },
    day_7: { immediate: 0.24, course: 0.24 },
    day_28: { immediate: 0.22, course: 0.78 },
  },
  months: {
    immediate_post: { immediate: 0.04, course: 0 },
    hours_48: { immediate: 0.06, course: 0.03 },
    day_7: { immediate: 0.12, course: 0.12 },
    day_28: { immediate: 0.16, course: 0.5 },
  },
  none: {
    immediate_post: { immediate: 0, course: 0 },
    hours_48: { immediate: 0, course: 0 },
    day_7: { immediate: 0, course: 0 },
    day_28: { immediate: 0, course: 0 },
  },
})

/**
 * Feature-specific persistence modifies the broad onset curve.
 * Scores here describe visible burden, so hydration-related fine lines and
 * congestion-related pores may improve immediately and should shift scores.
 * Persistence simply shows how much remains at later horizons.
 */
export const FEATURE_HORIZON_MULTIPLIER_V2 =
  Object.freeze({
    active_inflammatory_acne: {
      immediate_post: 0.72,
      hours_48: 0.9,
      day_7: 1,
      day_28: 0.92,
    },
    comedonal_congestion: {
      immediate_post: 1,
      hours_48: 1,
      day_7: 0.92,
      day_28: 0.62,
    },
    oiliness: {
      immediate_post: 1,
      hours_48: 0.95,
      day_7: 0.86,
      day_28: 0.58,
    },
    erythema_redness: {
      immediate_post: 0.55,
      hours_48: 0.82,
      day_7: 1,
      day_28: 0.72,
    },
    barrier_stress: {
      immediate_post: 0.72,
      hours_48: 0.9,
      day_7: 1,
      day_28: 0.7,
    },
    visual_dehydration: {
      immediate_post: 1,
      hours_48: 0.96,
      day_7: 0.82,
      day_28: 0.48,
    },
    pore_visibility: {
      immediate_post: 1,
      hours_48: 0.98,
      day_7: 0.9,
      day_28: 0.6,
    },
    texture_roughness: {
      immediate_post: 0.92,
      hours_48: 0.96,
      day_7: 1,
      day_28: 0.7,
    },
    visible_pigmentation: {
      immediate_post: 0.5,
      hours_48: 0.72,
      day_7: 0.92,
      day_28: 1,
    },
    underlying_pigment_support: {
      immediate_post: 0.2,
      hours_48: 0.35,
      day_7: 0.7,
      day_28: 1,
    },
    luminosity_loss: {
      immediate_post: 1,
      hours_48: 1,
      day_7: 0.88,
      day_28: 0.58,
    },
    fine_line_visibility: {
      immediate_post: 1,
      hours_48: 0.95,
      day_7: 0.8,
      day_28: 0.48,
    },
    visible_laxity: {
      immediate_post: 0.4,
      hours_48: 0.48,
      day_7: 0.7,
      day_28: 1,
    },
    firmness_appearance_loss: {
      immediate_post: 0.5,
      hours_48: 0.56,
      day_7: 0.76,
      day_28: 1,
    },
    peri_orbital_concern: {
      immediate_post: 1,
      hours_48: 0.9,
      day_7: 0.75,
      day_28: 0.46,
    },
    lip_pigmentation: {
      immediate_post: 0.35,
      hours_48: 0.6,
      day_7: 0.88,
      day_28: 1,
    },
  })

export const MULTI_MODALITY_SATURATION_V2 =
  Object.freeze({
    second_contribution_multiplier: 0.7,
    third_contribution_multiplier: 0.48,
    later_contribution_multiplier: 0.3,
    maximum_single_session_reduction_fraction: 0.46,
  })

export const PREDICTION_RANGE_WIDTH_V2 =
  Object.freeze({
    high: { lower: 0.8, upper: 1.18 },
    medium_high: { lower: 0.72, upper: 1.26 },
    medium: { lower: 0.62, upper: 1.36 },
    low: { lower: 0.45, upper: 1.5 },
    not_reliably_predictable: {
      lower: 0,
      upper: 1.65,
    },
  })

/**
 * Short-lived treatment effects are added to the burden score, not hidden.
 * They are reported separately from therapeutic improvement.
 */
export const TRANSIENT_EFFECT_PROFILES_V2 =
  Object.freeze({
    laser: {
      erythema_redness: {
        immediate_post: 6,
        hours_48: 2,
        day_7: 0,
        day_28: 0,
      },
      barrier_stress: {
        immediate_post: 4,
        hours_48: 2,
        day_7: 0,
        day_28: 0,
      },
    },
    laser_special_protocol: {
      erythema_redness: {
        immediate_post: 7,
        hours_48: 3,
        day_7: 0,
        day_28: 0,
      },
      barrier_stress: {
        immediate_post: 5,
        hours_48: 2,
        day_7: 0,
        day_28: 0,
      },
    },
    chemical_peel: {
      erythema_redness: {
        immediate_post: 7,
        hours_48: 3,
        day_7: 0,
        day_28: 0,
      },
      barrier_stress: {
        immediate_post: 6,
        hours_48: 4,
        day_7: 1,
        day_28: 0,
      },
      visual_dehydration: {
        immediate_post: 3,
        hours_48: 5,
        day_7: 1,
        day_28: 0,
      },
      texture_roughness: {
        immediate_post: 0,
        hours_48: 3,
        day_7: 1,
        day_28: 0,
      },
    },
    invasive: {
      erythema_redness: {
        immediate_post: 10,
        hours_48: 5,
        day_7: 1,
        day_28: 0,
      },
      barrier_stress: {
        immediate_post: 8,
        hours_48: 5,
        day_7: 1,
        day_28: 0,
      },
      visual_dehydration: {
        immediate_post: 2,
        hours_48: 3,
        day_7: 1,
        day_28: 0,
      },
    },
    energy_invasive: {
      erythema_redness: {
        immediate_post: 11,
        hours_48: 6,
        day_7: 1,
        day_28: 0,
      },
      barrier_stress: {
        immediate_post: 9,
        hours_48: 5,
        day_7: 1,
        day_28: 0,
      },
    },
    energy: {
      erythema_redness: {
        immediate_post: 4,
        hours_48: 1,
        day_7: 0,
        day_28: 0,
      },
    },
    mechanical_exfoliation: {
      erythema_redness: {
        immediate_post: 3,
        hours_48: 1,
        day_7: 0,
        day_28: 0,
      },
      barrier_stress: {
        immediate_post: 2,
        hours_48: 1,
        day_7: 0,
        day_28: 0,
      },
    },
    hydradermabrasion: {
      erythema_redness: {
        immediate_post: 2,
        hours_48: 0,
        day_7: 0,
        day_28: 0,
      },
    },
    hydrafacial_probe: {
      erythema_redness: {
        immediate_post: 2,
        hours_48: 0,
        day_7: 0,
        day_28: 0,
      },
    },
    manual: {
      erythema_redness: {
        immediate_post: 4,
        hours_48: 1,
        day_7: 0,
        day_28: 0,
      },
    },
  })

export const MODALITY_TRANSIENT_OVERRIDES_V2 =
  Object.freeze({
    q_switch_1064: {
      visible_pigmentation: {
        immediate_post: 2,
        hours_48: 2,
        day_7: 0,
        day_28: 0,
      },
    },
    q_switch_532: {
      visible_pigmentation: {
        immediate_post: 4,
        hours_48: 3,
        day_7: 1,
        day_28: 0,
      },
    },
    carbon_facial: {
      visual_dehydration: {
        immediate_post: 2,
        hours_48: 1,
        day_7: 0,
        day_28: 0,
      },
    },
    manual_extraction: {
      erythema_redness: {
        immediate_post: 5,
        hours_48: 1,
        day_7: 0,
        day_28: 0,
      },
    },
  })

export const DELIVERED_DOSE_DEFAULTS_V2 =
  Object.freeze({
    completed_without_explicit_dose: 0.92,
    completed_with_endpoint_reached: 1,
    partial_endpoint: 0.78,
    stopped_for_safety_ceiling: 0.58,
    skipped: 0,
    not_started: 0,
    in_progress: 0,
    missing_execution_step: 0,
  })

export const CONFIDENCE_SCORE_THRESHOLDS_V2 =
  Object.freeze({
    high: 0.86,
    medium_high: 0.72,
    medium: 0.56,
    low: 0.35,
  })
