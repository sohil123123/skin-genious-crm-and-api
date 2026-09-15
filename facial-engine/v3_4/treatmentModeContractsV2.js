import {
  getClinicStepDurationRuleV3_4,
} from './clinicStepDurationRulesV3_4.js'

export const TREATMENT_MODE_CONTRACTS_VERSION =
  'aia_treatment_mode_contracts_v3.9.0'

export const TREATMENT_MODE_IDS = ['single', 'express', 'multiple']

const FINAL_SKIN_PROTECTION_DURATION_MINUTES =
  getClinicStepDurationRuleV3_4(
    'final_serum_moisturizer_sunscreen',
  ).min_minutes


export const MANDATORY_SESSION_ACTIONS_V2_8 = Object.freeze({
  lymphatic_drainage: {
    modality_id: 'lymphatic_drainage',
    required_in_every_session: true,
    minimum_duration_minutes: 5,
    maximum_duration_minutes: 15,
    omission_policy:
      'block_session_release_if_missing',
    purpose:
      'Mandatory face massage and lymphatic drainage step retained from the clinic workflow.',
  },
  final_skin_protection: {
    modality_id:
      'final_serum_moisturizer_sunscreen',
    required_in_every_session: true,
    fixed_duration_minutes:
      FINAL_SKIN_PROTECTION_DURATION_MINUTES,
    omission_policy:
      'block_session_release_if_missing',
  },
})

export const MINIMUM_SESSION_DURATION_COMPLETION_POLICY_V2_8 =
  Object.freeze({
    applies_to: ['single', 'express', 'multiple'],
    minimum_minutes: 60,
    selection_order: [
      'highest_residual_score_improving_eligible_step',
      'extend_mandatory_lymphatic_drainage_within_5_to_15_minutes',
    ],
    prohibit_high_risk_modality_time_padding: true,
    prohibit_unselected_step_insertion_by_session_compiler: true,
    block_release_if_safe_minimum_cannot_be_reached: true,
  })

export const TREATMENT_MODE_CONTRACTS_V2 = Object.freeze({
  single: {
    id: 'single',
    label: 'Single Session',
    business_purpose:
      'Best possible visible and clinically appropriate outcome for the client today.',
    optimizer_objective_preset: 'immediate',
    duration: {
      hard_minimum_minutes: 60,
      target_minutes: 70,
      hard_maximum_minutes: 75,
      fixed_final_skin_protection_minutes:
        FINAL_SKIN_PROTECTION_DURATION_MINUTES,
    },
    selection: {
      exactly_one_hero: false,
      allow_multiple_heroes: true,
      maximum_corrective_actions: 6,
      maximum_supportive_actions: 4,
      allow_secondary_actions: true,
      hero_relative_utility_floor: 0.56,
      hero_minimum_selection_utility: 0.04,
      hero_must_have_meaningful_direct_benefit: true,
      allow_modular_hydrafacial_probes: true,
      allow_optional_hydrafacial_bundle: true,
    },

    mandatory_session_actions:
      MANDATORY_SESSION_ACTIONS_V2_8,
    minimum_duration_completion_policy:
      MINIMUM_SESSION_DURATION_COMPLETION_POLICY_V2_8,
    output: {
      detailed_sessions_now: 1,
      reassessment_gate: false,
    },
  },

  express: {
    id: 'express',
    label: 'Express',
    business_purpose:
      'Best possible visible outcome in 40 minutes using one carefully chosen hero.',
    optimizer_objective_preset: 'event_ready',
    duration: {
      hard_minimum_minutes: 35,
      target_minutes: 40,
      hard_maximum_minutes: 45,
      fixed_final_skin_protection_minutes:
        FINAL_SKIN_PROTECTION_DURATION_MINUTES,
    },
    selection: {
      exactly_one_hero: true,
      allow_multiple_heroes: false,
      maximum_corrective_actions: 1,
      maximum_supportive_actions: 2,
      allow_secondary_actions: false,
      hero_relative_utility_floor: 1,
      hero_minimum_selection_utility: 0,
      hero_must_have_meaningful_direct_benefit: true,
      allow_modular_hydrafacial_probes: true,
      allow_optional_hydrafacial_bundle: true,
    },

    mandatory_session_actions:
      MANDATORY_SESSION_ACTIONS_V2_8,
    minimum_duration_completion_policy: { ...MINIMUM_SESSION_DURATION_COMPLETION_POLICY_V2_8, minimum_minutes: 40 },
    output: {
      detailed_sessions_now: 1,
      reassessment_gate: false,
    },
  },

  multiple: {
    id: 'multiple',
    label: 'Multiple-Session Plan',
    business_purpose:
      'Best achievable outcome over a dynamic course of up to eight sessions.',
    optimizer_objective_preset: 'course',
    duration: {
      hard_minimum_minutes: 60,
      target_minutes: 70,
      hard_maximum_minutes: 75,
      fixed_final_skin_protection_minutes:
        FINAL_SKIN_PROTECTION_DURATION_MINUTES,
    },
    selection: {
      exactly_one_hero: false,
      allow_multiple_heroes: true,
      maximum_corrective_actions: 6,
      maximum_supportive_actions: 4,
      allow_secondary_actions: true,
      hero_relative_utility_floor: 0.56,
      hero_minimum_selection_utility: 0.04,
      hero_must_have_meaningful_direct_benefit: true,
      allow_modular_hydrafacial_probes: true,
      allow_optional_hydrafacial_bundle: true,
    },
    course: {
      maximum_sessions: 8,
      detailed_block_size: 2,
      reassessment_after_every_sessions: 2,
      generate_future_detailed_steps_before_reassessment: false,
      stop_early_when_goals_met: true,
      repeated_modality_multiplier: 0.82,
      repeated_hero_multiplier: 0.72,
      allow_repeat_when_still_best: true,
    },

    mandatory_session_actions:
      MANDATORY_SESSION_ACTIONS_V2_8,
    minimum_duration_completion_policy:
      { ...MINIMUM_SESSION_DURATION_COMPLETION_POLICY_V2_8, minimum_minutes: 60 },
    output: {
      detailed_sessions_now: 2,
      reassessment_gate: true,
    },
  },
})

