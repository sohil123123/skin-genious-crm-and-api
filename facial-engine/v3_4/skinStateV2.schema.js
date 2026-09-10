import { FACE_ZONE_ATLAS_VERSION, FACE_ZONE_IDS } from './faceZoneAtlasV2.js'

export const SKIN_STATE_SCHEMA_VERSION = 'aia_skin_state_v3.3.0'
export const VISION_EVIDENCE_SCHEMA_VERSION = 'aia_vision_evidence_v3.3.0'

export const IMAGE_MODES = [
  'red',
  'subsurface_polarized',
  'surface_polarized',
  'white',
  'woods_uv',
]

export const EVIDENCE_GRADES = {
  0: 'absent',
  1: 'minimal',
  2: 'mild',
  3: 'moderate',
  4: 'marked',
  5: 'severe',
}

export const MODE_AGREEMENT_VALUES = [
  'strong',
  'partial',
  'conflicting',
  'single_mode_only',
]

export const ASSESSMENT_STATUS_VALUES = [
  'assessable',
  'partially_assessable',
  'not_assessable',
]

export const ARTIFACT_FLAGS = [
  'makeup_or_tint',
  'lip_product',
  'beard_or_stubble',
  'hair_occlusion',
  'specular_glare',
  'deep_shadow',
  'motion_blur',
  'focus_loss',
  'exposure_mismatch',
  'mode_misclassification',
  'post_treatment_residue',
  'other',
]

/**
 * Core observable features. These are the treatment-planning truth layer.
 * Client-facing report parameters are derived from these features.
 */
export const CORE_FEATURE_IDS = [
  'active_inflammatory_acne',
  'comedonal_congestion',
  'oiliness',
  'erythema_redness',
  'barrier_stress',
  'visual_dehydration',
  'pore_visibility',
  'texture_roughness',
  'visible_pigmentation',
  'underlying_pigment_support',
  'luminosity_loss',
  'fine_line_visibility',
  'visible_laxity',
  'firmness_appearance_loss',
  'peri_orbital_concern',
  'lip_pigmentation',
]

export const CLIENT_REPORT_PARAMETER_IDS = [
  'skin_type',
  'barrier_health_sensitivity',
  'visual_acne',
  'skin_sebum',
  'vascularity_redness',
  'skin_hydration',
  'skin_luminosity_glow',
  'superficial_pigmentation',
  'peri_orbital_health',
  'lip_pigmentation',
  'texture_open_pores',
  'superficial_wrinkles',
  'jawline_sagging',
  'skin_firmness_elasticity',
  'textural_radiance',
]

/**
 * Canonical AI-vision output shape.
 *
 * Rules:
 * - The vision model outputs only anchored grades, mode evidence and flags.
 * - It does not output a free 1–100 score.
 * - Every score is calculated later by deterministic code.
 */
export const VISION_EVIDENCE_PACKET_TEMPLATE = {
  schema_version: VISION_EVIDENCE_SCHEMA_VERSION,
  zone_atlas_version: FACE_ZONE_ATLAS_VERSION,
  scan: {
    scan_id: '',
    image_set_hash: '',
    capture_type: 'baseline', // baseline | post_treatment
    paired_baseline_scan_id: null,
    modes_received: [...IMAGE_MODES],
    fixed_device_capture_assumption: true,
  },
  model_execution: {
    model_version: '',
    prompt_version: '',
    created_at_iso: '',
  },
  morphology_exclusion_map: null,
  reconciliation_audit: [],
  features: {
    // One object for each CORE_FEATURE_IDS item.
    // See createEmptyFeatureEvidence().
  },
}

export function createEmptyComponentEvidence() {
  return {
    grade_0_to_5: 0,
    plausible_grade_range_0_to_5: { min: 0, max: 0 },
    assessment_confidence_0_to_100: 100,
    measurement_primitives: {
      coverage_0_to_100: 0,
      contrast_0_to_100: 0,
      cross_mode_corroboration_0_to_100: 0,
      regional_salience_0_to_100: 0,
    },
    evidence_modes: [],
    corroboration_modes: [],
    reason: '',
  }
}

export function createEmptyZoneEvidence() {
  return {
    assessment_status: 'assessable',
    components: {},
    mode_agreement: 'strong',
    artifact_flags: [],
    visibility_fraction_0_to_100: 100,
    evidence_summary: '',
  }
}

export function createEmptyFeatureEvidence(featureId, applicableZones = FACE_ZONE_IDS) {
  if (!CORE_FEATURE_IDS.includes(featureId)) {
    throw new Error(`Unknown core feature: ${featureId}`)
  }

  return {
    feature_id: featureId,
    applicable_zones: [...applicableZones],
    lead_modes: [],
    support_modes: [],
    treatment_relevance: 'direct', // direct | derived | subtype_only
    zones: Object.fromEntries(
      applicableZones.map((zoneId) => [zoneId, createEmptyZoneEvidence()]),
    ),
    global_artifact_flags: [],
    global_evidence_summary: '',
  }
}

/**
 * Deterministic output consumed by treatment planning and reporting.
 */
export const SKIN_STATE_V2_TEMPLATE = {
  schema_version: SKIN_STATE_SCHEMA_VERSION,
  source_evidence_schema_version: VISION_EVIDENCE_SCHEMA_VERSION,
  zone_atlas_version: FACE_ZONE_ATLAS_VERSION,
  scan: {
    scan_id: '',
    image_set_hash: '',
    capture_type: 'baseline',
    paired_baseline_scan_id: null,
  },
  scoring_execution: {
    mapper_version: '',
    formula_config_version: '',
    created_at_iso: '',
    immutable_assessment: true,
  },
  core_features: {
    // [feature_id]: {
    //   global_burden_score_1_to_100: 1,
    //   zone_scores_1_to_100: { [zone_id]: 1 },
    //   score_reliability: { tier, deductions, reasons },
    //   dominant_zones: [],
    //   peak_zone: null,
    // }
  },
  derived_report_parameters: {
    // [parameter_id]: {
    //   internal_burden_score_1_to_100: 1,
    //   display_score_1_to_100: 100,
    //   display_polarity: 'higher_is_better' | 'higher_is_worse' | 'label_only',
    //   display_label: '',
    // }
  },
  skin_type: {
    label: 'balanced',
    modifiers: [],
    evidence: {
      t_zone_oiliness_1_to_100: 1,
      cheek_oiliness_1_to_100: 1,
      dehydration_burden_1_to_100: 1,
      regional_pattern: 'uniform',
    },
  },
}
