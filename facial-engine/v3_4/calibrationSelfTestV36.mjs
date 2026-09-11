import {makeWire,runWire} from './calibrationTestFixturesV36.mjs'
import assert from 'node:assert/strict'
import {CORE_FEATURE_IDS} from './skinStateV2.schema.js'
import {FACE_ZONE_IDS} from './faceZoneAtlasV2.js'
import {MORPHOLOGY_FLAG_IDS_V3} from './visionMorphologyExclusionMapV3.js'
import {CORE_FEATURE_FORMULAS_V2 as F, DERIVED_REPORT_FORMULAS_V2 as D, buildSkinStateV2, scoreCoreFeature, assertFormulaIntegrity} from './skinStateScoreMapperV2.js'
import {decodeUnifiedVisionOutputV35, buildUnifiedVisionPromptV35, unifiedStructuredOutputFormatV35} from './unifiedVisionAdapterV35.js'
import {mergeVisionEvidencePacketsV2} from './mergeVisionEvidenceV2.js'
import {CALIBRATION_CONFIG_V36, calibrationKnotsV36, interpolateV36, deriveCalibratedParameterV36, normalizeContinuousGradeV36, CALIBRATION_VERSION_V36} from './clinicalCalibrationV36.js'
import {buildSkinAnalysisReportV3} from './buildSkinAnalysisReportV3.js'
import {buildLegacyDiagnosisV34,buildLegacyPostDiagnosisV34} from './legacyFacialAdapterV34.js'
import {runPostTreatmentReassessmentV2} from './postTreatmentReassessmentV2.js'
import {PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION} from './pairwiseOutcomeEvidenceV2.js'
import {COMPONENT_ANCHOR_SPECIFICATION_V2} from './componentAnchorSpecificationV2.js'
import { buildPreSessionPredictionV2 } from './predictedOutcomeEngineV2.js'

const checks=[]
function test(name, fn){fn();checks.push(name)}
const run=runWire(makeWire())
const state=run.skin_state

