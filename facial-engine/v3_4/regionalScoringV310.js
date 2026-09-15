import {correctedParameterV311} from './scoringCorrectionsV311.js'
import {CONTRACT,DERIVED,GROUPS,PARAMETER_MODES,DEVICE_PROFILE,MEASUREMENT_VERSION,FORMULA_VERSION,groupWeight} from './regionalMeasurementV310.js'
import {scoreLegacyMeasurementsV37,legacySkinTypeV37} from './legacyScoringV37.js'
import {LEGACY_MEASUREMENT_VERSION_V37} from './legacyMeasurementContractV37.js'
import {CORE_FEATURE_IDS,SKIN_STATE_SCHEMA_VERSION,VISION_EVIDENCE_SCHEMA_VERSION} from './skinStateV2.schema.js'
import {FACE_ZONE_ATLAS_VERSION} from './faceZoneAtlasV2.js'
const clamp=x=>Math.max(0,Math.min(1,x))
const mean=rows=>rows.reduce((s,[x,w])=>s+x*w,0)/rows.reduce((s,[,w])=>s+w,0)
const LINKS={active_inflammatory_acne:'visual_acne',comedonal_congestion:'visual_acne',oiliness:'skin_sebum',erythema_redness:'vascularity_redness',barrier_stress:'barrier_health_sensitivity',visual_dehydration:'skin_hydration',pore_visibility:'texture_open_pores',texture_roughness:'texture_open_pores',visible_pigmentation:'superficial_pigmentation',underlying_pigment_support:'superficial_pigmentation',luminosity_loss:'skin_luminosity_glow',fine_line_visibility:'superficial_wrinkles',visible_laxity:'jawline_sagging',firmness_appearance_loss:'skin_firmness_elasticity',peri_orbital_concern:'peri_orbital_health',lip_pigmentation:'lip_pigmentation'}
function legacyParameter(id,p){const c=CONTRACT[id];const rows=Object.entries(p.regions);const isEstimate=rows.some(([,r])=>r.estimated)||(DERIVED[id]?.length??0)>0;return {
 metrics:Object.fromEntries(c.metrics.map(n=>[n,{value:mean(rows.map(([g,r])=>[r.metrics[n],groupWeight(g)])),confidence_0_1:Math.min(isEstimate?.55:1,...rows.map(([,r])=>r.confidence)),basis:isEstimate?'supported_estimate':'visible_evidence'}])),
 supporting_modes:[...c.modes.primary],supporting_regions:rows.map(([g])=>g),evidence_summary:p.observation,client_observation:p.observation,estimation_reason:isEstimate?'Regional supported visual estimate; physiological names denote appearance proxies.':'',
}}
function precise(p){const health=p.calibration.unrounded_health_1_to_100;return {...p,internal_burden_score_1_to_100:101-health,calibration:{...p.calibration,measurement_version:MEASUREMENT_VERSION,regional_aggregation:'fixed_atlas_area_weighted_primitive_mean',validation_status:'engineering_revision_requires_clinical_repeatability_acceptance'}}}
function featureBurden(id,parameter,m){
 // Specific sub-concerns use the same measured primitives; these are explicit
 // planning proxies, not an independently inferred second score namespace.
 if(id==='comedonal_congestion')return clamp(m.comedone_density_index)
 if(id==='oiliness')return clamp(.40*m.shine_norm+.25*m.fluorescence_norm+.20*m.porphyrin_norm+.15*m.congestion_norm)
 if(id==='texture_roughness')return clamp(m.texture_roughness_index)
 if(id==='underlying_pigment_support')return clamp(m.woods_cluster_density)
 return (parameter.internal_burden_score_1_to_100-1)/99
}
export function buildRegionalRun(measured,{scanId,imageSetHash,modelVersion,captureType='baseline',pairedBaselineScanId=null}){
 const packet={version:LEGACY_MEASUREMENT_VERSION_V37,parameters:Object.fromEntries(Object.entries(measured.parameters).map(([id,p])=>[id,legacyParameter(id,p)])),skin_type:{label:measured.skin_type.label,confidence_0_1:measured.skin_type.confidence,basis:'visible_evidence',evidence_summary:measured.skin_type.observation,modifiers:[]}}
 const corrected=(id,p,regions)=>correctedParameterV311(id,p,Object.fromEntries(Object.keys(Object.values(regions)[0].metrics).map(n=>[n,mean(Object.entries(regions).map(([g,r])=>[r.metrics[n],groupWeight(g)]))])))
 const parameters=Object.fromEntries(Object.entries(scoreLegacyMeasurementsV37(packet)).map(([id,p])=>[id,precise(corrected(id,p,measured.parameters[id].regions))]))
 const regional={}
 for(const[id,p]of Object.entries(measured.parameters)){
  regional[id]={}
  for(const[g,r]of Object.entries(p.regions)){
   const local={...packet,parameters:{...packet.parameters,[id]:legacyParameter(id,{...p,regions:{[g]:r}})}}
   regional[id][g]=precise(corrected(id,scoreLegacyMeasurementsV37(local)[id],{[g]:r}))
  }
  parameters[id].regional_health_scores_1_to_100=Object.fromEntries(Object.entries(regional[id]).map(([g,r])=>[g,r.calibration.unrounded_health_1_to_100]))
  parameters[id].measurement_modes=PARAMETER_MODES[id]
 }
 const core={},features={}
 for(const id of CORE_FEATURE_IDS){const pId=LINKS[id],p=parameters[pId],zoneScores={},normalized={},zones={};const rows=[]
  for(const[g,r]of Object.entries(measured.parameters[pId].regions)){
   const burden=featureBurden(id,regional[pId][g],r.metrics);rows.push([burden,groupWeight(g)])
   for(const z of GROUPS[g]){zoneScores[z]=1+99*burden;normalized[z]=burden;zones[z]={assessment_status:r.estimated?'partially_assessable':'assessable',visibility_fraction_0_to_100:100*measured.regions[g].visible_fraction,components:{},mode_agreement:'single_mode_only',artifact_flags:[],evidence_summary:measured.parameters[pId].observation,measurement_group:g,spatial_resolution:'shared_group_estimate_not_independent_subzone_measurement'}}
  }
  const burden=['comedonal_congestion','oiliness','texture_roughness','underlying_pigment_support'].includes(id)?mean(rows):(p.internal_burden_score_1_to_100-1)/99,confidence=Math.min(p.data_quality.confidence_0_1,...Object.values(measured.parameters[pId].regions).map(r=>r.confidence))*100
  const ranked=Object.entries(zoneScores).sort((a,b)=>b[1]-a[1])
  core[id]={feature_id:id,assessment_status:'supported_regional_measurement',global_burden_score_1_to_100:1+99*burden,raw_normalized_burden_0_to_1:burden,guarded_normalized_burden_0_to_1:burden,zone_scores_1_to_100:zoneScores,zone_normalized_burdens_0_to_1:normalized,dominant_zones:ranked.slice(0,3).map(([z])=>z),peak_zone:ranked[0]?.[0]??null,aggregation_details:{method:'fixed_group_area_mean',source_parameter:pId},guardrails_applied:[],measurement_sensitivity:'continuous_regional_legacy_primitives',score_reliability:{score_1_to_100:confidence,tier:confidence>=85?'high':confidence>=60?'moderate':'low',reasons:confidence<60?['supported_visual_estimate']:[],audit:{mean_component_confidence_0_to_100:confidence}},treatment_evidence_v37:{confidence_0_1:confidence/100,priority_multiplier:1,linked_parameters:[pId],low_confidence_estimate:confidence<60},measurement_provenance:{version:MEASUREMENT_VERSION,source_parameter:pId,uses_same_primitives_as_client_score:true}}
  features[id]={feature_id:id,zones,global_artifact_flags:[],global_evidence_summary:measured.parameters[pId].observation}
 }
 const skinType=legacySkinTypeV37(packet)
 parameters.skin_type={parameter_id:'skin_type',display_label:skinType.label,value_type:'label',data_quality:skinType.data_quality}
 const scan={scan_id:scanId,image_set_hash:imageSetHash,capture_type:captureType,paired_baseline_scan_id:pairedBaselineScanId,modes_received:['red','subsurface_polarized','surface_polarized','white','woods_uv']}
 const state={schema_version:SKIN_STATE_SCHEMA_VERSION,zone_atlas_version:FACE_ZONE_ATLAS_VERSION,scan,scoring_execution:{mapper_version:MEASUREMENT_VERSION,calibration_version:FORMULA_VERSION,formula_config_version:FORMULA_VERSION,calibration_status:'four_parameter_revision_existing_outer_boundaries_not_population_validated',created_at_iso:new Date().toISOString(),immutable_assessment:true,history_excluded_from_image_scoring:true},morphology_exclusion_summary:{map_schema_version:null,reconciliation_action_count:0,reconciliation_audit:[]},legacy_measurements:packet,regional_measurements:measured,core_features:core,derived_report_parameters:parameters,skin_type:skinType}
 const evidence={schema_version:VISION_EVIDENCE_SCHEMA_VERSION,zone_atlas_version:FACE_ZONE_ATLAS_VERSION,scan,model_execution:{model_version:modelVersion,prompt_version:MEASUREMENT_VERSION,created_at_iso:new Date().toISOString()},features,legacy_measurements:packet,regional_measurements:measured,device_profile:DEVICE_PROFILE,morphology_exclusion_map:null,reconciliation_audit:[]}
 return {skin_state:state,evidence_packet:evidence}
}
