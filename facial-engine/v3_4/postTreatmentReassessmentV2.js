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
    const hasMeasurement = Number.isFinite(before) && Number.isFinite(after)
    const improvementPoints = hasMeasurement ? before - after : null
    const threshold = thresholds[featureId] ?? 5
    const numeric = hasMeasurement ? numericDirection(improvementPoints, threshold) : 'insufficient_evidence'
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
      absolute_change_points: hasMeasurement ? Math.abs(improvementPoints) : null,
      numeric_change_direction: numeric,
      minimum_detectable_change_points: threshold,
      treatable_gap_closed_percent:
        !hasMeasurement || before <= 1
          ? 0
          : Math.round(clamp((improvementPoints / (before - 1)) * 100, -100, 100)),
      pairwise_change_direction: pairwiseDirection,
      pairwise_change_confidence:
        pairwiseComparison?.global_change_confidence ?? null,
      pairwise_change_magnitude: pairwiseComparison?.visible_change_magnitude ?? null,
      pairwise_validation_status: validation,
      score_reliability_tier: reliabilityTier(beforeFeature, afterFeature),
      display_numeric_change: hasMeasurement && shouldDisplayNumericChange({
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

function buildDerivedParameterOutcomes(baselineState, postState, coreOutcomes, pairQuality = null) {
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
    // V3.12: source_components is empty on the regional (V3.10+) path, which left every
    // derived parameter 'not_pairwise_checked'. Fall back to the core features whose
    // aggregation links point at this parameter so pairwise validation actually applies.
    const linkedFeatureIds = Object.entries(baselineState.core_features ?? {})
      .filter(([, feature]) => feature?.aggregation_details?.source_parameter === parameterId)
      .map(([featureId]) => featureId)
    const componentFeatureIds = Object.keys(beforeParameter.source_components ?? {})
      .filter((featureId) => coreOutcomes[featureId])
    const sourceFeatureIds = componentFeatureIds.length ? componentFeatureIds : linkedFeatureIds
    const sourceOutcomes = sourceFeatureIds.map((featureId) => coreOutcomes[featureId]).filter(Boolean)
    // V3.12: validate the derived parameter's OWN measured direction against the pairwise
    // evidence of its source features. Inheriting the core-feature validation was wrong for
    // balance parameters (skin_sebum): health can rise while the oiliness burden falls, or
    // fall when skin is over-stripped past balance.
    const mdc = sourceOutcomes.length
      ? Math.max(...sourceOutcomes.map((outcome) => outcome.minimum_detectable_change_points ?? 5))
      : 5
    const derivedDirection = numericDirection(improvementPoints, mdc)
    const pairwiseDirections = sourceOutcomes.map((outcome) => outcome.pairwise_change_direction).filter(Boolean)
    let validation = 'not_pairwise_checked'
    if (pairwiseDirections.length) {
      const set = new Set(pairwiseDirections)
      if (set.has('not_reliably_measurable')) validation = 'inconclusive'
      else if (set.has('mixed') || (set.has('improved') && set.has('worsened'))) {
        validation = derivedDirection === 'stable' ? 'pairwise_mixed' : 'partially_supported'
      } else if (set.size === 1 && set.has('stable')) {
        validation = derivedDirection === 'stable' ? 'confirmed' : 'inconclusive'
      } else {
        const visual = set.has('improved') ? 'improved' : 'worsened'
        if (derivedDirection === visual) validation = 'confirmed'
        else if (derivedDirection === 'stable') validation = 'weak_pairwise_only_change'
        else validation = 'contradicted'
      }
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
      confidence_0_1: Math.min(beforeParameter.data_quality?.confidence_0_1 ?? 1, afterParameter.data_quality?.confidence_0_1 ?? 1),
      estimated_comparison: !!(beforeParameter.data_quality?.is_estimated || afterParameter.data_quality?.is_estimated),
      source_feature_ids: sourceFeatureIds,
      // V3.12 display support (all derived from the validated core outcomes above).
      minimum_detectable_change_points: mdc,
      numeric_change_direction: derivedDirection,
      pairwise_change_directions: pairwiseDirections,
      pairwise_change_magnitudes: sourceOutcomes.map((outcome) => outcome.pairwise_change_magnitude).filter(Boolean),
      display_numeric_change:
        derivedDirection !== 'stable' &&
        pairQuality !== 'poor' &&
        ['confirmed', 'partially_supported'].includes(validation),
      zones_visibly_improved: [...new Set(sourceOutcomes.flatMap((outcome) => outcome.zones_visibly_improved ?? []))],
      zones_visibly_worsened: [...new Set(sourceOutcomes.flatMap((outcome) => outcome.zones_visibly_worsened ?? []))],
      transient_reactivity_notes: sourceOutcomes
        .map((outcome) => String(outcome.transient_reactivity_note ?? '').trim())
        .filter(Boolean),
      pairwise_summaries: sourceOutcomes.map((outcome) => String(outcome.pairwise_summary ?? '').trim()).filter(Boolean),
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
  intervalDays = null,
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

  const beforeVersion = baselineRun.skin_state.scoring_execution?.formula_config_version
  const afterVersion = postRun.skin_state.scoring_execution?.formula_config_version
  if (!beforeVersion || beforeVersion !== afterVersion) throw new Error('Cannot compare different scoring/calibration versions')

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
    pairwise?.comparison_meta?.pair_quality ?? null,
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
    comparison_context: {
      // Days between the reference scan and this post scan. null = unknown (treated as same-day
      // by the report adapter, i.e. structural parameters are carried forward, not rescored).
      interval_days: Number.isFinite(intervalDays) ? intervalDays : null,
      pair_quality: pairwise?.comparison_meta?.pair_quality ?? null,
      pair_quality_issues: pairwise?.comparison_meta?.pair_quality_issues ?? [],
    },
    generated_at_iso: new Date().toISOString(),
  }

  if (includeRawPairwiseOutput) result.raw_pairwise_output = pairwiseRaw
  return result
}
