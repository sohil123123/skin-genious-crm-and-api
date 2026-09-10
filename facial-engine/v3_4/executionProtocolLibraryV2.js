import {
  MODALITY_RESPONSE_LIBRARY_V2,
} from './modalityResponseLibraryV2.js'
import {
  CLINIC_STEP_DURATION_RULES_VERSION,
  getClinicDurationRangeV3_4,
  getClinicStepDurationRuleV3_4,
} from './clinicStepDurationRulesV3_4.js'

export const EXECUTION_PROTOCOL_LIBRARY_VERSION =
  'aia_execution_protocol_library_v3.4.0'

const HIGH_RISK_CATEGORIES = new Set([
  'laser',
  'laser_special_protocol',
  'energy',
  'energy_invasive',
  'invasive',
  'chemical_peel',
])

const MODALITY_STAGE_OVERRIDES = {
  hydrafacial_cutin_spatula: 20,
  microdermabrasion_diamond: 20,
  microdermabrasion_crystal: 20,
  hydrafacial_suction_extraction: 30,
  manual_extraction: 30,
  high_frequency_glass_electrode: 35,
  salicylic_spot: 36,
  carbon_facial: 40,
  q_switch_1064: 42,
  q_switch_532: 42,
  q_switch_755: 42,
  q_switch_lip_pigmentation_protocol: 42,
  radio_frequency: 45,
  hifu: 45,
  microneedling: 45,
  dermaroller: 45,
  microneedling_rf: 45,
  party_peel: 38,
  whitening_peel: 38,
  sali_ds_peel: 38,
  salicylic_30_peel: 38,
  salicylic_20_peel: 38,
  pumpkin_peel: 38,
  mandelic_peel: 38,
  fusion_peel_e: 38,
  glyco_35_peel: 38,
  combination_peel: 38,
  yellow_peel: 38,
  cosmelan_protocol: 38,
  biorepeelcl3: 38,
  salmon_peel: 38,
  jet_oxygen_infusion: 55,
  ultrasound_infusion_face: 55,
  ultrasound_infusion_ocular: 55,
  led_blue: 60,
  led_red: 60,
  led_green: 60,
  hydrafacial_ice_probe: 62,
  lymphatic_drainage: 65,
  charcoal_mask: 70,
  calming_mask: 70,
  brightening_mask: 70,
  hydrating_mask: 70,
  lifting_mask: 70,
  final_serum_moisturizer_sunscreen: 90,
  hydrafacial_full_protocol: 25,
  hydrafacial_teenage_line_probe: 25,
}

