import { CORE_FEATURE_IDS } from './skinStateV2.schema.js'

export const ZONAL_TREATMENT_OPTIMIZER_CONFIG_VERSION =
  'aia_zonal_treatment_optimizer_config_v3.3.0'

export const OPTIMIZER_OBJECTIVE_PRESETS_V2 = {
  event_ready: {
    immediate_weight: 0.88,
    course_weight: 0.12,
    downtime_penalty_multiplier: 1.45,
    label: 'Maximise visible short-term improvement with minimal downtime.',
  },
  immediate: {
    immediate_weight: 0.8,
    course_weight: 0.2,
    downtime_penalty_multiplier: 1.2,
    label: 'Prioritise visible improvement from the current session.',
  },
  balanced: {
    immediate_weight: 0.58,
    course_weight: 0.42,
    downtime_penalty_multiplier: 0.8,
    label: 'Balance immediate visible benefit with course-level correction.',
  },
  course: {
    immediate_weight: 0.25,
    course_weight: 0.75,
    downtime_penalty_multiplier: 0.45,
    label: 'Prioritise the strongest clinically appropriate course-level correction.',
  },
  structural: {
    immediate_weight: 0.12,
    course_weight: 0.88,
    downtime_penalty_multiplier: 0.35,
    label: 'Prioritise delayed structural improvement.',
  },
}

export const OPTIMIZER_DEFAULTS_V2 = {
  objective_preset: 'balanced',
  session_target_minutes: 60,
  session_tolerance_minutes: 15,
  fixed_preparation_overhead_minutes: 8,
  maximum_corrective_modalities: 3,
  maximum_supportive_modalities: 3,
  minimum_feature_burden_to_treat: 18,
  minimum_atomic_utility: 0.018,
  minimum_modality_utility: 0.035,
  zone_inclusion_fraction_of_peak: 0.34,
  maximum_zones_per_modality: 12,
  allow_component_only_selection: true,
  allow_multi_wavelength_qswitch: false,
  allow_multiple_primary_lasers: false,
  allow_supportive_corrective_fallback: true,
  always_include_final_skin_protection: true,
  always_include_lymphatic_drainage: true,
  lymphatic_drainage_minimum_minutes: 5,
  lymphatic_drainage_maximum_minutes: 15,
  minimum_duration_completion_enabled: true,
  minimum_duration_completion_maximum_filler_actions: 3,
  preserve_selected_support_in_session_compiler: true,
  minimum_direct_utility_fraction_for_primary_corrective: 0.55,
  carbon_facial_minimum_direct_feature_burden: 45,
  carbon_facial_minimum_direct_utility_fraction: 0.72,
  modality_prior_policy: 'neutral_no_modality_boost',
}


export const MINIMUM_DURATION_FILLER_CATEGORY_ALLOWLIST_V2_8 =
  Object.freeze([
    'photobiomodulation',
    'infusion',
    'mask',
    'manual_supportive',
    'hydrafacial_probe',
    'energy_supportive',
    'targeted_topical',
  ])

export const MINIMUM_DURATION_FILLER_EXCLUDED_MODALITIES_V2_8 =
  Object.freeze([
    'lymphatic_drainage',
    'final_serum_moisturizer_sunscreen',
  ])

