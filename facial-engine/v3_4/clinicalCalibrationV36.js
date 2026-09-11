import { LEGACY_CLINICAL_ANCHORS_V36 } from './legacyClinicalAnchorsV36.js'
import { FACE_ZONE_ATLAS_V2 } from './faceZoneAtlasV2.js'

export const CALIBRATION_VERSION_V36 = 'aia_legacy_anchor_bridge_v3.6.0'
export const CALIBRATION_STATUS_V36 = 'legacy_rubric_transferred_interpolation_not_clinically_validated'
const clamp = x => Math.max(0, Math.min(1, x))
const G = [0, .12, .30, .50, .73, 1]
export function interpolateV36(value, xs, ys) {
  if (!Number.isFinite(value)) throw new Error('Calibration requires finite evidence')
  if (xs.length !== ys.length || xs.length < 2 || xs.some((x,i) => !Number.isFinite(x) || (i && x <= xs[i-1]))) throw new Error('Invalid calibration knots')
  if (value <= xs[0]) return ys[0]
  for (let i=1;i<xs.length;i++) if (value <= xs[i]) return ys[i-1] + (ys[i]-ys[i-1]) * (value-xs[i-1])/(xs[i]-xs[i-1])
  return ys.at(-1)
}
export function normalizeContinuousGradeV36(grade) { return interpolateV36(grade,[0,1,2,3,4,5],G) }

// Exact source cuts where the old FINAL parameter index defines numeric bins.
// These are not claims that new core burdens equal the old pixel-proxy indices.
const EXACT_CUTS = {
  barrier_health_sensitivity: [.20,.35,.55,.75],
  visual_acne: [.20,.35,.55,.75],
  skin_hydration: [.30,.45,.60,.75],
  skin_luminosity_glow: [.25,.40,.60,.78],
  superficial_pigmentation: [.18,.36,.58,.78],
  texture_open_pores: [.18,.34,.56,.76],
  superficial_wrinkles: [.18,.34,.54,.75],
}
// Explicit implementation choices: source has descriptive or component bands,
// but NO final index cutoffs for these parameters. Never label these as recovered.
const INFERRED_CUTS = {
  vascularity_redness: [.20,.40,.60,.80],
  peri_orbital_health: [.20,.40,.60,.80],
  lip_pigmentation: [.117,.267,.467,.667], // new .46/.34/.20 weights + old darkness/melanin bands; unevenness uses darkness proxy
  jawline_sagging: [.20,.40,.60,.80],
  skin_firmness_elasticity: [.20,.35,.55,.75], // micro-laxity bands used as provisional whole-parameter proxy
  textural_radiance: [.20,.35,.55,.75], // provisional; no old final TRI cutoffs supplied
}
export const CALIBRATION_CONFIG_V36 = Object.fromEntries(
  Object.keys(LEGACY_CLINICAL_ANCHORS_V36).filter(id=>id!=='skin_sebum').map(id=>[id,{
    cuts: EXACT_CUTS[id] ?? INFERRED_CUTS[id],
    cuts_origin: EXACT_CUTS[id] ? 'exact_legacy_final_index_thresholds' : 'inferred_not_in_legacy_final_score_specification',
    legacy_polarity: LEGACY_CLINICAL_ANCHORS_V36[id].polarity_metadata.score_polarity,
    source_variable: LEGACY_CLINICAL_ANCHORS_V36[id].source_variable,
    bridge_origin: 'inferred_semantic_anchor_interpolation_with_new_formula_reference_points',
  }]),
)

