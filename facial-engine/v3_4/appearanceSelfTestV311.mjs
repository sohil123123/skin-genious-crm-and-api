import assert from 'node:assert/strict'
import {CONTRACT,GROUPS,decodeMeasurements} from './regionalMeasurementV310.js'
import {metricBoundsV37} from './legacyMeasurementContractV37.js'
import {buildRegionalRun} from './regionalScoringV310.js'
import {regionalMeasurementChanges} from './regionalReassessmentV310.js'
const fixture={regions:Object.fromEntries(Object.keys(GROUPS).map(g=>[g,{visible_fraction:1,obstruction_note:''}])),parameters:Object.fromEntries(Object.entries(CONTRACT).map(([id,c])=>[id,{regions:Object.fromEntries(c.regions.map(g=>[g,{values:c.observed_metrics.map(n=>{const[lo,hi]=metricBoundsV37(id,n);return lo+.35*(hi-lo)}),confidence:.9,estimated:false,...(id==='superficial_pigmentation'?{landmark_reference:'Synthetic cheek patch relative to adjacent skin'}:{})}])),observation:'Synthetic fixture, not patient evidence.'}])),skin_type:{label:'combination',confidence:.9,observation:'Synthetic skin type'}}
const run=f=>buildRegionalRun(decodeMeasurements(f),{scanId:'test',imageSetHash:'test',modelVersion:'mock'}).skin_state
const set=(f,id,name,v)=>{for(const r of Object.values(f.parameters[id].regions))r.values[CONTRACT[id].observed_metrics.indexOf(name)]=v}
const score=(s,id)=>s.derived_report_parameters[id].calibration.unrounded_health_1_to_100
const b=run(fixture),checks=[]
let f=structuredClone(fixture);set(f,'skin_luminosity_glow','sebum_gloss_index',.99);assert.equal(score(run(f),'skin_luminosity_glow'),score(b,'skin_luminosity_glow'));checks.push('Oil gloss alone cannot increase glow')
f=structuredClone(fixture);set(f,'skin_luminosity_glow','color_luminance_index',.55);assert(score(run(f),'skin_luminosity_glow')>score(b,'skin_luminosity_glow'));checks.push('Defined diffuse luminosity raises glow')
f=structuredClone(fixture);set(f,'skin_hydration','visible_dry_patch_index',.1);assert(score(run(f),'skin_hydration')>score(b,'skin_hydration'));checks.push('Less visible dryness raises hydration appearance')
f=structuredClone(fixture);set(f,'skin_hydration','subsurface_diffusion_index',.99);set(f,'skin_hydration','sebum_balance_ratio',.99);assert.equal(score(run(f),'skin_hydration'),score(b,'skin_hydration'));checks.push('Diffusion and oil balance do not determine hydration')
f=structuredClone(fixture);set(f,'texture_open_pores','texture_roughness_index',.1);assert(score(run(f),'textural_radiance')>score(b,'textural_radiance'));checks.push('Shared smoother texture raises radiance')
f=structuredClone(fixture);set(f,'superficial_pigmentation','white_patch_contrast',.05);set(f,'superficial_pigmentation','subsurface_patch_contrast',.85);const a=run(f);assert(regionalMeasurementChanges(b.regional_measurements,a.regional_measurements).superficial_pigmentation.cheek_left.review_flags.includes('mode_contrast_disagreement'));checks.push('Contradictory pigment modes flagged without score override')
const untouched=['visual_acne','skin_sebum','vascularity_redness','peri_orbital_health','lip_pigmentation','jawline_sagging','skin_firmness_elasticity','superficial_wrinkles']
f=structuredClone(fixture);set(f,'skin_hydration','visible_dry_patch_index',.1);for(const id of untouched)assert.equal(score(run(f),id),score(b,id));checks.push('Targeted extra primitive does not alter unrelated scores')
console.log(JSON.stringify({ok:true,checks,scope:'Synthetic computational tests only; no live AI accuracy or repeatability claim.'},null,2))
