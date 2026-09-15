// Engineering weights: fixed before evaluation, not fitted to a patient's gains.
// Existing outer score boundaries retained; new input semantics require acceptance.
import {interpolateLegacyIndexV37} from './legacyScoringV37.js'
const clamp=x=>Math.max(0,Math.min(1,x))
export function correctedParameterV311(id,previous,m) {
 let index,terms
 if(id==='skin_hydration')terms={absence_of_flaking:.35*(1-m.shared_flaking_index),absence_of_dry_patch:.30*(1-m.visible_dry_patch_index),absence_of_dehydration_lines:.25*(1-m.microline_density_index),surface_reflectance_support:.10*m.surface_reflectance_index}
 else if(id==='skin_luminosity_glow')terms={reflection_uniformity:.35*m.surface_reflectance_uniformity,diffuse_luminosity:.30*m.color_luminance_index,shadow_softness:.20*m.shadow_softness_index,absence_of_dullness:.15*(1-m.dryness_dullness_index)}
 else if(id==='textural_radiance')terms={shared_surface_roughness:.40*m.shared_roughness_index,reflection_discontinuity:.35*(1-m.surface_smooth_scatter_index),surface_microshadows:.25*m.keratin_shadow_index}
 else return previous
 for(const[k,v]of Object.entries(m))if(typeof v!=='number'||!Number.isFinite(v))throw Error(`Nonfinite correction metric ${id}.${k}`)
 index=clamp(Object.values(terms).reduce((a,b)=>a+b,0))
 const cuts=previous.calibration.exact_final_index_cuts
 const quality=id==='textural_radiance'?1-index:interpolateLegacyIndexV37(index,[0,...cuts,1])
 const health=1+99*clamp(quality),score=Math.round(health)
 return {...previous,display_score_1_to_100:score,internal_burden_score_1_to_100:101-health,display_band:score>=85?'excellent':score>=70?'good':score>=50?'moderate':score>=30?'needs_attention':'high_concern',calibration:{...previous.calibration,version:'aia_four_parameter_equations_v3.11.0',status:'engineering_weights_not_population_validated',legacy_index_0_to_1:undefined,legacy_grade_1_to_5:undefined,prior_equation_index_audit:previous.calibration.legacy_index_0_to_1,corrected_index_0_to_1:index,display_method:'revised_appearance_index_with_existing_outer_boundaries',unrounded_health_1_to_100:health,calibrated_concern_0_to_1:1-quality,metric_values:m,weighted_index_terms:terms}}
}
