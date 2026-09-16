import assert from 'node:assert/strict'
import {baseline,changed,glow,opts} from './comparativeSelfTestV316.mjs'
import {PARAMETERS,MEASUREMENT_VERSION} from './visibleImpactRubricV315.js'
import {measurementPrompt,PARAMETER_MODES} from './regionalMeasurementV310.js'
import {applyComparison,stableComparison,comparisonFormat,comparisonPrompt,baselineContext} from './comparativeReassessmentV316.js'
const checks=[],test=(name,fn)=>{fn();checks.push(name)}
test('model schema no longer requests redundant status labels',()=>{
 for(const section of Object.values(comparisonFormat().schema.properties))for(const row of Object.values(section.properties))assert(!('status' in row.properties))
})
test('schema-shaped response without status runs end to end',()=>{
 const b=baseline(),w=glow(1.5,b)
 for(const section of Object.values(w))for(const row of Object.values(section))delete row.status
 assert.equal(applyComparison(b,w,opts).post_run.skin_state.derived_report_parameters.skin_luminosity_glow.display_score_1_to_100,70)
})
test('legacy stable label cannot contradict a supported oily-film endpoint',()=>{
 const b=baseline(),w=stableComparison(b.skin_state)
 changed(w,'features','oily_film',1.5);changed(w,'parameters','skin_sebum',1.5)
 w.features.oily_film.status='stable';w.parameters.skin_sebum.status='stable'
 const r=applyComparison(b,w,opts)
 assert.equal(r.comparative_audit.features.oily_film.status,'compared')
 assert.equal(r.comparative_audit.features.oily_film.reported_status,'stable')
 assert.equal(r.post_run.skin_state.derived_report_parameters.skin_sebum.display_score_1_to_100,70)
})
test('stable-labelled numeric change still requires regional evidence',()=>{
 const b=baseline(),w=glow(1.5,b);w.features.diffuse_dullness.status='stable';w.features.diffuse_dullness.regions=[]
 assert.throws(()=>applyComparison(b,w,opts),/Missing paired evidence/)
})
test('fine texture improvement can support texture and radiance while pores stay fixed',()=>{
 const b=baseline(),w=stableComparison(b.skin_state)
 changed(w,'features','micrograin',1.5)
 changed(w,'parameters','texture_open_pores',1.75);changed(w,'parameters','textural_radiance',1.5)
 const r=applyComparison(b,w,opts)
 assert.equal(r.post_run.skin_state.derived_report_parameters.texture_open_pores.display_score_1_to_100,65)
 assert.equal(r.post_run.skin_state.derived_report_parameters.textural_radiance.display_score_1_to_100,70)
 assert.equal(r.comparative_audit.features.pore_prominence.accepted_delta_impact,0)
})
test('UV unchanged does not veto supported visible pigment improvement',()=>{
 const b=baseline(),w=stableComparison(b.skin_state)
 changed(w,'features','pigment_contrast',1);changed(w,'parameters','superficial_pigmentation',1)
 assert.equal(applyComparison(b,w,opts).post_run.skin_state.derived_report_parameters.superficial_pigmentation.display_score_1_to_100,80)
})
test('glow improvement does not automatically change hydration',()=>{
 const r=applyComparison(baseline(),glow(.75),opts)
 assert.equal(r.post_run.skin_state.derived_report_parameters.skin_hydration.display_score_1_to_100,60)
})
test('same appearance policy is included in baseline and comparison',()=>{
 for(const p of [measurementPrompt(),comparisonPrompt()])assert(p.includes('VISIBLE APPEARANCE CONTRACT V3.17'))
 for(const id of Object.keys(PARAMETERS))assert.deepEqual(PARAMETER_MODES[id].primary,['white'])
 assert(PARAMETER_MODES.superficial_pigmentation.support.includes('subsurface_polarized'))
})
test('old reference version blocked even when formula is current',()=>{
 const b=baseline();b.skin_state.regional_measurements.version='aia_visual_impact_measurement_v3.15.0'
 assert.throws(()=>baselineContext(b.skin_state),/saved BEFORE images/)
 assert.equal(MEASUREMENT_VERSION,'aia_visual_impact_measurement_v3.17.0')
})
console.log(JSON.stringify({ok:true,checks,live_model_calls:0,scope:'Software contract tests; not clinical accuracy validation.'},null,2))
