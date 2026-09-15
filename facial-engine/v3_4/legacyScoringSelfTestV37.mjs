import {readFileSync} from 'node:fs'
import vm from 'node:vm'
import {LEGACY_RUBRIC_V37} from './legacyRubricV37.js'
import assert from 'node:assert/strict'
import {makeMeasurements,makeWire,runWire} from './legacyScoringFixturesV37.mjs'
import {PARAMETER_CONTRACT_V37,validateLegacyMeasurementsV37} from './legacyMeasurementContractV37.js'
import {legacyIndexV37,scoreLegacyMeasurementsV37,CALIBRATION_VERSION_V37} from './legacyScoringV37.js'
import {buildSkinStateV2} from './skinStateScoreMapperV2.js'
import {buildUnifiedVisionPromptV35,unifiedStructuredOutputFormatV35,encodeEvidencePacketToUnifiedWireV35} from './unifiedVisionAdapterV35.js'
import {buildSkinAnalysisReportV3} from './buildSkinAnalysisReportV3.js'
import {buildLegacyDiagnosisV34,buildLegacyPostDiagnosisV34} from './legacyFacialAdapterV34.js'
import {buildPreSessionPredictionV2} from './predictedOutcomeEngineV2.js'
import {runSkinStateV2} from './runSkinStateV2.js'
import {runPostTreatmentReassessmentV2} from './postTreatmentReassessmentV2.js'
import {PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION} from './pairwiseOutcomeEvidenceV2.js'
import {CORE_FEATURE_IDS,IMAGE_MODES} from './skinStateV2.schema.js'
const passed=[]
const test=(n,f)=>{f();passed.push(n)}
const near=(a,b)=>assert(Math.abs(a-b)<1e-10,`${a} != ${b}`)
const set=(p,id,values)=>{for(const [n,v] of Object.entries(values))p.parameters[id].metrics[n].value=v}
const run=runWire(makeWire()),s=run.skin_state

