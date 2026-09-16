// Offline self-test for the V3.12 post-diagnosis display policy.
// Synthetic fixtures only; no OpenAI calls, no clinical accuracy claims.
//   node facial-engine/v3_4/reassessmentDisplaySelfTestV312.mjs
import assert from 'node:assert/strict'
import { CONTRACT, GROUPS, decodeMeasurements } from './regionalMeasurementV310.js'
import { metricBoundsV37 } from './legacyMeasurementContractV37.js'
import { buildRegionalRun } from './regionalScoringV310.js'
import { runPostTreatmentReassessmentV2 } from './postTreatmentReassessmentV2.js'
import { buildLegacyPostDiagnosisV34, POST_DIAGNOSIS_DISPLAY_POLICY_V312 } from './legacyFacialAdapterV34.js'
import { decodeCompactPairwise } from './compactPairwiseV393.js'
import { CORE_FEATURE_IDS } from './skinStateV2.schema.js'

const LEGACY_KEY = {
  skin_sebum: 'skin_sebum_content',
  skin_luminosity_glow: 'skin_luminosity_glow_index',
  texture_open_pores: 'texture_open_pores_scoring',
  vascularity_redness: 'vascularity_redness_profiling',
  jawline_sagging: 'jawline_sagging_score',
  skin_firmness_elasticity: 'skin_firmness_elasticity_index',
  barrier_health_sensitivity: 'barrier_health_sensitivity',
}
// core feature -> report parameter (mirrors regionalScoringV310 LINKS)
const FEATURE_PARAM = {
  active_inflammatory_acne: 'visual_acne', comedonal_congestion: 'visual_acne', oiliness: 'skin_sebum',
  erythema_redness: 'vascularity_redness', barrier_stress: 'barrier_health_sensitivity', visual_dehydration: 'skin_hydration',
  pore_visibility: 'texture_open_pores', texture_roughness: 'texture_open_pores', visible_pigmentation: 'superficial_pigmentation',
  underlying_pigment_support: 'superficial_pigmentation', luminosity_loss: 'skin_luminosity_glow', fine_line_visibility: 'superficial_wrinkles',
  visible_laxity: 'jawline_sagging', firmness_appearance_loss: 'skin_firmness_elasticity', peri_orbital_concern: 'peri_orbital_health',
  lip_pigmentation: 'lip_pigmentation',
}

// metricOverrides: { parameterId: { metricName: value } } applied to every region.
function fixtureAt(factorByParameter = {}, metricOverrides = {}) {
  return {
    regions: Object.fromEntries(Object.keys(GROUPS).map((g) => [g, { visible_fraction: 1, obstruction_note: '' }])),
    parameters: Object.fromEntries(Object.entries(CONTRACT).map(([id, c]) => {
      const factor = factorByParameter[id] ?? 0.35
      const over = metricOverrides[id] ?? {}
      return [id, {
        regions: Object.fromEntries(c.regions.map((g) => [g, {
          values: c.observed_metrics.map((n) => { if (over[n] !== undefined) return over[n]; const [lo, hi] = metricBoundsV37(id, n); return lo + factor * (hi - lo) }),
          confidence: 0.9, estimated: false, landmark_reference: 'Synthetic anatomical reference',
        }])),
        observation: 'Synthetic contract fixture, not patient evidence.',
      }]
    })),
    skin_type: { label: 'combination', confidence: 0.9, observation: 'Synthetic skin type' },
  }
}
const run = (fixture, scanId) => buildRegionalRun(decodeMeasurements(fixture), { scanId, imageSetHash: scanId, modelVersion: 'mock' })
const health = (state, id) => state.skin_state.appearance_report_parameters?.[id]?.unrounded_health_1_to_100 ?? (101 - state.skin_state.derived_report_parameters[id].internal_burden_score_1_to_100)

function pairwise(directionByParameter = {}, pairQuality = 'good', magnitudeByParameter = null) {
  const value = {
    comparison_meta: { pair_quality: pairQuality, pair_quality_issues: [] },
    feature_comparisons: Object.fromEntries(CORE_FEATURE_IDS.map((id) => [id, {
      global_change_direction: directionByParameter[FEATURE_PARAM[id]] ?? 'stable',
      ...(magnitudeByParameter ? { visible_change_magnitude: magnitudeByParameter[FEATURE_PARAM[id]] ?? 'none' } : {}),
      global_change_confidence: 'high', zones_visibly_improved: [], zones_visibly_worsened: [],
      transient_reactivity_note: directionByParameter[FEATURE_PARAM[id]] === 'worsened' ? 'Treatment-day flush visible on cheeks.' : '',
      global_summary: 'Synthetic evidence.',
    }])),
  }
  return decodeCompactPairwise(value, { baseline_scan_id: 'b', post_scan_id: 'p' })
}

// Find, per parameter, a metric factor that moves health up or down relative to the 0.35 baseline.
function factorFor(id, wantHigher) {
  const base = health(run(fixtureAt(), 'probe-b'), id)
  for (const f of [0.1, 0.6, 0.05, 0.75]) {
    const h = health(run(fixtureAt({ [id]: f }), 'probe-' + f), id)
    if (wantHigher ? h - base >= 8 : base - h >= 8) return f
  }
  throw new Error(`No factor moves ${id} ${wantHigher ? 'up' : 'down'} by >=8 points`)
}

