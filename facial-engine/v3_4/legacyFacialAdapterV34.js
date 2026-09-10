import {
  CLIENT_REPORT_PARAMETER_IDS,
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  DERIVED_REPORT_FORMULAS_V2,
} from './skinStateScoreMapperV2.js'

const PARAMETER_META = Object.freeze({
  skin_type: {
    legacy_key: 'skin_type',
    label: 'Skin Type Classification',
    score_semantics: 'label',
    score_polarity: 'label_only',
    ideal_score_direction: 'maintain',
    comparison_mode: 'label_mapping',
  },
  barrier_health_sensitivity: {
    legacy_key: 'barrier_health_sensitivity',
    label: 'Barrier Health + Sensitivity (Combined Score)',
  },
  visual_acne: {
    legacy_key: 'visual_acne_grading',
    label: 'Visual Acne Grading',
  },
  skin_sebum: {
    legacy_key: 'skin_sebum_content',
    label: 'Skin Sebum Content',
  },
  vascularity_redness: {
    legacy_key: 'vascularity_redness_profiling',
    label: 'Vascularity / Redness',
  },
  skin_hydration: {
    legacy_key: 'skin_hydration_score',
    label: 'Skin Hydration',
  },
  skin_luminosity_glow: {
    legacy_key: 'skin_luminosity_glow_index',
    label: 'Skin Luminosity / Glow',
  },
  superficial_pigmentation: {
    legacy_key: 'superficial_pigmentation_score',
    label: 'Superficial Pigmentation',
  },
  peri_orbital_health: {
    legacy_key: 'periorbital_health',
    label: 'Peri-Orbital Health',
  },
  lip_pigmentation: {
    legacy_key: 'lip_pigmentation',
    label: 'Lip Pigmentation',
  },
  texture_open_pores: {
    legacy_key: 'texture_open_pores_scoring',
    label: 'Texture + Open Pores',
  },
  superficial_wrinkles: {
    legacy_key: 'superficial_wrinkles_scoring',
    label: 'Superficial Wrinkles',
  },
  jawline_sagging: {
    legacy_key: 'jawline_sagging_score',
    label: 'Jawline Sagging',
  },
  skin_firmness_elasticity: {
    legacy_key: 'skin_firmness_elasticity_index',
    label: 'Skin Firmness & Elasticity',
  },
  textural_radiance: {
    legacy_key: 'textural_radiance_index',
    label: 'Textural Radiance',
  },
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

function healthScoreToFive(score) {
  const n = Number(score)
  if (!Number.isFinite(n)) return null
  return Math.max(1, Math.min(5, Math.round(1 + ((n - 1) / 99) * 4)))
}

function concernBurdenToFive(burden) {
  const n = Number(burden)
  if (!Number.isFinite(n)) return null
  return Math.max(1, Math.min(5, Math.round(1 + ((n - 1) / 99) * 4)))
}

function readableFeature(featureId) {
  return String(featureId || '').replaceAll('_', ' ')
}

function scoreExplanation(card) {
  if (card.value_type === 'label') {
    const modifiers = card.modifiers?.length
      ? ` with ${card.modifiers.join(', ')} modifier${card.modifiers.length === 1 ? '' : 's'}`
      : ''
    return `The five-mode scan classifies the current skin pattern as ${card.display_label}${modifiers}.`
  }
  const burden = card.concern_burden_score_1_to_100
  const health = card.client_health_score_1_to_100
  return `The current five-mode assessment shows a concern burden of ${burden}/100 and a client health score of ${health}/100 for this parameter.`
}

function simpleClientDescription(card) {
  if (card.value_type === 'label') {
    return `Your current skin pattern is classified as ${card.display_label}.`
  }
  const health = Number(card.client_health_score_1_to_100)
  if (health >= 80) return 'This area currently looks strong and relatively well balanced.'
  if (health >= 60) return 'This area is fairly balanced with some visible room for improvement.'
  if (health >= 40) return 'This area shows a meaningful visible concern that can be targeted in treatment.'
  return 'This area is one of the stronger opportunities for visible improvement in the current assessment.'
}

function oldPolarity(parameterId) {
  if (parameterId === 'skin_type') {
    return {
      score_semantics: 'label',
      score_polarity: 'label_only',
      ideal_score_direction: 'maintain',
      comparison_mode: 'label_mapping',
    }
  }
  return {
    score_semantics: 'health',
    score_polarity: 'higher_is_better',
    ideal_score_direction: 'increase',
    comparison_mode: 'direct_numeric',
  }
}

export function buildLegacyDiagnosisV34({ skinAnalysisReport, skinState }) {
  const cards = skinAnalysisReport?.primary_client_assessment?.parameter_cards ?? []
  const diagnosisReport = {}

  for (const card of cards) {
    const meta = PARAMETER_META[card.parameter_id]
    if (!meta) continue
    const polarity = oldPolarity(card.parameter_id)
    const scoreOrLabel = card.value_type === 'label'
      ? card.display_label
      : healthScoreToFive(card.client_health_score_1_to_100)
    const dominantZones = card.parameter_id === 'skin_type'
      ? []
      : [...new Set(
          (DERIVED_REPORT_FORMULAS_V2[card.parameter_id]?.components
            ? Object.keys(DERIVED_REPORT_FORMULAS_V2[card.parameter_id].components)
            : [])
            .flatMap((featureId) => skinState?.core_features?.[featureId]?.dominant_zones ?? [])
        )].slice(0, 4)

    diagnosisReport[meta.legacy_key] = {
      parameter_name: meta.label,
      parameter_id: card.parameter_id,
      description: `V3.4 five-mode assessment of ${meta.label.toLowerCase()}.`,
      client_description: simpleClientDescription(card),
      score_or_label: scoreOrLabel,
      score_explanation: scoreExplanation(card),
      affected_area_image: null,
      affected_zones: dominantZones,
      possible_causes: [],
      ...polarity,
      normalized_burden_0_to_1:
        card.value_type === 'score'
          ? Number((card.concern_burden_score_1_to_100 / 100).toFixed(3))
          : null,
      v3_4_client_health_score_1_to_100:
        card.client_health_score_1_to_100 ?? null,
      v3_4_concern_burden_score_1_to_100:
        card.concern_burden_score_1_to_100 ?? null,
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'five_mode_v3_4_skin_state',
        confidence_0_1: 1,
      },
    }
  }

  const numericCards = cards
    .filter((card) => card.value_type === 'score')
    .sort((a, b) =>
      b.concern_burden_score_1_to_100 - a.concern_burden_score_1_to_100
    )

  const treatable = numericCards
    .filter((card) => Number(card.concern_burden_score_1_to_100) >= 30)
    .map((card, index) => {
      const meta = PARAMETER_META[card.parameter_id]
      const currentScore = healthScoreToFive(card.client_health_score_1_to_100)
      const targetHealth = Math.min(100, Number(card.client_health_score_1_to_100) + 8)
      return {
        parameter: meta?.label ?? card.label,
        parameter_id: card.parameter_id,
        current_score: currentScore,
        target_single_session_score: healthScoreToFive(targetHealth),
        is_primary_concern: index < 3,
        reason_for_selection:
          `Measured V3.4 concern burden is ${card.concern_burden_score_1_to_100}/100.`,
        short_description: simpleClientDescription(card),
        score_semantics: 'health',
        score_polarity: 'higher_is_better',
        ideal_score_direction: 'increase',
        comparison_mode: 'direct_numeric',
      }
    })

  return {
    diagnosis_report: diagnosisReport,
    treatable_concerns_summary: {
      description:
        'Parameters showing measurable deviations and their expected improvement after a single treatment session.',
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
    const isPrimary = typeof item === 'object' && item?.is_primary_concern === true
    for (const featureId of features) {
      const target = isPrimary ? primary : secondary
      if (!target.includes(featureId)) target.push(featureId)
    }
  }

  if (!primary.length) {
    const ranked = CORE_FEATURE_IDS
      .map((featureId) => ({
        featureId,
        burden: Number(skinState?.core_features?.[featureId]?.global_burden_score_1_to_100 ?? 0),
      }))
      .sort((a, b) => b.burden - a.burden)
      .filter((item) => item.burden >= 25)
    for (const item of ranked.slice(0, 3)) primary.push(item.featureId)
    for (const item of ranked.slice(3, 6)) secondary.push(item.featureId)
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
        before_image: null,
        post_treatment_score_or_label: after,
        post_treatment_image: null,
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
    const beforeBurden = Number(before?.internal_burden_score_1_to_100 ?? 0)
    const afterBurden = Number(after?.internal_burden_score_1_to_100 ?? 0)
    const beforeHealth = 101 - beforeBurden
    const afterHealth = 101 - afterBurden
    const delta = afterHealth - beforeHealth
    const direction = delta > 2 ? 'improved' : delta < -2 ? 'declined' : 'stable'
    const magnitude = Math.abs(delta)
    const responseStrength = magnitude < 3 ? 'none' : magnitude < 7 ? 'mild' : magnitude < 13 ? 'moderate' : 'strong'

    reassessment[legacyKey] = {
      parameter_name: meta?.label ?? parameterId,
      before_treatment_score_or_label: healthScoreToFive(beforeHealth),
      before_image: null,
      post_treatment_score_or_label: healthScoreToFive(afterHealth),
      post_treatment_image: null,
      result: direction,
      score_semantics: 'health',
      score_polarity: 'higher_is_better',
      comparison_mode: 'direct_numeric',
      ideal_score_direction: 'increase',
      base_post_score_or_label_internal: healthScoreToFive(afterHealth),
      raw_comparison_result_internal: direction,
      response_strength: responseStrength,
      patient_facing_change_points: Math.abs(
        (healthScoreToFive(afterHealth) ?? 0) - (healthScoreToFive(beforeHealth) ?? 0),
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
    reassessment,
    v3_4_reassessment: reassessmentResult,
  }
}
