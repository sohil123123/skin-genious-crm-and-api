import { recommendConcernsV38 } from './concernRecommendationsV38.js'
import {
  CLIENT_REPORT_PARAMETER_IDS,
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  DERIVED_REPORT_FORMULAS_V2,
} from './skinStateScoreMapperV2.js'

const PARAMETER_META = Object.freeze({
  skin_type: { legacy_key: 'skin_type', label: 'Skin Type Classification', score_semantics: 'label', score_polarity: 'label_only', ideal_score_direction: 'maintain', comparison_mode: 'label_mapping' },
  barrier_health_sensitivity: { legacy_key: 'barrier_health_sensitivity', label: 'Barrier Health + Sensitivity (Combined Score)' },
  visual_acne: { legacy_key: 'visual_acne_grading', label: 'Visual Acne Grading' },
  skin_sebum: { legacy_key: 'skin_sebum_content', label: 'Skin Sebum Content' },
  vascularity_redness: { legacy_key: 'vascularity_redness_profiling', label: 'Vascularity / Redness' },
  skin_hydration: { legacy_key: 'skin_hydration_score', label: 'Skin Hydration' },
  skin_luminosity_glow: { legacy_key: 'skin_luminosity_glow_index', label: 'Skin Luminosity / Glow' },
  superficial_pigmentation: { legacy_key: 'superficial_pigmentation_score', label: 'Superficial Pigmentation' },
  peri_orbital_health: { legacy_key: 'periorbital_health', label: 'Peri-Orbital Health' },
  lip_pigmentation: { legacy_key: 'lip_pigmentation', label: 'Lip Pigmentation' },
  texture_open_pores: { legacy_key: 'texture_open_pores_scoring', label: 'Texture + Open Pores' },
  superficial_wrinkles: { legacy_key: 'superficial_wrinkles_scoring', label: 'Superficial Wrinkles' },
  jawline_sagging: { legacy_key: 'jawline_sagging_score', label: 'Jawline Sagging' },
  skin_firmness_elasticity: { legacy_key: 'skin_firmness_elasticity_index', label: 'Skin Firmness & Elasticity' },
  textural_radiance: { legacy_key: 'textural_radiance_index', label: 'Textural Radiance' },
})

const LABEL_ALIASES = Object.freeze({
  'skin type classification': 'skin_type',
  'barrier health + sensitivity': 'barrier_health_sensitivity',
  'barrier health + sensitivity (combined score)': 'barrier_health_sensitivity',
  'visual acne grading': 'visual_acne',
  'skin sebum content': 'skin_sebum',
  'skin sebum index': 'skin_sebum',
  'vascularity / redness': 'vascularity_redness',
  'vascularity / redness profiling': 'vascularity_redness',
  'skin hydration': 'skin_hydration',
  'skin hydration score': 'skin_hydration',
  'skin luminosity / glow': 'skin_luminosity_glow',
  'skin luminosity / glow index': 'skin_luminosity_glow',
  'superficial pigmentation': 'superficial_pigmentation',
  'superficial pigmentation score': 'superficial_pigmentation',
  'peri-orbital health': 'peri_orbital_health',
  'periorbital health': 'peri_orbital_health',
  'lip pigmentation': 'lip_pigmentation',
  'texture + open pores': 'texture_open_pores',
  'texture & open pores': 'texture_open_pores',
  'superficial wrinkles': 'superficial_wrinkles',
  'jawline sagging': 'jawline_sagging',
  'skin firmness & elasticity': 'skin_firmness_elasticity',
  'textural radiance': 'textural_radiance',
})