const MODALITY_REQUIREMENTS = {
  // Simple facial steps are intentionally auto-released. Their duration is
  // chosen by the optimizer/compiler and they do not require fake preset data.
  hydrafacial_full_protocol: [],
  hydrafacial_cutin_spatula: [],
  hydrafacial_suction_extraction: [],
  hydrafacial_ice_probe: [],
  hydrafacial_teenage_line_probe: [],
  jet_oxygen_infusion: [],
  ultrasound_infusion_face: [],
  ultrasound_infusion_ocular: [],
  led_blue: [],
  led_red: [],
  led_green: [],
  microdermabrasion_diamond: [],
  microdermabrasion_crystal: [],
  high_frequency_glass_electrode: [],
  manual_extraction: [],
  lymphatic_drainage: [],
  charcoal_mask: [],
  calming_mask: [],
  brightening_mask: [],
  hydrating_mask: [],
  lifting_mask: [],
  final_serum_moisturizer_sunscreen: [],
  salicylic_spot: [],

  q_switch_1064: [
    'clinic_protocol_id',
    'wavelength_nm',
    'fluence_or_energy_by_zone',
    'frequency_hz',
    'spot_size_or_handpiece',
    'passes_or_shots_by_zone',
  ],
  q_switch_532: [],
  q_switch_755: [],
  q_switch_lip_pigmentation_protocol: [],
  carbon_facial: [
    'clinic_protocol_id',
    'carbon_product',
    'wavelength_nm',
    'fluence_or_energy_by_zone',
    'frequency_hz',
    'spot_size_or_handpiece',
    'passes_by_zone',
  ],
  radio_frequency: [
    'clinic_protocol_id',
    'device_preset_or_energy_by_zone',
    'passes_by_zone',
    'contact_medium',
  ],
  hifu: [
    'clinic_protocol_id',
    'cartridge_depth_by_zone',
  ],
  microneedling: [
    'clinic_protocol_id',
    'needle_depth_by_zone',
    'passes_by_zone',
  ],
  dermaroller: [
    'clinic_protocol_id',
    'needle_depth',
    'passes_by_direction_and_zone',
  ],
  microneedling_rf: [
    'clinic_protocol_id',
    'needle_depth_by_zone',
    'passes_by_zone',
  ],

  // Peel neutralisation is the only hard execution validation required here.
  // Contact time, layers and endpoint remain AI-customised facial-protocol fields.
  party_peel: ['clinic_protocol_id', 'neutralization_method'],
  whitening_peel: ['clinic_protocol_id', 'neutralization_method'],
  sali_ds_peel: ['clinic_protocol_id', 'neutralization_or_removal_method'],
  salicylic_30_peel: ['clinic_protocol_id', 'neutralization_or_removal_method'],
  salicylic_20_peel: ['clinic_protocol_id', 'neutralization_or_removal_method'],
  pumpkin_peel: ['clinic_protocol_id', 'neutralization_or_removal_method'],
  mandelic_peel: ['clinic_protocol_id', 'neutralization_or_removal_method'],
  fusion_peel_e: ['clinic_protocol_id', 'neutralization_method'],
  glyco_35_peel: ['clinic_protocol_id', 'neutralization_method'],
  combination_peel: ['clinic_protocol_id', 'neutralization_or_removal_method'],
  yellow_peel: ['clinic_protocol_id', 'removal_and_aftercare'],
  cosmelan_protocol: ['clinic_protocol_id', 'removal_method'],
  biorepeelcl3: ['clinic_protocol_id', 'removal_method'],
  salmon_peel: ['clinic_protocol_id', 'removal_method'],
}

const MODALITY_EQUIPMENT = {
  hydrafacial_full_protocol: ['Hydrafacial machine', 'selected probes', 'selected solutions'],
  hydrafacial_cutin_spatula: ['Hydrafacial machine', 'cutin removal spatula'],
  hydrafacial_suction_extraction: ['Hydrafacial machine', 'suction/bubble pen'],
  hydrafacial_ice_probe: ['Hydrafacial machine', 'ice probe'],
  jet_oxygen_infusion: ['jet/oxygen infusion handpiece', 'selected solution'],
  ultrasound_infusion_face: ['face ultrasound infusion probe', 'selected solution'],
  ultrasound_infusion_ocular: ['ocular ultrasound infusion probe', 'selected solution'],
  q_switch_1064: ['Q-Switch laser', '1064-nm handpiece', 'laser eye protection'],
  q_switch_532: ['Q-Switch laser', '532-nm handpiece', 'laser eye protection'],
  q_switch_755: ['verified 755-nm Q-Switch handpiece', 'laser eye protection'],
  carbon_facial: ['Q-Switch laser', 'carbon lotion', 'laser eye protection'],
  radio_frequency: ['RF machine', 'RF probe', 'contact gel'],
  hifu: ['HiFU machine', 'selected cartridges', 'ultrasound/contact gel'],
  microneedling: ['Dermapen/microneedling device', 'sterile cartridge', 'sterile consumables'],
  dermaroller: ['sterile dermaroller', 'sterile consumables'],
  microneedling_rf: ['MNRF device', 'sterile RF needle cartridge', 'sterile consumables'],
  led_blue: ['LED device in blue mode', 'eye protection'],
  led_red: ['LED device in red mode', 'eye protection'],
  led_green: ['LED device in green mode', 'eye protection'],
  microdermabrasion_diamond: ['microdermabrasion machine', 'diamond tip'],
  microdermabrasion_crystal: ['microdermabrasion machine', 'crystal tip'],
  high_frequency_glass_electrode: ['high-frequency machine', 'selected glass electrode'],
  manual_extraction: ['comedone extractor/loop', 'gauze', 'antiseptic supplies'],
  lymphatic_drainage: ['approved massage medium'],
  charcoal_mask: ['charcoal mask', 'mask applicator'],
  calming_mask: ['calming mask', 'mask applicator'],
  brightening_mask: ['brightening mask', 'mask applicator'],
  hydrating_mask: ['hydrating mask', 'mask applicator'],
  lifting_mask: ['lifting mask', 'mask applicator'],
  final_serum_moisturizer_sunscreen: ['selected serum', 'selected moisturizer', 'selected sunscreen'],
}

