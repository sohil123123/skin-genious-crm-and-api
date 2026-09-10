import {
  MODALITY_EXECUTION_PROTOCOLS_V2,
} from './executionProtocolLibraryV2.js'

export const DOCTOR_APPROVED_PROTOCOL_CONSTRAINTS_VERSION =
  'aia_doctor_approved_protocol_constraints_v3.3.0'

export const DOCTOR_APPROVED_GLOBAL_PROTOCOL_POLICY_V3 = Object.freeze({
  approved: true,
  approved_by: 'Dr. Aakriti Mehra',
  source: 'constraints.json v1.1 plus July 2026 facial-engine clarifications',
  engine_scope: 'general_facial_treatment_engine',
  selection_mode: 'ai_adaptive_within_doctor_constraints',
  objective:
    'Maximise expected visible result for the current client while respecting only the defined clinical, patient-history, zone, device and combination constraints.',
  live_approval_prompt_required: false,
  manual_fixed_preset_required: false,
  validation_philosophy:
    'Validate parameters that have explicit doctor-defined limits. Do not block simple facial steps merely because they have no machine preset.',
  history_used_for_image_scoring: false,
  dynamic_question_layer_required: false,
  opencv_or_external_scan_qc_required: false,
  q_switch_wavelength_nm: 1064,
  q_switch_spot_area_cm2: 1,
  machine_passes_minimum: 1,
  machine_passes_maximum: 2,
})

const numberRange = (min, max, unit, step = null) => ({
  type: 'number_range',
  min,
  max,
  unit,
  step,
  provenance: 'doctor_constraints_explicit',
})

const fixed = (value, provenance = 'doctor_constraints_explicit') => ({
  type: 'fixed',
  value,
  provenance,
})

const enumValues = (values, provenance = 'doctor_constraints_explicit') => ({
  type: 'enum',
  values,
  provenance,
})

const byZone = (valueSpec) => ({
  type: 'by_zone',
  value_spec: valueSpec,
})

export const APPROVED_RESOURCE_CATALOG_V3 = Object.freeze({
  q_switch: {
    wavelengths_nm: [1064],
    frequency_hz: numberRange(1, 10, 'Hz', 1),
    energy_mj: numberRange(100, 2000, 'mJ', 10),
    spot_area_cm2: fixed(1),
    passes: numberRange(1, 2, 'passes', 1),
    source_field_correction:
      'The source field named fluence_range contains 1–10 Hz and is treated as repetition frequency.',
  },
  radio_frequency: {
    energy_level: numberRange(1, 10, 'device_level', 1),
    passes: numberRange(1, 2, 'passes', 1),
  },
  hifu: {
    cartridge_depths_mm: enumValues([1.5, 3, 4.5]),
  },
  needling: {
    depth_mm: numberRange(0.25, 1.5, 'mm', 0.25),
    passes: numberRange(1, 2, 'passes', 1),
  },
  led_modes: ['blue', 'red', 'green'],
  hydrafacial_probes: [
    'ice_probe',
    'ocular_ultrasound_infusion',
    'face_ultrasound_infusion',
    'cutin_removal_spatula',
    'lifting_probe_rf',
    'suction_probe_bubble_pen',
    'teenage_line',
    'oxygen_injection_hydra_spray',
  ],
  infusion_solutions: [
    'hyaluronic_acid',
    'vitamin_c',
    'tranexamic_acid',
    'pdrn',
    'exosomes',
    'lifting',
    'glutathione',
    'hydrafacial_serum_as1',
    'hydrafacial_serum_sa2',
    'hydrafacial_serum_a03',
  ],
})

export const FACIAL_ENGINE_DISABLED_MODALITY_IDS_V3 = Object.freeze([
  'q_switch_532',
  'q_switch_755',
  'q_switch_lip_pigmentation_protocol',
])

export const SIMPLE_AUTORELEASE_MODALITY_IDS_V3 = Object.freeze([
  'hydrafacial_full_protocol',
  'hydrafacial_cutin_spatula',
  'hydrafacial_suction_extraction',
  'hydrafacial_ice_probe',
  'hydrafacial_teenage_line_probe',
  'jet_oxygen_infusion',
  'ultrasound_infusion_face',
  'ultrasound_infusion_ocular',
  'led_blue',
  'led_red',
  'led_green',
  'microdermabrasion_diamond',
  'microdermabrasion_crystal',
  'high_frequency_glass_electrode',
  'manual_extraction',
  'lymphatic_drainage',
  'charcoal_mask',
  'calming_mask',
  'brightening_mask',
  'hydrating_mask',
  'lifting_mask',
  'final_serum_moisturizer_sunscreen',
  'salicylic_spot',
])

