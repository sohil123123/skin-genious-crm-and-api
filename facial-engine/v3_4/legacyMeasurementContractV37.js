import { LEGACY_VISUAL_ANCHORS_V37 } from './legacyVisualAnchorReferenceV37.js'
import { LEGACY_RUBRIC_V37 } from './legacyRubricV37.js'
export const LEGACY_MEASUREMENT_VERSION_V37 = 'aia_legacy_measurements_v3.7.0'
export const PARAMETER_CONTRACT_V37 = {
  barrier_health_sensitivity: {source:'combined_barrier_sensitivity', cuts:[.20,.35,.55,.75], metrics:['surface_texture_uniformity','hydration_signal_index','erythema_intensity_index','vascular_pattern_index','flaking_texture_index']},
  visual_acne: {source:'visual_acne_scoring', cuts:[.20,.35,.55,.75], metrics:['lesion_load_normalized','inflammation_normalized','comedone_density_index','inflammatory_cluster_index']},
  skin_sebum: {source:'sebum_content_scoring', cuts:[.20,.38,.58,.78], metrics:['shine_norm','fluorescence_norm','porphyrin_norm','congestion_norm']},
  vascularity_redness: {source:'vascularity_redness_scoring', semantic:true, metrics:['legacy_grade_continuous']},
  skin_hydration: {source:'skin_hydration_scoring', health:true, cuts:[.30,.45,.60,.75], metrics:['surface_reflectance_index','subsurface_diffusion_index','microline_density_index','sebum_balance_ratio','dry_patch_fluorescence_index']},
  skin_luminosity_glow: {source:'skin_luminosity_index', health:true, cuts:[.25,.40,.60,.78], metrics:['surface_reflectance_uniformity','color_luminance_index','subsurface_diffusion_index','shadow_softness_index','sebum_gloss_index','dryness_dullness_index']},
  superficial_pigmentation: {source:'superficial_pigmentation_scoring', cuts:[.18,.36,.58,.78], metrics:['coverage_area_percent','mean_intensity_index','contrast_to_surrounding_skin_index','woods_cluster_density','uniformity_index','region_variation_index']},
  peri_orbital_health: {source:'peri_orbital_skin_health_scoring', metrics:['pigment_index','vascular_index','shadow_hollow_index','puffiness_index','texture_line_index']},
  lip_pigmentation: {source:'lip_pigmentation_scoring', semantic:true, metrics:['intrinsic_melanin_index','surface_darkness_index','vascular_congestion_index','legacy_grade_continuous']},
  texture_open_pores: {source:'texture_pores_scoring', cuts:[.18,.34,.56,.76], metrics:['pore_density_index','pore_diameter_index','pore_clarity_index','texture_roughness_index','blackhead_congestion_index']},
  superficial_wrinkles: {source:'superficial_wrinkles_scoring', cuts:[.18,.34,.54,.75], metrics:['wrinkle_depth_index','microline_density_index','chronicity_uv_index','regional_uniformity_index','structural_vs_dehydration_index']},
  jawline_sagging: {source:'jawline_sagging_scoring', semantic:true, metrics:['mandibular_line_deflection_angle','pre_jowl_sulcus_depth_index','jowl_bulge_prominence_index','submental_fullness_index','dermal_collagen_thinning_index','left_right_asymmetry_index','legacy_grade_continuous']},
  skin_firmness_elasticity: {source:'skin_firmness_elasticity_index', metrics:['micro_laxity_pattern_index','collagen_reflectance_uniformity','elastic_recoil_proxy_index']},
  textural_radiance: {source:'textural_radiance_index', metrics:['micro_clarity_index','surface_smooth_scatter_index','keratin_shadow_index']},
}
export function metricBoundsV37(id,name) {
  if(name==='legacy_grade_continuous') return [1,5]
  if(name==='mandibular_line_deflection_angle') return [0,12]
  if(name==='coverage_area_percent'||id==='peri_orbital_health') return [0,100]
  return [0,1]
}
const obj=properties=>({type:'object',properties,required:Object.keys(properties),additionalProperties:false})
const num=(minimum,maximum)=>({type:'number',minimum,maximum})
const text={type:'string'}
const modes={type:'array',minItems:1,items:{type:'string',enum:['white','surface_polarized','subsurface_polarized','red','woods_uv']}}
const metric=(id,name)=>obj({value:num(...metricBoundsV37(id,name)),confidence_0_1:num(0,1),basis:{type:'string',enum:['visible_evidence','supported_estimate']}})
export function legacyMeasurementSchemaV37() {
 const parameters=Object.fromEntries(Object.entries(PARAMETER_CONTRACT_V37).map(([id,c])=>[id,obj({
   metrics:obj(Object.fromEntries(c.metrics.map(n=>[n,metric(id,n)]))),
   supporting_modes:modes,supporting_regions:{type:'array',minItems:1,items:text},
   evidence_summary:text,estimation_reason:text,client_observation:text,
 })]))
 return obj({version:{type:'string',enum:[LEGACY_MEASUREMENT_VERSION_V37]},parameters:obj(parameters),skin_type:obj({
   label:{type:'string',enum:['dry','oily','combination','balanced']},confidence_0_1:num(0,1),
   basis:{type:'string',enum:['visible_evidence','supported_estimate']},evidence_summary:text,
   modifiers:{type:'array',items:{type:'string',enum:['sensitive','sun_reactive','dehydrated']}},
 })})
}
export function validateLegacyMeasurementsV37(packet) {
 if(packet?.version!==LEGACY_MEASUREMENT_VERSION_V37) throw new Error('V3.7 legacy measurements required: re-run original images; old burdens cannot be converted.')
 const exact=(o,keys,path)=>{if(!o||typeof o!=='object'||Array.isArray(o)||Object.keys(o).sort().join('|')!==[...keys].sort().join('|'))throw new Error(`Invalid keys at ${path}`)}
 exact(packet,['version','parameters','skin_type'],'legacy_measurements')
 exact(packet.parameters,Object.keys(PARAMETER_CONTRACT_V37),'parameters')
 const validBasis=b=>['visible_evidence','supported_estimate'].includes(b)
 const finite=(v,lo,hi)=>typeof v==='number'&&Number.isFinite(v)&&v>=lo&&v<=hi
 for(const [id,c] of Object.entries(PARAMETER_CONTRACT_V37)) {
  const p=packet.parameters[id]
  exact(p,['metrics','supporting_modes','supporting_regions','evidence_summary','estimation_reason','client_observation'],id)
  exact(p.metrics,c.metrics,`${id}.metrics`)
  if(!Array.isArray(p.supporting_modes)||!p.supporting_modes.length||p.supporting_modes.some(m=>!modes.items.enum.includes(m)))throw new Error(`Missing image support: ${id}`)
  if(!Array.isArray(p.supporting_regions)||!p.supporting_regions.length||p.supporting_regions.some(s=>typeof s!=='string'||!s.trim()))throw new Error(`Missing region support: ${id}`)
  for(const k of ['evidence_summary','client_observation'])if(typeof p[k]!=='string'||!p[k].trim())throw new Error(`Missing ${k}: ${id}`)
  if(typeof p.estimation_reason!=='string')throw new Error(`Invalid estimation reason: ${id}`)
  for(const name of c.metrics) {
   const m=p.metrics[name];exact(m,['value','confidence_0_1','basis'],`${id}.${name}`)
   if(!finite(m.value,...metricBoundsV37(id,name))||!finite(m.confidence_0_1,0,1)||!validBasis(m.basis))throw new Error(`Invalid measurement: ${id}.${name}`)
   if(m.confidence_0_1===0)throw new Error(`No supported estimate for ${id}.${name}; retry image analysis, never fill a score with a constant.`)
   if(m.basis==='supported_estimate'&&!p.estimation_reason.trim())throw new Error(`Missing estimation reason: ${id}.${name}`)
  }
 }
 const t=packet.skin_type
 exact(t,['label','confidence_0_1','basis','evidence_summary','modifiers'],'skin_type')
 if(!['dry','oily','combination','balanced'].includes(t.label)||!finite(t.confidence_0_1,0,1)||!t.confidence_0_1||!validBasis(t.basis)||typeof t.evidence_summary!=='string'||!t.evidence_summary.trim()||!Array.isArray(t.modifiers)||t.modifiers.some(m=>!['sensitive','sun_reactive','dehydrated'].includes(m)))throw new Error('Invalid skin type evidence')
 return packet
}
export function legacyMeasurementPromptV37() {
 return `
LEGACY MEASUREMENT CONTRACT V3.7 (the l field):
Keep the full regional f/m evidence. Also extract l measurements from THESE SAME five images and the same visible findings. The two namespaces have different definitions: never copy or rescale a generic 0-5 concern burden into a legacy metric. Use the exact metric definitions/bands below. No population normalization, healthy-score target, old client score, forced midpoint, or 0.05 rounding. Use continuous values justified by visible evidence. Do not fabricate exact pixel counts, DBSCAN outputs, collagen concentrations, hydration readings or mechanical recoil tests; legacy physiological names mean appearance proxies only. Mark indirect appearance proxies supported_estimate.
Confidence and severity are independent. Low confidence NEVER makes a value healthier. Absence of flaking does not prove optimal hydration. White-light reflectance, plumpness, diffusion and micro-lines must be assessed separately. Do not mistake constitutive skin colour for pathological pigment or lighting brightness for glow.
Every parameter needs a best supported numerical estimate, including partially obscured jawline: use visible contour transitions/adjacent lower-face evidence across modes and mark supported_estimate, explain exactly what was visible and what was inferred. Do not use unknown components encoded as zero in f as evidence of health. If there is literally no relevant direct or indirect evidence, set confidence zero so the server can retry the analysis; never invent a patient-specific score or a neutral constant.
For each parameter give supporting_modes, supporting_regions, an internal evidence_summary and estimation_reason (empty only when no estimate is used), plus a brief plain-language client_observation about visible appearance, without scores, code, confidence percentages, diagnosis or treatment claims. Do not write 'not assessable' in a client observation; describe the supported appearance. Never claim an occluded structure was directly seen.
Every supported_estimate has confidence <=0.55, preserving the original legacy estimation rule. Other measurements must also reflect actual evidence quality; no confidence defaults.
Only vascularity_redness, lip_pigmentation and jawline_sagging request legacy_grade_continuous (1-5, higher=worse): apply the supplied old clinical criteria, dominant pattern, and interpolate between adjacent clinical descriptions. Explain that choice in evidence_summary. For lip/jaw the source lacks a complete final grade rule: use its component bands/clinical interpretation without inventing final numeric index thresholds. This remains a semantic estimate. Never use new feature anchors as old final grades.
Peri-orbital indices are 0-100 (as in source); coverage_area_percent is 0-100; mandibular angle is 0-12 degrees; all other non-grade indices are 0-1. Skin type follows the legacy matrix and mixed regional pattern rule, with confidence separately.
Backend computes all final indices and 1-100 client scores. Return the l schema exactly.
ORIGINAL EXTRACTION ANCHOR REFERENCE:
${JSON.stringify(LEGACY_VISUAL_ANCHORS_V37)}
These are historical bin representative values, not an instruction to output bins/midpoints or round by 0.05. Preserve their clinical meanings when judging compatible metrics and interpolate when evidence supports an intermediate value. Do not replace them with the f namespace's normalized concern anchors. Crucially, barrier_uniformity_bin and hydration_signal_bin were deficit-coded in the original extractor: their final corresponding health indices are 1 minus the listed midpoint. Coverage_index is not coverage_area_percent; estimate actual percent for the latter. Where names represent different metrics, use the exact final rubric definition below rather than assume equality. Original fallback proxy formulas are not direct measurements and must not be substituted for visible evidence.
FROZEN LEGACY RUBRIC:\n${JSON.stringify(LEGACY_RUBRIC_V37)}
`
}