export const CONCERN_TO_CORE_FEATURES_V2 = {
  acne: {
    active_inflammatory_acne: 1.0,
    comedonal_congestion: 0.85,
    oiliness: 0.55,
    erythema_redness: 0.25,
  },
  active_acne: {
    active_inflammatory_acne: 1.0,
    erythema_redness: 0.35,
  },
  congestion: {
    comedonal_congestion: 1.0,
    pore_visibility: 0.7,
    oiliness: 0.55,
  },
  blackheads: {
    comedonal_congestion: 1.0,
    pore_visibility: 0.65,
  },
  oiliness: {
    oiliness: 1.0,
    comedonal_congestion: 0.5,
    pore_visibility: 0.35,
  },
  pores: {
    pore_visibility: 1.0,
    comedonal_congestion: 0.6,
    texture_roughness: 0.4,
    oiliness: 0.35,
  },
  texture: {
    texture_roughness: 1.0,
    pore_visibility: 0.55,
    comedonal_congestion: 0.25,
  },
  pigmentation: {
    visible_pigmentation: 1.0,
    underlying_pigment_support: 0.7,
    luminosity_loss: 0.35,
  },
  tanning: {
    visible_pigmentation: 0.9,
    underlying_pigment_support: 0.4,
    luminosity_loss: 0.55,
  },
  dullness: {
    luminosity_loss: 1.0,
    visual_dehydration: 0.55,
    texture_roughness: 0.45,
    visible_pigmentation: 0.25,
  },
  glow: {
    luminosity_loss: 1.0,
    visual_dehydration: 0.65,
    texture_roughness: 0.35,
  },
  hydration: {
    visual_dehydration: 1.0,
    barrier_stress: 0.65,
    luminosity_loss: 0.35,
    fine_line_visibility: 0.2,
  },
  sensitivity: {
    barrier_stress: 1.0,
    erythema_redness: 0.75,
    visual_dehydration: 0.45,
  },
  redness: {
    erythema_redness: 1.0,
    barrier_stress: 0.45,
  },
  fine_lines: {
    fine_line_visibility: 1.0,
    visual_dehydration: 0.35,
    firmness_appearance_loss: 0.3,
  },
  anti_ageing: {
    fine_line_visibility: 0.85,
    visible_laxity: 1.0,
    firmness_appearance_loss: 1.0,
    visual_dehydration: 0.25,
  },
  anti_aging: {
    fine_line_visibility: 0.85,
    visible_laxity: 1.0,
    firmness_appearance_loss: 1.0,
    visual_dehydration: 0.25,
  },
  laxity: {
    visible_laxity: 1.0,
    firmness_appearance_loss: 0.85,
    fine_line_visibility: 0.3,
  },
  firmness: {
    firmness_appearance_loss: 1.0,
    visible_laxity: 0.8,
    fine_line_visibility: 0.3,
  },
  jawline: {
    visible_laxity: 1.0,
    firmness_appearance_loss: 0.75,
  },
  under_eye: {
    peri_orbital_concern: 1.0,
    fine_line_visibility: 0.45,
    visual_dehydration: 0.3,
  },
  peri_orbital: {
    peri_orbital_concern: 1.0,
    fine_line_visibility: 0.45,
  },
  lip_pigmentation: {
    lip_pigmentation: 1.0,
  },
}

export const FEATURE_FAMILY_V2 = {
  active_inflammatory_acne: 'acne',
  comedonal_congestion: 'congestion',
  oiliness: 'sebum',
  erythema_redness: 'inflammation',
  barrier_stress: 'barrier',
  visual_dehydration: 'hydration',
  pore_visibility: 'surface',
  texture_roughness: 'surface',
  visible_pigmentation: 'pigment',
  underlying_pigment_support: 'pigment',
  luminosity_loss: 'radiance',
  fine_line_visibility: 'ageing',
  visible_laxity: 'structure',
  firmness_appearance_loss: 'structure',
  peri_orbital_concern: 'peri_orbital',
  lip_pigmentation: 'lip',
}

export const ELIGIBILITY_MULTIPLIER_V2 = {
  allowed: 1,
  allowed_with_caution: 0.66,
  denied: 0,
  not_applicable: 0,
  allowed_supportive_no_profile: 0.82,
}

export const INTENSITY_MULTIPLIER_V2 = {
  none: 0,
  very_low: 0.42,
  low: 0.58,
  low_medium: 0.72,
  medium: 0.84,
  medium_high: 0.93,
  high: 1,
}

export const RESPONSE_CONFIDENCE_MULTIPLIER_V2 = {
  high: 1,
  medium: 0.86,
  low: 0.67,
}

export const EVIDENCE_TIER_MULTIPLIER_V2 = {
  A: 1,
  B: 0.91,
  C: 0.77,
  D: 0.58,
}