const CATEGORY_INSTRUCTION = {
  hydradermabrasion: {
    technique:
      'Follow the approved modular Hydrafacial sequence. Treat only the selected zones and use only the selected components. Keep the handpiece moving, respect local intensity ceilings, and avoid protected zones.',
    endpoint:
      'Selected zones appear clean and hydrated without persistent erythema, bruising, abrasion, or discomfort.',
    safety_signals: [
      'persistent erythema',
      'bruising or petechiae',
      'burning or pain',
      'unexpected suction marks',
    ],
    stop_conditions: [
      'persistent discomfort',
      'visible bruising',
      'barrier disruption',
      'unexpected device response',
    ],
    transition:
      'Remove all residue, reassess treated zones, and proceed only when the skin is comfortable.',
  },
  hydrafacial_probe: {
    technique:
      'Use only the named probe on the selected zones. Apply the clinic-approved preset, contact medium and passes. Do not add unselected probes.',
    endpoint:
      'The intended local endpoint is reached without persistent redness, bruising, discomfort or barrier disruption.',
    safety_signals: [
      'persistent redness',
      'bruising',
      'burning',
      'skin dragging or abrasion',
    ],
    stop_conditions: [
      'pain above tolerance',
      'bruising',
      'barrier disruption',
      'equipment malfunction',
    ],
    transition:
      'Remove residue and confirm local comfort before moving to the next selected step.',
  },
  laser: {
    technique:
      'Confirm wavelength, handpiece, zone map and AI-customised facial protocol before enabling the device. Protect eyes and all excluded zones. Deliver only the compiled personalised parameters and coverage recorded in the protocol.',
    endpoint:
      'Reach only the protocol-defined clinical endpoint without exceeding local tolerance.',
    safety_signals: [
      'unexpected whitening, greying or tissue response',
      'excessive heat or pain',
      'persistent erythema outside the expected endpoint',
      'device alarm or inconsistent output',
    ],
    stop_conditions: [
      'endpoint exceeded',
      'unexpected pain',
      'unexpected tissue response',
      'loss of eye protection',
      'device fault',
    ],
    transition:
      'Stop energy delivery, cool or recover as prescribed, and document actual shots/passes and endpoint.',
  },
  laser_special_protocol: {
    technique:
      'Proceed only under the doctor-constraint validated adaptive protocol. Confirm case clearance, exact zone, eye protection, parameters and aftercare before starting.',
    endpoint:
      'Only the doctor-approved endpoint is permitted.',
    safety_signals: [
      'unexpected pain',
      'unexpected whitening or tissue response',
      'swelling beyond protocol expectation',
    ],
    stop_conditions: [
      'doctor protocol mismatch',
      'endpoint exceeded',
      'unexpected reaction',
      'device fault',
    ],
    transition:
      'Apply the approved recovery and aftercare steps and document the exact delivered parameters.',
  },
  energy: {
    technique:
      'Confirm the approved device protocol, zone map, contact medium and intensity ceiling. Maintain the prescribed movement/vector pattern and monitor comfort and the protocol endpoint continuously.',
    endpoint:
      'Protocol-defined thermal, lift or treatment endpoint without excessive heat, pain or focal injury.',
    safety_signals: [
      'hot spots',
      'pain or sharp sensation',
      'persistent erythema',
      'uneven device contact',
    ],
    stop_conditions: [
      'temperature or endpoint limit reached',
      'pain above tolerance',
      'device contact lost',
      'unexpected skin response',
    ],
    transition:
      'Remove contact medium, inspect every treated zone and provide selected recovery support.',
  },
  energy_invasive: {
    technique:
      'Maintain sterile technique. Confirm cartridge, depth, energy, zone map and exclusions from the stored protocol. Deliver the exact approved pattern while monitoring endpoint and tolerance.',
    endpoint:
      'Even protocol-defined endpoint without excessive bleeding, heat, pain or tissue injury.',
    safety_signals: [
      'excessive bleeding',
      'unexpected heat or pain',
      'uneven penetration',
      'unexpected tissue response',
    ],
    stop_conditions: [
      'sterility breach',
      'endpoint exceeded',
      'unexpected bleeding or pain',
      'device fault',
    ],
    transition:
      'Complete sterile recovery care and record actual depths, energy, passes and tolerance.',
  },
  invasive: {
    technique:
      'Maintain sterile technique. Confirm cartridge or roller, zone-specific depth, passes and approved sterile product. Avoid active inflammatory lesions and protected zones.',
    endpoint:
      'Uniform protocol-defined endpoint without excessive bleeding, trauma or discomfort.',
    safety_signals: [
      'excessive pinpoint bleeding',
      'tearing or dragging',
      'unexpected pain',
      'sterility concern',
    ],
    stop_conditions: [
      'sterility breach',
      'excessive bleeding',
      'endpoint exceeded',
      'unexpected pain',
    ],
    transition:
      'Apply only approved sterile recovery products and document actual depths, passes and tolerance.',
  },
  chemical_peel: {
    technique:
      'Degrease and protect sensitive areas as prescribed. Apply only to the selected zones using the exact stored contact time, layers, endpoint and neutralization/removal method.',
    endpoint:
      'Only the product-specific approved endpoint is permitted.',
    safety_signals: [
      'rapid intense burning',
      'unexpected frosting or colour change',
      'uneven reaction',
      'pain above tolerance',
    ],
    stop_conditions: [
      'endpoint reached early',
      'unexpected burning or pain',
      'unexpected tissue response',
      'timer or protocol mismatch',
    ],
    transition:
      'Neutralize or remove exactly as prescribed, confirm complete removal, and proceed to recovery.',
  },
  mechanical_exfoliation: {
    technique:
      'Use the selected tip and approved preset with controlled, non-overlapping passes. Reduce or avoid treatment over reactive, inflamed or fragile zones.',
    endpoint:
      'Even surface refinement without abrasions, petechiae or persistent erythema.',
    safety_signals: [
      'skin dragging',
      'abrasion',
      'petechiae',
      'persistent redness',
    ],
    stop_conditions: [
      'barrier disruption',
      'pain',
      'unexpected bleeding',
      'device fault',
    ],
    transition:
      'Remove residue, reassess the barrier and continue only with compatible selected steps.',
  },
  photobiomodulation: {
    technique:
      'Confirm the selected colour mode, device preset, exposure time, positioning and eye protection. Keep the device at the approved distance for the full timed exposure.',
    endpoint:
      'Completed prescribed exposure with comfort and no excessive warmth.',
    safety_signals: [
      'eye discomfort',
      'excessive warmth',
      'headache or intolerance',
    ],
    stop_conditions: [
      'eye protection displaced',
      'client intolerance',
      'device fault',
    ],
    transition:
      'Switch off the device before removing eye protection and document the completed exposure.',
  },
  infusion: {
    technique:
      'Confirm the selected solution and compatibility. Use the approved device preset and controlled movement over only the selected zones.',
    endpoint:
      'Even product distribution with comfortable, hydrated appearance and no irritation.',
    safety_signals: [
      'stinging',
      'unexpected redness',
      'skin dragging',
      'allergic-type symptoms',
    ],
    stop_conditions: [
      'allergic-type reaction',
      'persistent stinging',
      'unexpected redness',
      'device fault',
    ],
    transition:
      'Remove excess product if required and confirm comfort before the next step.',
  },
  manual: {
    technique:
      'Prepare the skin and instruments. Extract only suitable visible comedones in the selected zones using minimal controlled pressure and the approved attempt limit.',
    endpoint:
      'Suitable contents removed without tissue tearing, bleeding, bruising or repeated trauma.',
    safety_signals: [
      'bleeding',
      'bruising',
      'marked redness',
      'lesion resistance',
    ],
    stop_conditions: [
      'lesion does not release within attempt limit',
      'bleeding or bruising',
      'client discomfort',
    ],
    transition:
      'Clean the zone, apply the selected recovery step and document any lesion left untreated.',
  },
  energy_supportive: {
    technique:
      'Use the selected electrode and stored preset only on the intended local zones. Maintain the prescribed contact or sparking method and duration.',
    endpoint:
      'Completed local exposure without burning, excessive dryness or discomfort.',
    safety_signals: [
      'sparking beyond protocol',
      'burning',
      'unexpected discomfort',
    ],
    stop_conditions: [
      'burning',
      'device fault',
      'client intolerance',
    ],
    transition:
      'Switch off before removing the electrode and inspect the treated areas.',
  },
  targeted_topical: {
    technique:
      'Apply only to the selected spots or zones using the exact product concentration, contact time and removal method.',
    endpoint:
      'Completed prescribed local contact without spreading to protected skin.',
    safety_signals: [
      'intense burning',
      'unexpected whitening or irritation',
      'product migration',
    ],
    stop_conditions: [
      'endpoint reached early',
      'unexpected pain',
      'product spreads outside target',
    ],
    transition:
      'Remove or neutralize fully and apply the selected recovery step.',
  },
  manual_supportive: {
    technique:
      'Use the approved medium and direction map with light, controlled pressure. Avoid inflamed, recently treated or contraindicated zones.',
    endpoint:
      'Comfortable completion without increased redness, tenderness or irritation.',
    safety_signals: [
      'increased redness',
      'pain',
      'dizziness or discomfort',
    ],
    stop_conditions: [
      'client discomfort',
      'increased inflammation',
      'contraindicated zone encountered',
    ],
    transition:
      'Remove excess medium and proceed to the selected finishing steps.',
  },
  mask: {
    technique:
      'Apply an even layer of the selected mask while avoiding eyes, lips, hairline and protected zones. Keep on for the approved contact time and remove completely.',
    endpoint:
      'Mask removed fully with the skin comfortable and no persistent irritation.',
    safety_signals: [
      'burning',
      'itching',
      'allergic-type symptoms',
    ],
    stop_conditions: [
      'unexpected burning',
      'allergic-type reaction',
      'client intolerance',
    ],
    transition:
      'Remove all residue and proceed to final skincare.',
  },
  mandatory_finishing_step: {
    technique:
      'Apply the selected serum, moisturizer and sunscreen evenly in that order, adjusting quantity for treated and protected zones.',
    endpoint:
      'Even comfortable finish with complete sunscreen coverage and no product intolerance.',
    safety_signals: [
      'stinging',
      'unexpected redness',
      'allergic-type symptoms',
    ],
    stop_conditions: [
      'allergic-type reaction',
      'persistent stinging',
    ],
    transition:
      'Review immediate aftercare and complete the session record.',
  },
}