const HYDRATED = { skin_hydration: { plumpness_index: 0.9, dewy_finish_index: 0.9, microline_density_index: 0.1, visible_dry_patch_index: 0.05 } }
const GLOWING = { skin_luminosity_glow: { diffuse_brightness_index: 0.9, tone_evenness_index: 0.9, surface_reflectance_uniformity: 0.9, dryness_dullness_index: 0.1 } }
const checks = []
async function reassess(post, pw, opts = {}) {
  const baseline = opts.baseline ?? run(fixtureAt(), 'baseline')
  const result = await runPostTreatmentReassessmentV2({
    baseline: { ...baseline, imagesByMode: {} }, post: { ...post, imagesByMode: {} },
    pairwiseCall: async () => pw, modelVersion: 'mock', intervalDays: opts.intervalDays ?? null,
  })
  return { result, legacy: buildLegacyPostDiagnosisV34(result, { intervalDays: opts.intervalDays ?? null }) }
}

// 1. Identical scans: everything stable, structural carried forward, no internal strings.
{
  const { result, legacy } = await reassess(run(fixtureAt(), 'post-same'), pairwise())
  const r = legacy.reassessment
  assert.equal(r.skin_sebum_content.result, 'stable')
  assert.equal(r.jawline_sagging_score.result, 'not_assessed_this_interval')
  assert.equal(r.jawline_sagging_score.post_treatment_score_or_label, r.jawline_sagging_score.before_treatment_score_or_label)
  assert(!JSON.stringify(legacy.reassessment).includes('See V3.4'), 'internal build reference leaked')
  assert.notEqual(result.derived_parameter_outcomes.skin_sebum.validation_status, 'not_pairwise_checked')
  assert.equal(legacy.metadata.display_policy_version, POST_DIAGNOSIS_DISPLAY_POLICY_V312.version)
  checks.push('Identical scans are stable; structural parameters carried forward; pairwise validation reaches derived outcomes')
}

// 2. Confirmed improvement: displayed delta equals rounded after minus rounded before.
{
  const { legacy } = await reassess(run(fixtureAt({}, { ...HYDRATED, ...GLOWING }), 'post-up'), pairwise({ skin_hydration: 'improved', skin_luminosity_glow: 'improved' }))
  for (const key of ['skin_hydration_score', 'skin_luminosity_glow_index']) {
    const item = legacy.reassessment[key]
    assert.equal(item.result, 'improved', key)
    const shown = item.post_treatment_score_or_label - item.before_treatment_score_or_label
    assert.equal(item.patient_facing_change_points, shown)
    assert(item.score_explanation.includes(`improved by ${shown} points`), item.score_explanation)
    assert.equal(item.raw.pairwise_validation_status, 'confirmed')
  }
  assert.equal(legacy.reassessment.skin_sebum_content.ideal_score_direction, 'balance')
  checks.push('Confirmed improvement displays a delta equal to the rounded scores shown')
}
// 2b. Sebum is a balance parameter: shiny baseline (0.6) becoming balanced (0.35) is an improvement.
{
  const shiny = run(fixtureAt({ skin_sebum: 0.6 }), 'baseline-shiny')
  const { legacy } = await reassess(run(fixtureAt(), 'post-balanced'), pairwise({ skin_sebum: 'improved' }), { baseline: shiny })
  const item = legacy.reassessment.skin_sebum_content
  assert.equal(item.result, 'improved')
  assert(item.post_treatment_score_or_label > item.before_treatment_score_or_label)
  checks.push('Sebum: over-shiny baseline moving to balance shows as improved')
}

// 3. Numeric change the pairwise comparison does not see: not shown, baseline carried forward, raw kept.
{
  const up = { texture_open_pores: factorFor('texture_open_pores', true) }
  const { legacy } = await reassess(run(fixtureAt(up), 'post-unconfirmed'), pairwise({}))
  const item = legacy.reassessment.texture_open_pores_scoring
  assert.equal(item.result, 'stable')
  assert.equal(item.post_treatment_score_or_label, item.before_treatment_score_or_label)
  assert.equal(item.raw.display_blocked_reason, 'pairwise_inconclusive')
  assert(item.raw.after_health_score_1_to_100 > item.v3_4_before_health_score_1_to_100)
  checks.push('Unconfirmed numeric change is not shown, raw reading retained for the clinic')
}

// 4. Confirmed decline stays honest in the data with a transient note for reactive parameters.
{
  const down = { vascularity_redness: factorFor('vascularity_redness', false) }
  const { legacy } = await reassess(run(fixtureAt(down), 'post-down'), pairwise({ vascularity_redness: 'worsened' }))
  const item = legacy.reassessment.vascularity_redness_profiling
  assert.equal(item.result, 'declined')
  assert.equal(item.raw_comparison_result_internal, 'declined')
  assert(item.post_treatment_score_or_label < item.before_treatment_score_or_label)
  assert(item.transient_reactivity_note.toLowerCase().includes('flush') || item.transient_reactivity_note.toLowerCase().includes('redness'))
  checks.push('Confirmed decline is recorded as declined with a transient-reactivity note')
}