export const PEEL_NEUTRALIZATION_RULES_V3 = Object.freeze({
  alkaline_neutralizer: [
    'party_peel',
    'whitening_peel',
    'pumpkin_peel',
    'fusion_peel_e',
    'glyco_35_peel',
  ],
  normal_saline: [
    'sali_ds_peel',
    'salicylic_30_peel',
    'salicylic_20_peel',
    'mandelic_peel',
    'combination_peel',
    'yellow_peel',
    'cosmelan_protocol',
    'biorepeelcl3',
    'salmon_peel',
  ],
  rule:
    'Peels containing glycolic acid or lactic acid use alkaline neutralizer. All other facial peels are neutralized or removed with normal saline (NS).',
  approved_by: 'Dr. Aakriti Mehra',
})

export function neutralizerForPeelV3(modalityId) {
  if (PEEL_NEUTRALIZATION_RULES_V3.alkaline_neutralizer.includes(modalityId)) {
    return 'alkaline_neutralizer'
  }
  if (PEEL_NEUTRALIZATION_RULES_V3.normal_saline.includes(modalityId)) {
    return 'normal_saline_ns'
  }
  return null
}

const qSwitch1064Envelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints_and_clarification',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_qswitch_1064_facial',
    },
    wavelength_nm: fixed(1064),
    fluence_or_energy_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.q_switch.energy_mj,
    ),
    frequency_hz: APPROVED_RESOURCE_CATALOG_V3.q_switch.frequency_hz,
    spot_size_or_handpiece: fixed({
      spot_area_cm2: 1,
      label: 'fixed_1_cm2_spot',
    }),
    passes_or_shots_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.q_switch.passes,
    ),
  },
  free_ai_fields: ['clinical_endpoint'],
})

const carbonEnvelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints_and_clarification',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_carbon_facial',
    },
    carbon_product: fixed('Carbon Lotion', 'doctor_constraints_resource_catalog'),
    wavelength_nm: fixed(1064),
    fluence_or_energy_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.q_switch.energy_mj,
    ),
    frequency_hz: APPROVED_RESOURCE_CATALOG_V3.q_switch.frequency_hz,
    spot_size_or_handpiece: fixed({
      spot_area_cm2: 1,
      label: 'fixed_1_cm2_spot',
    }),
    passes_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.q_switch.passes,
    ),
  },
  free_ai_fields: ['clinical_endpoint'],
})

const radioFrequencyEnvelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints_and_clarification',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_rf_facial',
    },
    device_preset_or_energy_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.radio_frequency.energy_level,
    ),
    passes_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.radio_frequency.passes,
    ),
    contact_medium: fixed('pH Neutral Gel', 'doctor_constraints_resource_catalog'),
  },
  free_ai_fields: ['temperature_or_endpoint_monitoring'],
})

const hifuEnvelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_hifu_facial',
    },
    cartridge_depth_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.hifu.cartridge_depths_mm,
    ),
  },
  free_ai_fields: [
    'energy_by_zone',
    'shot_count_and_vector_map',
    'anatomical_avoid_map',
  ],
})

const microneedlingEnvelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints_and_clarification',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_microneedling_facial',
    },
    needle_depth_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.needling.depth_mm,
    ),
    passes_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.needling.passes,
    ),
  },
  free_ai_fields: [
    'speed_or_device_preset',
    'sterile_product_if_used',
    'clinical_endpoint',
  ],
})

const dermarollerEnvelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints_and_clarification',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_dermaroller_facial',
    },
    needle_depth: APPROVED_RESOURCE_CATALOG_V3.needling.depth_mm,
    passes_by_direction_and_zone: {
      type: 'pass_map',
      min: 1,
      max: 2,
      provenance: 'doctor_constraints_explicit',
    },
  },
  free_ai_fields: ['sterile_product_if_used', 'clinical_endpoint'],
})

const mnrfEnvelope = Object.freeze({
  approved: true,
  source: 'doctor_constraints_and_clarification',
  adaptive: true,
  parameter_constraints: {
    clinic_protocol_id: {
      type: 'generated_protocol_reference',
      prefix: 'ai_adaptive_mnrf_facial',
    },
    needle_depth_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.needling.depth_mm,
    ),
    passes_by_zone: byZone(
      APPROVED_RESOURCE_CATALOG_V3.needling.passes,
    ),
  },
  free_ai_fields: [
    'rf_energy_by_zone',
    'insulated_or_noninsulated_tip',
    'clinical_endpoint',
  ],
})

