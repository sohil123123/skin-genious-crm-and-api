import {
  runSkinStateV2,
  SKIN_STATE_RUNNER_VERSION,
} from './runSkinStateV2.js'
import {
  CORE_FEATURE_IDS,
  SKIN_STATE_SCHEMA_VERSION,
} from './skinStateV2.schema.js'
import {
  SYSTEM_PROMPT_PAIRWISE_OUTCOME_EVIDENCE_V2,
  PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION,
} from './pairwiseOutcomeEvidenceV2.js'
import { parseStrictJsonOutput } from './mergeVisionEvidenceV2.js'

export const POST_TREATMENT_REASSESSMENT_VERSION =
  'aia_post_treatment_reassessment_v2.0.0'

/**
 * Starting thresholds only. These must be replaced with parameter-specific
 * minimum detectable changes from repeated-scan validation data.
 */
export const DEFAULT_MIN_DETECTABLE_CHANGE_V2 = Object.fromEntries(
  CORE_FEATURE_IDS.map((featureId) => [featureId, 5]),
)

const DIRECTION_VALUES = new Set([
  'improved',
  'stable',
  'worsened',
  'mixed',
  'not_reliably_measurable',
])

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value))
}

function numericDirection(improvementPoints, threshold) {
  if (improvementPoints >= threshold) return 'improved'
  if (improvementPoints <= -threshold) return 'worsened'
  return 'stable'
}

function validatePairwiseOutput(value) {
  const parsed = parseStrictJsonOutput(value, 'pairwise outcome output')
  if (
    parsed.pairwise_outcome_version !== PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION
  ) {
    throw new Error(
      `Expected pairwise version ${PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION}; received ${parsed.pairwise_outcome_version}`,
    )
  }
  if (!parsed.feature_comparisons || typeof parsed.feature_comparisons !== 'object') {
    throw new Error('pairwise outcome must contain feature_comparisons')
  }
  for (const [featureId, comparison] of Object.entries(
    parsed.feature_comparisons,
  )) {
    if (!CORE_FEATURE_IDS.includes(featureId)) {
      throw new Error(`Unknown pairwise feature: ${featureId}`)
    }
    if (!DIRECTION_VALUES.has(comparison.global_change_direction)) {
      throw new Error(
        `${featureId}.global_change_direction is invalid: ${comparison.global_change_direction}`,
      )
    }
  }
  return parsed
}

function validationStatus(numeric, pairwise) {
  if (!pairwise) return 'not_pairwise_checked'
  if (pairwise === 'not_reliably_measurable') return 'pairwise_inconclusive'
  if (pairwise === 'mixed') {
    return numeric === 'stable' ? 'pairwise_mixed' : 'partially_supported'
  }
  if (numeric === pairwise) return 'confirmed'
  if (numeric === 'stable' && pairwise !== 'stable') return 'weak_pairwise_only_change'
  if (pairwise === 'stable' && numeric !== 'stable') return 'numeric_change_not_visually_confirmed'
  return 'contradicted'
}

function shouldDisplayNumericChange({
  improvementPoints,
  threshold,
  validation,
  pairQuality,
}) {
  if (Math.abs(improvementPoints) < threshold) return false
  if (pairQuality === 'poor') return false
  return ![
    'contradicted',
    'pairwise_inconclusive',
    'numeric_change_not_visually_confirmed',
  ].includes(validation)
}

function reliabilityTier(beforeFeature, afterFeature) {
  const before = beforeFeature.score_reliability?.score_1_to_100 ?? 1
  const after = afterFeature.score_reliability?.score_1_to_100 ?? 1
  const minimum = Math.min(before, after)
  return minimum >= 85 ? 'high' : minimum >= 70 ? 'moderate' : 'low'
}