// 5. Poor pair quality blocks every numeric change.
{
  const { legacy } = await reassess(run(fixtureAt({}, HYDRATED), 'post-poor'), pairwise({ skin_hydration: 'improved' }, 'poor'))
  const item = legacy.reassessment.skin_hydration_score
  assert.equal(item.result, 'stable')
  assert.equal(item.raw.display_blocked_reason, 'pair_quality_poor')
  checks.push('Poor pair quality carries the baseline forward and asks for a repeat scan')
}

// 6. Structural parameters may be rescored once the interval is long enough.
{
  const up = { jawline_sagging: factorFor('jawline_sagging', true) }
  const post = run(fixtureAt(up), 'post-course-end')
  const sameDay = await reassess(post, pairwise({ jawline_sagging: 'improved' }))
  const courseEnd = await reassess(post, pairwise({ jawline_sagging: 'improved' }), { intervalDays: 30 })
  assert.equal(sameDay.legacy.reassessment.jawline_sagging_score.result, 'not_assessed_this_interval')
  assert.equal(courseEnd.legacy.reassessment.jawline_sagging_score.result, 'improved')
  assert.equal(courseEnd.legacy.metadata.structural_parameters_assessed, true)
  checks.push('Structural parameters rescore only after the minimum interval')
}

// ---- V3.13 pairwise-first cases (magnitude supplied) ----
// 7. Numeric flat, pairwise clear improvement: shown, bounded by the numeric margin.
{
  const { legacy } = await reassess(run(fixtureAt(), 'post-flat'), pairwise({ skin_hydration: 'improved' }, 'good', { skin_hydration: 'clear' }))
  const item = legacy.reassessment.skin_hydration_score
  assert.equal(item.result, 'improved')
  assert.equal(item.raw.scoring_path, 'pairwise_first_v3_13')
  assert.equal(item.post_treatment_score_or_label - item.before_treatment_score_or_label, 6, 'clear band on a flat numeric is capped at the margin')
  checks.push('Pairwise-first: clear visible improvement shows, capped by numeric margin when measurement is flat')
}
// 8. Numeric support plus strong band: full table points.
{
  const { legacy } = await reassess(run(fixtureAt({}, HYDRATED), 'post-strong'), pairwise({ skin_hydration: 'improved' }, 'good', { skin_hydration: 'strong' }))
  const item = legacy.reassessment.skin_hydration_score
  assert.equal(item.result, 'improved')
  assert.equal(item.post_treatment_score_or_label - item.before_treatment_score_or_label, 12)
  checks.push('Pairwise-first: strong band with numeric support gives full table points')
}
// 9. Blackhead clearance reaches the pores score via comedonal_congestion.
{
  const { legacy } = await reassess(run(fixtureAt(), 'post-comedones'), pairwise({ visual_acne: 'improved' }, 'good', { visual_acne: 'clear', texture_open_pores: 'none' }))
  const item = legacy.reassessment.texture_open_pores_scoring
  assert.equal(item.result, 'improved', 'comedonal_congestion improvement must reach pores')
  checks.push('Pairwise-first: comedone clearance is credited to Texture + Open Pores')
}
// 10. Worsened slight on redness: declined with note, not hidden in the data.
{
  const { legacy } = await reassess(run(fixtureAt(), 'post-flush'), pairwise({ vascularity_redness: 'worsened' }, 'good', { vascularity_redness: 'slight' }))
  const item = legacy.reassessment.vascularity_redness_profiling
  assert.equal(item.result, 'declined')
  assert.equal(item.before_treatment_score_or_label - item.post_treatment_score_or_label, 3)
  assert(item.transient_reactivity_note !== 'none')
  checks.push('Pairwise-first: slight worsening is recorded as declined with a transient note')
}
// 11. Band none: stable, baseline carried forward.
{
  const { legacy } = await reassess(run(fixtureAt(), 'post-none'), pairwise({ skin_luminosity_glow: 'improved' }, 'good', { skin_luminosity_glow: 'none' }))
  const item = legacy.reassessment.skin_luminosity_glow_index
  assert.equal(item.result, 'stable')
  assert.equal(item.post_treatment_score_or_label, item.before_treatment_score_or_label)
  checks.push('Pairwise-first: no visible change stays stable')
}
// 12. Appearance scores exist for every parameter and drive the baseline card.
{
  const b = run(fixtureAt(), 'appearance-check').skin_state
  assert(Object.keys(b.appearance_report_parameters).length >= 14)
  assert.equal(b.appearance_report_parameters.jawline_sagging.source, 'engine_health_passthrough_structural')
  assert.equal(b.appearance_report_parameters.skin_hydration.source, 'appearance_formula_v3_13')
  checks.push('Appearance scores present; structural parameters pass the engine health through')
}

console.log(JSON.stringify({ ok: true, checks, scope: 'Synthetic fixtures; no clinical accuracy or live model validation.' }, null, 2))