const PEEL_IDS = Object.freeze([
  'party_peel',
  'whitening_peel',
  'sali_ds_peel',
  'salicylic_30_peel',
  'salicylic_20_peel',
  'pumpkin_peel',
  'mandelic_peel',
  'fusion_peel_e',
  'glyco_35_peel',
  'combination_peel',
  'yellow_peel',
  'cosmelan_protocol',
  'biorepeelcl3',
  'salmon_peel',
])

function peelEnvelope(modalityId) {
  const neutralization = neutralizerForPeelV3(modalityId)
  return {
    approved: true,
    source: 'doctor_constraints_named_product_and_neutralization_rule',
    adaptive: true,
    parameter_constraints: {
      clinic_protocol_id: {
        type: 'generated_protocol_reference',
        prefix: `ai_adaptive_${modalityId}`,
      },
      neutralization_method: fixed(neutralization),
      neutralization_or_removal_method: fixed(neutralization),
      removal_method: fixed(neutralization),
      removal_and_aftercare: fixed(neutralization),
    },
    free_ai_fields: [
      'contact_time_minutes',
      'in_clinic_contact_time',
      'layers',
      'layers_or_spot_method',
      'clinical_endpoint',
      'exact_product_formula',
      'exact_product_protocol',
      'verified_formula',
      'home_maintenance_protocol',
    ],
  }
}

export const DOCTOR_APPROVED_PROTOCOL_ENVELOPES_V3 = Object.freeze({
  q_switch_1064: qSwitch1064Envelope,
  carbon_facial: carbonEnvelope,
  radio_frequency: radioFrequencyEnvelope,
  hifu: hifuEnvelope,
  microneedling: microneedlingEnvelope,
  dermaroller: dermarollerEnvelope,
  microneedling_rf: mnrfEnvelope,
})

export function getDoctorApprovedProtocolEnvelopeV3(modalityId) {
  if (FACIAL_ENGINE_DISABLED_MODALITY_IDS_V3.includes(modalityId)) {
    return {
      approved: false,
      disabled_for_engine_scope: true,
      reason: 'Not part of the general facial engine.',
      parameter_constraints: {},
      free_ai_fields: [],
    }
  }

  if (DOCTOR_APPROVED_PROTOCOL_ENVELOPES_V3[modalityId]) {
    return DOCTOR_APPROVED_PROTOCOL_ENVELOPES_V3[modalityId]
  }

  if (PEEL_IDS.includes(modalityId)) {
    return peelEnvelope(modalityId)
  }

  if (SIMPLE_AUTORELEASE_MODALITY_IDS_V3.includes(modalityId)) {
    return {
      approved: true,
      source: 'simple_facial_step_no_parameter_validation_required',
      adaptive: true,
      simple_autorelease: true,
      parameter_constraints: {},
      free_ai_fields: ['duration_minutes', 'selected_product_or_solution'],
    }
  }

  if (MODALITY_EXECUTION_PROTOCOLS_V2[modalityId]) {
    return {
      approved: true,
      source: 'general_facial_modality',
      adaptive: true,
      parameter_constraints: {},
      free_ai_fields: [],
    }
  }

  return null
}

export function createApprovedAdaptiveProtocolRegistryV3() {
  return Object.fromEntries(
    Object.keys(MODALITY_EXECUTION_PROTOCOLS_V2).map((modalityId) => {
      const envelope = getDoctorApprovedProtocolEnvelopeV3(modalityId)
      return [
        modalityId,
        {
          approved: envelope?.approved === true,
          disabled_for_engine_scope:
            envelope?.disabled_for_engine_scope === true,
          approval_basis: envelope?.approved
            ? 'doctor_constraint_envelope'
            : 'not_approved_for_general_facial_engine',
          parameter_source: envelope?.simple_autorelease
            ? 'simple_step_no_parameter_validation'
            : 'ai_selected_within_constraints',
          envelope,
        },
      ]
    }),
  )
}

export function validateDoctorApprovedProtocolConstraintsV3() {
  const errors = []
  const registry = createApprovedAdaptiveProtocolRegistryV3()

  for (const [modalityId, entry] of Object.entries(registry)) {
    if (FACIAL_ENGINE_DISABLED_MODALITY_IDS_V3.includes(modalityId)) {
      if (entry.approved) {
        errors.push(`${modalityId} should be disabled for the facial engine.`)
      }
      continue
    }
    if (!entry.approved) {
      errors.push(`${modalityId} is missing an approved facial-engine envelope.`)
    }
  }

  return {
    valid: errors.length === 0,
    errors,
    modality_count: Object.keys(registry).length,
    active_approved_count: Object.values(registry).filter(
      (entry) => entry.approved,
    ).length,
    disabled_for_scope_count: FACIAL_ENGINE_DISABLED_MODALITY_IDS_V3.length,
  }
}
