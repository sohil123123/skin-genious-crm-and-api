import { CORE_FEATURE_IDS } from './skinStateV2.schema.js'
import { PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION } from './pairwiseOutcomeEvidenceV2.js'
import { PAIRWISE_FEATURE_DEFINITIONS_V313 } from './appearanceScoringV313.js'

export const COMPACT_PAIRWISE_VERSION = 'aia_pairwise_compact_v3.13.0'
export const COMPACT_PAIRWISE_PROMPT = `Compare the supplied before and after five-mode images of the same person directly.
Return only the requested structured JSON. Do not assign health scores or predict treatment benefits.
For every requested feature, report direction, confidence, supporting improved/worsened zones, and one short evidence summary.
Check exposure, framing, pose, glare, product residue and mode matching. Mention relevant uncertainty.
Never assume treatment caused improvement, or that a new lesion or increased redness is temporary.
Use the same anatomical region and mode for comparisons. Distinguish visible dryness from shine.
No component-by-component explanations, repeated unchanged-zone descriptions, or full skin states.
Keep each summary and reactivity note to at most 25 words. Empty notes are allowed.
Canonical zones: forehead_left, forehead_center, forehead_right, glabella, temple_left, temple_right, nose, malar_medial_left, malar_medial_right, cheek_lateral_left, cheek_lateral_right, peri_orbital_left, peri_orbital_right, perioral, chin, jawline_left, jawline_right, lips.
For every feature also report visible_change_magnitude: none (no visible difference), slight (a trained eye notices), clear (the client would notice in a mirror), strong (obvious at a glance). Judge appearance parameters at the size a client would see; do not shrink a clear change to slight because it is borderline. Structural features (visible_laxity, firmness_appearance_loss, pits/scars, fixed lines) are never more than none on a same-day pair.
FEATURE DEFINITIONS (judge exactly these):
${Object.entries(PAIRWISE_FEATURE_DEFINITIONS_V313).map(([k,v])=>`- ${k}: ${v}`).join('\n')}`

const obj = properties => ({type:'object', properties, required:Object.keys(properties), additionalProperties:false})
const string = {type:'string'}
const strings = {type:'array',items:string}
const direction = {type:'string',enum:['improved','stable','worsened','mixed','not_reliably_measurable']}
const magnitude = {type:'string',enum:['none','slight','clear','strong']}
const feature = obj({global_change_direction:direction, global_change_confidence:{type:'string',enum:['high','medium','low']}, visible_change_magnitude:magnitude, zones_visibly_improved:strings,zones_visibly_worsened:strings,transient_reactivity_note:string,global_summary:string})
export function compactPairwiseFormat() {
  return {type:'json_schema',name:'pairwise_compact_v393',strict:true,schema:obj({
    comparison_meta:obj({pair_quality:{type:'string',enum:['good','usable_with_caution','poor']},pair_quality_issues:strings}),
    feature_comparisons:obj(Object.fromEntries(CORE_FEATURE_IDS.map(id=>[id,feature])))
  })}
}
export function compactPairwiseInput(input) {
  return {baseline_scan_id:input.baseline_scan_id,post_scan_id:input.post_scan_id,requested_features:[...CORE_FEATURE_IDS]}
}
export function decodeCompactPairwise(value,input) {
  if (!['good','usable_with_caution','poor'].includes(value?.comparison_meta?.pair_quality)) throw Error('Invalid compact pair quality')
  for (const id of CORE_FEATURE_IDS) {
    const f=value.feature_comparisons?.[id]
    if (!f || !direction.enum.includes(f.global_change_direction) || !['high','medium','low'].includes(f.global_change_confidence) || !Array.isArray(f.zones_visibly_improved) || !Array.isArray(f.zones_visibly_worsened) || typeof f.global_summary!=='string' || typeof f.transient_reactivity_note!=='string') throw Error(`Invalid compact pairwise feature: ${id}`)
  }
  return {...value,pairwise_outcome_version:PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION,wire_version:COMPACT_PAIRWISE_VERSION,
    zone_atlas_version:'aia_face_zone_atlas_v2.0.0',comparison_meta:{...value.comparison_meta,baseline_scan_id:input.baseline_scan_id,post_scan_id:input.post_scan_id},
    feature_comparisons:Object.fromEntries(CORE_FEATURE_IDS.map(id=>[id,{...value.feature_comparisons[id],visible_change_magnitude:magnitude.enum.includes(value.feature_comparisons[id].visible_change_magnitude)?value.feature_comparisons[id].visible_change_magnitude:null,feature_id:id,zones:{},zones_stable:[],detail_scope:'global_direction_with_supporting_zones'}]))}
}

// Both calls inspect images independently. Await both settlements so a failed
// branch cannot leave an unhandled rejection or an abandoned paid call.
export async function runConcurrentReassessment(absoluteCall,pairwiseCall) {
  const started=Date.now(), timings={}
  const timed=async(name,call)=>{const t=Date.now();try{return await call()}finally{timings[name]=Date.now()-t}}
  const results=await Promise.allSettled([timed('absolute_ms',absoluteCall),timed('pairwise_ms',pairwiseCall)])
  const failed=results.find(x=>x.status==='rejected')
  if(failed) throw failed.reason
  return {postRun:results[0].value,pairwise:results[1].value,latency_profile:{...timings,total_ms:Date.now()-started,architecture:'parallel_absolute_and_compact_pairwise',openai_call_count:2}}
}