// Uniform reference severity is used only to define deterministic curve knots;
// no artificial patient, historical report or target outcome enters scoring.
export function calibrationKnotsV36(parameterId, definition, formulas) {
  const cfg=CALIBRATION_CONFIG_V36[parameterId]
  if (!cfg) throw new Error(`Missing calibration config: ${parameterId}`)
  const xs=G.map(g=>Object.entries(definition.components).reduce((sum,[id,w])=>{
    const a=formulas[id].aggregation
    return sum+w*((a.mean+a.top_zones)*g+a.extent*clamp(g/.5))
  },0))
  const bounds=[0,...cfg.cuts,1]
  const centers=bounds.slice(0,-1).map((v,i)=>(v+bounds[i+1])/2)
  // New grade 0=absent; grades 1..4 map to legacy clinical anchor centers;
  // grade 5 represents the extreme endpoint. Health scales reverse the centers.
  const ys=cfg.legacy_polarity==='higher_is_better'
    ? [0,1-centers[4],1-centers[3],1-centers[2],1-centers[1],1]
    : [0,...centers.slice(0,4),1]
  return {xs,ys}
}
const rawFeature = f => {
  const n=f?.guarded_normalized_burden_0_to_1
  if (Number.isFinite(n)) return n
  if (Number.isFinite(f?.global_burden_score_1_to_100)) return (f.global_burden_score_1_to_100-1)/99
  throw new Error('Missing core burden for calibration')
}
function sebumBalance(core) {
  const f=core.oiliness
  const zoneRaw=z=> f.zone_normalized_burdens_0_to_1?.[z] ?? (Number.isFinite(f.zone_scores_1_to_100?.[z]) ? (f.zone_scores_1_to_100[z]-1)/99 : null)
  const mean=ids=>{const v=ids.map(zoneRaw).filter(Number.isFinite);if(!v.length)throw new Error('Sebum/skin type requires visible T-zone and cheek evidence');return v.reduce((a,b)=>a+b,0)/v.length}
  const tRaw=mean(FACE_ZONE_ATLAS_V2.groups.t_zone), cRaw=mean(FACE_ZONE_ATLAS_V2.groups.cheeks)
  // Old oil midpoints: none .05, mild .25, moderate .55, strong .80.
  // Trace .15 and extreme 1 are explicit new interpolation endpoints.
  const amount=r=>interpolateV36(r,G,[.05,.15,.25,.55,.80,1])
  const t=amount(tRaw), c=amount(cRaw), oil=.65*t+.35*c
  const dryness=rawFeature(core.visual_dehydration)
  const distance=clamp(Math.abs(oil-.50)/.50)
  // Low shine is not proof of lipid deficiency. The low-oil arm requires dry
  // appearance; excessive oil remains a concern without dehydration evidence.
  const burden=oil<.50 ? distance*dryness : distance
  return {burden,audit:{t_zone_oil_state:t,cheek_oil_state:c,oil_state_0_to_1:oil,target:.50,
    target_source:'legacy_aiPrompts.hydration.sebum_balance_ratio',
    legacy_sebum_state_grade_1_to_5:1+[.20,.38,.58,.78].filter(x=>oil>=x).length,
    dry_appearance_burden:dryness,distance_from_target:distance,
    low_oil_dryness_gate:'new_inferred_visual_guardrail',
    oil_state_bridge:'inferred_new_visual_oil_evidence_to_legacy_bin_midpoints',
    t_zone_raw:tRaw,cheek_raw:cRaw}}
}
function trustworthyIdeal(core,definition) {
  return Object.keys(definition.components).every(id=>{
    const f=core[id],a=f?.score_reliability?.audit
    return a && a.usable_area_fraction_0_to_1>=.95 && a.mean_component_confidence_0_to_100>=90 &&
      f.score_reliability.score_1_to_100>=90 && !(f.score_reliability.reasons?.length) &&
      !['visible_laxity','firmness_appearance_loss'].includes(id)
  })
}
export function deriveCalibratedParameterV36(parameterId,core,definition,formulas,{idealCap=true}={}) {
  const raw=Object.entries(definition.components).reduce((s,[id,w])=>s+w*rawFeature(core[id]),0)
  let burden,audit
  if(parameterId==='skin_sebum') {
    const result=sebumBalance(core);burden=result.burden;audit=result.audit
  } else {
    const cfg=CALIBRATION_CONFIG_V36[parameterId], knots=calibrationKnotsV36(parameterId,definition,formulas)
    burden=clamp(interpolateV36(raw,knots.xs,knots.ys))
    const legacyIndex=cfg.legacy_polarity==='higher_is_better'?1-burden:burden
    const grade=1+cfg.cuts.filter(c=>legacyIndex>=c).length
    const legacy=LEGACY_CLINICAL_ANCHORS_V36[parameterId]
    const entry=legacy.score_bins?.[grade] ?? legacy.clinical_definitions?.[grade]
    audit={...cfg,knots,legacy_equivalent_index_0_to_1:legacyIndex,legacy_equivalent_grade_1_to_5:grade,
      legacy_anchor_label:entry?.label??entry?.anchor??null}
  }
  let health=100-Math.round(99*clamp(burden))
  const cap=idealCap && health===100 && !trustworthyIdeal(core,definition)
  if(cap)health=99
  return {
    parameter_id:parameterId,
    internal_burden_score_1_to_100:101-health,
    display_score_1_to_100:health,
    display_polarity:'higher_is_better',
    display_band:health>=85?'excellent':health>=70?'good':health>=50?'moderate':health>=30?'needs_attention':'high_concern',
    source_components:definition.components,
    calibration:{version:CALIBRATION_VERSION_V36,status:CALIBRATION_STATUS_V36,
      raw_new_composite_burden_0_to_1:raw,calibrated_concern_0_to_1:burden,
      unrounded_health_1_to_100:100-99*burden,ideal_score_cap_applied:cap,...audit},
  }
}
export function deriveSkinTypeV36(core) {
  const s=sebumBalance(core),a=s.audit,t=a.t_zone_oil_state,c=a.cheek_oil_state,oil=a.oil_state_0_to_1,dry=a.dry_appearance_burden
  const mixed=Math.abs(t-c)>=.15 && Math.max(t,c)>=.25
  let label='balanced'
  if(mixed)label='combination'
  else if(oil>.55 && dry<.40)label='oily'
  else if(oil<.25 && dry>=.30)label='dry'
  else if(oil>=.25 && oil<=.55)label='combination'
  const modifiers=[]
  if(dry>.45)modifiers.push('dehydrated')
  if(rawFeature(core.erythema_redness)>.60)modifiers.push('redness_prone')
  if(rawFeature(core.barrier_stress)>.55)modifiers.push('barrier_stressed')
  return {label,modifiers,evidence:{...a,t_zone_oiliness_1_to_100:1+Math.round(99*a.t_zone_raw),cheek_oiliness_1_to_100:1+Math.round(99*a.cheek_raw),dehydration_burden_1_to_100:1+Math.round(99*dry),regional_pattern:mixed?'mixed':'uniform',mixed_pattern_threshold_origin:'inferred_0.15_on_legacy_oil_state_scale'}}
}