// Canonical A5 image order on the current frontend:
// 1 red, 2 subsurface, 3 surface, 4 white, 5 Woods/UV.
const PARAMETER_IMAGE_INDEX = Object.freeze({
  skin_type: 4,
  barrier_health_sensitivity: 3,
  visual_acne: 2,
  skin_sebum: 3,
  vascularity_redness: 1,
  skin_hydration: 4,
  skin_luminosity_glow: 4,
  superficial_pigmentation: 5,
  peri_orbital_health: 2,
  lip_pigmentation: 5,
  texture_open_pores: 3,
  superficial_wrinkles: 3,
  jawline_sagging: 4,
  skin_firmness_elasticity: 4,
  textural_radiance: 3,
})

const ZONE_LABEL = Object.freeze({
  forehead_left: 'forehead', forehead_center: 'central forehead', forehead_right: 'forehead',
  glabella: 'area between the brows', temple_left: 'temples', temple_right: 'temples', nose: 'nose',
  malar_medial_left: 'upper cheeks', malar_medial_right: 'upper cheeks',
  cheek_lateral_left: 'outer cheeks', cheek_lateral_right: 'outer cheeks',
  peri_orbital_left: 'under-eye area', peri_orbital_right: 'under-eye area',
  perioral: 'mouth area', chin: 'chin',
  jawline_left: 'jawline', jawline_right: 'jawline', lips: 'lips',
})

// Retained for older post-treatment consumers only. New baseline diagnosis does
// NOT collapse V3 scores to 1-5.


function readableFeature(featureId) {
  return String(featureId || '').replaceAll('_', ' ')
}

function burdenBand(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return 'not reliably quantified'
  if (n < 15) return 'minimal'
  if (n < 30) return 'mild'
  if (n < 45) return 'moderate'
  if (n < 60) return 'noticeable'
  if (n < 75) return 'marked'
  return 'high'
}

function uniqueReadableZones(zoneIds = [], limit = 3) {
  return [...new Set(zoneIds.map((id) => ZONE_LABEL[id] ?? String(id).replaceAll('_', ' ')))].slice(0, limit)
}

function joinNatural(items) {
  if (!items.length) return ''
  if (items.length === 1) return items[0]
  if (items.length === 2) return `${items[0]} and ${items[1]}`
  return `${items.slice(0, -1).join(', ')}, and ${items.at(-1)}`
}

function featureData(skinState, featureId) {
  const feature = skinState?.core_features?.[featureId]
  return {
    burden: Number(feature?.global_burden_score_1_to_100 ?? 0),
    reliability: Number(feature?.score_reliability?.score_1_to_100 ?? 0),
    reliabilityTier: feature?.score_reliability?.tier ?? 'unknown',
    reliabilityReasons: feature?.score_reliability?.reasons ?? [],
    zones: feature?.dominant_zones ?? [],
    peakZone: feature?.peak_zone ?? null,
  }
}

function parameterReliability(parameterId, skinState) {
  const q = parameterId === 'skin_type' ? skinState.skin_type?.data_quality : skinState.derived_report_parameters?.[parameterId]?.data_quality
  if (q) { const score = Math.round(q.confidence_0_1 * 100); return {score, tier:score>=85?'high':score>=70?'moderate':'low', reasons:q.is_estimated?[q.estimation_reason||'Supported image estimate']:[]} }

  if (parameterId === 'skin_type') {
    const ids = ['oiliness', 'visual_dehydration', 'barrier_stress']
    const values = ids.map((id) => Number(skinState?.core_features?.[id]?.score_reliability?.score_1_to_100 ?? 0)).filter(Number.isFinite)
    const score = values.length ? Math.round(values.reduce((a, b) => a + b, 0) / values.length) : 0
    return { score, tier: score >= 85 ? 'high' : score >= 70 ? 'moderate' : 'low', reasons: [] }
  }
  const components = DERIVED_REPORT_FORMULAS_V2[parameterId]?.components ?? {}
  const entries = Object.entries(components)
  let weighted = 0
  let total = 0
  const reasons = []
  for (const [featureId, weight] of entries) {
    const reliability = Number(skinState?.core_features?.[featureId]?.score_reliability?.score_1_to_100 ?? 0)
    if (Number.isFinite(reliability)) {
      weighted += reliability * Number(weight)
      total += Number(weight)
    }
    for (const reason of skinState?.core_features?.[featureId]?.score_reliability?.reasons ?? []) {
      if (!reasons.includes(reason)) reasons.push(reason)
    }
  }
  const score = total > 0 ? Math.round(weighted / total) : 0
  return { score, tier: score >= 85 ? 'high' : score >= 70 ? 'moderate' : 'low', reasons }
}

