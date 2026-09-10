import {
  DERIVED_REPORT_FORMULAS_V2,
} from './skinStateScoreMapperV2.js'

export const COURSE_LENGTH_RECOMMENDATION_VERSION =
  'aia_course_length_recommendation_v3.1.0'

export const COURSE_LENGTH_RULES_V3 = Object.freeze({
  minimum_sessions: 5,
  maximum_sessions: 8,
  base_sessions: 5,
  add_one_if: {
    high_burden_parameter_count_at_least: 3,
    high_burden_threshold_1_to_100: 65,
  },
  add_one_if_deeper_or_structural_concern: true,
  add_one_if_multiple_spacing_sensitive_modalities: true,
  override_allowed: true,
  status:
    'transparent_engineering_rule_for_clinic_calibration',
})

const clamp = (value, min, max) =>
  Math.max(min, Math.min(max, Number(value)))

function burdenValues(skinState) {
  return Object.keys(
    DERIVED_REPORT_FORMULAS_V2,
  )
    .map(
      (parameterId) =>
        skinState
          ?.derived_report_parameters?.[
          parameterId
        ]?.internal_burden_score_1_to_100,
    )
    .filter(Number.isFinite)
}

function hasStructuralConcern(skinState) {
  return [
    'visible_laxity',
    'firmness_appearance_loss',
    'underlying_pigment_support',
  ].some(
    (featureId) =>
      Number(
        skinState?.core_features?.[
          featureId
        ]?.global_burden_score_1_to_100 ??
        0,
      ) >= 55,
  )
}

function hasSpacingSensitiveModalities(
  optimizerOrCourse,
) {
  const actions =
    optimizerOrCourse?.plan?.modality_actions ??
    optimizerOrCourse
      ?.current_detailed_block
      ?.detailed_sessions
      ?.flatMap(
        (session) =>
          session.optimiser_plan?.plan
            ?.modality_actions ?? [],
      ) ??
    []
  const categories = new Set(
    actions.map((action) => action.category),
  )
  return [
    'laser',
    'energy',
    'energy_invasive',
    'invasive',
    'chemical_peel',
  ].filter((category) =>
    categories.has(category),
  ).length >= 2
}

export function recommendCourseLengthV3({
  skinState,
  optimizerOrCourse = null,
  doctorOverrideSessions = null,
} = {}) {
  if (!skinState?.core_features) {
    throw new Error(
      'Skin State V2 is required for course-length recommendation.',
    )
  }

  if (
    doctorOverrideSessions !== null
  ) {
    const value = Number(
      doctorOverrideSessions,
    )
    if (
      !Number.isInteger(value) ||
      value <
        COURSE_LENGTH_RULES_V3.minimum_sessions ||
      value >
        COURSE_LENGTH_RULES_V3.maximum_sessions
    ) {
      throw new Error(
        'Doctor override must be an integer from 5 to 8 sessions.',
      )
    }
    return {
      version:
        COURSE_LENGTH_RECOMMENDATION_VERSION,
      recommended_sessions: value,
      source: 'doctor_override',
      reasons: [
        'Course length supplied as an explicit doctor override.',
      ],
      rule_config:
        COURSE_LENGTH_RULES_V3,
    }
  }

  const values = burdenValues(skinState)
  const highCount = values.filter(
    (score) =>
      score >=
      COURSE_LENGTH_RULES_V3
        .add_one_if
        .high_burden_threshold_1_to_100,
  ).length
  const meanBurden =
    values.length
      ? values.reduce(
          (sum, value) => sum + value,
          0,
        ) / values.length
      : 0

  let sessions =
    COURSE_LENGTH_RULES_V3.base_sessions
  const reasons = [
    'Base corrective plan length: 5 sessions.',
  ]

  if (
    highCount >=
      COURSE_LENGTH_RULES_V3
        .add_one_if
        .high_burden_parameter_count_at_least ||
    meanBurden >= 58
  ) {
    sessions += 1
    reasons.push(
      'Added one session because several report parameters have high burden or the overall burden is elevated.',
    )
  }

  if (hasStructuralConcern(skinState)) {
    sessions += 1
    reasons.push(
      'Added one session for deeper pigment, firmness or laxity work that generally needs staged response.',
    )
  }

  if (
    hasSpacingSensitiveModalities(
      optimizerOrCourse,
    )
  ) {
    sessions += 1
    reasons.push(
      'Added one session because the selected correction mix includes multiple spacing-sensitive modality categories.',
    )
  }

  sessions = clamp(
    sessions,
    COURSE_LENGTH_RULES_V3.minimum_sessions,
    COURSE_LENGTH_RULES_V3.maximum_sessions,
  )

  return {
    version:
      COURSE_LENGTH_RECOMMENDATION_VERSION,
    recommended_sessions: sessions,
    source:
      'deterministic_course_length_engine',
    mean_report_parameter_burden:
      Number(meanBurden.toFixed(1)),
    high_burden_parameter_count:
      highCount,
    structural_or_deeper_concern:
      hasStructuralConcern(skinState),
    spacing_sensitive_mix:
      hasSpacingSensitiveModalities(
        optimizerOrCourse,
      ),
    reasons,
    rule_config:
      COURSE_LENGTH_RULES_V3,
  }
}