function buildCoreFeatureOutcomes(
  baselineState,
  postState,
  pairwise,
  thresholds,
) {
  const outcomes = {}
  const pairQuality = pairwise?.comparison_meta?.pair_quality ?? null

  for (const featureId of CORE_FEATURE_IDS) {
    const beforeFeature = baselineState.core_features[featureId]
    const afterFeature = postState.core_features[featureId]
    const before = beforeFeature.global_burden_score_1_to_100
    const after = afterFeature.global_burden_score_1_to_100
    const improvementPoints = before - after
    const threshold = thresholds[featureId] ?? 5
    const numeric = numericDirection(improvementPoints, threshold)
    const pairwiseComparison = pairwise?.feature_comparisons?.[featureId] ?? null
    const pairwiseDirection =
      pairwiseComparison?.global_change_direction ?? null
    const validation = validationStatus(numeric, pairwiseDirection)

    const beforeZones = beforeFeature.zone_scores_1_to_100 ?? {}
    const afterZones = afterFeature.zone_scores_1_to_100 ?? {}
    const zoneIds = [...new Set([...Object.keys(beforeZones), ...Object.keys(afterZones)])]
    const zoneChanges = Object.fromEntries(
      zoneIds.map((zoneId) => {
        const beforeZone = beforeZones[zoneId] ?? null
        const afterZone = afterZones[zoneId] ?? null
        return [
          zoneId,
          {
            before_burden_score_1_to_100: beforeZone,
            after_burden_score_1_to_100: afterZone,
            improvement_points:
              beforeZone == null || afterZone == null ? null : beforeZone - afterZone,
          },
        ]
      }),
    )

    outcomes[featureId] = {
      feature_id: featureId,
      before_burden_score_1_to_100: before,
      after_burden_score_1_to_100: after,
      improvement_points: improvementPoints,
      absolute_change_points: Math.abs(improvementPoints),
      numeric_change_direction: numeric,
      minimum_detectable_change_points: threshold,
      treatable_gap_closed_percent:
        before <= 1
          ? 0
          : Math.round(clamp((improvementPoints / (before - 1)) * 100, -100, 100)),
      pairwise_change_direction: pairwiseDirection,
      pairwise_change_confidence:
        pairwiseComparison?.global_change_confidence ?? null,
      pairwise_validation_status: validation,
      score_reliability_tier: reliabilityTier(beforeFeature, afterFeature),
      display_numeric_change: shouldDisplayNumericChange({
        improvementPoints,
        threshold,
        validation,
        pairQuality,
      }),
      zones_visibly_improved:
        pairwiseComparison?.zones_visibly_improved ?? [],
      zones_visibly_worsened:
        pairwiseComparison?.zones_visibly_worsened ?? [],
      transient_reactivity_note:
        pairwiseComparison?.transient_reactivity_note ?? '',
      pairwise_summary: pairwiseComparison?.global_summary ?? '',
      zone_changes: zoneChanges,
    }
  }

  return outcomes
}

function buildDerivedParameterOutcomes(baselineState, postState, coreOutcomes) {
  const outcomes = {}
  const baselineParameters = baselineState.derived_report_parameters
  const postParameters = postState.derived_report_parameters

  for (const parameterId of Object.keys(baselineParameters)) {
    if (parameterId === 'skin_type') continue
    const beforeParameter = baselineParameters[parameterId]
    const afterParameter = postParameters[parameterId]
    const beforeBurden = beforeParameter.internal_burden_score_1_to_100
    const afterBurden = afterParameter.internal_burden_score_1_to_100
    const improvementPoints = beforeBurden - afterBurden
    const sourceFeatureIds = Object.keys(beforeParameter.source_components ?? {})
    const sourceValidations = sourceFeatureIds
      .map((featureId) => coreOutcomes[featureId]?.pairwise_validation_status)
      .filter(Boolean)

    let validation = 'not_pairwise_checked'
    if (sourceValidations.includes('contradicted')) validation = 'contradicted'
    else if (
      sourceValidations.includes('pairwise_inconclusive') ||
      sourceValidations.includes('numeric_change_not_visually_confirmed')
    ) {
      validation = 'inconclusive'
    } else if (sourceValidations.length && sourceValidations.every((value) => value === 'confirmed')) {
      validation = 'confirmed'
    } else if (sourceValidations.length) {
      validation = 'partially_supported'
    }

    outcomes[parameterId] = {
      parameter_id: parameterId,
      display_polarity: beforeParameter.display_polarity,
      before_internal_burden_score_1_to_100: beforeBurden,
      after_internal_burden_score_1_to_100: afterBurden,
      before_display_score_1_to_100: beforeParameter.display_score_1_to_100,
      after_display_score_1_to_100: afterParameter.display_score_1_to_100,
      improvement_points: improvementPoints,
      change_direction:
        improvementPoints > 0
          ? 'improved'
          : improvementPoints < 0
            ? 'worsened'
            : 'stable',
      validation_status: validation,
      source_feature_ids: sourceFeatureIds,
    }
  }

  outcomes.skin_type = {
    parameter_id: 'skin_type',
    before_label: baselineState.skin_type.label,
    after_label: postState.skin_type.label,
    before_modifiers: baselineState.skin_type.modifiers,
    after_modifiers: postState.skin_type.modifiers,
    change_direction:
      baselineState.skin_type.label === postState.skin_type.label &&
      JSON.stringify(baselineState.skin_type.modifiers) ===
        JSON.stringify(postState.skin_type.modifiers)
        ? 'stable'
        : 'changed',
  }

  return outcomes
}

