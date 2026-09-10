import { FACE_ZONE_ATLAS_V2 } from './faceZoneAtlasV2.js'
import {
  getClinicStepDurationRuleV3_4,
} from './clinicStepDurationRulesV3_4.js'

export const CLINICAL_CONSTRAINTS_V2_VERSION = 'aia_clinical_constraints_v3.4.0'

export const ELIGIBILITY_STATUS = {
  ALLOWED: 'allowed',
  CAUTION: 'allowed_with_caution',
  DENIED: 'denied',
  NOT_APPLICABLE: 'not_applicable',
}

export const INTENSITY_LEVELS = ['none', 'very_low', 'low', 'low_medium', 'medium', 'medium_high', 'high']

export const FULL_SKIN_FACE_ZONES = [...FACE_ZONE_ATLAS_V2.groups.full_skin_face]
export const CHEEK_ZONES = [...FACE_ZONE_ATLAS_V2.groups.cheeks]
export const FOREHEAD_ZONES = [...FACE_ZONE_ATLAS_V2.groups.forehead]
export const T_ZONE_ZONES = [...FACE_ZONE_ATLAS_V2.groups.t_zone]
export const PROTECTION_ZONES = ['peri_orbital_left', 'peri_orbital_right', 'perioral', 'lips']

export const V2_SAFETY_THRESHOLDS = {
  scan_reliability: {
    caution_below: 70,
    strongly_caution_below: 60,
  },
  global: {
    barrier_stress: { caution: 58, deny_aggressive: 78 },
    erythema_redness: { caution: 60, deny_aggressive: 78 },
    visual_dehydration: { caution: 66, deny_aggressive: 84 },
    active_inflammatory_acne: { caution_for_non_acne_energy: 62 },
  },
  zone: {
    barrier_stress: { caution: 66, deny_aggressive: 82 },
    erythema_redness: { caution: 70, deny_aggressive: 85 },
    visual_dehydration: { caution: 74, deny_aggressive: 88 },
    active_inflammatory_acne: {
      q_switch_caution: 30,
      q_switch_deny: 50,
    },
  },
  temperature_c: {
    caution_global: 36.4,
    deny_aggressive_global: 36.9,
    moderate_cheek_asymmetry: 0.3,
    significant_cheek_asymmetry: 0.4,
    relative_zone_delta: 0.4,
  },
}

const PROFILE = (definition) => ({
  category: 'supportive',
  energy_based: false,
  laser_based: false,
  heat_based: false,
  invasive: false,
  exfoliation_strength: 'none',
  risk_class: 'gentle',
  default_max_intensity: 'medium',
  pregnancy_allowed: false,
  breastfeeding_allowed: true,
  allowed_zones: FULL_SKIN_FACE_ZONES,
  protection_zones: [],
  aliases: [],
  ...definition,
})

/**
 * This is an eligibility catalogue, not yet the response/efficacy library.
 * It describes what each modality is and where it may safely be considered.
 */