export function getTreatmentModeContractV2(treatmentMode) {
  const normalized = String(treatmentMode ?? 'single')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_')

  const aliases = {
    single_session: 'single',
    singlesession: 'single',
    express_session: 'express',
    expresssession: 'express',
    plan: 'multiple',
    multi: 'multiple',
    multiple_session: 'multiple',
    multiple_sessions: 'multiple',
  }

  const id = aliases[normalized] ?? normalized
  const contract = TREATMENT_MODE_CONTRACTS_V2[id]
  if (!contract) {
    throw new Error(
      `Unknown treatment mode "${treatmentMode}". Expected single, express or multiple.`,
    )
  }
  return contract
}

export function applyTreatmentModeContractV2(
  treatmentMode,
  sessionConstraints = {},
) {
  const contract = getTreatmentModeContractV2(treatmentMode)

  const callerMaximum = Number(sessionConstraints.hard_maximum_minutes)
  if (
    Number.isFinite(callerMaximum) &&
    callerMaximum > contract.duration.hard_maximum_minutes
  ) {
    throw new Error(
      `${contract.id} mode cannot exceed ${contract.duration.hard_maximum_minutes} minutes.`,
    )
  }

  const settings = {
    ...sessionConstraints,
    session_target_minutes: contract.duration.target_minutes,
    session_tolerance_minutes: 0,
    hard_minimum_minutes: contract.duration.hard_minimum_minutes,
    hard_maximum_minutes: contract.duration.hard_maximum_minutes,
    mandatory_final_minutes:
      contract.duration.fixed_final_skin_protection_minutes,
    mandatory_session_action_ids: [
      MANDATORY_SESSION_ACTIONS_V2_8.lymphatic_drainage.modality_id,
      MANDATORY_SESSION_ACTIONS_V2_8.final_skin_protection.modality_id,
    ],
    always_include_lymphatic_drainage: true,
    lymphatic_drainage_minimum_minutes:
      MANDATORY_SESSION_ACTIONS_V2_8.lymphatic_drainage
        .minimum_duration_minutes,
    lymphatic_drainage_maximum_minutes:
      MANDATORY_SESSION_ACTIONS_V2_8.lymphatic_drainage
        .maximum_duration_minutes,
    mandatory_reserved_minutes:
      MANDATORY_SESSION_ACTIONS_V2_8.lymphatic_drainage
        .minimum_duration_minutes +
      MANDATORY_SESSION_ACTIONS_V2_8.final_skin_protection
        .fixed_duration_minutes,
    minimum_duration_completion_policy:
      contract.minimum_duration_completion_policy ?? null,
    maximum_corrective_modalities:
      contract.selection.maximum_corrective_actions,
    maximum_supportive_modalities:
      contract.selection.maximum_supportive_actions,
    allow_secondary_actions:
      contract.selection.allow_secondary_actions,
    allow_multiple_heroes:
      contract.selection.allow_multiple_heroes,
    exactly_one_hero:
      contract.selection.exactly_one_hero,
    hero_relative_utility_floor:
      contract.selection.hero_relative_utility_floor,
    hero_minimum_selection_utility:
      contract.selection.hero_minimum_selection_utility,
    hero_must_have_meaningful_direct_benefit:
      contract.selection.hero_must_have_meaningful_direct_benefit,
    allow_component_only_selection:
      contract.selection.allow_modular_hydrafacial_probes,
    allow_optional_hydrafacial_bundle:
      contract.selection.allow_optional_hydrafacial_bundle,
    treatment_mode: contract.id,
  }

  return { contract, settings }
}

export function validateTreatmentModePlanV2(planResult) {
  const treatmentMode =
    planResult?.input_summary?.treatment_mode ??
    planResult?.plan?.treatment_mode
  const contract = getTreatmentModeContractV2(treatmentMode)
  const errors = []

  const duration =
    Number(planResult?.plan?.estimated_total_duration_minutes) || 0
  const heroes = planResult?.plan?.hero_actions ?? []

  if (duration > contract.duration.hard_maximum_minutes) {
    errors.push(
      `Duration ${duration} exceeds ${contract.duration.hard_maximum_minutes}.`,
    )
  }
  if (duration < contract.duration.hard_minimum_minutes) {
    errors.push(
      `Duration ${duration} is below ${contract.duration.hard_minimum_minutes}.`,
    )
  }

  const mandatoryActionIds = new Set(
    (planResult?.plan?.mandatory_actions ?? []).map(
      (action) => action.modality_id,
    ),
  )
  for (const mandatoryAction of Object.values(
    MANDATORY_SESSION_ACTIONS_V2_8,
  )) {
    if (!mandatoryActionIds.has(mandatoryAction.modality_id)) {
      errors.push(
        `Mandatory action ${mandatoryAction.modality_id} is missing.`,
      )
    }
  }
  if (contract.selection.exactly_one_hero && heroes.length !== 1) {
    errors.push(`Express mode requires exactly one hero; found ${heroes.length}.`)
  }
  if (
    !contract.selection.allow_secondary_actions &&
    (planResult?.plan?.secondary_actions?.length ?? 0) > 0
  ) {
    errors.push('This treatment mode does not permit secondary corrective actions.')
  }

  return {
    valid: errors.length === 0,
    treatment_mode: contract.id,
    errors,
  }
}