function parameterDominantZones(parameterId, skinState) {
  if (parameterId === 'skin_type') return []
  return [...new Set(
    Object.keys(DERIVED_REPORT_FORMULAS_V2[parameterId]?.components ?? {})
      .flatMap((featureId) => skinState?.core_features?.[featureId]?.dominant_zones ?? [])
  )].slice(0, 4)
}

function groundedDescription(parameterId, card, skinState) {
  const observation = skinState.derived_report_parameters?.[parameterId]?.client_observation
  if (observation) return observation

  if (card.value_type === 'label') {
    const modifiers = card.modifiers?.length ? ` (${card.modifiers.join(', ')})` : ''
    return `Your current five-mode pattern is ${card.display_label}${modifiers}.`
  }
  const zones = uniqueReadableZones(parameterDominantZones(parameterId, skinState))
  const where = zones.length ? ` The strongest signal is in the ${joinNatural(zones)}.` : ''
  const F = (id) => featureData(skinState, id)

  switch (parameterId) {
    case 'barrier_health_sensitivity': {
      const b = F('barrier_stress'), d = F('visual_dehydration'), r = F('erythema_redness')
      return `Barrier-related stress is ${burdenBand(b.burden)}, with ${burdenBand(d.burden)} visible dehydration and ${burdenBand(r.burden)} redness.${where}`
    }
    case 'visual_acne': {
      const a = F('active_inflammatory_acne'), c = F('comedonal_congestion')
      const dominant = c.burden > a.burden + 5 ? 'Congestion is more prominent than active inflammatory lesions.' : a.burden > c.burden + 5 ? 'Active inflammatory lesions are the stronger acne signal.' : 'Inflammatory activity and congestion are relatively similar.'
      return `Active inflammatory acne is ${burdenBand(a.burden)} and comedonal congestion is ${burdenBand(c.burden)}. ${dominant}${where}`
    }
    case 'skin_sebum': return `This score reflects oil balance, considering the T-zone and cheeks separately and checking for visible dryness when shine is low.${where}`
    case 'vascularity_redness': return `Visible redness/vascular prominence is ${burdenBand(F('erythema_redness').burden)} overall.${where}`
    case 'skin_hydration': return `Visible dehydration is ${burdenBand(F('visual_dehydration').burden)}, so hydration appearance is correspondingly ${Number(card.client_health_score_1_to_100) >= 80 ? 'strong' : Number(card.client_health_score_1_to_100) >= 60 ? 'fairly good' : 'an area for improvement'}.${where}`
    case 'skin_luminosity_glow': return `Loss of luminosity/glow is ${burdenBand(F('luminosity_loss').burden)}.${where}`
    case 'superficial_pigmentation': {
      const v = F('visible_pigmentation'), u = F('underlying_pigment_support')
      return `Visible pigmentation is ${burdenBand(v.burden)}, with ${burdenBand(u.burden)} underlying pigment support on the deeper/UV-sensitive views.${where}`
    }
    case 'peri_orbital_health': return `The combined under-eye concern is ${burdenBand(F('peri_orbital_concern').burden)} across pigment, vascular, shadow, puffiness and fine-line appearance.${where}`
    case 'lip_pigmentation': return `Visible lip pigmentation/unevenness is ${burdenBand(F('lip_pigmentation').burden)} on this scan.${where}`
    case 'texture_open_pores': {
      const p = F('pore_visibility'), t = F('texture_roughness')
      return `Pore visibility is ${burdenBand(p.burden)} and surface roughness is ${burdenBand(t.burden)}.${where}`
    }
    case 'superficial_wrinkles': return `Visible fine-line burden is ${burdenBand(F('fine_line_visibility').burden)}.${where}`
    case 'jawline_sagging': return `Visible contour laxity is ${burdenBand(F('visible_laxity').burden)} in the assessable jawline/cheek regions.${where}`
    case 'skin_firmness_elasticity': return `Visible firmness/plumpness loss is ${burdenBand(F('firmness_appearance_loss').burden)}.${where}`
    case 'textural_radiance': {
      const t = F('texture_roughness'), l = F('luminosity_loss')
      return `Textural radiance is being limited by ${burdenBand(t.burden)} roughness and ${burdenBand(l.burden)} luminosity loss.${where}`
    }
    default: return `The five-mode scan gives a health score of ${card.client_health_score_1_to_100}/100 for this parameter.${where}`
  }
}