test('all formulas and component-specific anchors complete',()=>{
 assertFormulaIntegrity()
 for(const [id,f] of Object.entries(F))for(const c of Object.keys(f.components)) assert.equal(Object.keys(COMPONENT_ANCHOR_SPECIFICATION_V2.features[id].components[c].anchors).length,6)
 const prompt=buildUnifiedVisionPromptV35()
 assert(prompt.includes('One trace or equivocal inflammatory spot'))
 assert(prompt.includes('Marked dryness, flaking, barrier disruption'))
 assert(prompt.includes('Continuous grades'))
})
test('strict wire schema declares fractional grades',()=>{
 assert.equal(unifiedStructuredOutputFormatV35().schema.properties.f.properties['0'].properties.c.properties['0'].properties.g.items.type,'number')
 assert.equal(state.scoring_execution.calibration_version,CALIBRATION_VERSION_V36)
 assert.equal(run.evidence_packet.features.oiliness.zones.nose.components.visible_shine_intensity.grade_0_to_5,2.35)
})
test('NaN/out-of-range/missing grades and old wire are rejected',()=>{
 for(const bad of [NaN,Infinity,-.1,5.1,null,'2.1']) {
  const wire=makeWire();wire.f['0'].c['0'].g[0]=bad
  assert.throws(()=>runWire(wire))
 }
 const old=makeWire();old.v=5;assert.throws(()=>runWire(old),/version/)
})
test('every parameter uses continuous monotone mapping; no five-value snapping',()=>{
 for(const id of Object.keys(CALIBRATION_CONFIG_V36)) {
  const {xs,ys}=calibrationKnotsV36(id,D[id],F)
  assert.equal(xs.length,6)
  let prev=-1;const distinct=new Set()
  for(let i=0;i<=1000;i++){
   const y=interpolateV36(i/1000,xs,ys)
   assert(y>=prev-1e-10,`${id} nonmonotonic`);prev=y;distinct.add(100-Math.round(99*y))
  }
  assert.equal(distinct.size,100,`${id} must allow all 100 integer health values before quality cap`)
 }
})
test('exact legacy final-index boundaries and polarity retained',()=>{
 assert.deepEqual(CALIBRATION_CONFIG_V36.skin_luminosity_glow.cuts,[.25,.40,.60,.78])
 assert.deepEqual(CALIBRATION_CONFIG_V36.barrier_health_sensitivity.cuts,[.20,.35,.55,.75])
 assert.equal(CALIBRATION_CONFIG_V36.skin_hydration.legacy_polarity,'higher_is_better')
 assert.equal(CALIBRATION_CONFIG_V36.skin_firmness_elasticity.legacy_polarity,'higher_is_worse')
 assert.match(CALIBRATION_CONFIG_V36.jawline_sagging.cuts_origin,/inferred/)
 for (const [id,cfg] of Object.entries(CALIBRATION_CONFIG_V36)) {
  const {xs,ys}=calibrationKnotsV36(id,D[id],F)
  for (let i=1;i<xs.length-1;i++) assert(Math.abs(interpolateV36(xs[i]+1e-8,xs,ys)-interpolateV36(xs[i]-1e-8,xs,ys))<1e-6)
 }
})
test('continuous changes register within one old integer grade',()=>{
 const a=runWire(makeWire(2.10)).skin_state,b=runWire(makeWire(2.45)).skin_state
 for(const id of Object.keys(D).filter(id=>id!=='skin_sebum'))assert(a.derived_report_parameters[id].display_score_1_to_100>b.derived_report_parameters[id].display_score_1_to_100,id)
})
test('confidence/corroboration cannot create disease severity',()=>{
 const high=makeWire(2.35,95),low=makeWire(2.35,40)
 for(const feat of Object.values(low.f))for(const col of Object.values(feat.c))col.x.fill(0)
 const a=runWire(high).skin_state,b=runWire(low).skin_state
 for(const id of CORE_FEATURE_IDS) assert.equal(a.core_features[id].global_burden_score_1_to_100,b.core_features[id].global_burden_score_1_to_100,id)
 assert(a.core_features.oiliness.score_reliability.score_1_to_100>b.core_features.oiliness.score_reliability.score_1_to_100)
})
test('absent feature remains zero burden even with high corroboration',()=>{
 const s=runWire(makeWire(0)).skin_state
 for(const f of Object.values(s.core_features))assert.equal(f.guarded_normalized_burden_0_to_1,0)
 assert.equal(s.derived_report_parameters.jawline_sagging.display_score_1_to_100,99)
 assert.equal(s.derived_report_parameters.visual_acne.display_score_1_to_100,100)
 const l=runWire(makeWire(0,40)).skin_state
 assert.equal(l.derived_report_parameters.visual_acne.display_score_1_to_100,99)
})
test('unobservable feature is rejected instead of reported as healthy',()=>{
 const w=makeWire();w.f['0'].s.fill(2);w.f['0'].v.fill(0)
 assert.throws(()=>runWire(w),/no usable zones/)
})
function setCoreOil(s,value,dry=.4) {
 const c=structuredClone(s.core_features)
 for(const z of Object.keys(c.oiliness.zone_normalized_burdens_0_to_1))c.oiliness.zone_normalized_burdens_0_to_1[z]=value
 c.oiliness.guarded_normalized_burden_0_to_1=value
 c.visual_dehydration.guarded_normalized_burden_0_to_1=dry
 return c
}
test('sebum balance penalizes both excess and low-oil dryness; low shine alone is not dryness',()=>{
 const score=(oil,dry)=>deriveCalibratedParameterV36('skin_sebum',setCoreOil(state,oil,dry),D.skin_sebum,F).display_score_1_to_100
 const target=interpolateV36(.5,[.05,.15,.25,.55,.8,1],[0,.12,.3,.5,.73,1])
 assert(score(target,.8)>score(0,.8))
 assert(score(target,.8)>score(1,.8))
 assert(score(0,0)>score(0,.8))
})
test('regional mixed oil pattern gives combination skin',()=>{
 const w=makeWire(1)
 const fi=CORE_FEATURE_IDS.indexOf('oiliness'),zones=F.oiliness.applicable_zones
 for(const col of Object.values(w.f[fi].c))for(let i=0;i<zones.length;i++) if(['nose','glabella','forehead_left','forehead_center','forehead_right','chin'].includes(zones[i])) {col.g[i]=3;col.c[i]=50;col.t[i]=50;col.r[i]=50}
 assert.equal(runWire(w).skin_state.skin_type.label,'combination')
})
test('baseline/report/legacy payload agree on 1–100 higher-is-better',()=>{
 const report=buildSkinAnalysisReportV3({skinState:state}),legacy=buildLegacyDiagnosisV34({skinState:state,skinAnalysisReport:report})
 const cards=report.primary_client_assessment.parameter_cards.filter(p=>p.value_type==='score')
 assert.equal(cards.length,14)
 for(const card of cards){const d=Object.values(legacy.diagnosis_report).find(x=>x.parameter_id===card.parameter_id);assert.equal(d.score_or_label,card.client_health_score_1_to_100);assert.equal(d.score_polarity,'higher_is_better');assert.match(d.score_scale,/100/)}
 const post=buildLegacyPostDiagnosisV34({baseline:{skin_state:state},post_treatment:{skin_state:state}})
 for(const d of Object.values(post.reassessment).filter(d=>typeof d.before_treatment_score_or_label==='number'))assert(d.before_treatment_score_or_label>5)
})
test('no-treatment prediction preserves calibrated baseline and ordered ranges',()=>{
 const prediction=buildPreSessionPredictionV2({baselineSkinState:state,optimizerResult:{plan:{treatment_mode:'single',modality_actions:[]}}})
 for(const [id,p] of Object.entries(prediction.predicted_report_parameters).filter(([id])=>id!=='skin_type')){
   for(const horizon of Object.values(p.horizons)){
     const b=horizon.predicted_internal_burden_score_1_to_100
     assert.equal(b.expected,state.derived_report_parameters[id].internal_burden_score_1_to_100,id)
     assert(b.best_case<=b.expected && b.expected<=b.conservative,id)
   }
 }
})
// Verify genuine API surface below; exports checked explicitly by import.
assert.equal(typeof buildPreSessionPredictionV2,'function')
const baseline={...run,imagesByMode:{white:'synthetic-baseline'}}
const post={...structuredClone(run),imagesByMode:{white:'synthetic-post'}}
let pairImages
const pairwiseCall=async request=>{pairImages=request.images;return {pairwise_outcome_version:PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION,comparison_meta:{pair_quality:'good'},feature_comparisons:Object.fromEntries(CORE_FEATURE_IDS.map(id=>[id,{global_change_direction:'stable'}]))}}
await runPostTreatmentReassessmentV2({baseline,post,pairwiseCall,modelVersion:'offline-fixture'})
assert.deepEqual(pairImages,{baseline:baseline.imagesByMode,post_treatment:post.imagesByMode})
checks.push('reassessment pairwise receives both original image sets')
post.skin_state.scoring_execution.formula_config_version='old-scoring'
await assert.rejects(()=>runPostTreatmentReassessmentV2({baseline,post,pairwiseCall,modelVersion:'offline-fixture'}),/different scoring/)
checks.push('mixed-version reassessment rejected')
console.log(JSON.stringify({ok:true,checks:checks.length,passed:checks,scope:'Offline synthetic software validation; no real scans or live model calls',sample_scores:Object.fromEntries(Object.entries(state.derived_report_parameters).filter(([,p])=>p.calibration).map(([id,p])=>[id,p.display_score_1_to_100]))},null,2))
