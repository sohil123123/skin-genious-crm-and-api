import {
  CLINIC_STEP_DURATION_RULES_V3_4,
} from './clinicStepDurationRulesV3_4.js'
import {
  getDoctorApprovedProtocolEnvelopeV3,
} from './doctorApprovedProtocolConstraintsV3.js'
import {
  runSkinStateV2,
} from './runSkinStateV2.js'
import {
  optimizeZonalTreatmentV2,
} from './zonalTreatmentOptimizerV2.js'
import {
  compileTherapistSessionV2,
} from './therapistSessionCompilerV2.js'
import {
  buildLegacyDiagnosisV34,
  buildLegacyTreatmentPlanV34,
} from './legacyFacialAdapterV34.js'

const assert = (value, message) => {
  if (!value) throw new Error(message)
}

assert(typeof runSkinStateV2 === 'function', 'runSkinStateV2 missing')
assert(typeof optimizeZonalTreatmentV2 === 'function', 'optimizer missing')
assert(typeof compileTherapistSessionV2 === 'function', 'compiler missing')
assert(typeof buildLegacyDiagnosisV34 === 'function', 'legacy diagnosis adapter missing')
assert(typeof buildLegacyTreatmentPlanV34 === 'function', 'legacy treatment adapter missing')

const cleanser = CLINIC_STEP_DURATION_RULES_V3_4.cleanse_and_prepare
assert(cleanser?.min_minutes === 2 && cleanser?.max_minutes === 2, 'Cleanser must be 2 minutes')
const suction = CLINIC_STEP_DURATION_RULES_V3_4.hydrafacial_suction_extraction
assert(suction?.min_minutes === 2 && suction?.max_minutes === 4, 'Suction must be 2–4 minutes')
const q = getDoctorApprovedProtocolEnvelopeV3('q_switch_1064')
assert(q?.parameter_constraints?.wavelength_nm?.value === 1064, 'Q-Switch must be 1064 nm')
assert(q?.parameter_constraints?.spot_size_or_handpiece?.value?.spot_area_cm2 === 1, 'Spot must be 1 cm²')
assert(q?.parameter_constraints?.passes_or_shots_by_zone?.value_spec?.min === 1, 'Pass min must be 1')
assert(q?.parameter_constraints?.passes_or_shots_by_zone?.value_spec?.max === 2, 'Pass max must be 2')

console.log(JSON.stringify({
  ok: true,
  engine: 'facial_v3_4',
  integration_adapter: true,
  cleanser_minutes: [cleanser.min_minutes, cleanser.max_minutes],
  suction_minutes: [suction.min_minutes, suction.max_minutes],
  q_switch: { wavelength_nm: 1064, spot_area_cm2: 1, passes: [1, 2] },
}, null, 2))