function protocolReleasePolicy(entry) {
  if (!entry.activation_status?.startsWith('active')) {
    return 'disabled_modality'
  }
  if ((MODALITY_REQUIREMENTS[entry.id] ?? []).length > 0) {
    return 'requires_adaptive_doctor_constraint_protocol'
  }
  return 'built_in_general_protocol'
}

function buildProtocol(modalityId, entry) {
  const category = CATEGORY_INSTRUCTION[entry.category] ??
    CATEGORY_INSTRUCTION.manual_supportive
  const clinicDurationRule =
    getClinicStepDurationRuleV3_4(modalityId)
  const clinicDurationRange =
    getClinicDurationRangeV3_4(modalityId)
  return {
    protocol_id: `execution_${modalityId}_v2`,
    modality_id: modalityId,
    modality_name: entry.name,
    category: entry.category,
    activation_status: entry.activation_status,
    release_policy: protocolReleasePolicy({ ...entry, id: modalityId }),
    sequence_stage: MODALITY_STAGE_OVERRIDES[modalityId] ?? 50,
    duration_range_minutes:
      clinicDurationRange ?? {
        min: Number(entry.typical_duration_minutes?.min ?? 5),
        max: Number(entry.typical_duration_minutes?.max ?? 15),
      },
    duration_policy: clinicDurationRule
      ? {
          source:
            'clinic_staff_observed_operational_time',
          rules_version:
            CLINIC_STEP_DURATION_RULES_VERSION,
          timing_mode:
            clinicDurationRule.timing_mode,
          includes:
            clinicDurationRule.includes,
          minutes_per_ingredient:
            clinicDurationRule.minutes_per_ingredient,
        }
      : null,
    execution_substeps:
      clinicDurationRule?.execution_substeps ?? null,
    required_protocol_parameters: MODALITY_REQUIREMENTS[modalityId] ?? [],
    ingredients_equipments: MODALITY_EQUIPMENT[modalityId] ?? [entry.name],
    therapist_instruction_template: {
      technique: category.technique,
      endpoint: category.endpoint,
      safety_signals: category.safety_signals,
      stop_conditions: category.stop_conditions,
      transition_cue: category.transition,
    },
    patient_script_context: {
      plain_language_action: entry.name,
      immediate_benefit_claims:
        entry.patient_facing_benefit_claims?.immediate_or_short_term ?? [],
      course_benefit_claims:
        entry.patient_facing_benefit_claims?.course_or_delayed ?? [],
      limitations_and_non_claims:
        entry.limitations_and_non_claims ?? [],
    },
    settings_source: 'approved_protocol_library_only',
  }
}

