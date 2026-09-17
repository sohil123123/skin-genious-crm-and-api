// Existing entry filename: V3.15 canonical visible-impact baseline and planning evidence.
import {FEATURES,PARAMETERS,GROUPS,MEASUREMENT_VERSION,FORMULA_VERSION,impactToHealth,healthToBurden} from './visibleImpactRubricV315.js'
import {decodeMeasurements,PARAMETER_MODES,DEVICE_PROFILE} from './regionalMeasurementV310.js'
import {CORE_FEATURE_IDS,SKIN_STATE_SCHEMA_VERSION,VISION_EVIDENCE_SCHEMA_VERSION} from './skinStateV2.schema.js'
import {FACE_ZONE_ATLAS_VERSION} from './faceZoneAtlasV2.js'
const LINKS={active_inflammatory_acne:'visual_acne',comedonal_congestion:'visual_acne',oiliness:'skin_sebum',erythema_redness:'vascularity_redness',barrier_stress:'barrier_health_sensitivity',visual_dehydration:'skin_hydration',pore_visibility:'texture_open_pores',texture_roughness:'texture_open_pores',visible_pigmentation:'superficial_pigmentation',underlying_pigment_support:'superficial_pigmentation',luminosity_loss:'skin_luminosity_glow',fine_line_visibility:'superficial_wrinkles',visible_laxity:'jawline_sagging',firmness_appearance_loss:'skin_firmness_elasticity',peri_orbital_concern:'peri_orbital_health',lip_pigmentation:'lip_pigmentation'}
const SUBFEATURES={active_inflammatory_acne:['papules','pustules','deep_inflammatory_lesions'],comedonal_congestion:['comedones'],oiliness:['oily_film'],pore_visibility:['pore_prominence'],texture_roughness:['roughness'],underlying_pigment_support:['uv_pigment_pattern']}
const band=s=>s>=85?'excellent':s>=70?'good':s>=50?'moderate':s>=30?'needs_attention':'high_concern'
const tier=c=>c>=.85?'high':c>=.6?'moderate':'low'
export function buildRegionalRun(input,{scanId,imageSetHash,modelVersion,captureType='baseline',pairedBaselineScanId=null}){
 const m=decodeMeasurements(input),parameters={},core={},features={}
 const unavailable=Object.entries(m.parameters).filter(([,p])=>p.state==='unavailable').map(([id])=>id)
 if(unavailable.length)throw Error(`No supported numerical estimate for: ${unavailable.join(', ')}. Review the exposed-region evidence or obtain a clinician score; unavailable skin must not become 100.`)
 for(const[id,p]of Object.entries(m.parameters)){
  const health=impactToHealth(p.impact_level),score=Math.round(health),q=p.confidence
  const supporting=p.supporting_features.map(f=>({feature:f,...m.features[f]}))
  const regions=[...new Set(supporting.flatMap(f=>f.regions))]
  parameters[id]={parameter_id:id,display_score_1_to_100:score,internal_burden_score_1_to_100:101-health,display_polarity:'higher_is_better',display_band:band(score),
   source_components:Object.fromEntries(Object.entries(LINKS).filter(([,param])=>param===id).map(([f])=>[f,1])),client_observation:p.rationale,
   data_quality:{is_estimated:p.state==='supported_estimate',confidence_0_1:q,estimation_basis:'anchored_visual_impact_judgment',supporting_modes:PARAMETER_MODES[id].primary,supporting_regions:regions,evidence_summary:p.rationale,treatment_priority_multiplier:1,low_confidence_estimate:q<.6},measurement_modes:PARAMETER_MODES[id],
   // No independently measured regional parameter scores are fabricated from the global judgment.
   regional_health_scores_1_to_100:{},
   calibration:{version:FORMULA_VERSION,status:'clinician_informed_anchor_definitions_not_population_validated',measurement_version:MEASUREMENT_VERSION,display_method:'continuous_interpolation_of_anchored_overall_visible_impact',unrounded_health_1_to_100:health,calibrated_concern_0_to_1:healthToBurden(health),overall_impact_level:p.impact_level,supporting_visual_features:p.supporting_features,dominant_visual_features:p.dominant_features,feature_evidence:supporting,measurement_confidence_changes_score:false,area_multiplier_used:false,structural_components:PARAMETERS[id].features.filter(f=>FEATURES[f].response_class==='structural'),population_percentile:false}}
 }
 for(const id of CORE_FEATURE_IDS){
  const pid=LINKS[id];if(!pid)throw Error(`Unknown planning feature ${id}`)
  const p=m.parameters[pid],ids=SUBFEATURES[id]??p.supporting_features
  const available=ids.filter(f=>m.features[f].state!=='unavailable')
  // Specific planning concerns use the strongest available named component, never include absent categories in an average.
  // Other planning features use the same canonical overall judgment as the report.
  const level=SUBFEATURES[id]?(available.length?Math.max(...available.map(f=>m.features[f].impact_level)):null):p.impact_level
  const q=available.length?Math.min(...available.map(f=>m.features[f].confidence),SUBFEATURES[id]?1:p.confidence):0
  const burden=level===null?null:healthToBurden(impactToHealth(level))
  const zones={},zoneScores={},normalized={}
  const groups=[...new Set(available.flatMap(f=>m.features[f].regions))]
  for(const g of groups){const relevant=available.filter(f=>m.features[f].regions.includes(g))
   for(const z of GROUPS[g]){
    // Localization of a global impact estimate, not an independent per-zone measurement.
    zoneScores[z]=1+99*burden;normalized[z]=burden
    zones[z]={assessment_status:relevant.some(f=>m.features[f].state==='supported_estimate')?'partially_assessable':'assessable',visibility_fraction_0_to_100:100*m.regions[g].visible_fraction,components:{},mode_agreement:'not_independently_quantified',artifact_flags:[],evidence_summary:relevant.map(f=>m.features[f].evidence).join(' '),measurement_group:g,spatial_resolution:'global_feature_impact_localized_to_supported_regions_not_independent_zone_measurement'}
   }
  }
  const ranked=Object.keys(zoneScores)
  core[id]={feature_id:id,assessment_status:level===null?'not_assessable':'supported_visual_impact',global_burden_score_1_to_100:burden===null?null:1+99*burden,raw_normalized_burden_0_to_1:burden,guarded_normalized_burden_0_to_1:burden,zone_scores_1_to_100:zoneScores,zone_normalized_burdens_0_to_1:normalized,dominant_zones:ranked.slice(0,3),peak_zone:ranked[0]??null,aggregation_details:{method:SUBFEATURES[id]?'dominant_available_component_impact':'canonical_parameter_impact',source_parameter:pid,source_visual_features:available,regional_ranking_available:false},guardrails_applied:[],measurement_sensitivity:'continuous_clinical_appearance_anchor_position',score_reliability:{score_1_to_100:q*100,tier:tier(q),reasons:q<.6?['limited_visual_support']:[],audit:{mean_component_confidence_0_to_100:q*100}},treatment_evidence_v37:{confidence_0_1:q,priority_multiplier:1,linked_parameters:[pid],low_confidence_estimate:q<.6},measurement_provenance:{version:MEASUREMENT_VERSION,source_parameter:pid,uses_same_primitives_as_client_score:true,...(id==='underlying_pigment_support'?{limitation:'UV optical context only; not measured pigment depth'}:{})}}
  features[id]={feature_id:id,zones,global_artifact_flags:[],global_evidence_summary:available.map(f=>m.features[f].evidence).join(' ')}
 }
 const skinType={label:m.skin_type.label,modifiers:[],evidence:{source:'visible_regional_pattern',description:m.skin_type.observation},data_quality:{confidence_0_1:m.skin_type.confidence,is_estimated:true}}
 parameters.skin_type={parameter_id:'skin_type',display_label:skinType.label,value_type:'label',data_quality:skinType.data_quality}
 const scan={scan_id:scanId,image_set_hash:imageSetHash,capture_type:captureType,paired_baseline_scan_id:pairedBaselineScanId,modes_received:['red','subsurface_polarized','surface_polarized','white','woods_uv']}
 const state={schema_version:SKIN_STATE_SCHEMA_VERSION,zone_atlas_version:FACE_ZONE_ATLAS_VERSION,scan,scoring_execution:{mapper_version:MEASUREMENT_VERSION,calibration_version:FORMULA_VERSION,formula_config_version:FORMULA_VERSION,calibration_status:'visible_impact_anchor_revision_requires_case_benchmark_and_repeatability_review',created_at_iso:new Date().toISOString(),immutable_assessment:true,history_excluded_from_image_scoring:true},morphology_exclusion_summary:{map_schema_version:null,reconciliation_action_count:0,reconciliation_audit:[]},regional_measurements:m,core_features:core,derived_report_parameters:parameters,skin_type:skinType}
 const evidence={schema_version:VISION_EVIDENCE_SCHEMA_VERSION,zone_atlas_version:FACE_ZONE_ATLAS_VERSION,scan,model_execution:{model_version:modelVersion,prompt_version:MEASUREMENT_VERSION,created_at_iso:new Date().toISOString()},features,regional_measurements:m,device_profile:DEVICE_PROFILE,morphology_exclusion_map:null,reconciliation_audit:[]}
 return {skin_state:state,evidence_packet:evidence}
}
