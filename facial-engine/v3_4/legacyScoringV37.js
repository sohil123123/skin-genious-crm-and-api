import { PARAMETER_CONTRACT_V37, validateLegacyMeasurementsV37 } from './legacyMeasurementContractV37.js'
export const CALIBRATION_VERSION_V37='aia_legacy_equations_v3.7.0'
export const CALIBRATION_STATUS_V37='source_equations_preserved_display_interpolation_engineering_extension'
const clamp=x=>Math.max(0,Math.min(1,x))
export function interpolateLegacyIndexV37(x,bounds) {
 for(let i=1;i<bounds.length;i++)if(x<=bounds[i])return ((i-1)+(x-bounds[i-1])/(bounds[i]-bounds[i-1]))/5
 return 1
}
// Literal transcription of supplied scoring equations. No generic new-feature burden enters here.
export function legacyIndexV37(id,m) {
 switch(id) {
 case 'barrier_health_sensitivity':return .30*(1-m.surface_texture_uniformity)+.25*(1-m.hydration_signal_index)+.25*m.erythema_intensity_index+.10*m.vascular_pattern_index+.10*m.flaking_texture_index
 case 'visual_acne':return .40*m.lesion_load_normalized+.30*m.inflammation_normalized+.15*m.comedone_density_index+.15*m.inflammatory_cluster_index
 case 'skin_sebum':return .40*m.shine_norm+.25*m.fluorescence_norm+.20*m.porphyrin_norm+.15*m.congestion_norm
 case 'skin_hydration':return .40*m.surface_reflectance_index+.25*m.subsurface_diffusion_index+.15*(1-m.microline_density_index)+.10*m.sebum_balance_ratio+.10*(1-m.dry_patch_fluorescence_index)
 case 'skin_luminosity_glow':return .30*m.surface_reflectance_uniformity+.25*m.color_luminance_index+.20*m.subsurface_diffusion_index+.15*m.shadow_softness_index+.05*m.sebum_gloss_index+.05*(1-m.dryness_dullness_index)
 case 'superficial_pigmentation':return .25*clamp((m.coverage_area_percent-5)/65)+.40*m.mean_intensity_index+.15*m.contrast_to_surrounding_skin_index+.15*m.woods_cluster_density+.05*((1-m.uniformity_index+m.region_variation_index)/2)
 case 'texture_open_pores':return .30*m.pore_density_index+.25*m.pore_diameter_index+.20*(1-m.pore_clarity_index)+.15*m.texture_roughness_index+.10*m.blackhead_congestion_index
 case 'superficial_wrinkles':return .30*m.wrinkle_depth_index+.20*m.microline_density_index+.15*m.chronicity_uv_index+.15*(1-m.regional_uniformity_index)+.20*m.structural_vs_dehydration_index
 case 'peri_orbital_health':return (.30*m.pigment_index+.20*m.vascular_index+.30*m.shadow_hollow_index+.10*m.puffiness_index+.10*m.texture_line_index)/100
 case 'skin_firmness_elasticity':return .40*m.micro_laxity_pattern_index+.35*(1-m.collagen_reflectance_uniformity)+.25*(1-m.elastic_recoil_proxy_index)
 case 'textural_radiance':return .40*(1-m.micro_clarity_index)+.35*(1-m.surface_smooth_scatter_index)+.25*m.keratin_shadow_index
 case 'vascularity_redness':case 'lip_pigmentation':case 'jawline_sagging':return (m.legacy_grade_continuous-1)/4
 default:throw new Error(`Unknown legacy parameter ${id}`)
 }
}
export function scoreLegacyMeasurementsV37(packet,definitions={}) {
 validateLegacyMeasurementsV37(packet)
 const result={}
 for(const [id,c] of Object.entries(PARAMETER_CONTRACT_V37)) {
  const p=packet.parameters[id], m=Object.fromEntries(Object.entries(p.metrics).map(([n,x])=>[n,x.value]))
  const index=clamp(legacyIndexV37(id,m))
  let quality,method,grade=null
  if(id==='skin_sebum') {
   // SSI remains the original state spectrum. Health display is a separately identified target-distance view.
   quality=1-clamp(Math.abs(index-.50)/.50)
   method='display_extension_target_distance_from_legacy_sebum_balance_target_0.50'
   grade=1+c.cuts.filter(x=>index>=x-1e-12).length
  }else if(c.cuts) {
   const position=interpolateLegacyIndexV37(index,[0,...c.cuts,1])
   quality=c.health?position:1-position
   method='continuous_display_interpolation_between_exact_legacy_final_index_boundaries'
   grade=1+c.cuts.filter(x=>index>=x-1e-12).length
  }else {
   quality=1-index
   method=c.semantic?'continuous_semantic_legacy_grade_estimate_no_invented_final_index_cutoffs':'direct_continuous_legacy_index_no_final_grade_cutoffs_in_source'
   if(c.semantic)grade=Math.max(1,Math.min(5,Math.round(m.legacy_grade_continuous)))
  }
  const estimated=Object.entries(p.metrics).filter(([,x])=>x.basis==='supported_estimate').map(([n])=>n)
  const confidence=Math.min(...Object.values(p.metrics).map(x=>x.confidence_0_1),estimated.length?.55:1)
  const unrounded=1+99*clamp(quality), score=Math.round(unrounded)
  const dataQuality={is_estimated:!!estimated.length,estimated_fields:estimated,estimation_basis:estimated.length?'supported_image_estimate':'visible_image_evidence',confidence_0_1:confidence,
    estimation_reason:p.estimation_reason,supporting_modes:p.supporting_modes,supporting_regions:p.supporting_regions,evidence_summary:p.evidence_summary,
    treatment_priority_multiplier:1,low_confidence_estimate:confidence<.60}
  result[id]={parameter_id:id,display_score_1_to_100:score,internal_burden_score_1_to_100:101-score,display_polarity:'higher_is_better',
   display_band:score>=85?'excellent':score>=70?'good':score>=50?'moderate':score>=30?'needs_attention':'high_concern',
   source_components:definitions[id]?.components??{},client_observation:p.client_observation,data_quality:dataQuality,
   calibration:{version:CALIBRATION_VERSION_V37,status:CALIBRATION_STATUS_V37,source_variable:c.source,legacy_index_0_to_1:index,
    legacy_grade_1_to_5:grade,legacy_continuous_grade:m.legacy_grade_continuous??null,legacy_polarity:id==='skin_sebum'?'state_spectrum':c.health?'higher_is_better':'higher_is_worse',
    exact_final_index_cuts:c.cuts??null,display_method:method,unrounded_health_1_to_100:unrounded,
    calibrated_concern_0_to_1:1-quality,metric_values:m,measurement_confidence_changes_score:false,
    ...(id==='skin_sebum'?{oil_state_0_to_1:index,target:.50,target_origin:'legacy_extraction_sebum_balance_ratio; health_display_is_new',legacy_sebum_state_grade_1_to_5:grade}:{}),
    ...(c.semantic?{semantic_mapping_status:id==='vascularity_redness'?'source_final_clinical_descriptions':'source_component_bands_only_final_rule_incomplete'}:{}),
   }}
 }
 return result
}
export function legacySkinTypeV37(packet) {
 const t=packet.skin_type
 return {label:t.label,modifiers:[...t.modifiers],evidence:{source:'legacy_skin_type_criteria',description:t.evidence_summary},
  data_quality:{is_estimated:t.basis==='supported_estimate',confidence_0_1:Math.min(t.confidence_0_1,t.basis==='supported_estimate'?.55:1)}}
}
export const FEATURE_PARAMETER_LINKS_V37={
 active_inflammatory_acne:['visual_acne'],comedonal_congestion:['visual_acne','texture_open_pores'],oiliness:['skin_sebum'],
 erythema_redness:['vascularity_redness'],barrier_stress:['barrier_health_sensitivity'],visual_dehydration:['skin_hydration'],
 pore_visibility:['texture_open_pores'],texture_roughness:['texture_open_pores','textural_radiance'],visible_pigmentation:['superficial_pigmentation'],
 underlying_pigment_support:['superficial_pigmentation'],luminosity_loss:['skin_luminosity_glow'],fine_line_visibility:['superficial_wrinkles'],
 visible_laxity:['jawline_sagging'],firmness_appearance_loss:['skin_firmness_elasticity'],peri_orbital_concern:['peri_orbital_health'],lip_pigmentation:['lip_pigmentation'],
}
export function attachPlanningConfidenceV37(core,parameters) {
 for(const [id,ids] of Object.entries(FEATURE_PARAMETER_LINKS_V37)) {
  const f=core[id];if(!f)continue
  // Preserve original regional burdens; do not spread a global estimate into invisible zones.
  const parameterConfidence=Math.min(...ids.map(p=>parameters[p].data_quality.confidence_0_1))
  const original=Math.max(0,Math.min(1,(f.score_reliability?.score_1_to_100??0)/100))
  const component=f.score_reliability?.audit?.mean_component_confidence_0_to_100
  const confidence=Math.min(parameterConfidence,original,Number.isFinite(component)?component/100:original)
  f.treatment_evidence_v37={confidence_0_1:confidence,priority_multiplier:1,linked_parameters:ids,
   low_confidence_estimate:confidence<.60,original_core_reliability_0_1:original}
 }
 return core
}
