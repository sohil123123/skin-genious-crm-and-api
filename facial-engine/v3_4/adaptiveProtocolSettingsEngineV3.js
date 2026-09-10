import {
  MODALITY_EXECUTION_PROTOCOLS_V2,
} from './executionProtocolLibraryV2.js'
import {
  DOCTOR_APPROVED_PROTOCOL_CONSTRAINTS_VERSION,
  DOCTOR_APPROVED_GLOBAL_PROTOCOL_POLICY_V3,
  FACIAL_ENGINE_DISABLED_MODALITY_IDS_V3,
  getDoctorApprovedProtocolEnvelopeV3,
  neutralizerForPeelV3,
} from './doctorApprovedProtocolConstraintsV3.js'
import {
  chemicalPeelContactTimeV3_4,
  getClinicStepDurationRuleV3_4,
  selectClinicDurationMinutesV3_4,
} from './clinicStepDurationRulesV3_4.js'

export const ADAPTIVE_PROTOCOL_SETTINGS_ENGINE_VERSION =
  'aia_adaptive_protocol_settings_engine_v3.4.0'

export const ADAPTIVE_PROTOCOL_RESOLUTION_STATUS_V3 = Object.freeze({
  RELEASE_READY: 'release_ready',
  BLOCKED_SAFETY_OR_ELIGIBILITY: 'blocked_safety_or_eligibility',
  BLOCKED_INVALID_AI_PROPOSAL: 'blocked_invalid_ai_proposal',
  DISABLED_FOR_ENGINE_SCOPE: 'disabled_for_engine_scope',
})

const INTENSITY_POSITION = Object.freeze({
  none: 0.1,
  very_low: 0.18,
  low: 0.3,
  low_medium: 0.42,
  medium: 0.56,
  medium_high: 0.72,
  high: 0.88,
})

const clamp = (value, min, max) =>
  Math.max(min, Math.min(max, Number(value)))

const roundToStep = (value, step) =>
  step ? Math.round(value / step) * step : value

const unique = (values) => [...new Set((values ?? []).filter(Boolean))]

function dominantIntensity(action) {
  const values = Object.values(action?.intensity_by_zone ?? {})
  if (!values.length) {
    return (
      INTENSITY_POSITION[
        action?.eligibility_summary?.maximum_intensity
      ] ?? 0.5
    )
  }
  return (
    values.reduce(
      (sum, value) => sum + (INTENSITY_POSITION[value] ?? 0.5),
      0,
    ) / values.length
  )
}

function burdenPosition(action) {
  const burdens = (action?.target_features ?? [])
    .map(
      (feature) =>
        Number(feature.peak_burden_score_1_to_100) / 100,
    )
    .filter(Number.isFinite)
  if (!burdens.length) return 0.5
  return clamp(
    burdens.reduce((sum, value) => sum + value, 0) / burdens.length,
    0,
    1,
  )
}

function outcomePosition(action) {
  return clamp(
    0.58 * dominantIntensity(action) + 0.42 * burdenPosition(action),
    0,
    1,
  )
}

function zonePosition(action, zoneId) {
  const explicit = action?.intensity_by_zone?.[zoneId]
  if (explicit) return INTENSITY_POSITION[explicit] ?? 0.5
  const zone = action?.zone_delivery?.find((item) => item.zone_id === zoneId)
  const peak = Math.max(
    0,
    ...(zone?.targets ?? []).map(
      (target) => Number(target.burden_score_1_to_100 ?? 0) / 100,
    ),
  )
  return clamp(0.55 * outcomePosition(action) + 0.45 * (peak || 0.5), 0, 1)
}

function safetyBlock(action, regionalTemperaturesC, eligibilitySummary) {
  const status =
    action?.eligibility_summary?.overall_status ??
    eligibilitySummary?.overall_status ??
    'allowed'
  const denialTemperatures = Object.entries(regionalTemperaturesC ?? {})
    .filter(([, value]) => Number(value) >= 36.9)
    .map(([region, value]) => ({ region, temperature_c: Number(value) }))

  const reasons = []
  if (status === 'denied') reasons.push('modality_denied_by_eligibility_engine')
  if (denialTemperatures.length) reasons.push('regional_temperature_denial')

  return {
    blocked: reasons.length > 0,
    eligibility_status: status,
    reasons,
    denial_temperatures: denialTemperatures,
  }
}

function numericFromRange(spec, position) {
  const raw = Number(spec.min) + (Number(spec.max) - Number(spec.min)) * position
  return clamp(
    Number(roundToStep(raw, spec.step).toFixed(4)),
    Number(spec.min),
    Number(spec.max),
  )
}

