export const CLINIC_STEP_DURATION_RULES_VERSION =
  'aia_clinic_step_duration_rules_v3.4.0'

export const CLINIC_STEP_DURATION_SOURCE_V3_4 = Object.freeze({
  source_type: 'clinic_staff_observed_operational_time',
  approved_for_engine_use: true,
  recorded_at: '2026-08-01',
  scope: 'general_facial_treatment_engine',
  note:
    'These are actual staff-observed treatment-step times. They replace broad engineering estimates but do not alter clinical eligibility, device settings, treatment efficacy or safety constraints.',
})

export const INFUSION_MODALITY_IDS_V3_4 = Object.freeze([
  'jet_oxygen_infusion',
  'ultrasound_infusion_face',
  'ultrasound_infusion_ocular',
])

export const PEEL_OFF_MASK_MODALITY_IDS_V3_4 = Object.freeze([
  'charcoal_mask',
  'calming_mask',
  'brightening_mask',
  'hydrating_mask',
  'lifting_mask',
])

export const CHEMICAL_PEEL_MODALITY_IDS_V3_4 = Object.freeze([
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

const durationRule = ({
  min,
  max,
  timing_mode,
  includes = [],
  minutes_per_ingredient = null,
  execution_substeps = null,
  note = null,
}) => Object.freeze({
  min_minutes: min,
  max_minutes: max,
  timing_mode,
  includes: Object.freeze([...includes]),
  minutes_per_ingredient,
  execution_substeps:
    execution_substeps === null
      ? null
      : Object.freeze(
          execution_substeps.map((step) =>
            Object.freeze({ ...step }),
          ),
        ),
  source:
    CLINIC_STEP_DURATION_SOURCE_V3_4.source_type,
  note,
})

const baseRules = {
  cleanse_and_prepare: durationRule({
    min: 2,
    max: 2,
    timing_mode: 'fixed',
    includes: [
      'surface cleansing',
      'complete cleanser removal',
      'pat dry and preparation check',
    ],
  }),

  hydrafacial_suction_extraction: durationRule({
    min: 2,
    max: 4,
    timing_mode: 'adaptive_within_clinic_range',
    includes: [
      'selected-zone suction probe application',
      'brief residue removal and comfort check',
    ],
  }),

  carbon_facial: durationRule({
    min: 7,
    max: 7,
    timing_mode: 'fixed_substeps',
    includes: [
      'carbon lotion application and drying',
      'Q-Switch laser delivery over carbon',
    ],
    execution_substeps: [
      {
        substep_id:
          'carbon_lotion_application_and_dry',
        title:
          'Apply Carbon Lotion and Allow to Dry',
        duration_minutes: 3,
        sequence_offset: 0,
        parameter_fields: ['carbon_product'],
        ingredients_equipments: [
          'carbon lotion',
          'mask or lotion applicator',
        ],
        technique:
          'Apply an even, controlled layer of the approved carbon lotion over only the selected treatment zones. Keep eyes, lips, hairline and protected areas clear. Allow the layer to dry fully before laser delivery.',
        endpoint:
          'A thin, even and visibly dry carbon layer is present over the selected zones without pooling or migration.',
        transition_cue:
          'Confirm the carbon layer is dry, apply laser eye protection and load the compiled Q-Switch settings before starting the laser substep.',
      },
      {
        substep_id: 'carbon_qswitch_laser',
        title: 'Q-Switch Laser over Carbon',
        duration_minutes: 4,
        sequence_offset: 1,
        parameter_fields: [
          'wavelength_nm',
          'fluence_or_energy_by_zone',
          'frequency_hz',
          'spot_size_or_handpiece',
          'passes_by_zone',
        ],
        ingredients_equipments: [
          'Q-Switch laser',
          'laser eye protection',
        ],
        technique:
          'Deliver the compiled 1064-nm Q-Switch settings over the dried carbon layer only in the selected zones. Follow the compiled zone map, energy, frequency, fixed spot area and pass count exactly.',
        endpoint:
          'The planned carbon photoacoustic response is completed without excessive heat, pain, whitening or reaction beyond the intended endpoint.',
        transition_cue:
          'Stop laser delivery, remove all carbon residue, confirm comfort and document actual passes and endpoint.',
      },
    ],
  }),

  final_serum_moisturizer_sunscreen: durationRule({
    min: 3,
    max: 3,
    timing_mode: 'fixed',
    includes: [
      'serum application',
      'moisturizer application',
      'complete sunscreen coverage',
    ],
  }),

  salicylic_spot: durationRule({
    min: 2,
    max: 2,
    timing_mode: 'fixed',
    includes: [
      'targeted application',
      'specified local contact/removal',
    ],
  }),
}

for (const modalityId of INFUSION_MODALITY_IDS_V3_4) {
  baseRules[modalityId] = durationRule({
    min: 3,
    max: 3,
    timing_mode: 'fixed_per_single_ingredient',
    minutes_per_ingredient: 3,
    includes: [
      'application of one selected ingredient or solution',
    ],
    note:
      'One infusion action represents one selected ingredient. A future multi-ingredient workflow must create separately timed applications or supply an explicit ingredient-count rule.',
  })
}

for (const modalityId of PEEL_OFF_MASK_MODALITY_IDS_V3_4) {
  baseRules[modalityId] = durationRule({
    min: 15,
    max: 15,
    timing_mode: 'fixed',
    includes: [
      'prepare mask',
      'apply mask',
      'allow mask to dry',
      'remove mask completely',
    ],
  })
}

for (const modalityId of CHEMICAL_PEEL_MODALITY_IDS_V3_4) {
  baseRules[modalityId] = durationRule({
    min: 3,
    max: 4,
    timing_mode: 'adaptive_within_clinic_range',
    includes: [
      'apply selected peel',
      'observe intended endpoint/contact period',
      'neutralize or remove as prescribed',
    ],
  })
}

export const CLINIC_STEP_DURATION_RULES_V3_4 =
  Object.freeze(baseRules)

export function getClinicStepDurationRuleV3_4(
  stepOrModalityId,
) {
  return (
    CLINIC_STEP_DURATION_RULES_V3_4[
      stepOrModalityId
    ] ?? null
  )
}

export function getClinicDurationRangeV3_4(
  stepOrModalityId,
) {
  const rule =
    getClinicStepDurationRuleV3_4(
      stepOrModalityId,
    )
  if (!rule) return null
  return {
    min: rule.min_minutes,
    max: rule.max_minutes,
  }
}

export function selectClinicDurationMinutesV3_4(
  stepOrModalityId,
  position0To1 = 0.5,
) {
  const rule =
    getClinicStepDurationRuleV3_4(
      stepOrModalityId,
    )
  if (!rule) return null
  if (rule.min_minutes === rule.max_minutes) {
    return rule.min_minutes
  }
  const position = Math.max(
    0,
    Math.min(1, Number(position0To1)),
  )
  return Math.round(
    rule.min_minutes +
      (rule.max_minutes - rule.min_minutes) *
        position,
  )
}

export function chemicalPeelContactTimeV3_4(
  position0To1 = 0.5,
) {
  const position = Math.max(
    0,
    Math.min(1, Number(position0To1)),
  )
  return position >= 0.6 ? 4 : 3
}

export function durationForInfusionIngredientsV3_4(
  ingredientCount = 1,
) {
  const count = Number(ingredientCount)
  if (!Number.isInteger(count) || count < 1) {
    throw new Error(
      'Infusion ingredient count must be a positive integer.',
    )
  }
  return count * 3
}

export function validateClinicStepDurationRulesV3_4() {
  const errors = []
  for (const [id, rule] of Object.entries(
    CLINIC_STEP_DURATION_RULES_V3_4,
  )) {
    if (
      !Number.isFinite(rule.min_minutes) ||
      !Number.isFinite(rule.max_minutes) ||
      rule.min_minutes <= 0 ||
      rule.max_minutes < rule.min_minutes
    ) {
      errors.push(`Invalid duration rule for ${id}.`)
    }
    if (rule.execution_substeps) {
      const total = rule.execution_substeps.reduce(
        (sum, step) =>
          sum + Number(step.duration_minutes),
        0,
      )
      if (
        total !== rule.min_minutes ||
        total !== rule.max_minutes
      ) {
        errors.push(
          `${id} execution substeps total ${total}, expected ${rule.min_minutes}.`,
        )
      }
    }
  }
  return {
    valid: errors.length === 0,
    version:
      CLINIC_STEP_DURATION_RULES_VERSION,
    rule_count: Object.keys(
      CLINIC_STEP_DURATION_RULES_V3_4,
    ).length,
    errors,
  }
}