function scoreExplanation(parameterId, card, skinState) {
  return groundedDescription(parameterId, card, skinState)
}

function oldPolarity(parameterId) {
  if (parameterId === 'skin_type') return { score_semantics: 'label', score_polarity: 'label_only', ideal_score_direction: 'maintain', comparison_mode: 'label_mapping' }
  return { score_semantics: 'health', score_polarity: 'higher_is_better', ideal_score_direction: 'increase', comparison_mode: 'direct_numeric' }
}

export function buildLegacyDiagnosisV34({ skinAnalysisReport, skinState }) {
  const cards = skinAnalysisReport?.primary_client_assessment?.parameter_cards ?? []
  const diagnosisReport = {}

  for (const card of cards) {
    const meta = PARAMETER_META[card.parameter_id]
    if (!meta) continue
    const polarity = oldPolarity(card.parameter_id)
    const reliability = parameterReliability(card.parameter_id, skinState)
    const dominantZones = parameterDominantZones(card.parameter_id, skinState)
    const scoreOrLabel = card.value_type === 'label'
      ? card.display_label
      : Number(card.client_health_score_1_to_100)

    diagnosisReport[meta.legacy_key] = {
      parameter_name: meta.label,
      parameter_id: card.parameter_id,
      description: card.value_type === 'label'
        ? 'Five-mode assessment of current skin-type pattern.'
        : 'Client health score from the five-mode V3.7 legacy-measurement Skin State engine. Higher is better.',
      client_description: groundedDescription(card.parameter_id, card, skinState),
      score_or_label: scoreOrLabel,
      score_scale: card.value_type === 'label' ? 'label' : '1_to_100_client_health_higher_is_better',
      calibration_version: skinState.scoring_execution?.calibration_version ?? null,
      score_explanation: scoreExplanation(card.parameter_id, card, skinState),
      calibration_audit: skinState.derived_report_parameters?.[card.parameter_id]?.calibration ?? null,
      affected_area_image: PARAMETER_IMAGE_INDEX[card.parameter_id] ?? null,
      affected_zones: dominantZones,
      // Image-only analysis should not invent etiological causes. History enters
      // later in treatment selection, not baseline image scoring.
      possible_causes: [],
      ...polarity,
      normalized_burden_0_to_1: card.value_type === 'score'
        ? Number(((card.concern_burden_score_1_to_100 - 1) / 99).toFixed(3))
        : null,
      v3_4_client_health_score_1_to_100: card.client_health_score_1_to_100 ?? null,
      v3_4_concern_burden_score_1_to_100: card.concern_burden_score_1_to_100 ?? null,
      data_quality: {
        is_estimated: reliability.tier === 'low',
        estimated_fields: reliability.tier === 'low' ? ['image_measurement_confidence'] : [],
        estimation_basis: 'five_mode_v3_7_legacy_measurements',
        confidence_0_1: Number((reliability.score / 100).toFixed(2)),
        reliability_score_1_to_100: reliability.score,
        reliability_tier: reliability.tier,
        reliability_reasons: reliability.reasons,
        ...(skinState.derived_report_parameters?.[card.parameter_id]?.data_quality ?? skinState.skin_type?.data_quality ?? {}),
      },
    }
  }

  const suggestions = recommendConcernsV38(skinState)
  const treatable = suggestions.map(suggestion => {
    const card=cards.find(c=>c.parameter_id===suggestion.parameter_id)
    const meta=PARAMETER_META[suggestion.parameter_id]
    return {...suggestion,parameter:meta?.label??card?.label,
      score_scale:'1_to_100_client_health_higher_is_better',
      reason_for_selection:'Selected for expected visible improvement with an appropriate same-day treatment; you can change the primary concerns.',
      short_description:groundedDescription(suggestion.parameter_id,card,skinState),
      score_semantics:'health',score_polarity:'higher_is_better',ideal_score_direction:'increase',comparison_mode:'direct_numeric',
      treatment_data_quality:skinState.derived_report_parameters[suggestion.parameter_id]?.data_quality??null,
    }
  })

  return {
    diagnosis_report: diagnosisReport,
    treatable_concerns_summary: {
      description: 'Concerns ranked by expected visible improvement. Suggested primary concerns can be changed by the client.',
      parameters_with_abnormal_scores: treatable,
    },
    v3_4_report: skinAnalysisReport,
    v3_4_skin_state: skinState,
  }
}

