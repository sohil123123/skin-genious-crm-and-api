import {CORE_FEATURE_IDS} from './skinStateV2.schema.js'
import {FACE_ZONE_IDS} from './faceZoneAtlasV2.js'
import {MORPHOLOGY_FLAG_IDS_V3} from './visionMorphologyExclusionMapV3.js'
import {CORE_FEATURE_FORMULAS_V2 as F,buildSkinStateV2} from './skinStateScoreMapperV2.js'
import {decodeUnifiedVisionOutputV35} from './unifiedVisionAdapterV35.js'
import {mergeVisionEvidencePacketsV2} from './mergeVisionEvidenceV2.js'
import {normalizeContinuousGradeV36} from './clinicalCalibrationV36.js'
export function makeWire(grade=2.35,q=90) {
 const primitive = Math.round(normalizeContinuousGradeV36(grade)*20)*5
 return {v:6,m:FACE_ZONE_IDS.map(()=>({s:0,v:100,f:MORPHOLOGY_FLAG_IDS_V3.map(()=>0)})),f:Object.fromEntries(CORE_FEATURE_IDS.map((id,fi)=>{
   const z=F[id].applicable_zones.length,arr=v=>Array(z).fill(v)
   return [fi,{s:arr(0),v:arr(100),d:arr(0),c:Object.fromEntries(Object.keys(F[id].components).map((_,ci)=>[ci,{g:arr(grade),q:arr(q),c:arr(primitive),t:arr(primitive),x:arr(80),r:arr(primitive)}]))}]
 }))}
}
export function runWire(wire) {
 const decoded=decodeUnifiedVisionOutputV35(wire,{scanId:'synthetic-calibration-test',imageSetHash:'synthetic-not-a-patient',modelVersion:'offline-fixture'})
 const packet=mergeVisionEvidencePacketsV2(decoded.moduleOutputs,{morphologyExclusionMap:decoded.morphology,modelVersion:'offline-fixture'})
 return {evidence_packet:packet,skin_state:buildSkinStateV2(packet)}
}