function generateFromSpec(spec, action, position) {
  if (!spec) return null
  if (spec.type === 'fixed') return structuredClone(spec.value)
  if (spec.type === 'number_range') return numericFromRange(spec, position)
  if (spec.type === 'enum') {
    const values = spec.values ?? []
    if (!values.length) return null
    return values[Math.min(values.length - 1, Math.floor(position * values.length))]
  }
  if (spec.type === 'generated_protocol_reference') {
    return `${spec.prefix ?? `ai_adaptive_${action.modality_id}`}_v1`
  }
  if (spec.type === 'by_zone') {
    return Object.fromEntries(
      (action.selected_zones ?? []).map((zoneId) => [
        zoneId,
        generateFromSpec(spec.value_spec, action, zonePosition(action, zoneId)),
      ]),
    )
  }
  if (spec.type === 'pass_map') {
    const passes = Math.round(Number(spec.min) + (Number(spec.max) - Number(spec.min)) * position)
    return Object.fromEntries(
      (action.selected_zones ?? []).map((zoneId) => [
        zoneId,
        {
          horizontal: clamp(passes, spec.min, spec.max),
          vertical: clamp(passes, spec.min, spec.max),
        },
      ]),
    )
  }
  return null
}

function selectedSolution(action) {
  const priorities = (action.target_features ?? []).map((item) => item.feature_id)
  const mapping = [
    [['active_inflammatory_acne', 'comedonal_congestion', 'oiliness'], 'hydrafacial_serum_sa2'],
    [['visible_pigmentation', 'underlying_pigment_support'], 'tranexamic_acid'],
    [['luminosity_loss'], 'vitamin_c'],
    [['visible_laxity', 'firmness_appearance_loss'], 'lifting'],
    [['fine_line_visibility', 'peri_orbital_concern'], 'pdrn'],
    [['visual_dehydration', 'barrier_stress'], 'hyaluronic_acid'],
  ]
  for (const [features, solution] of mapping) {
    if (features.some((featureId) => priorities.includes(featureId))) return solution
  }
  return 'hyaluronic_acid'
}

function freeAiParameters(action, envelope, position) {
  const fields = new Set(envelope?.free_ai_fields ?? [])
  const result = {}
  const modalityId = action.modality_id

  if (fields.has('duration_minutes')) {
    const clinicRule =
      getClinicStepDurationRuleV3_4(
        action.modality_id,
      )
    result.duration_minutes = clinicRule
      ? selectClinicDurationMinutesV3_4(
          action.modality_id,
          position,
        )
      : action.estimated_duration_minutes ?? null
  }
  if (fields.has('selected_product_or_solution')) {
    result.selected_product_or_solution = selectedSolution(action)
  }
  if (fields.has('clinical_endpoint')) {
    result.clinical_endpoint =
      position >= 0.7
        ? 'clear_targeted_endpoint_without_excess_irritation'
        : 'uniform_tolerated_endpoint'
  }
  if (fields.has('temperature_or_endpoint_monitoring')) {
    result.temperature_or_endpoint_monitoring =
      'continuous comfort and uniform warmth monitoring; stop before focal heat or pain'
  }
  if (fields.has('energy_by_zone')) {
    result.energy_by_zone = Object.fromEntries(
      (action.selected_zones ?? []).map((zoneId) => [
        zoneId,
        position >= 0.7 ? 'higher_within_device_safe_facial_range' : 'moderate_facial_range',
      ]),
    )
  }
  if (fields.has('shot_count_and_vector_map')) {
    result.shot_count_and_vector_map = Object.fromEntries(
      (action.selected_zones ?? []).map((zoneId) => [
        zoneId,
        {
          density: position >= 0.7 ? 'high' : 'moderate',
          vector: 'zone_specific_lifting_vector',
        },
      ]),
    )
  }
  if (fields.has('anatomical_avoid_map')) {
    result.anatomical_avoid_map = {
      treat_only: action.selected_zones ?? [],
      protect: unique([
        ...(action.eligibility_summary?.required_protection ?? []),
        'peri_orbital',
        'lips',
      ]),
    }
  }
  if (fields.has('speed_or_device_preset')) {
    result.speed_or_device_preset = position >= 0.7 ? 'high' : position >= 0.4 ? 'medium' : 'low'
  }
  if (fields.has('sterile_product_if_used')) {
    result.sterile_product_if_used = selectedSolution(action)
  }
  if (fields.has('rf_energy_by_zone')) {
    result.rf_energy_by_zone = Object.fromEntries(
      (action.selected_zones ?? []).map((zoneId) => [
        zoneId,
        position >= 0.7 ? 'higher_facial_setting' : 'moderate_facial_setting',
      ]),
    )
  }
  if (fields.has('insulated_or_noninsulated_tip')) {
    result.insulated_or_noninsulated_tip = 'clinic_available_tip_selected_for_target_depth'
  }
  if (fields.has('contact_time_minutes')) {
    result.contact_time_minutes =
      chemicalPeelContactTimeV3_4(position)
  }
  if (fields.has('in_clinic_contact_time')) {
    result.in_clinic_contact_time =
      chemicalPeelContactTimeV3_4(position)
  }
  if (fields.has('layers')) {
    result.layers = position >= 0.72 ? 2 : 1
  }
  if (fields.has('layers_or_spot_method')) {
    result.layers_or_spot_method =
      (action.selected_zones ?? []).length <= 3 ? 'spot_or_zone_directed_application' : 'single_even_layer'
  }
  if (fields.has('exact_product_formula')) result.exact_product_formula = action.modality_name
  if (fields.has('exact_product_protocol')) result.exact_product_protocol = action.modality_name
  if (fields.has('verified_formula')) result.verified_formula = action.modality_name
  if (fields.has('home_maintenance_protocol')) {
    result.home_maintenance_protocol = 'clinic_homecare_handoff_after_treatment'
  }

  const neutralizer = neutralizerForPeelV3(modalityId)
  if (neutralizer) result.neutralization_method = neutralizer

  return result
}

