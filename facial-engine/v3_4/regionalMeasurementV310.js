import {APPEARANCE_INSTRUCTIONS} from './appearanceContractV317.js'
// Existing entry filename. V3.15 replaces the V3.14 severity*sqrt(area) wire contract.
import {MEASUREMENT_VERSION,FORMULA_VERSION,GROUPS,DEVICE_PROFILE,FEATURES,PARAMETERS} from './visibleImpactRubricV315.js'
import {FACE_ZONE_ATLAS_V2} from './faceZoneAtlasV2.js'
export {MEASUREMENT_VERSION,FORMULA_VERSION,GROUPS,DEVICE_PROFILE}
export const DERIVED={}
export const CONTRACT=Object.fromEntries(Object.entries(PARAMETERS).map(([id,p])=>[id,{regions:p.regions,metrics:p.features,observed_metrics:p.features}]))
export const PARAMETER_MODES=Object.fromEntries(Object.entries(PARAMETERS).map(([id,p])=>[id,{primary:p.primary_modes??p.modes,support:[...(p.support_modes??[]),...(id==='vascularity_redness'?['red']:id==='superficial_pigmentation'||id==='visual_acne'?['woods_uv']:[])]}]))
const obj=properties=>({type:'object',properties,required:Object.keys(properties),additionalProperties:false})
const str={type:'string'},confidence={type:'number',minimum:0,maximum:1}
const state={type:'string',enum:['observed','supported_estimate','unavailable']}
const impact={type:['number','null'],minimum:0,maximum:5}
const strings=values=>({type:'array',items:{type:'string',enum:values},maxItems:values.length})
export function measurementFormat(){return {type:'json_schema',name:'visible_impact_v317',strict:true,schema:obj({
 version:{type:'string',enum:[MEASUREMENT_VERSION]},
 regions:obj(Object.fromEntries(Object.keys(GROUPS).map(g=>[g,obj({visible_fraction:confidence,obstruction_note:str})]))),
 features:obj(Object.fromEntries(Object.entries(FEATURES).map(([id,f])=>[id,obj({state,impact_level:impact,confidence,regions:strings(f.regions),evidence:str,extent_note:str})]))),
 parameters:obj(Object.fromEntries(Object.entries(PARAMETERS).map(([id,p])=>[id,obj({state,impact_level:impact,confidence,supporting_features:strings(p.features),dominant_features:strings(p.features),rationale:str})]))),
 skin_type:obj({label:{type:'string',enum:['dry','oily','combination','balanced']},confidence,observation:str})
})}}
export function measurementPrompt(){return `V3.17: BASELINE VISIBLE-APPEARANCE ASSESSMENT. Return only the strict JSON. Inspect the actual five independently photographed optical modes. This is an anchored AI visual assessment, not a physiological instrument or a pixel-area measurement.
DEVICE ${JSON.stringify(DEVICE_PROFILE)}
ANATOMICAL REGIONS ${JSON.stringify(GROUPS)}
FEATURE OBSERVATIONS ${JSON.stringify(FEATURES)}
PARAMETER DEFINITIONS AND CLINICAL APPEARANCE ANCHORS ${JSON.stringify(PARAMETERS)}

${APPEARANCE_INSTRUCTIONS}

IMPACT SCALE for each feature AND each overall parameter: 0 excellent/absent concern with adequate visible support; 1 mild impact; 2 moderate impact; 3 marked impact; 4 severe impact; 5 extreme impact. Use continuous positions between anchors, to two decimals when supported. The numerical backend converts 0,1,2,3,4,5 to health 100,80,60,40,20,1. An impact of 2.5 is 50. Do not output a final health score.

FIRST inspect each feature once and record its visible impact and anatomical locations. THEN make the overall parameter judgment against THAT parameter's anchors, explaining which features matter. Overall impact is a clinical appearance synthesis, not an average of the feature numbers. Dominant regions and findings can determine the impression. Do not multiply severity by affected-area fraction, average over clear skin to dilute a conspicuous patch, or reward absent scars as cancellation of prominent pores. Extent is descriptive context only and must not be penalized twice. A small imperceptible dot should not dominate, but a conspicuous forehead patch may substantially affect pigmentation without covering the face.

CALIBRATION INTENT: mild physiological abnormality is not the same as negligible cosmetic impact. Noticeable forehead pigmentation, conspicuous pores/congestion and limited surface luminosity can warrant moderate appearance-impact positions even when disease is not severe. Hydration can be reasonably good while glow or fine radiance is much weaker. Do not cluster all parameters near excellent simply because the person lacks severe pathology. Equally, do not force low scores where the skin genuinely has excellent supported appearance. Judge each parameter independently and use natural skin tone as the reference, never lighter skin as healthier.

EVIDENCE STATES ARE MANDATORY.

REGIONS MEAN WHERE YOU INSPECTED, NOT ONLY WHERE A PROBLEM EXISTS.

For every feature with state "observed" or "supported_estimate":
- regions MUST contain at least one allowed anatomical region that
  actually supports the observation.
- This includes absent or barely visible findings. For example,
  "no crepiness appreciable" must list the exposed regions inspected
  to establish that observation.
- Use only regions listed for that feature in FEATURE OBSERVATIONS.
- Do not invent inspected regions or include skin hidden by hair,
  beard, equipment or other obstruction.

Only state "unavailable" may have regions: [].
For unavailable features, impact_level must be null and confidence 0.

Before returning JSON, check every feature:
observed/supported_estimate => nonempty supported regions,
numeric impact_level, and confidence greater than zero.
unavailable => empty regions, null impact_level, confidence zero.
Correct any inconsistency using the image evidence.

observed: direct interpretable evidence; impact 0 means observed absence or positively supported excellent appearance. supported_estimate: relevant visible indirect evidence supports a best estimate; explain that support and cap confidence at .55. unavailable: no direct or indirect support; impact_level MUST be null, confidence 0, regions empty. Never encode unavailable as zero impact. Never infer excellent jawline from beard coverage. Inspect remaining exposed contour/cheek evidence across relevant modes; a supported estimate is allowed only if you can describe what supports it. A statement merely saying 'beard obscures contour' is not supporting evidence. For each overall parameter list the actually supporting features; if none support any estimate, use unavailable with null impact. No neutral or healthy fallback value. Unsupported intermediate features may be omitted from the overall synthesis when other sufficient evidence supports it; state those limits.

Do not assert a lighting mismatch without visible evidence, and do not erase genuine brightness by global normalization. Respect primary modes: white/surface for surface appearance, white/subsurface for pigment/redness. Woods UV optical pattern is context only, not pigment depth or a substitute for visible pigment. Red-lit intensity alone is not clinical redness. No pixel counts, optical concentration measurements, measured recoil or inferred symptoms. No treatment assumptions, improvement floors, patient-ID lookup or copied desired score.

Keep feature evidence at most 16 words and extent_note at most 8 words. Parameter rationale at most 32 words, describing supported appearance; do not mention code or numerical scores. Report actual obstruction notes. Skin type is a separate visible regional pattern. Return version ${MEASUREMENT_VERSION}.`}
export function groupWeight(g){if(!GROUPS[g])throw Error(`Unknown group ${g}`);return GROUPS[g].reduce((s,z)=>s+FACE_ZONE_ATLAS_V2.zones[z].default_area_weight,0)}
const exact=(o,keys,path)=>{if(!o||typeof o!=='object'||Array.isArray(o)||Object.keys(o).sort().join('|')!==[...keys].sort().join('|'))throw Error(`Invalid keys ${path}`)}
const unit=v=>typeof v==='number'&&Number.isFinite(v)&&v>=0&&v<=1
const validImpact=v=>typeof v==='number'&&Number.isFinite(v)&&v>=0&&v<=5
const enumArray=(v,allowed)=>Array.isArray(v)&&new Set(v).size===v.length&&v.every(x=>allowed.includes(x))
// Explicit unsupported-only messages from the observed #58 failure. This supplements, not replaces, typed states.
const unsupportedOnly=s=>/^(?:no supported observation|(?:beard|facial hair|mustache and beard) obscures?[^.!;]*|not assessable|not visible|unable to assess)[.!]?$/i.test(s.trim())
export function decodeMeasurements(input){
 const v=structuredClone(input)
 exact(v,['version','regions','features','parameters','skin_type'],'wire')
 if(v.version!==MEASUREMENT_VERSION)throw Error('V3.17 requires fresh baseline observations under the revised appearance definitions. Retain the old report; do not relabel or convert stored values.')
 exact(v.regions,Object.keys(GROUPS),'regions');exact(v.features,Object.keys(FEATURES),'features');exact(v.parameters,Object.keys(PARAMETERS),'parameters')
 for(const[g,r]of Object.entries(v.regions)){exact(r,['visible_fraction','obstruction_note'],g);if(!unit(r.visible_fraction)||typeof r.obstruction_note!=='string')throw Error(`Invalid visibility ${g}`)}
 for(const[id,f]of Object.entries(v.features)){
  exact(f,['state','impact_level','confidence','regions','evidence','extent_note'],id)
  if(!['observed','supported_estimate','unavailable'].includes(f.state)||!unit(f.confidence)||!enumArray(f.regions,FEATURES[id].regions)||typeof f.evidence!=='string'||!f.evidence.trim()||typeof f.extent_note!=='string')throw Error(`Invalid feature ${id}`)
  if(f.impact_level!==null&&!validImpact(f.impact_level))throw Error(`Invalid impact ${id}`)
  if(f.state==='unavailable'||unsupportedOnly(f.evidence)){f.state='unavailable';f.impact_level=null;f.confidence=0;f.regions=[];continue}
  if (
    !validImpact(f.impact_level) ||
    f.confidence === 0 ||
    !f.regions.length
    ) {
    throw Error(
        `Unsupported feature ${id}: ${JSON.stringify(f)}`
    )
  }
  if(!f.regions.some(g=>v.regions[g].visible_fraction>0))throw Error(`No visible support for ${id}`)
  if(f.regions.some(g=>v.regions[g].visible_fraction<.5))f.state='supported_estimate'
  if(f.state==='supported_estimate')f.confidence=Math.min(f.confidence,.55)
 }
 for(const[id,p]of Object.entries(v.parameters)){
  exact(p,['state','impact_level','confidence','supporting_features','dominant_features','rationale'],id)
  if(!['observed','supported_estimate','unavailable'].includes(p.state)||!unit(p.confidence)||!enumArray(p.supporting_features,PARAMETERS[id].features)||!enumArray(p.dominant_features,PARAMETERS[id].features)||typeof p.rationale!=='string'||!p.rationale.trim())throw Error(`Invalid parameter ${id}`)
  if(p.impact_level!==null&&!validImpact(p.impact_level))throw Error(`Invalid parameter impact ${id}`)
  p.supporting_features=p.supporting_features.filter(f=>v.features[f].state!=='unavailable')
  p.dominant_features=p.dominant_features.filter(f=>p.supporting_features.includes(f))
  if(p.state==='unavailable'||unsupportedOnly(p.rationale)||!p.supporting_features.length){p.state='unavailable';p.impact_level=null;p.confidence=0;p.dominant_features=[];continue}
  if(!validImpact(p.impact_level)||p.confidence===0)throw Error(`No supported impact ${id}`)
  const supporting=p.supporting_features.map(f=>v.features[f])
  // The overall judgment may not claim perfect appearance while its dominant evidence reports concern.
  if(p.impact_level===0&&supporting.some(f=>f.impact_level>0))throw Error(`Perfect impact contradicts supporting finding ${id}`)
  if(p.impact_level>0&&supporting.every(f=>f.impact_level===0))throw Error(`Concern lacks supporting finding ${id}`)
  if(p.state==='observed'&&supporting.every(f=>f.state==='supported_estimate'))p.state='supported_estimate'
  p.confidence=Math.min(p.confidence,...supporting.map(f=>f.confidence),p.state==='supported_estimate'?.55:1)
 }
 exact(v.skin_type,['label','confidence','observation'],'skin_type')
 if(!['dry','oily','combination','balanced'].includes(v.skin_type.label)||!unit(v.skin_type.confidence)||!v.skin_type.confidence||typeof v.skin_type.observation!=='string'||!v.skin_type.observation.trim())throw Error('Invalid skin type')
 return v
}
