import {PARAMETER_CONTRACT_V37,metricBoundsV37} from './legacyMeasurementContractV37.js'
import {LEGACY_VISUAL_ANCHORS_V37} from './legacyVisualAnchorReferenceV37.js'
import {LEGACY_RUBRIC_V37} from './legacyRubricV37.js'
import {FACE_ZONE_ATLAS_V2} from './faceZoneAtlasV2.js'
export const MEASUREMENT_VERSION='aia_regional_measurement_v3.11.0'
export const FORMULA_VERSION='aia_regional_appearance_equations_v3.11.0'
export const DEVICE_PROFILE={id:'aia_five_optical_modes_spec_v1',source:'Technical Specification (1).docx plus user confirmation of independent captures',white:'Broadband visible white; 5000–6500 K',surface_polarized:'400–700 nm broadband, parallel polarization',subsurface_polarized:'400–700 nm broadband, cross polarization',woods_uv:'365 nm preferred; 365–385 nm stated range; visible fluorescence capture',red:'630–660 nm stated red illumination range; polarization optional',exact_center_wavelengths_verified:false}
export const GROUPS={forehead_left:['forehead_left','temple_left'],forehead_center:['forehead_center','glabella'],forehead_right:['forehead_right','temple_right'],nose:['nose'],cheek_left:['malar_medial_left','cheek_lateral_left'],cheek_right:['malar_medial_right','cheek_lateral_right'],lower_face:['perioral','chin'],eye_left:['peri_orbital_left'],eye_right:['peri_orbital_right'],jaw_left:['jawline_left'],jaw_right:['jawline_right'],lips:['lips']}
const face=['forehead_left','forehead_center','forehead_right','nose','cheek_left','cheek_right','lower_face']
export const PARAMETER_MODES={
 barrier_health_sensitivity:{primary:['surface_polarized','white'],support:['subsurface_polarized','red']},
 visual_acne:{primary:['white','subsurface_polarized'],support:['surface_polarized','red','woods_uv']},
 skin_sebum:{primary:['surface_polarized','white'],support:['woods_uv']},
 vascularity_redness:{primary:['subsurface_polarized','white'],support:['red']},
 skin_hydration:{primary:['surface_polarized','white'],support:['subsurface_polarized','woods_uv']},
 skin_luminosity_glow:{primary:['white','surface_polarized'],support:['subsurface_polarized']},
 superficial_pigmentation:{primary:['white','subsurface_polarized'],support:['woods_uv']},
 peri_orbital_health:{primary:['white','subsurface_polarized'],support:['surface_polarized']},
 lip_pigmentation:{primary:['white'],support:['subsurface_polarized']},
 texture_open_pores:{primary:['surface_polarized','white'],support:['subsurface_polarized']},
 superficial_wrinkles:{primary:['surface_polarized','white'],support:[]},
 jawline_sagging:{primary:['white'],support:['surface_polarized']},
 skin_firmness_elasticity:{primary:['white','surface_polarized'],support:[]},
 textural_radiance:{primary:['surface_polarized','white'],support:[]},
 skin_type:{primary:['white','surface_polarized'],support:['woods_uv']},
}
// Pore diameter/clarity follow the supplied extractor's relationships with continuous
// primitives instead of bin midpoints. They are explicitly derived proxies.
export const DERIVED={texture_open_pores:['pore_diameter_index','pore_clarity_index'],skin_luminosity_glow:['subsurface_diffusion_index']}
export const CONTRACT=Object.fromEntries(Object.entries(PARAMETER_CONTRACT_V37).map(([id,c])=>[id,{...c,regions:id==='peri_orbital_health'?['eye_left','eye_right']:id==='lip_pigmentation'?['lips']:id==='jawline_sagging'?['jaw_left','jaw_right']:id==='skin_firmness_elasticity'?['cheek_left','cheek_right','jaw_left','jaw_right']:id==='superficial_wrinkles'?[...face,'eye_left','eye_right']:face,observed_metrics:c.metrics.filter(n=>!(DERIVED[id]??[]).includes(n)),modes:PARAMETER_MODES[id]}]))
// Additional primitives are not inserted into the legacy packet; they feed only revised parameters.
CONTRACT.skin_hydration.observed_metrics.push('visible_dry_patch_index')
CONTRACT.superficial_pigmentation.observed_metrics.push('white_patch_contrast','subsurface_patch_contrast')
const obj=properties=>({type:'object',properties,required:Object.keys(properties),additionalProperties:false})
const str={type:'string'},num=(min,max)=>({type:'number',minimum:min,maximum:max})
export function measurementFormat(){return {type:'json_schema',name:'regional_measurement_v310',strict:true,schema:obj({
 regions:obj(Object.fromEntries(Object.keys(GROUPS).map(g=>[g,obj({visible_fraction:num(0,1),obstruction_note:str})]))),
 parameters:obj(Object.fromEntries(Object.entries(CONTRACT).map(([id,c])=>[id,obj({
  regions:obj(Object.fromEntries(c.regions.map(g=>[g,obj({values:{type:'array',items:num(0,100),minItems:c.observed_metrics.length,maxItems:c.observed_metrics.length},confidence:num(.01,1),estimated:{type:'boolean'},...(id==='superficial_pigmentation'?{landmark_reference:str}:{})})]))),
  observation:str,
 })]))),skin_type:obj({label:{type:'string',enum:['dry','oily','combination','balanced']},confidence:num(.01,1),observation:str})
})}}
const REVISED_INSTRUCTIONS=`V3.11 OVERRIDES FOR FOUR APPEARANCE PARAMETERS. These definitions supersede conflicting old reference proxy interpretations, but retain the numerical units. Do not target an improvement or infer treatment from filenames.
GLOW: color_luminance_index is diffuse luminous appearance relative to this person's underlying skin tone, not absolute skin lightness. Exclude tight white oily highlights, sweat and product glints from the estimate. 0 means pronounced diffuse dullness, .5 moderate luminosity, 1 clear even diffuse luminosity. surface_reflectance_uniformity describes the continuity and evenness of broad reflection outside those highlights (0 disrupted, .5 mixed, 1 even). shadow_softness_index describes gentle surface transitions after excluding anatomical shadows; 0 harsh, .5 mixed, 1 soft. dryness_dullness_index is visible dull surface burden, 0 absent, .5 moderate, 1 marked. Sebum gloss and guessed subsurface diffusion DO NOT contribute to glow. Do not equate reduced oil shine with reduced glow, or demand lighter constitutional skin.
HYDRATION APPEARANCE: visible_dry_patch_index is the extent/severity of visibly dry surface patches in white and surface_polarized, 0 absent, .5 moderate, 1 marked. Use microline_density_index for dehydration-type fine surface lines, excluding fixed wrinkles. Do not identify isolated reflective dots or fluorescent fibres as flakes without corroborating morphology. Surface reflectance is supporting evidence only; no water content is measured. Flaking is shared from barrier flaking_texture_index. UV fluorescence and sebum balance have no direct weight in the revised hydration score.
TEXTURAL RADIANCE: roughness is shared from texture_open_pores.texture_roughness_index, not guessed independently. surface_smooth_scatter_index is continuity of fine surface reflection outside oil highlights; 0 fragmented, .5 mixed, 1 continuous. keratin_shadow_index means visible fine surface microshadow burden, 0 absent, .5 moderate, 1 marked; it is not a biochemical keratin measurement. Exclude pores, beard edges and anatomical shadows from this fine roughness interpretation.
PIGMENT: For each fixed region, include landmark_reference (at most 15 words): anatomy and patch location, with nearby unaffected reference skin and exclusions. Assess coverage over the same anatomical region definition, not over the entire crop including hair or beard. Separate diffuse patches/freckles from discrete moles, inflammatory red spots, pore dots, hair shadows and product residue. mean_intensity_index is relative brown patch darkness against nearby same-region unaffected skin, not absolute pixel darkness. white_patch_contrast and subsurface_patch_contrast independently estimate that same patch contrast in the named modes (0 no contrast, .5 moderate, 1 very marked). Record contrast_to_surrounding_skin_index as their mean; code enforces it. If a mode is obstructed use a supported estimate and lower confidence, never fake corroboration. Woods supports morphology only: fluorescence changes alone do not establish visible pigment change. Ignore red illumination for pigment quantification. Do not invent a new patch because it is easier to see from a different angle. These are anchored visual estimates, not pixel masks or instrument percentages.`
function definitions(){const out={};function walk(o){if(!o||typeof o!=='object')return;for(const [k,v]of Object.entries(o)){if(v&&typeof v==='object'&&!Array.isArray(v)&&v.description&&!out[k])out[k]=String(v.description);walk(v)}}walk(LEGACY_RUBRIC_V37);return Object.fromEntries([...new Set(Object.values(CONTRACT).flatMap(c=>c.observed_metrics))].map(k=>[k,out[k]??'Apply the named legacy appearance metric, using the supplied anchor meanings.']))}
export function measurementPrompt(){return `You measure the SAME person's skin in five independently captured optical modes. No model training is involved. ${REVISED_INSTRUCTIONS}\nReturn compact structured visual estimates only; numerical code computes all final scores.\nDEVICE ${JSON.stringify(DEVICE_PROFILE)}\nREGION GROUPS (patient anatomical left/right) ${JSON.stringify(GROUPS)}\nCONTRACT: values arrays follow observed_metrics order exactly. ${JSON.stringify(CONTRACT)}\nDEFINITIONS ${JSON.stringify(definitions())}\nLEGACY REFERENCE ANCHORS ${JSON.stringify(LEGACY_VISUAL_ANCHORS_V37)}\n
Assess each region with the specified PRIMARY modes and use SUPPORT modes only for the relevant feature. Inspect all five modes but do not average contradictory or irrelevant channels. Parallel white preserves surface reflectance; cross white supports diffuse pigment/redness appearance. UVA captures visible fluorescence, not water or melanin concentration. Red-channel intensity alone is not a clinical erythema score. Never assume a capture mismatch without a specific visible reason. Do not automatically discount a brighter or smoother appearance. Do not use skin-tone lightness as a health target.
For pigment, estimate patch contrast and mean patch intensity separately from its area. A faint patch can cover the same area as a dark patch. Exclude hair/shadow/raised lesions from diffuse pigment. For roughness, distinguish smooth surface from persistent pore openings; glare and roughness are different. For glow, assess within-region uniformity and clarity, not raw brightness alone. For hydration, measure visible dry-surface appearance; do not claim water measurements. Fluorescent specks may be residue/fibres: only label dry-patch support when morphology in white/surface agrees. Physiology-named variables (diffusion, collagen, recoil, chronicity) are legacy visual proxies, never direct physiological measurements.
Use continuous intermediate positions between historical anchors; do not choose a bin and emit its midpoint. No healthy default, improvement floor, treatment assumption or rounding to 0.05. Use at most two decimals for normalized values, retaining one decimal for percentages/angles where justified. Peri-orbital values and coverage_area_percent are 0–100; legacy_grade_continuous 1–5; mandibular angle 0–12; all other metrics 0–1. Check each value's unit.
The original barrier hydration/texture reference bins can be deficit-coded; final health/uniformity indices increase with health. Semantic grade 1 is least concern and 5 most concern. Same visible severity must receive the same absolute estimate, independent of whether this is a baseline or post-treatment capture. No patient history, treatment selections, desired scores or prior scores are supplied.
Each parameter region has confidence separately from values. estimated=true for physiological proxies, substantial obstruction or indirect inference. Never fill invisible anatomy with a healthy constant; use the best supported estimate only. If no supported estimate is possible, state this in observation; do not fabricate evidence. Keep every observation to 25 words about visible appearance, without treatment claims or numerical final scores. Region obstruction_note empty unless an actual obstruction is present. Do not produce component narratives or duplicated scoring JSON.`}
export function groupWeight(g){return GROUPS[g].reduce((s,z)=>s+FACE_ZONE_ATLAS_V2.zones[z].default_area_weight,0)}
const finite=(v,lo,hi)=>typeof v==='number'&&Number.isFinite(v)&&v>=lo&&v<=hi
export function decodeMeasurements(value){
 if(!value?.parameters||!value.regions||!value.skin_type)throw Error('Missing regional measurement sections')
 const exact=(o,keys,label)=>{if(!o||Array.isArray(o)||Object.keys(o).sort().join('|')!==keys.slice().sort().join('|'))throw Error(`Invalid keys: ${label}`)}
 exact(value.parameters,Object.keys(CONTRACT),'parameters');exact(value.regions,Object.keys(GROUPS),'regions')
 for(const g of Object.keys(GROUPS))if(!finite(value.regions[g].visible_fraction,0,1)||typeof value.regions[g].obstruction_note!=='string')throw Error(`Invalid visibility ${g}`)
 const parameters={}
 for(const[id,c]of Object.entries(CONTRACT)){
  const p=value.parameters[id];exact(p.regions,c.regions,id)
  if(typeof p.observation!=='string'||!p.observation.trim())throw Error(`Missing observation ${id}`)
  if(!c.regions.some(g=>value.regions[g].visible_fraction>0))throw Error(`No visible support for ${id}; review capture, no constant score substituted`)
  parameters[id]={regions:{},observation:p.observation}
  for(const g of c.regions){const r=p.regions[g];if(!Array.isArray(r.values)||r.values.length!==c.observed_metrics.length||!finite(r.confidence,.01,1)||typeof r.estimated!=='boolean')throw Error(`Invalid row ${id}.${g}`)
   const metrics=Object.fromEntries(c.observed_metrics.map((name,i)=>{const [lo,hi]=metricBoundsV37(id,name);if(!finite(r.values[i],lo,hi))throw Error(`Invalid unit/range ${id}.${g}.${name}`);return[name,r.values[i]]}))
   const estimated=r.estimated||value.regions[g].visible_fraction<.5||['skin_hydration','skin_firmness_elasticity','jawline_sagging','superficial_wrinkles'].includes(id)
   if(id==='superficial_pigmentation' && (typeof r.landmark_reference!=='string'||!r.landmark_reference.trim()))throw Error(`Missing pigment landmark ${g}`)
   parameters[id].regions[g]={metrics,confidence:estimated?Math.min(.55,r.confidence):r.confidence,estimated,...(id==='superficial_pigmentation'?{landmark_reference:r.landmark_reference}:{})}
  }
 }
 for(const g of CONTRACT.superficial_pigmentation.regions){const m=parameters.superficial_pigmentation.regions[g].metrics;m.contrast_to_surrounding_skin_index=(m.white_patch_contrast+m.subsurface_patch_contrast)/2}
 for(const g of CONTRACT.skin_hydration.regions)parameters.skin_hydration.regions[g].metrics.shared_flaking_index=parameters.barrier_health_sensitivity.regions[g].metrics.flaking_texture_index
 for(const g of CONTRACT.textural_radiance.regions)parameters.textural_radiance.regions[g].metrics.shared_roughness_index=parameters.texture_open_pores.regions[g].metrics.texture_roughness_index
 for(const g of CONTRACT.texture_open_pores.regions){const m=parameters.texture_open_pores.regions[g].metrics;const oil=parameters.skin_sebum.regions[g].metrics.shine_norm;m.pore_diameter_index=.70*m.pore_density_index+.30*oil;m.pore_clarity_index=1-.60*m.texture_roughness_index-.40*oil}
 for(const g of CONTRACT.skin_luminosity_glow.regions)parameters.skin_luminosity_glow.regions[g].metrics.subsurface_diffusion_index=parameters.skin_hydration.regions[g].metrics.subsurface_diffusion_index
 if(!['dry','oily','combination','balanced'].includes(value.skin_type.label)||!finite(value.skin_type.confidence,.01,1)||!value.skin_type.observation?.trim())throw Error('Invalid skin type')
 return {version:MEASUREMENT_VERSION,regions:value.regions,parameters,skin_type:value.skin_type}
}