function validateSpec(value, spec, path, errors) {
  if (!spec) return
  if (spec.type === 'fixed') {
    if (JSON.stringify(value) !== JSON.stringify(spec.value)) {
      errors.push(`${path} must equal ${JSON.stringify(spec.value)}.`)
    }
    return
  }
  if (spec.type === 'number_range') {
    const numeric = Number(value)
    if (!Number.isFinite(numeric) || numeric < spec.min || numeric > spec.max) {
      errors.push(`${path} must be between ${spec.min} and ${spec.max} ${spec.unit}.`)
    }
    return
  }
  if (spec.type === 'enum') {
    if (!(spec.values ?? []).includes(value)) {
      errors.push(`${path} must be one of ${JSON.stringify(spec.values)}.`)
    }
    return
  }
  if (spec.type === 'by_zone') {
    if (!value || typeof value !== 'object') {
      errors.push(`${path} must be a zone map.`)
      return
    }
    for (const [zoneId, zoneValue] of Object.entries(value)) {
      validateSpec(zoneValue, spec.value_spec, `${path}.${zoneId}`, errors)
    }
    return
  }
  if (spec.type === 'pass_map') {
    if (!value || typeof value !== 'object') {
      errors.push(`${path} must be a pass map.`)
      return
    }
    for (const [zoneId, directions] of Object.entries(value)) {
      for (const [direction, passes] of Object.entries(directions ?? {})) {
        if (Number(passes) < spec.min || Number(passes) > spec.max) {
          errors.push(`${path}.${zoneId}.${direction} must be ${spec.min}–${spec.max}.`)
        }
      }
    }
  }
}

export function validateAdaptiveProtocolSettingsV3({ action, proposal } = {}) {
  const errors = []
  const envelope = getDoctorApprovedProtocolEnvelopeV3(action?.modality_id)

  if (!action?.modality_id) errors.push('Selected action is required.')
  if (!envelope) errors.push('No modality envelope exists.')
  if (envelope?.disabled_for_engine_scope) {
    errors.push('Modality is disabled for the general facial engine.')
  }
  if (proposal?.approved !== true) errors.push('Proposal must carry approved=true.')
  if (proposal?.modality_id !== action?.modality_id) errors.push('Modality ID mismatch.')

  const parameters = proposal?.parameters ?? {}
  for (const [field, spec] of Object.entries(envelope?.parameter_constraints ?? {})) {
    if (parameters[field] === undefined || parameters[field] === null) {
      errors.push(`Required constrained parameter ${field} is missing.`)
      continue
    }
    validateSpec(parameters[field], spec, field, errors)
  }

  return {
    valid: errors.length === 0,
    errors,
    modality_id: action?.modality_id ?? null,
    constraint_version: DOCTOR_APPROVED_PROTOCOL_CONSTRAINTS_VERSION,
  }
}