function selectedParameterId(item) {
  if (typeof item === 'string') {
    const normalized = item.trim().toLowerCase()
    if (CLIENT_REPORT_PARAMETER_IDS.includes(normalized)) return normalized
    return LABEL_ALIASES[normalized] ?? null
  }
  if (!item || typeof item !== 'object') return null
  const candidates = [
    item.parameter_id,
    item.parameter,
    item.concern,
    item.name,
    item.label,
  ].filter(Boolean)
  for (const value of candidates) {
    const normalized = String(value).trim().toLowerCase()
    if (CLIENT_REPORT_PARAMETER_IDS.includes(normalized)) return normalized
    if (LABEL_ALIASES[normalized]) return LABEL_ALIASES[normalized]
  }
  return null
}

function featuresForParameter(parameterId) {
  if (parameterId === 'skin_type') return []
  return Object.keys(DERIVED_REPORT_FORMULAS_V2[parameterId]?.components ?? {})
}

export function resolveConcernFeaturesV34(selectedConcerns, skinState) {
  const primary = []
  const secondary = []

  for (const item of Array.isArray(selectedConcerns) ? selectedConcerns : []) {
    const parameterId = selectedParameterId(item)
    if (!parameterId) continue
    const features = featuresForParameter(parameterId)
    const isPrimary = typeof item === 'string' || (typeof item === 'object' && item?.is_primary_concern === true)
    for (const featureId of features) {
      const target = isPrimary ? primary : secondary
      if (!target.includes(featureId)) target.push(featureId)
    }
  }

  if (!Array.isArray(selectedConcerns) || selectedConcerns.length === 0) {
    for (const item of recommendConcernsV38(skinState)) {
      const target=item.is_primary_concern?primary:secondary
      for(const featureId of featuresForParameter(item.parameter_id))if(!target.includes(featureId))target.push(featureId)
    }
  }

  return {
    primaryConcerns: [...new Set(primary)],
    secondaryConcerns: [...new Set(secondary.filter((id) => !primary.includes(id)))],
  }
}