export const MODALITY_PROFILES_V2 = {
  hydrafacial: PROFILE({
    name: 'Hydrafacial Machine',
    category: 'machine',
    exfoliation_strength: 'low',
    risk_class: 'gentle',
    pregnancy_allowed: true,
    aliases: ['Hydrafacial', 'Hydrafacial Machine', 'Suction Probe / Bubble Pen'],
  }),
  q_switch_laser: PROFILE({
    name: 'Q-Switch Laser',
    category: 'laser',
    energy_based: true,
    laser_based: true,
    heat_based: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium_high',
    pregnancy_allowed: false,
    aliases: ['Q-Switch', 'Q Switch', 'Q-Switch Laser', 'Targeted Laser'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  carbon_facial: PROFILE({
    name: 'Carbon Facial',
    category: 'laser',
    energy_based: true,
    laser_based: true,
    heat_based: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['Carbon Facial', 'Carbon Laser Facial', 'Hollywood Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  radio_frequency: PROFILE({
    name: 'Radio Frequency Machine',
    category: 'energy',
    energy_based: true,
    heat_based: true,
    risk_class: 'moderate',
    default_max_intensity: 'medium_high',
    pregnancy_allowed: false,
    aliases: ['RF', 'Radio Frequency', 'Radio Frequency Machine', 'Lifting Probe (RF)'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  hifu: PROFILE({
    name: 'HiFU Machine',
    category: 'energy',
    energy_based: true,
    heat_based: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium_high',
    pregnancy_allowed: false,
    aliases: ['HiFU', 'HIFU', 'HiFU Machine'],
    allowed_zones: [
      'temple_left', 'temple_right',
      'malar_medial_left', 'malar_medial_right',
      'cheek_lateral_left', 'cheek_lateral_right',
      'chin', 'jawline_left', 'jawline_right',
    ],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'perioral', 'lips'],
  }),
  microneedling_rf: PROFILE({
    name: 'Micro Needling Radio Frequency',
    category: 'energy_invasive',
    energy_based: true,
    heat_based: true,
    invasive: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['MNRF', 'Microneedling RF', 'Micro Needling Radio Frequency'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  led_light_therapy: PROFILE({
    name: 'LED Light Therapy',
    category: 'energy_supportive',
    energy_based: true,
    risk_class: 'gentle',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['LED', 'LED Light Therapy', 'LED Light Therapy Machine'],
  }),
  microneedling: PROFILE({
    name: 'Micro Needling Machine',
    category: 'invasive',
    invasive: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['Microneedling', 'Micro Needling', 'Micro Needling Machine'],
    protection_zones: ['lips'],
  }),
  dermapen: PROFILE({
    name: 'Dermapen',
    category: 'invasive',
    invasive: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['Dermapen'],
    protection_zones: ['lips'],
  }),
  dermaroller: PROFILE({
    name: 'Dermaroller',
    category: 'invasive',
    invasive: true,
    risk_class: 'aggressive',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['Dermaroller'],
    protection_zones: ['lips'],
  }),
  microdermabrasion: PROFILE({
    name: 'Microdermabrasion Machine',
    category: 'machine',
    exfoliation_strength: 'medium',
    risk_class: 'moderate',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['Microdermabrasion', 'Microdermabrasion Machine'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  high_frequency: PROFILE({
    name: 'High-Frequency Machine',
    category: 'energy_supportive',
    energy_based: true,
    risk_class: 'moderate',
    default_max_intensity: 'medium',
    pregnancy_allowed: false,
    aliases: ['High Frequency', 'High-Frequency Machine'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  jet_infusion: PROFILE({
    name: 'Jet / Oxygen Infusion',
    category: 'supportive',
    risk_class: 'gentle',
    pregnancy_allowed: true,
    aliases: ['Jet Infusion', 'Oxygen Injection', 'Oxygen Injection (Hydra spray)'],
  }),
  ultrasound_infusion: PROFILE({
    name: 'Ultrasound Infusion',
    category: 'supportive',
    energy_based: true,
    risk_class: 'gentle',
    pregnancy_allowed: false,
    aliases: ['Ultrasound Infusion', 'Face Ultrasound Infusion Probe', 'Ocular Ultrasound Infusion Probe'],
  }),
  high_intensity_extraction: PROFILE({
    name: 'Targeted Extraction',
    category: 'manual_or_machine',
    risk_class: 'moderate',
    default_max_intensity: 'medium',
    pregnancy_allowed: true,
    aliases: ['Extraction', 'Targeted Extraction'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  face_massage_lymphatic: PROFILE({
    name: 'Face Massage + Lymphatic Drainage',
    category: 'supportive',
    risk_class: 'gentle',
    pregnancy_allowed: true,
    aliases: ['Face Massage', 'Lymphatic Drainage', 'Face Massage + Lymphatic Drainage'],
    protection_zones: ['lips'],
  }),

  party_peel: PROFILE({
    name: 'Party Peel', category: 'chemical_peel', exfoliation_strength: 'medium', risk_class: 'moderate',
    default_max_intensity: 'medium', pregnancy_allowed: true, aliases: ['Party Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  whitening_peel: PROFILE({
    name: 'Whitening Peel', category: 'chemical_peel', exfoliation_strength: 'medium', risk_class: 'moderate',
    default_max_intensity: 'medium', aliases: ['Whitening Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  sali_ds_peel: PROFILE({
    name: 'Sali DS Peel', category: 'chemical_peel', exfoliation_strength: 'high', risk_class: 'aggressive',
    default_max_intensity: 'medium_high', aliases: ['Sali DS Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  salicylic_30_peel: PROFILE({
    name: 'Salicylic Acid 30% Peel', category: 'chemical_peel', exfoliation_strength: 'high', risk_class: 'aggressive',
    default_max_intensity: 'high', aliases: ['Salicylic Acid 30% Peel', '30% Salicylic Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  pumpkin_peel: PROFILE({
    name: 'Gel Based Pumpkin Peel', category: 'chemical_peel', exfoliation_strength: 'low_to_medium', risk_class: 'gentle',
    default_max_intensity: 'medium', pregnancy_allowed: true, aliases: ['Gel Based Pumpkin Peel', 'Pumpkin Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  mandelic_peel: PROFILE({
    name: 'Gel Based Mandelic Peel', category: 'chemical_peel', exfoliation_strength: 'low_to_medium', risk_class: 'gentle',
    default_max_intensity: 'medium', aliases: ['Gel Based Mandelic Peel', 'Mandelic Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  fusion_peel_e: PROFILE({
    name: 'Fusion Peel-E', category: 'chemical_peel', exfoliation_strength: 'medium_to_high', risk_class: 'aggressive',
    default_max_intensity: 'medium_high', aliases: ['Fusion Peel-E', 'Fusion Peel E'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  glyco_35_peel: PROFILE({
    name: 'Glyco Peel 35', category: 'chemical_peel', exfoliation_strength: 'medium_to_high', risk_class: 'aggressive',
    default_max_intensity: 'medium_high', aliases: ['Glyco Peel 35', 'Glycolic 35 Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  combination_peel: PROFILE({
    name: 'Combination Peel', category: 'chemical_peel', exfoliation_strength: 'medium_to_high', risk_class: 'aggressive',
    default_max_intensity: 'medium_high', aliases: ['Combination Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
  salicylic_20_peel: PROFILE({
    name: '20% Salicylic Acid Peel', category: 'chemical_peel', exfoliation_strength: 'medium', risk_class: 'moderate',
    default_max_intensity: 'medium', aliases: ['20% Salicylic Acid Peel', 'Salicylic Acid 20% Peel'],
    protection_zones: ['peri_orbital_left', 'peri_orbital_right', 'lips'],
  }),
}

export const MODALITY_ID_BY_ALIAS = Object.fromEntries(
  Object.entries(MODALITY_PROFILES_V2).flatMap(([id, profile]) =>
    [profile.name, ...(profile.aliases ?? [])].map((alias) => [alias.trim().toLowerCase(), id]),
  ),
)

export function resolveModalityIdV2(modality) {
  if (!modality) return null
  if (MODALITY_PROFILES_V2[modality]) return modality
  return MODALITY_ID_BY_ALIAS[String(modality).trim().toLowerCase()] ?? null
}

export const SESSION_LEVEL_RULES_V2 = {
  mandatory_final_step: {
    rule_id: 'V2_SESSION_FINISH_SERUM_MOISTURIZER_SUNSCREEN',
    instruction: 'Every facial treatment must finish with Serum + Moisturizer + Sunscreen as one final step.',
    duration_minutes:
      getClinicStepDurationRuleV3_4(
        'final_serum_moisturizer_sunscreen',
      ).min_minutes,
  },
  lymphatic_drainage: {
    rule_id: 'V2_SESSION_LYMPHATIC_WHEN_POSSIBLE',
    instruction: 'Add face massage and lymphatic drainage wherever clinically appropriate and compatible with the corrective plan.',
  },
  sequence_freedom: {
    rule_id: 'V2_SESSION_NO_FIXED_SEQUENCE',
    instruction: 'There is no fixed sequence; different zones may receive different treatments when safety and flow remain coherent.',
  },
  active_acne_spot_support: {
    rule_id: 'V2_ACTIVE_ACNE_SPOT_SALICYLIC',
    instruction: 'When active acne lesions are present, include a targeted salicylic-family spot step unless contraindicated.',
  },
}

export const PATIENT_HISTORY_RULES_V2 = {
  hifu_age_window: { min_age: 30, max_age: 60 },
  sun_exposure: {
    high_hours_per_day: 2,
    moderate_hours_per_day: 1,
  },
  pregnancy: {
    allowed_modality_ids: ['hydrafacial', 'jet_infusion', 'high_intensity_extraction', 'face_massage_lymphatic', 'party_peel', 'pumpkin_peel'],
  },
  recent_laser_days: 7,
  recent_event_days: 7,
  recent_travel_days: 7,
  ingredient_exclusions: {
    aloe_vera_allergy: ['aloe vera', 'aloe barbadensis'],
    vitamin_c_allergy: ['vitamin c', 'ascorbic acid', 'ethyl ascorbic acid'],
    breastfeeding: ['retinol', 'retinaldehyde', 'retinoid'],
  },
}

export const RULE_CATALOG_V2 = {
  V2_UNKNOWN_MODALITY: 'The requested modality is not present in the V2 eligibility catalogue.',
  V2_ZONE_NOT_SUPPORTED: 'The modality is not intended for this facial zone.',
  V2_PROTECTION_ZONE: 'The zone requires protection or explicit specialist clearance for this modality.',
  V2_PREGNANCY_DENY: 'The modality is outside the pregnancy-safe set defined in the current clinic constraints.',
  V2_BREASTFEEDING_RETINOID_EXCLUSION: 'Retinoid-containing products or peels must be excluded while breastfeeding.',
  V2_HIFU_AGE_DENY: 'HiFU is permitted only from age 30 through 60 when clinically indicated.',
  V2_HIGH_SUN_QSWITCH_DENY: 'High daily sun exposure blocks Q-Switch/laser pigment treatment.',
  V2_HIGH_SUN_STRONG_PEEL_DENY: 'High daily sun exposure blocks medium-high/high chemical peels.',
  V2_MODERATE_SUN_STRONG_PEEL_DENY: 'Moderate daily sun exposure blocks high/deep-equivalent peels.',
  V2_TRAVEL_EVENT_QSWITCH_DENY: 'Travel or an important social event within seven days blocks Q-Switch.',
  V2_TRAVEL_EVENT_STRONG_PEEL_DENY: 'Travel or an important social event within seven days blocks strong peels.',
  V2_RECENT_ACID_QSWITCH_DENY: 'Recent salicylic/glycolic use blocks Q-Switch in this session.',
  V2_RECENT_ACID_STRONG_PEEL_DENY: 'Recent salicylic/glycolic use blocks strong peels.',
  V2_RECENT_RETINOL_QSWITCH_DENY: 'Retinol within 24 hours blocks Q-Switch and aggressive exfoliation.',
  V2_RECENT_RETINOL_AGGRESSIVE_DENY: 'Retinol within 24 hours blocks medium-high/high peels and aggressive exfoliation.',
  V2_RECENT_RETINOL_ENERGY_CAUTION: 'Recent retinol restricts otherwise allowed non-ablative energy to low intensity when barrier/redness are stable.',
  V2_RECENT_LASER_DENY: 'Recent laser/high-heat treatment blocks laser and high-heat modalities.',
  V2_BLOOD_THINNER_AGGRESSIVE_DENY: 'Blood thinners restrict the session to gentle, non-invasive treatment.',
  V2_GLOBAL_BARRIER_DENY: 'Global barrier stress is too high for aggressive treatment.',
  V2_ZONE_BARRIER_DENY: 'Zone-level barrier stress is too high for aggressive treatment.',
  V2_GLOBAL_ERYTHEMA_DENY: 'Global erythema burden is too high for aggressive treatment.',
  V2_ZONE_ERYTHEMA_DENY: 'Zone-level erythema burden is too high for aggressive treatment.',
  V2_GLOBAL_DEHYDRATION_DENY: 'Global visual dehydration burden is too high for aggressive treatment.',
  V2_ZONE_DEHYDRATION_DENY: 'Zone-level visual dehydration burden is too high for aggressive treatment.',
  V2_SKIN_STATE_CAUTION: 'Skin-state burden requires reduced intensity and additional recovery support.',
  V2_QSWITCH_ACTIVE_ACNE_ZONE_DENY: 'Q-Switch cannot be used directly over a zone with substantial active inflammatory acne.',
  V2_QSWITCH_ACTIVE_ACNE_ZONE_CAUTION: 'Q-Switch in this zone requires avoidance of active lesions and conservative settings.',
  V2_SCAN_RELIABILITY_CAUTION: 'Low evidence reliability does not automatically deny treatment but requires caution or recapture.',
  V2_TEMPERATURE_GLOBAL_DENY: 'Elevated average facial surface temperature blocks aggressive heat/exfoliation.',
  V2_TEMPERATURE_GLOBAL_CAUTION: 'Elevated average facial surface temperature requires reduced intensity and monitoring.',
  V2_TEMPERATURE_LOCAL_CAUTION: 'Local temperature asymmetry requires reduced intensity on the hotter side.',
}

export function assertClinicalConstraintsV2Integrity() {
  for (const [id, profile] of Object.entries(MODALITY_PROFILES_V2)) {
    if (!profile.name) throw new Error(`${id}: missing name`)
    if (!INTENSITY_LEVELS.includes(profile.default_max_intensity)) {
      throw new Error(`${id}: invalid default_max_intensity ${profile.default_max_intensity}`)
    }
    for (const zoneId of profile.allowed_zones) {
      if (!FACE_ZONE_ATLAS_V2.zones[zoneId]) throw new Error(`${id}: unknown allowed zone ${zoneId}`)
    }
    for (const zoneId of profile.protection_zones ?? []) {
      if (!FACE_ZONE_ATLAS_V2.zones[zoneId]) throw new Error(`${id}: unknown protection zone ${zoneId}`)
    }
  }
  return true
}
