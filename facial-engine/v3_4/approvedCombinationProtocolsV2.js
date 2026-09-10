export const COMBINATION_PROTOCOL_REGISTRY_VERSION =
  'aia_combination_protocol_registry_v3.3.0'

export const DEFAULT_CLINIC_PROTOCOL_IDS_V2 = Object.freeze([
  'hydrafacial_modular_probe_sequence_v1',
  'led_recovery_after_corrective_v1',
  'hydration_support_after_corrective_v1',
  'final_skin_protection_v1',
  'manual_extraction_with_gentle_hydration_v1',
])

export const COMBINATION_PROTOCOL_REGISTRY_V2 = Object.freeze({
  hydrafacial_modular_probe_sequence_v1: {
    id: 'hydrafacial_modular_probe_sequence_v1',
    status: 'clinic_preapproved',
    live_workflow_prompt_required: false,
    match: {
      shared_parent_modality_id: 'hydrafacial_full_protocol',
    },
    scope:
      'Allows one or more individually selected Hydrafacial probes in the same session without requiring the complete machine sequence.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  led_recovery_after_corrective_v1: {
    id: 'led_recovery_after_corrective_v1',
    status: 'clinic_preapproved',
    live_workflow_prompt_required: false,
    match: {
      one_modality_ids: ['led_red', 'led_blue'],
      other_categories: [
        'laser',
        'laser_special_protocol',
        'energy',
        'energy_invasive',
        'invasive',
        'chemical_peel',
      ],
    },
    scope:
      'Allows selected LED recovery/acne support after a compatible corrective procedure.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  hydration_support_after_corrective_v1: {
    id: 'hydration_support_after_corrective_v1',
    status: 'clinic_preapproved',
    live_workflow_prompt_required: false,
    match: {
      one_modality_ids: [
        'hydrating_mask',
        'calming_mask',
        'jet_oxygen_infusion',
        'ultrasound_infusion_face',
        'ultrasound_infusion_ocular',
        'hydrafacial_ice_probe',
      ],
      other_categories: [
        'laser',
        'laser_special_protocol',
        'energy',
        'energy_invasive',
        'invasive',
        'chemical_peel',
        'mechanical_exfoliation',
        'hydradermabrasion',
        'hydrafacial_probe',
      ],
    },
    scope:
      'Allows a compatible hydration, cooling or calming support step after corrective treatment.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  final_skin_protection_v1: {
    id: 'final_skin_protection_v1',
    status: 'clinic_preapproved',
    live_workflow_prompt_required: false,
    match: {
      one_modality_ids: ['final_serum_moisturizer_sunscreen'],
      other_any: true,
    },
    scope:
      'Mandatory final serum, moisturiser and sunscreen after every facial session.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  manual_extraction_with_gentle_hydration_v1: {
    id: 'manual_extraction_with_gentle_hydration_v1',
    status: 'clinic_preapproved',
    live_workflow_prompt_required: false,
    match: {
      one_modality_ids: [
        'manual_extraction',
        'hydrafacial_suction_extraction',
      ],
      other_modality_ids: [
        'hydrating_mask',
        'calming_mask',
        'jet_oxygen_infusion',
        'ultrasound_infusion_face',
        'led_red',
      ],
    },
    scope:
      'Allows targeted extraction followed by compatible recovery/hydration.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  qswitch_multi_wavelength_zonal_v1: {
    id: 'qswitch_multi_wavelength_zonal_v1',
    status: 'disabled_not_in_general_facial_engine',
    live_workflow_prompt_required: false,
    match: {
      all_modality_ids: ['q_switch_1064'],
      minimum_matches: 2,
    },
    scope:
      'Multi-wavelength Q-Switch is disabled because the general facial engine uses only 1064 nm.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
    activation_requirements: [
      'AI-selected wavelength-by-zone settings validated against Dr. Aakriti constraints.',
      'Device capability taken from the approved constraints resource catalog.',
      'No therapist approval prompt during live treatment.',
    ],
  },

  carbon_plus_qswitch_zonal_v1: {
    id: 'carbon_plus_qswitch_zonal_v1',
    status: 'clinic_preapproved_constraint_envelope',
    live_workflow_prompt_required: false,
    match: {
      one_modality_ids: ['carbon_facial'],
      other_modality_ids: ['q_switch_1064'],
    },
    scope:
      'Allows Carbon Facial and targeted 1064-nm Q-Switch only when the optimizer finds distinct non-redundant zone benefit.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  multiple_peel_zonal_v1: {
    id: 'multiple_peel_zonal_v1',
    status: 'clinic_preapproved_constraint_envelope',
    live_workflow_prompt_required: false,
    match: {
      both_categories: ['chemical_peel'],
    },
    scope:
      'Allows two distinct peel protocols only when explicitly approved for separate zones or a defined layered protocol.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  peel_plus_hydrafacial_reduced_intensity_v1: {
    id: 'peel_plus_hydrafacial_reduced_intensity_v1',
    status: 'clinic_preapproved_constraint_envelope',
    live_workflow_prompt_required: false,
    match: {
      one_categories: ['chemical_peel'],
      other_categories: [
        'hydradermabrasion',
        'hydrafacial_probe',
        'mechanical_exfoliation',
      ],
    },
    scope:
      'Allows a gentle peel with selected reduced-intensity machine steps under a stored clinic protocol.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },

  rf_plus_surface_facial_v1: {
    id: 'rf_plus_surface_facial_v1',
    status: 'clinic_preapproved_constraint_envelope',
    live_workflow_prompt_required: false,
    match: {
      one_modality_ids: ['radio_frequency'],
      other_categories: [
        'hydradermabrasion',
        'hydrafacial_probe',
        'mechanical_exfoliation',
      ],
    },
    scope:
      'Allows RF plus compatible surface facial steps when sequence, intensity and total thermal/exfoliation load are clinic-approved.',
    exact_execution_source: 'ai_customised_within_doctor_constraints',
  },
})

const unique = (values) => [...new Set((values ?? []).filter(Boolean))]

function categoryMatches(candidate, values) {
  return (values ?? []).includes(candidate?.category)
}

function modalityMatches(candidate, values) {
  return (values ?? []).includes(candidate?.modality_id)
}

function matchesOrderedPair(a, b, match) {
  const oneMatches =
    modalityMatches(a, match.one_modality_ids) ||
    categoryMatches(a, match.one_categories)
  const otherMatches =
    match.other_any ||
    modalityMatches(b, match.other_modality_ids) ||
    categoryMatches(b, match.other_categories)
  return oneMatches && otherMatches
}

export function protocolMatchesPairV2(protocol, a, b) {
  const match = protocol?.match ?? {}

  if (
    match.shared_parent_modality_id &&
    a?.parent_modality_id === match.shared_parent_modality_id &&
    b?.parent_modality_id === match.shared_parent_modality_id
  ) {
    return true
  }

  if (
    match.all_modality_ids &&
    match.all_modality_ids.includes(a?.modality_id) &&
    match.all_modality_ids.includes(b?.modality_id)
  ) {
    return true
  }

  if (
    match.both_categories &&
    match.both_categories.includes(a?.category) &&
    match.both_categories.includes(b?.category)
  ) {
    return true
  }

  if (matchesOrderedPair(a, b, match)) return true
  if (matchesOrderedPair(b, a, match)) return true

  return false
}

export function getEffectiveClinicProtocolIdsV2(
  clinicProtocolIds = [],
) {
  return unique([
    ...DEFAULT_CLINIC_PROTOCOL_IDS_V2,
    ...clinicProtocolIds,
  ])
}

export function findApprovedCombinationProtocolV2(
  a,
  b,
  clinicProtocolIds = [],
) {
  const effectiveIds = getEffectiveClinicProtocolIdsV2(
    clinicProtocolIds,
  )

  for (const protocolId of effectiveIds) {
    const protocol =
      COMBINATION_PROTOCOL_REGISTRY_V2[protocolId]
    if (!protocol) continue
    if (protocolMatchesPairV2(protocol, a, b)) {
      return protocol
    }
  }
  return null
}

export function evaluateCombinationProtocolV2({
  a,
  b,
  baseCompatibility,
  clinicProtocolIds = [],
  requiresStoredProtocol = false,
}) {
  if (!baseCompatibility?.compatible) {
    return {
      compatible: false,
      protocol_status: 'prohibited',
      protocol_id: null,
      live_workflow_prompt_required: false,
      reasons: baseCompatibility?.reasons ?? [
        'The combination is prohibited by clinical compatibility rules.',
      ],
    }
  }

  const protocol = findApprovedCombinationProtocolV2(
    a,
    b,
    clinicProtocolIds,
  )

  if (requiresStoredProtocol || baseCompatibility?.conditional) {
    if (!protocol) {
      return {
        compatible: false,
        protocol_status: 'not_preapproved_in_backend',
        protocol_id: null,
        live_workflow_prompt_required: false,
        reasons: [
          'The combination is skipped automatically because no matching clinic-preapproved protocol is stored.',
        ],
      }
    }
  }

  return {
    compatible: true,
    protocol_status: protocol
      ? 'clinic_preapproved'
      : 'not_required',
    protocol_id: protocol?.id ?? null,
    live_workflow_prompt_required: false,
    reasons: protocol
      ? [
          `Combination covered by backend clinic protocol ${protocol.id}.`,
        ]
      : [],
  }
}

export function validateCombinationProtocolRegistryV2() {
  const errors = []

  for (const protocolId of DEFAULT_CLINIC_PROTOCOL_IDS_V2) {
    const protocol =
      COMBINATION_PROTOCOL_REGISTRY_V2[protocolId]
    if (!protocol) {
      errors.push(`Missing default protocol: ${protocolId}`)
      continue
    }
    if (protocol.live_workflow_prompt_required !== false) {
      errors.push(
        `${protocolId} must not require a live workflow prompt.`,
      )
    }
  }

  return {
    valid: errors.length === 0,
    version: COMBINATION_PROTOCOL_REGISTRY_VERSION,
    errors,
  }
}