export const MODALITY_EXECUTION_PROTOCOLS_V2 = Object.freeze(
  Object.fromEntries(
    Object.entries(MODALITY_RESPONSE_LIBRARY_V2).map(
      ([modalityId, entry]) => [
        modalityId,
        buildProtocol(modalityId, entry),
      ],
    ),
  ),
)

export const FOUNDATION_EXECUTION_PROTOCOLS_V2 = Object.freeze({
  cleanse_and_prepare: {
    protocol_id: 'foundation_cleanse_and_prepare_v2',
    sequence_stage: 10,
    duration_range_minutes:
      getClinicDurationRangeV3_4(
        'cleanse_and_prepare',
      ),
    duration_policy: {
      source:
        'clinic_staff_observed_operational_time',
      rules_version:
        CLINIC_STEP_DURATION_RULES_VERSION,
      timing_mode:
        getClinicStepDurationRuleV3_4(
          'cleanse_and_prepare',
        ).timing_mode,
      includes:
        getClinicStepDurationRuleV3_4(
          'cleanse_and_prepare',
        ).includes,
    },
    ingredients_equipments: [
      'gentle cleanser',
      'cleansing towels',
      'disposable headband',
      'cotton or gauze',
    ],
    therapist_instruction_template: {
      technique:
        'Secure the hair, remove surface makeup and sunscreen, cleanse the full treatment area thoroughly, rinse or remove completely, and pat dry. Inspect the skin again before corrective treatment.',
      endpoint:
        'Skin is visibly clean, dry where required, and free of product residue.',
      safety_signals: [
        'unexpected stinging',
        'visible irritation',
        'undisclosed makeup or product residue',
      ],
      stop_conditions: [
        'allergic-type reaction',
        'new contraindication identified',
      ],
      transition_cue:
        'Confirm the face is fully prepared and complete any procedure-specific degreasing or protection before the next step.',
    },
    patient_script_context: {
      plain_language_action: 'cleanse and prepare your skin',
      immediate_benefit_claims: [
        'creates a clean, even base for the personalised treatment',
      ],
      limitations_and_non_claims: [],
    },
  },
})