async function resolveRun(input, common) {
  if (input?.skin_state && input?.evidence_packet) return input
  return runSkinStateV2({ ...common, ...input })
}

/**
 * Score baseline and post-treatment scans with the same absolute engine, then
 * use the pairwise vision module only to validate and explain the measured change.
 */
export async function runPostTreatmentReassessmentV2({
  baseline,
  post,
  visionCall,
  pairwiseCall = visionCall,
  modelVersion,
  cache = null,
  patientContext = null,
  minimumDetectableChange = DEFAULT_MIN_DETECTABLE_CHANGE_V2,
  includeRawPairwiseOutput = false,
}) {
  if (!baseline || !post) throw new Error('baseline and post inputs are required')
  if (typeof pairwiseCall !== 'function') {
    throw new TypeError('pairwiseCall must be an async function')
  }

  const baselineRun = await resolveRun(baseline, {
    visionCall,
    modelVersion,
    cache,
    patientContext,
    captureType: 'baseline',
  })

  if (baselineRun.skin_state?.schema_version !== SKIN_STATE_SCHEMA_VERSION) {
    throw new Error('Invalid baseline Skin State V2 result')
  }

  const baselineScanId = baselineRun.skin_state.scan.scan_id
  const postRun = await resolveRun(post, {
    visionCall,
    modelVersion,
    cache,
    patientContext,
    captureType: 'post_treatment',
    pairedBaselineScanId: baselineScanId,
  })

  if (postRun.skin_state?.schema_version !== SKIN_STATE_SCHEMA_VERSION) {
    throw new Error('Invalid post-treatment Skin State V2 result')
  }

  const pairwiseRaw = await pairwiseCall({
    module_id: 'pairwise_outcome',
    system_prompt: SYSTEM_PROMPT_PAIRWISE_OUTCOME_EVIDENCE_V2,
    prompt_version: PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION,
    images: {
      baseline: baseline.imagesByMode ?? null,
      post_treatment: post.imagesByMode ?? null,
    },
    input: {
      baseline_scan_id: baselineScanId,
      post_scan_id: postRun.skin_state.scan.scan_id,
      baseline_skin_state: baselineRun.skin_state,
      post_skin_state: postRun.skin_state,
      requested_features: [...CORE_FEATURE_IDS],
    },
  })
  const pairwise = validatePairwiseOutput(pairwiseRaw)

  const coreFeatureOutcomes = buildCoreFeatureOutcomes(
    baselineRun.skin_state,
    postRun.skin_state,
    pairwise,
    minimumDetectableChange,
  )
  const derivedParameterOutcomes = buildDerivedParameterOutcomes(
    baselineRun.skin_state,
    postRun.skin_state,
    coreFeatureOutcomes,
  )

  const result = {
    reassessment_version: POST_TREATMENT_REASSESSMENT_VERSION,
    scorer_version: SKIN_STATE_RUNNER_VERSION,
    baseline: {
      scan_id: baselineScanId,
      skin_state: baselineRun.skin_state,
    },
    post_treatment: {
      scan_id: postRun.skin_state.scan.scan_id,
      skin_state: postRun.skin_state,
    },
    pairwise_evidence: pairwise,
    core_feature_outcomes: coreFeatureOutcomes,
    derived_parameter_outcomes: derivedParameterOutcomes,
    generated_at_iso: new Date().toISOString(),
  }

  if (includeRawPairwiseOutput) result.raw_pairwise_output = pairwiseRaw
  return result
}
