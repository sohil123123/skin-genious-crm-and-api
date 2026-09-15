// Synthetic software fixtures only. Never use these values as patient defaults.
import {PARAMETER_CONTRACT_V37,LEGACY_MEASUREMENT_VERSION_V37,metricBoundsV37} from './legacyMeasurementContractV37.js'
import {CORE_FEATURE_IDS} from './skinStateV2.schema.js'
import {FACE_ZONE_IDS} from './faceZoneAtlasV2.js'
import {MORPHOLOGY_FLAG_IDS_V3} from './visionMorphologyExclusionMapV3.js'
import {CORE_FEATURE_FORMULAS_V2 as F,buildSkinStateV2} from './skinStateScoreMapperV2.js'
import {decodeUnifiedVisionOutputV35} from './unifiedVisionAdapterV35.js'
import {mergeVisionEvidencePacketsV2} from './mergeVisionEvidenceV2.js'
export function makeMeasurements() {
 return {version:LEGACY_MEASUREMENT_VERSION_V37,parameters:Object.fromEntries(Object.entries(PARAMETER_CONTRACT_V37).map(([id,c])=>[id,{
  metrics:Object.fromEntries(c.metrics.map(n=>{const [lo,hi]=metricBoundsV37(id,n);return [n,{value:(lo+hi)/2,confidence_0_1:.90,basis:'visible_evidence'}]})),
  supporting_modes:['white','surface_polarized'],supporting_regions:['forehead'],evidence_summary:'Synthetic fixture; no patient evidence.',estimation_reason:'',client_observation:'Synthetic software test observation.'
 }])),skin_type:{label:'combination',confidence_0_1:.9,basis:'visible_evidence',evidence_summary:'Synthetic regional pattern.',modifiers:[]}}
}
export function makeWire(grade=2.35,q=90) {
 return {v:7,l:makeMeasurements(),m:FACE_ZONE_IDS.map(()=>({s:0,v:100,f:MORPHOLOGY_FLAG_IDS_V3.map(()=>0)})),f:Object.fromEntries(CORE_FEATURE_IDS.map((id,fi)=>{
  const n=F[id].applicable_zones.length,arr=v=>Array(n).fill(v)
  return [fi,{s:arr(0),v:arr(100),d:arr(0),c:Object.fromEntries(Object.keys(F[id].components).map((_,ci)=>[ci,{g:arr(grade),q:arr(q),c:arr(grade?30:0),t:arr(grade?30:0),x:arr(80),r:arr(grade?30:0)}]))}]
 }))}
}
export function runWire(wire) {
 const d=decodeUnifiedVisionOutputV35(wire,{scanId:'synthetic-test',imageSetHash:'synthetic-not-patient',modelVersion:'offline-fixture'})
 const p=mergeVisionEvidencePacketsV2(d.moduleOutputs,{morphologyExclusionMap:d.morphology,modelVersion:'offline-fixture'})
 p.legacy_measurements=d.legacyMeasurements
 return {evidence_packet:p,skin_state:buildSkinStateV2(p)}
}