export function getExecutionProtocolV2(modalityId) {
  return MODALITY_EXECUTION_PROTOCOLS_V2[modalityId] ?? null
}

export function validateExecutionProtocolLibraryV2() {
  const errors = []
  const modalityIds = Object.keys(MODALITY_RESPONSE_LIBRARY_V2)
  const protocolIds = Object.keys(MODALITY_EXECUTION_PROTOCOLS_V2)

  for (const modalityId of modalityIds) {
    const protocol = MODALITY_EXECUTION_PROTOCOLS_V2[modalityId]
    if (!protocol) {
      errors.push(`Missing execution protocol for ${modalityId}.`)
      continue
    }
    if (
      !Number.isFinite(protocol.sequence_stage) ||
      protocol.duration_range_minutes.min >
        protocol.duration_range_minutes.max
    ) {
      errors.push(`Invalid sequence or duration for ${modalityId}.`)
    }
  }

  for (const protocolId of protocolIds) {
    if (!MODALITY_RESPONSE_LIBRARY_V2[protocolId]) {
      errors.push(`Execution protocol has unknown modality ${protocolId}.`)
    }
  }

  return {
    valid: errors.length === 0,
    version: EXECUTION_PROTOCOL_LIBRARY_VERSION,
    modality_count: modalityIds.length,
    protocol_count: protocolIds.length,
    errors,
  }
}