function patientScriptForStep(step) {
  const existing = step?.patient_script?.script
  if (typeof existing === 'string' && existing.trim()) return existing.trim()
  const action = step?.patient_script_context?.plain_language_action || step?.title || 'continue with the next treatment step'
  const benefits = [
    ...(step?.patient_script_context?.immediate_benefit_claims ?? []),
    ...(step?.patient_script_context?.course_benefit_claims ?? []),
  ].filter(Boolean)
  const benefitText = benefits.length ? ` This is intended to ${benefits[0]}.` : ''
  return `We’ll now ${String(action).replace(/^./, (c) => c.toLowerCase())}.${benefitText}`
}

function legacyStep(step, index) {
  const howToDo = step?.therapist_instruction?.how_to_do
    ?? step?.therapist_instruction?.technique
    ?? ''
  return {
    step_number: index + 1,
    title: step.title ?? `Step ${index + 1}`,
    treatment: step.title ?? step.modality_id ?? 'Facial step',
    modality_id: step.modality_id ?? null,
    duration_minutes: Number(step.duration_minutes ?? 0),
    duration: `${Number(step.duration_minutes ?? 0)} minutes`,
    how_to_do: howToDo,
    ingredients_equipments: step.ingredients_equipments ?? [],
    script: patientScriptForStep(step),
    target_zones: step.target_zones ?? [],
    approved_parameters: step?.therapist_instruction?.approved_parameters ?? {},
    endpoint: step?.therapist_instruction?.endpoint ?? null,
    safety_signals: step?.therapist_instruction?.safety_signals ?? [],
    stop_conditions: step?.therapist_instruction?.stop_conditions ?? [],
    transition_cue: step?.therapist_instruction?.transition_cue ?? null,
    v3_4_compilation_id: step.compilation_id ?? null,
  }
}

export function compiledSessionToLegacyTreatmentV34(compiledSession, sessionNumber = 1) {
  const steps = (compiledSession?.steps ?? []).map(legacyStep)
  const total = steps.reduce((sum, step) => sum + Number(step.duration_minutes || 0), 0)
  return {
    session_number: sessionNumber,
    title: compiledSession?.session_title ?? `Personalised Facial — Session ${sessionNumber}`,
    script: compiledSession?.patient_session_summary ?? '',
    treatment_time: `${total} mins`,
    step_duration_total: total,
    week: sessionNumber,
    preparations_checklist_for_therapist:
      compiledSession?.pre_session_confirmations ?? [],
    concerns_addressed: [],
    steps,
    daily_home_care_routine: [],
    timing_validation: {
      calculated_from_steps: total,
      matches_treatment_time: true,
      source: 'clinic_step_duration_rules_v3_4',
    },
    v3_4: {
      compiler_version: compiledSession?.compiler_version ?? null,
      compile_status: compiledSession?.compile_status ?? null,
      release_ready: compiledSession?.release_ready ?? false,
      plan_id: compiledSession?.plan_id ?? null,
    },
  }
}

export function buildLegacyTreatmentPlanV34({
  treatments,
  treatmentMode,
  native = {},
  recommendedFullPlan = null,
}) {
  const total = treatments.reduce((sum, treatment) => {
    const n = Number.parseInt(String(treatment.treatment_time ?? ''), 10)
    return sum + (Number.isFinite(n) ? n : 0)
  }, 0)
  return {
    treatment_plan: {
      total_time: `${total} mins`,
      treatment_mode: treatmentMode,
      treatments,
    },
    // Existing AssessmentController currently reads this plural key for total_time.
    treatment_plans: {
      total_time: `${total} mins`,
    },
    recommended_full_plan: recommendedFullPlan,
    v3_4_native: native,
  }
}