export function resolveAdaptiveProtocolSettingsV3({
  action,
  aiProposal = null,
  skinState = null,
  patientHistory = {},
  regionalTemperaturesC = {},
  eligibilitySummary = null,
} = {}) {
  if (!action?.modality_id) throw new Error('Selected optimizer action is required.')
  if (!MODALITY_EXECUTION_PROTOCOLS_V2[action.modality_id]) {
    throw new Error(`Unknown modality: ${action.modality_id}`)
  }

  if (FACIAL_ENGINE_DISABLED_MODALITY_IDS_V3.includes(action.modality_id)) {
    return {
      approved: false,
      resolution_status:
        ADAPTIVE_PROTOCOL_RESOLUTION_STATUS_V3.DISABLED_FOR_ENGINE_SCOPE,
      modality_id: action.modality_id,
      reason: 'Not part of the general facial engine.',
    }
  }

  const safety = safetyBlock(action, regionalTemperaturesC, eligibilitySummary)
  if (safety.blocked) {
    return {
      approved: false,
      resolution_status:
        ADAPTIVE_PROTOCOL_RESOLUTION_STATUS_V3.BLOCKED_SAFETY_OR_ELIGIBILITY,
      modality_id: action.modality_id,
      safety_summary: safety,
    }
  }

  const envelope = getDoctorApprovedProtocolEnvelopeV3(action.modality_id)
  const position = outcomePosition(action)
  const generatedParameters = Object.fromEntries(
    Object.entries(envelope.parameter_constraints ?? {}).map(([field, spec]) => [
      field,
      generateFromSpec(spec, action, position),
    ]),
  )
  const generatedFree = freeAiParameters(action, envelope, position)

  const proposal = aiProposal
    ? {
        ...aiProposal,
        parameters: {
          ...generatedParameters,
          ...(aiProposal.parameters ?? {}),
        },
        free_ai_parameters: {
          ...generatedFree,
          ...(aiProposal.free_ai_parameters ?? {}),
        },
      }
    : {
        modality_id: action.modality_id,
        approved: true,
        parameter_source: envelope.simple_autorelease
          ? 'simple_step_no_parameter_validation'
          : 'adaptive_engine_selected_within_doctor_constraints',
        parameters: generatedParameters,
        free_ai_parameters: generatedFree,
        zone_rationales: (action.selected_zones ?? []).map((zoneId) => ({
          zone_id: zoneId,
          rationale:
            'Selected from the zone burden, treatment target and permitted intensity for this client.',
        })),
        safety_summary: {
          eligibility_status: safety.eligibility_status,
          constraints_applied: action.eligibility_summary?.triggered_rules ?? [],
          protected_or_skipped_zones:
            action.eligibility_summary?.required_protection ?? [],
        },
      }

  const validation = validateAdaptiveProtocolSettingsV3({ action, proposal })
  if (!validation.valid) {
    return {
      approved: false,
      resolution_status:
        ADAPTIVE_PROTOCOL_RESOLUTION_STATUS_V3.BLOCKED_INVALID_AI_PROPOSAL,
      modality_id: action.modality_id,
      validation,
      proposal,
    }
  }

  return {
    approved: true,
    resolution_status: ADAPTIVE_PROTOCOL_RESOLUTION_STATUS_V3.RELEASE_READY,
    approval_basis: 'doctor_constraint_envelope',
    protocol_id:
      proposal.parameters?.clinic_protocol_id ??
      `ai_adaptive_${action.modality_id}_v1`,
    parameter_source: proposal.parameter_source,
    parameters: {
      ...(proposal.parameters ?? {}),
      ...(proposal.free_ai_parameters ?? {}),
    },
    approved_how_to_do:
      envelope.simple_autorelease
        ? 'Perform the selected facial step for the allocated duration using the selected product or solution and the zone-specific technique in the execution library.'
        : 'Use the AI-selected settings exactly as listed. They have passed the explicit Dr. Aakriti constraint checks.',
    pre_step_confirmations: [
      'Patient-history and eligibility rules passed.',
      'Selected zones and protected zones confirmed.',
      ...(envelope.simple_autorelease
        ? []
        : ['Explicit machine constraints validated.']),
    ],
    additional_safety_signals: [],
    additional_stop_conditions: [
      'Stop for sharp pain, burning, unexpected heat or a reaction beyond the intended endpoint.',
    ],
    adaptive_settings_audit: {
      engine_version: ADAPTIVE_PROTOCOL_SETTINGS_ENGINE_VERSION,
      constraint_version: DOCTOR_APPROVED_PROTOCOL_CONSTRAINTS_VERSION,
      global_policy: DOCTOR_APPROVED_GLOBAL_PROTOCOL_POLICY_V3,
      outcome_position_0_to_1: Number(position.toFixed(4)),
      simple_autorelease: envelope.simple_autorelease === true,
      proposal,
      validation,
    },
  }
}

export function buildAdaptiveProtocolRegistryForPlanV3({
  optimizerResult,
  aiProposalsByModality = {},
  skinState = null,
  patientHistory = {},
  regionalTemperaturesC = {},
} = {}) {
  const actions = optimizerResult?.plan?.modality_actions ?? []
  return Object.fromEntries(
    actions.map((action) => [
      action.modality_id,
      resolveAdaptiveProtocolSettingsV3({
        action,
        aiProposal: aiProposalsByModality[action.modality_id] ?? null,
        skinState,
        patientHistory,
        regionalTemperaturesC,
        eligibilitySummary: action.eligibility_summary,
      }),
    ]),
  )
}
