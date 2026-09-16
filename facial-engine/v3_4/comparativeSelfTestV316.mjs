import assert from 'node:assert/strict'
import {pathToFileURL} from 'node:url'
import {fixture} from './visibleImpactSelfTestV315.mjs'
import {buildRegionalRun} from './regionalScoringV310.js'
import {FEATURES,PARAMETERS} from './visibleImpactRubricV315.js'
import {VERSION,stableComparison,applyComparison,baselineContext,comparisonFormat} from './comparativeReassessmentV316.js'
export const opts={scanId:'post',imageSetHash:'synthetic',modelVersion:'mock'}
export const baseline=()=>buildRegionalRun(fixture(),{...opts,scanId:'before'})
export function changed(w,section,id,after){const def=(section==='features'?FEATURES:PARAMETERS)[id];w[section][id]={...w[section][id],status:'compared',after_impact:after,confidence:.8,regions:[def.regions[0]],modes:[def.modes[0]],before_observation:'Defined finding visible in the reference region.',after_observation:'Same finding visibly changed in the matched region.',observation_basis:'skin_appearance',evidence:'Matched skin finding changes.',...(section==='parameters'?{anchor_explanation:'Matched feature change supports this within-anchor endpoint.'}:{})};return w}
export function glow(after,b=baseline()){const w=stableComparison(b.skin_state);changed(w,'features','diffuse_dullness',after);return changed(w,'parameters','skin_luminosity_glow',after)}
const row=r=>r.post_diagnosis.reassessment.skin_luminosity_glow_index
export function selfTest(){const results=[];const test=(n,f)=>{f();results.push(n)}
 test('stable preserves saved baseline',()=>assert.equal(row(applyComparison(baseline(),stableComparison(baseline().skin_state),opts)).post_treatment_score_or_label,60))
 test('half anchor improvement produces ten points',()=>assert.equal(row(applyComparison(baseline(),glow(1.5),opts)).post_treatment_score_or_label,70))
 test('small supported improvement retained',()=>assert.equal(row(applyComparison(baseline(),glow(1.94),opts)).post_treatment_score_or_label,61))
 test('large supported improvement has no gain cap',()=>assert.equal(row(applyComparison(baseline(),glow(.8),opts)).post_treatment_score_or_label,84))
 test('raw decline remains clinical; client stays stable',()=>{const r=applyComparison(baseline(),glow(2.3),opts);assert.equal(row(r).result,'stable');assert.equal(row(r).base_post_score_or_label_internal,54)})
 test('recovery does not create a new client gain',()=>{const r=applyComparison(baseline(),glow(2.3),opts);assert.equal(row(applyComparison(r.post_run,glow(2,r.post_run),opts)).patient_facing_change_points,0)})
 test('structural feature and jawline held',()=>{const w=stableComparison(baseline().skin_state);changed(w,'features','jowl_prominence',1);changed(w,'parameters','jawline_sagging',1);assert.equal(applyComparison(baseline(),w,opts).comparative_audit.parameters.jawline_sagging.accepted_delta_impact,0)})
 test('parameter change requires supporting feature change',()=>{const w=stableComparison(baseline().skin_state);changed(w,'parameters','skin_luminosity_glow',1.5);assert.equal(row(applyComparison(baseline(),w,opts)).patient_facing_change_points,0)})
 test('external material alone cannot change skin grade',()=>{const w=glow(1.5);w.features.diffuse_dullness.observation_basis='surface_material_only';assert.throws(()=>applyComparison(baseline(),w,opts),/material alone/)})
 test('material-only stable finding accepted without penalty',()=>{const w=stableComparison(baseline().skin_state);w.features.flaking.observation_basis='surface_material_only';assert.equal(applyComparison(baseline(),w,opts).comparative_audit.features.flaking.accepted_delta_impact,0)})
 test('uncertain flakes do not veto supported hydration improvement',()=>{const w=stableComparison(baseline().skin_state);Object.assign(w.features.flaking,{status:'not_comparable',after_impact:null,observation_basis:'uncertain_skin_or_material'});changed(w,'features','plumpness_deficit',1.5);changed(w,'parameters','skin_hydration',1.5);const r=applyComparison(baseline(),w,opts);assert.equal(r.post_run.skin_state.derived_report_parameters.skin_hydration.display_score_1_to_100,70);assert(r.comparative_audit.parameters.skin_hydration.unresolved_supporting_features.includes('flaking'))})
 test('held uncomparable parameter requires supported reference recheck',()=>{const b=baseline(),w=stableComparison(b.skin_state);Object.assign(w.parameters.skin_luminosity_glow,{status:'not_comparable',after_impact:null,observation_basis:'not_comparable'});const r=applyComparison(b,w,opts);assert.equal(row(applyComparison(r.post_run,glow(1.5,r.post_run),opts)).patient_facing_change_points,0)})
 test('confidence does not scale gain',()=>{const w=glow(1.5);w.parameters.skin_luminosity_glow.confidence=.2;assert.equal(row(applyComparison(baseline(),w,opts)).post_treatment_score_or_label,70)})
 test('endpoints outside rubric rejected',()=>assert.throws(()=>applyComparison(baseline(),glow(-.1),opts),/after anchor/))
 test('endpoint at zero handles decimal arithmetic',()=>{const b=baseline();const w=stableComparison(b.skin_state);for(const f of PARAMETERS.skin_luminosity_glow.features)changed(w,'features',f,0);changed(w,'parameters','skin_luminosity_glow',0);changed(w,'parameters','superficial_pigmentation',0);assert.equal(row(applyComparison(b,w,opts)).post_treatment_score_or_label,100)})
 test('missing paired observation rejected',()=>{const w=glow(1.5);w.features.diffuse_dullness.before_observation='';assert.throws(()=>applyComparison(baseline(),w,opts),/paired observation/)})
 test('all 48 schemas enforce exact allowed region/mode lists',()=>{const s=comparisonFormat().schema;for(const[section,defs]of Object.entries({features:FEATURES,parameters:PARAMETERS}))for(const[id,d]of Object.entries(defs)){const p=s.properties[section].properties[id].properties;assert.deepEqual(p.regions.items.enum,d.regions);assert.deepEqual(p.modes.items.enum,d.modes)}})
 test('unsupported mode rejected with useful diagnostics',()=>{const w=stableComparison(baseline().skin_state);changed(w,'features','dry_patches',1.5);w.features.dry_patches.modes=['woods_uv'];assert.throws(()=>applyComparison(baseline(),w,opts),/allowed_modes/)})
 test('changed model does not invalidate saved reference',()=>{const b=baseline();b.evidence_packet.model_execution.model_version='different';assert(baselineContext(b.skin_state))})
 test('older calibration cannot be silently migrated',()=>{const b=baseline();b.skin_state.scoring_execution.formula_config_version='old';assert.throws(()=>baselineContext(b.skin_state),/V3.17/)})
 test('no stale legacy cutoff or veto diagnostics',()=>{const r=applyComparison(baseline(),glow(1.5),opts);assert.equal(row(r).raw.minimum_detectable_change_points,null);assert.equal(row(r).raw.separate_pairwise_veto_used,false);assert.equal(r.post_diagnosis.metadata.display_policy_version,VERSION)})
 test('old delta-only model output rejected instead of interpreted as new schema',()=>{const w=glow(1.5);w.features.diffuse_dullness.delta_impact=-.5;assert.throws(()=>applyComparison(baseline(),w,opts),/keys/)})
 return {ok:true,checks:results,live_model_calls:0}
}
if(process.argv[1]&&import.meta.url===pathToFileURL(process.argv[1]).href)console.log(JSON.stringify(selfTest(),null,2))