test('frozen rubric objects exactly match supplied original source',()=>{
 const source=readFileSync(new URL('./reference/legacy-scoring-source.js.txt',import.meta.url),'utf8').split('export const SYSTEM_PROMPT_DIAGNOSIS')[0].replace(/^import .*\r?\n/m,'')
 const names=Object.keys(LEGACY_RUBRIC_V37),ctx={}
 vm.runInNewContext(source+'\nthis.result={'+names.join(',')+'}',ctx)
 const original=JSON.parse(JSON.stringify(ctx.result))
 for(const name of names)assert.deepEqual(LEGACY_RUBRIC_V37[name],Object.values(original[name])[0],name)
})
test('all 14 numeric client scores and calibration identity',()=>{
 assert.equal(s.scoring_execution.calibration_version,CALIBRATION_VERSION_V37)
 assert.equal(Object.keys(s.derived_report_parameters).length,15)
 for(const id of Object.keys(PARAMETER_CONTRACT_V37)){const x=s.derived_report_parameters[id];assert(Number.isInteger(x.display_score_1_to_100));assert(x.display_score_1_to_100>=1&&x.display_score_1_to_100<=100)}
})
test('nonuniform source-equation reference examples',()=>{
 near(legacyIndexV37('texture_open_pores',{pore_density_index:.7,pore_diameter_index:.6,pore_clarity_index:.4,texture_roughness_index:.1,blackhead_congestion_index:.3}),.525)
 near(legacyIndexV37('skin_hydration',{surface_reflectance_index:.4,subsurface_diffusion_index:.5,microline_density_index:.3,sebum_balance_ratio:.6,dry_patch_fluorescence_index:.2}),.53)
 near(legacyIndexV37('barrier_health_sensitivity',{surface_texture_uniformity:.7,hydration_signal_index:.4,erythema_intensity_index:.3,vascular_pattern_index:.2,flaking_texture_index:.5}),.385)
 near(legacyIndexV37('superficial_pigmentation',{coverage_area_percent:37.5,mean_intensity_index:.6,contrast_to_surrounding_skin_index:.4,woods_cluster_density:.2,uniformity_index:.8,region_variation_index:.3}),.4675)
 near(legacyIndexV37('skin_sebum',{shine_norm:.8,fluorescence_norm:.4,porphyrin_norm:.2,congestion_norm:.6}),.55)
 near(legacyIndexV37('peri_orbital_health',{pigment_index:80,vascular_index:20,shadow_hollow_index:60,puffiness_index:10,texture_line_index:30}),.5)
})
test('confidence changes audit metadata, not client score or treatment priority',()=>{
 const w=makeWire();for(const p of Object.values(w.l.parameters)){p.estimation_reason='Synthetic partial-view inference';for(const m of Object.values(p.metrics)){m.basis='supported_estimate';m.confidence_0_1=.25}}
 const low=runWire(w).skin_state
 for(const id of Object.keys(PARAMETER_CONTRACT_V37)){assert.equal(low.derived_report_parameters[id].display_score_1_to_100,s.derived_report_parameters[id].display_score_1_to_100);assert.equal(low.derived_report_parameters[id].data_quality.confidence_0_1,.25)}
 assert.equal(low.core_features.pore_visibility.treatment_evidence_v37.priority_multiplier,s.core_features.pore_visibility.treatment_evidence_v37.priority_multiplier)
})
test('estimation cap preserves original <=0.55 rule',()=>{
 const p=makeMeasurements();p.parameters.skin_hydration.estimation_reason='Synthetic indirect reflectance';p.parameters.skin_hydration.metrics.surface_reflectance_index.basis='supported_estimate'
 assert.equal(scoreLegacyMeasurementsV37(p).skin_hydration.data_quality.confidence_0_1,.55)
})
test('all exact legacy boundaries, direction and every integer display score',()=>{
 for(const [id,c] of Object.entries(PARAMETER_CONTRACT_V37)){if(!c.cuts||id==='skin_sebum')continue
  const unique=new Set();let last=c.health?0:101
  for(let i=0;i<=2000;i++){
   const x=i/2000,p=makeMeasurements()
   // Construct values that make each original index equal x, independent of display implementation.
   for(const n of c.metrics){let v=x;if(['surface_texture_uniformity','hydration_signal_index','pore_clarity_index','regional_uniformity_index','uniformity_index'].includes(n))v=1-x
    if(id==='skin_hydration'&&['microline_density_index','dry_patch_fluorescence_index'].includes(n))v=1-x
    if(id==='skin_luminosity_glow'&&n==='dryness_dullness_index')v=1-x
    if(n==='coverage_area_percent')v=5+65*x
    p.parameters[id].metrics[n].value=v
   }
   const r=scoreLegacyMeasurementsV37(p)[id];near(r.calibration.legacy_index_0_to_1,x)
   assert(c.health?r.display_score_1_to_100>=last:r.display_score_1_to_100<=last,id);last=r.display_score_1_to_100;unique.add(last)
   assert.equal(r.calibration.legacy_grade_1_to_5,1+c.cuts.filter(y=>x>=y-1e-12).length,`${id} grade at ${x}`)
  }
  assert.equal(unique.size,100,id)
 }
})
test('sebum target distance has both arms, independent of dehydration',()=>{
 const score=x=>{const p=makeMeasurements();for(const m of Object.values(p.parameters.skin_sebum.metrics))m.value=x;return scoreLegacyMeasurementsV37(p).skin_sebum.display_score_1_to_100}
 assert.equal(score(.5),100);assert.equal(score(.1),score(.9));assert(score(.1)<score(.3))
 const p=makeMeasurements(),a=scoreLegacyMeasurementsV37(p).skin_sebum;set(p,'skin_hydration',{dry_patch_fluorescence_index:1});assert.deepEqual(scoreLegacyMeasurementsV37(p).skin_sebum,a)
})
test('zero-confidence regional jawline is not a perfect client score',()=>{
 const w=makeWire(),i=CORE_FEATURE_IDS.indexOf('visible_laxity')
 for(const c of Object.values(w.f[i].c)){c.g.fill(0);c.q.fill(0);c.c.fill(0);c.t.fill(0);c.r.fill(0)}
 const p=w.l.parameters.jawline_sagging;p.estimation_reason='Synthetic indirect lower-face support';for(const m of Object.values(p.metrics)){m.basis='supported_estimate';m.confidence_0_1=.3}
 const v=runWire(w).skin_state
 assert.equal(v.core_features.visible_laxity.global_burden_score_1_to_100,null)
 assert.equal(Object.keys(v.core_features.visible_laxity.zone_scores_1_to_100).length,0)
 assert.equal(v.derived_report_parameters.jawline_sagging.display_score_1_to_100,51)
 const prediction=buildPreSessionPredictionV2({baselineSkinState:v,optimizerResult:{plan:{treatment_mode:'single',modality_actions:[]}}})
 assert(prediction.predicted_report_parameters.jawline_sagging)
})
test('no invented final cuts for incomplete source rules',()=>{
 for(const id of ['peri_orbital_health','lip_pigmentation','jawline_sagging','skin_firmness_elasticity','textural_radiance','vascularity_redness'])assert.equal(s.derived_report_parameters[id].calibration.exact_final_index_cuts,null)
})
test('old packets, nonfinite, null and unsupported defaults fail explicitly',()=>{
 const old=structuredClone(run.evidence_packet);delete old.legacy_measurements;assert.throws(()=>buildSkinStateV2(old),/re-run original images/)
 for(const bad of [null,NaN,Infinity,'0.5',-.1,1.1]){const p=makeMeasurements();p.parameters.skin_sebum.metrics.shine_norm.value=bad;assert.throws(()=>validateLegacyMeasurementsV37(p))}
 const p=makeMeasurements();p.parameters.skin_sebum.metrics.shine_norm.confidence_0_1=0;assert.throws(()=>validateLegacyMeasurementsV37(p),/never fill/)
})
test('reports and compatibility payload retain exact scores and estimation metadata',()=>{
 const report=buildSkinAnalysisReportV3({skinState:s}),legacy=buildLegacyDiagnosisV34({skinState:s,skinAnalysisReport:report})
 for(const d of Object.values(legacy.diagnosis_report)){if(d.parameter_id==='skin_type')continue;const p=s.derived_report_parameters[d.parameter_id];assert.equal(d.score_or_label,p.display_score_1_to_100);assert.equal(d.client_description,p.client_observation);assert.equal(d.data_quality.confidence_0_1,p.data_quality.confidence_0_1)}
 const post=buildLegacyPostDiagnosisV34({baseline:{skin_state:s},post_treatment:{skin_state:s}});assert(post.reassessment)
})
test('no-treatment forecast remains on exact new baseline, with honest projection status',()=>{
 const prediction=buildPreSessionPredictionV2({baselineSkinState:s,optimizerResult:{plan:{treatment_mode:'single',modality_actions:[]}}})
 for(const [id,p] of Object.entries(prediction.predicted_report_parameters)){if(id==='skin_type')continue;assert(p.projection_status);for(const h of Object.values(p.horizons)){const b=h.predicted_internal_burden_score_1_to_100;assert.equal(b.expected,s.derived_report_parameters[id].internal_burden_score_1_to_100,id);assert(b.best_case<=b.expected&&b.expected<=b.conservative)}}
})
test('wire roundtrip includes mandatory legacy measurements and prompt has no old bridge',()=>{
 const w=encodeEvidencePacketToUnifiedWireV35(run.evidence_packet);assert.equal(w.v,7);assert.deepEqual(runWire(w).skin_state.derived_report_parameters,s.derived_report_parameters)
 assert(unifiedStructuredOutputFormatV35().schema.required.includes('l'));assert(buildUnifiedVisionPromptV35().includes('PTI ='));assert(!buildUnifiedVisionPromptV35().includes('backend applies a versioned interpolation bridge'))
})
const images=Object.fromEntries(IMAGE_MODES.map(m=>[m,{sha256:`synthetic-${m}`}]))
let calls=0
const generic=await runSkinStateV2({scanId:'synthetic-generic',modelVersion:'offline',imagesByMode:images,visionCall:async request=>{calls++;assert.equal(request.module_id,'unified_assessment');assert(request.response_format.schema.properties.l);return makeWire()}})
assert.equal(calls,1);assert.equal(generic.skin_state.scoring_execution.calibration_version,CALIBRATION_VERSION_V37);passed.push('generic runner uses unified legacy measurement contract in one call')
const baseline={...run,imagesByMode:images},post={...structuredClone(run),imagesByMode:images}
const pairwiseCall=async()=>({pairwise_outcome_version:PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION,comparison_meta:{pair_quality:'good'},feature_comparisons:Object.fromEntries(CORE_FEATURE_IDS.map(id=>[id,{global_change_direction:'stable'}]))})
await runPostTreatmentReassessmentV2({baseline,post,pairwiseCall,modelVersion:'offline'});passed.push('baseline/post reassessment uses matching legacy scoring')
post.skin_state.scoring_execution.formula_config_version='old';await assert.rejects(()=>runPostTreatmentReassessmentV2({baseline,post,pairwiseCall,modelVersion:'offline'}),/different scoring/);passed.push('mixed-version comparison rejected')
console.log(JSON.stringify({ok:true,checks:passed.length,passed,scope:'Offline synthetic software checks, not live vision or clinical validation.'},null,2))