export const CLINICAL_ROLE_MULTIPLIER_V2 = {
  direct: 1,
  secondary: 0.84,
  supportive: 0.66,
  preventive: 0.56,
  conditional: 0.58,
  limited: 0.42,
  neutral: 0,
  avoid: 0,
  none: 0,
}

export const DOWNTIME_BAND_COST_V2 = {
  none: 0,
  none_to_low: 0.012,
  low: 0.025,
  low_to_moderate: 0.055,
  moderate: 0.08,
  moderate_to_high: 0.115,
  high: 0.15,
  unknown: 0.09,
}

export const CORRECTIVE_CATEGORY_GROUPS_V2 = {
  exfoliation: [
    'hydradermabrasion',
    'hydrafacial_probe',
    'mechanical_exfoliation',
    'chemical_peel',
  ],
  laser: ['laser', 'laser_special_protocol'],
  structural_energy: ['energy', 'energy_invasive'],
  invasive: ['invasive', 'energy_invasive'],
  extraction: ['manual', 'hydrafacial_probe'],
}

export const HARD_MUTUAL_EXCLUSION_GROUPS_V2 = [
  // V2.5 deliberately contains no count-based "one hero" or
  // "one modality of this type" blocks. Compatibility is determined by:
  // 1) clinical exclusion rules in the modality library,
  // 2) zone-specific eligibility, and
  // 3) clinic-preapproved combination protocols.
]

export const SUPPORT_TRIGGER_RULES_V2 = [
  {
    id: 'recovery_led_after_invasive_or_energy',
    selected_categories: ['laser', 'energy', 'energy_invasive', 'invasive'],
    preferred_support_ids: ['led_red'],
    reason: 'Provide explicit recovery support after the selected corrective modality.',
  },
  {
    id: 'blue_led_for_acne',
    feature_thresholds: {
      active_inflammatory_acne: 30,
    },
    preferred_support_ids: ['led_blue'],
    reason: 'Add acne-directed light support when inflammatory burden is meaningful.',
  },
  {
    id: 'hydration_support',
    feature_thresholds: {
      visual_dehydration: 48,
    },
    preferred_support_ids: ['hydrating_mask', 'jet_oxygen_infusion', 'ultrasound_infusion_face'],
    reason: 'Add hydration support because visible dehydration is clinically meaningful.',
  },
  {
    id: 'barrier_recovery_support',
    feature_thresholds: {
      barrier_stress: 48,
    },
    preferred_support_ids: ['calming_mask', 'hydrating_mask', 'led_red'],
    reason: 'Add barrier-compatible recovery support.',
  },
  {
    id: 'peri_orbital_support',
    feature_thresholds: {
      peri_orbital_concern: 45,
    },
    preferred_support_ids: ['ultrasound_infusion_ocular', 'lymphatic_drainage'],
    reason: 'Add a zone-specific peri-orbital supportive action when suitable.',
  },
]

export const SPECIAL_MODALITY_GATES_V2 = {
  q_switch_532: {
    disabled_for_engine_scope: true,
    reason: 'The general facial engine uses only 1064-nm Q-Switch.',
  },
  carbon_facial: {
    direct_feature_ids: [
      'active_inflammatory_acne',
      'comedonal_congestion',
      'oiliness',
      'pore_visibility',
    ],
    reason:
      'Carbon Facial is eligible only when a meaningful acne, congestion, oil or pore indication directly drives the expected benefit.',
  },
  microneedling_rf_for_active_acne: {
    capability_flag: 'mnrf_sebaceous_targeting_protocol_available',
    reason:
      'MNRF acne benefit requires a validated sebaceous-targeting probe and protocol.',
  },
}

export function assertValidFeaturePriorityMapV2(featurePriorityMap) {
  for (const featureId of Object.keys(featurePriorityMap ?? {})) {
    if (!CORE_FEATURE_IDS.includes(featureId)) {
      throw new Error(`Unknown core feature priority: ${featureId}`)
    }
  }
}