export function buildLegacyPostDiagnosisV34(reassessmentResult) {
  const postState = reassessmentResult?.post_treatment?.skin_state
  const baselineState = reassessmentResult?.baseline?.skin_state
  if (!postState) return { v3_4_reassessment: reassessmentResult }

  const reassessment = {}
  for (const parameterId of CLIENT_REPORT_PARAMETER_IDS) {
    const meta = PARAMETER_META[parameterId]
    const legacyKey = meta?.legacy_key ?? parameterId
    if (parameterId === 'skin_type') {
      const before = baselineState?.skin_type?.label ?? null
      const after = postState?.skin_type?.label ?? null
      reassessment[legacyKey] = {
        parameter_name: meta?.label ?? 'Skin Type',
        before_treatment_score_or_label: before,
        before_image: PARAMETER_IMAGE_INDEX[parameterId] ?? null,
        post_treatment_score_or_label: after,
        post_treatment_image: PARAMETER_IMAGE_INDEX[parameterId] ?? null,
        result: before === after ? 'stable' : 'changed',
        score_semantics: 'label',
        score_polarity: 'label_only',
        comparison_mode: 'label_mapping',
        ideal_score_direction: 'maintain',
        base_post_score_or_label_internal: after,
        raw_comparison_result_internal: before === after ? 'stable' : 'changed',
        response_strength: before === after ? 'none' : 'mild',
        patient_facing_change_points: 0,
        transient_reactivity_note: 'none',
        score_explanation: before === after
          ? 'Skin type classification is stable on the post-treatment scan.'
          : 'The visible post-treatment skin pattern currently maps to a different classification.',
      }
      continue
    }

    const before = baselineState?.derived_report_parameters?.[parameterId]
    const after = postState?.derived_report_parameters?.[parameterId]
    if (!Number.isFinite(before?.internal_burden_score_1_to_100) || !Number.isFinite(after?.internal_burden_score_1_to_100)) {
      throw new Error(`Missing supported reassessment score for ${parameterId}; retry the assessment.`)
    }
    const beforeBurden = before.internal_burden_score_1_to_100
    const afterBurden = after.internal_burden_score_1_to_100
    const beforeHealth = 101 - beforeBurden
    const afterHealth = 101 - afterBurden
    const delta = afterHealth - beforeHealth
    const direction = delta > 2 ? 'improved' : delta < -2 ? 'declined' : 'stable'
    const magnitude = Math.abs(delta)
    const responseStrength = magnitude < 3 ? 'none' : magnitude < 7 ? 'mild' : magnitude < 13 ? 'moderate' : 'strong'

    reassessment[legacyKey] = {
      parameter_name: meta?.label ?? parameterId,
      score_scale: '1_to_100_client_health_higher_is_better',
      before_treatment_score_or_label: Math.round(beforeHealth),
      before_image: PARAMETER_IMAGE_INDEX[parameterId] ?? null,
      post_treatment_score_or_label: Math.round(afterHealth),
      post_treatment_image: PARAMETER_IMAGE_INDEX[parameterId] ?? null,
      result: direction,
      score_semantics: 'health',
      score_polarity: 'higher_is_better',
      comparison_mode: 'direct_numeric',
      ideal_score_direction: 'increase',
      base_post_score_or_label_internal: afterHealth,
      raw_comparison_result_internal: direction,
      response_strength: responseStrength,
      patient_facing_change_points: Math.abs(
        (afterHealth ?? 0) - (beforeHealth ?? 0),
      ),
      transient_reactivity_note: 'See V3.4 pairwise evidence for any treatment-day reactivity.',
      score_explanation:
        direction === 'improved'
          ? `The measured post-treatment health score improved by ${Math.round(delta)} points.`
          : direction === 'declined'
            ? `The measured post-treatment health score is ${Math.round(Math.abs(delta))} points lower today; temporary treatment-day reactivity may contribute and is reported separately in V3.4.`
            : 'The measured post-treatment score is broadly stable today.',
      v3_4_before_health_score_1_to_100: beforeHealth,
      v3_4_after_health_score_1_to_100: afterHealth,
      v3_4_health_change_1_to_100: delta,
    }
  }

  return {
    metadata: { phase: 'reassessment', evaluation_type: 'post_treatment' },
    reassessment,
    v3_4_reassessment: reassessmentResult,
  }
}
