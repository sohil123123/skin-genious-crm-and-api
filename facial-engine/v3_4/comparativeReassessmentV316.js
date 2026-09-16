import {APPEARANCE_INSTRUCTIONS} from './appearanceContractV317.js'
import {FEATURES,PARAMETERS,MEASUREMENT_VERSION,FORMULA_VERSION,impactToHealth} from './visibleImpactRubricV315.js'
import {buildRegionalRun} from './regionalScoringV310.js'
import {buildLegacyPostDiagnosisV34} from './legacyFacialAdapterV34.js'
import {CLIENT_REPORT_PARAMETER_IDS} from './skinStateV2.schema.js'
export const VERSION='aia_comparative_reassessment_v3.17.0'
export const MODES=['red','subsurface_polarized','surface_polarized','white','woods_uv']
const obj=p=>({type:'object',properties:p,required:Object.keys(p),additionalProperties:false})
const allowedModes=d=>Array.isArray(d.modes)?d.modes:Object.values(d.modes??{}).flat()
const BASIS=['skin_appearance','surface_material_only','uncertain_skin_or_material','structural_hold','not_comparable']
const row=(definition,isParameter)=>obj({
 regions:{type:'array',items:{type:'string',enum:definition.regions}},
 modes:{type:'array',items:{type:'string',enum:allowedModes(definition)}},
 before_observation:{type:'string'},after_observation:{type:'string'},
 observation_basis:{type:'string',enum:BASIS},
 evidence:{type:'string'},
 ...(isParameter?{anchor_explanation:{type:'string'}}:{}),
 after_impact:{type:['number','null'],minimum:0,maximum:5},
 confidence:{type:'number',minimum:0,maximum:1}
})
export function comparisonFormat(){return {type:'json_schema',name:'comparative_anchors_v317',strict:true,schema:obj({features:obj(Object.fromEntries(Object.entries(FEATURES).map(([k,d])=>[k,row(d,false)]))),parameters:obj(Object.fromEntries(Object.entries(PARAMETERS).map(([k,d])=>[k,row(d,true)])))})}}
export function comparisonPrompt(){return `V3.17 PAIRED APPEARANCE MEASUREMENT. Inspect labelled reference and post-session five-mode captures TOGETHER. The saved reference impact and scoring anchors are fixed. Do not independently reassess or correct the baseline, infer success from treatment, or predict benefit. Output the strict schema only.

${APPEARANCE_INSTRUCTIONS}

Do not output a status label. For supported comparable observations return a numerical endpoint; unchanged endpoints equal reference. For non-comparable observations return null with the relevant observation_basis. The backend derives stable/compared/not_comparable from endpoints and evidence basis.

EVIDENCE FIRST, SCORE SECOND. For EACH row: select actually comparable allowed regions and modes; describe what was visible BEFORE and AFTER in those SAME locations; distinguish skin findings from visible material; then give after_impact positioned relative to the supplied reference_impact and rubric. after_impact is the baseline-anchored endpoint of the visual comparison, NOT an independent whole-face score. The backend computes signed delta = after_impact minus reference impact. Do not output a delta or final health score.

ANCHOR DISTANCE: impact 0=excellent/absent concern supported by visible skin; 1=mild; 2=moderate; 3=marked; 4=severe; 5=extreme. Use the supplied parameter-specific descriptions. Health knots are 100,80,60,40,20,1 respectively. A FULL anchor interval represents about 20 health points; 0.25 represents about 5; 0.5 about 10. These are units, NOT recommended gains. Judge how much of the actual distance between neighbouring appearance anchors the visible finding traversed. A clear change can occur within the same named band; do not force it to 0.05 or 0.1 just because it remains mild/moderate. Equally, do not assign a full anchor shift for merely noticing a difference. Both small and large signed movements need matching evidence; there is no target gain, cap on improvement other than 0..5, or minimum gain. Keep 2 decimals only when supported. Do not report more certainty because many decimal places are available.

For each PARAMETER, anchor_explanation must connect the fixed reference position to the observed endpoint: name the feature(s) and region(s) that changed, which stayed, and why the endpoint belongs where placed between the same anchors. Do not average unchanged areas to dilute a local visible impact, multiply by affected-area fraction, or mechanically average all feature grades. Unchanged scars should not cancel improved congestion/roughness; unchanged pigment need not erase a real diffuse glow improvement. Mixed changes can offset one another only when they actually affect this parameter; explain them. At least one changed supporting feature must substantiate any proposed parameter change. Confidence reports support separately; never shrink the endpoint change to compensate for uncertainty.

MATCHED REGIONAL REVIEW. Use the original captures, not memories of previous outputs. Check the SAME nose bridge/tip/alar regions for comedones and pores; the same central/lateral forehead patches for pigment; the same cheek areas for surface quality. Regions mean inspected skin, including absence of findings; use exact allowed IDs. You do not have numerical image registration or independent pixel counts. If correspondence is inadequate, use observation_basis=not_comparable and null after_impact. Do not invent cropped views, counts, removed lesions, or measurements. Avoid letting a whole-face impression substitute for inspection of the relevant local skin. Do not assume a lighting change simply to discount improvement; assess actual visibility, focus, pose and reflections.

MATERIAL ATTRIBUTION IS MANDATORY. Discrete white specks, product residue, cotton fibres and reflective glints are not by themselves skin flaking, dryness or barrier disruption. Look for adherent scale, lifted skin edges or contiguous rough/dry skin before calling flaking. Never treat the word 'residue' as positive evidence of barrier injury. Choose observation_basis=surface_material_only when only external material/reflection explains the difference; retain the reference endpoint (stable) and describe it. If skin versus material cannot be distinguished, choose observation_basis=uncertain_skin_or_material with a null endpoint. If genuine skin changes remain visible apart from material, use skin_appearance and assess those changes. Do not automatically classify all specks as harmless residue, or ignore actual irritation. Possible product film alone should neither create a hydration gain nor cancel other supported hydration evidence. An uncertain component does not automatically make an entire parameter uncomparable: assess other clearly comparable components and identify any excluded component in anchor_explanation. Do not assume the excluded component improved, worsened or stayed unchanged.

CAPTURE MODES are actual photographs: surface_polarized=parallel; subsurface_polarized=cross; white=visible appearance; red intensity alone is not clinical erythema. Respect each supplied row's mode list.

STRUCTURAL FEATURES (response_class structural), jawline and firmness are carried forward for this immediate workflow: observation_basis=structural_hold, after_impact equal to reference. No new mechanical elasticity or 3D sag inference. Skin type stays fixed. Unsupported reference features use observation_basis=not_comparable and null after_impact. Never infer concealed skin from beard or hair. Ordinary stable supported findings keep their reference impact exactly. A reference measurement held earlier as not comparable must be reviewed against its last supported reference; do not quietly reset it.

CONCISE OUTPUT: before_observation and after_observation each at most 12 words; evidence at most 16 words; parameter anchor_explanation at most 28 words. Use short concrete visual descriptions rather than repeated warnings. No treatment plan, full repeated measurement packet or prose outside JSON.`}
export function baselineContext(state){
 if(state.scoring_execution?.formula_config_version!==FORMULA_VERSION||state.regional_measurements?.version!==MEASUREMENT_VERSION)throw Error('V3.17 comparison requires a V3.17 baseline. Re-run the saved BEFORE images through V3.17 assessment and persist that baseline separately, retaining the old report. Do not rescore the after images as baseline or relabel stored V3.15 values.')
 return {method:VERSION,reference_scan_id:state.scan.scan_id,features:state.regional_measurements.features,parameters:state.regional_measurements.parameters,rubric:{features:FEATURES,parameters:PARAMETERS},prior_comparison:state.comparative_audit?Object.fromEntries(['features','parameters'].map(k=>[k,Object.fromEntries(Object.entries(state.comparative_audit[k]).map(([id,r])=>[id,{hold_reason:r.hold_reason,requires_reference_recheck:r.requires_reference_recheck}]))])):null}
}
export function stableComparison(state){
 if(!state?.regional_measurements)throw Error('Identical-capture comparison requires the saved reference state')
 return Object.fromEntries(['features','parameters'].map(section=>[section,Object.fromEntries(Object.entries(state.regional_measurements[section]).map(([id,b])=>[id,{
  status:b.impact_level===null?'not_comparable':'stable',regions:[],modes:[],before_observation:'Reference capture.',after_observation:'Identical capture file.',observation_basis:b.impact_level===null?'not_comparable':'skin_appearance',evidence:'Identical source capture files; no new image measurement.',...(section==='parameters'?{anchor_explanation:'Identical source captures retain the reference position.'}:{}),after_impact:b.impact_level,confidence:b.impact_level===null?0:1
 }]))]))
}
export function normalizeComparisonRow(input,reference){
 if(!input||typeof input!=='object'||Array.isArray(input))throw Error('Invalid comparison row')
 if(input.status!==undefined&&!['compared','stable','not_comparable'].includes(input.status))throw Error('Invalid advisory comparison status')
 const r={...input}
 const unresolved=['uncertain_skin_or_material','not_comparable'].includes(r.observation_basis)
 // Preserve contradictory null/numeric evidence for validator rejection; do not invent an endpoint.
 r.status=unresolved?'not_comparable':r.after_impact===reference.impact_level?'stable':'compared'
 return r
}
function validateRow(r,id,definition,reference,isParameter){
 const keys=['status','regions','modes','before_observation','after_observation','observation_basis','evidence','after_impact','confidence',...(isParameter?['anchor_explanation']:[])]
 if(!r||Object.keys(r).sort().join()!==keys.sort().join())throw Error(`Invalid comparison keys ${id}`)
 if(!['compared','stable','not_comparable'].includes(r.status)||!BASIS.includes(r.observation_basis)||!Number.isFinite(r.confidence)||r.confidence<0||r.confidence>1||!Array.isArray(r.regions)||!Array.isArray(r.modes))throw Error(`Invalid comparison row ${id}`)
 for(const k of ['before_observation','after_observation','evidence',...(isParameter?['anchor_explanation']:[])])if(typeof r[k]!=='string'||!r[k].trim())throw Error(`Missing paired observation ${id}.${k}`)
 if(r.after_impact!==null&&(!Number.isFinite(r.after_impact)||r.after_impact<0||r.after_impact>5))throw Error(`Invalid after anchor ${id}`)
 if(r.status==='not_comparable'&&r.after_impact!==null)throw Error(`Uncomparable endpoint must be null: ${id}`)
 if(r.status==='stable'&&r.after_impact!==reference.impact_level)throw Error(`Stable endpoint must equal reference: ${id}`)
 if(r.status==='compared'&&(r.after_impact===null||!r.confidence||!r.regions.length||!r.modes.length))throw Error(`Missing paired evidence ${id}`)
 if(r.observation_basis==='surface_material_only'&&(r.status!=='stable'||r.after_impact!==reference.impact_level))throw Error(`External material alone cannot change skin impact ${id}`)
 if(['uncertain_skin_or_material','not_comparable'].includes(r.observation_basis)&&r.status!=='not_comparable')throw Error(`Unresolved evidence must be not_comparable: ${id}`)
 if(r.observation_basis==='structural_hold'&&!(isParameter?['jawline_sagging','skin_firmness_elasticity'].includes(id):definition.response_class==='structural'))throw Error(`Invalid structural hold ${id}`)
 const modes=allowedModes(definition)
 if(r.regions.some(g=>!definition.regions.includes(g))||r.modes.some(m=>!modes.includes(m)))throw Error(`Unsupported comparison region/mode ${id}: ${JSON.stringify({received_regions:r.regions,received_modes:r.modes,allowed_regions:definition.regions,allowed_modes:modes})}`)
}
export function applyComparison(baseline,wire,options){
 const original=baseline.skin_state;baselineContext(original)
 const m=structuredClone(original.regional_measurements),audit={version:VERSION,reference_scan_id:original.scan.scan_id,scope:'immediate_post_session',features:{},parameters:{}}
 for(const section of ['features','parameters']){
  const defs=section==='features'?FEATURES:PARAMETERS
  if(!wire?.[section]||Object.keys(wire[section]).sort().join()!==Object.keys(defs).sort().join())throw Error(`Missing/extra comparison ${section}`)
  for(const[id,def]of Object.entries(defs)){
   const b=m[section][id],inputRow=wire[section][id],proposed=normalizeComparisonRow(inputRow,b);validateRow(proposed,id,def,b,section==='parameters');
   const r={...proposed,delta_impact:proposed.after_impact===null||b.impact_level===null?null:Number((proposed.after_impact-b.impact_level).toFixed(10))}
   let reason=null
   if(section==='features'?def.response_class==='structural':['jawline_sagging','skin_firmness_elasticity'].includes(id))reason='structural_same_session_hold'
   else if(b.state==='unavailable')reason='reference_unavailable'
   else if(original.comparative_audit?.[section]?.[id]?.requires_reference_recheck)reason='reference_recheck_required'
   else if(r.status==='not_comparable')reason='capture_not_comparable'
   else if(section==='parameters'&&r.delta_impact!==0&&!def.features.some(f=>Math.sign(audit.features[f].accepted_delta_impact)===Math.sign(r.delta_impact)))reason='no_matching_component_change'
   if(!reason&&r.delta_impact!==0&&r.status!=='compared')throw Error(`Change requires compared status ${id}`)
   const delta=reason?0:r.delta_impact
   if(b.impact_level!==null&&(b.impact_level+delta < -1e-9||b.impact_level+delta > 5+1e-9))throw Error(`Delta exceeds reference anchor bounds ${id}`)
   audit[section][id]={...r,status_source:'derived_from_endpoint_and_evidence_basis',reported_status:inputRow.status??null,...(section==='parameters'?{unresolved_supporting_features:def.features.filter(f=>audit.features[f].requires_reference_recheck||audit.features[f].hold_reason==='reference_unavailable')}:{}),accepted_delta_impact:delta,hold_reason:reason,requires_reference_recheck:['capture_not_comparable','reference_recheck_required','no_matching_component_change','component_reference_recheck_required'].includes(reason),reference_impact:b.impact_level,after_impact:b.impact_level===null?null:Number((b.impact_level+delta).toFixed(10))}
   if(b.impact_level!==null)b.impact_level=Number((b.impact_level+delta).toFixed(10))
   if(!reason&&r.status==='compared'){
    if(section==='features')b.evidence='Paired observation: '+r.evidence
    else b.rationale='Paired observation: '+r.evidence
   }
   // Original support/localization retained as reference metadata, not a new independent regional observation.
  }
 }
 const run=buildRegionalRun(m,options)
 run.skin_state.comparative_audit=audit
 run.skin_state.scoring_execution={...run.skin_state.scoring_execution,inference_architecture:'paired_comparison_anchored_to_saved_baseline',comparison_version:VERSION,history_excluded_from_image_scoring:false,reference_rescored:false}
 run.skin_state.regional_measurement_provenance={type:'reference_observations_with_paired_impact_updates',reference_scan_id:original.scan.scan_id,localization_and_visibility:'carried_reference_metadata_not_new_after_measurements',comparison_evidence:'comparative_audit'}
 run.evidence_packet.comparative_audit=audit
 run.evidence_packet.model_execution.prompt_version=VERSION
 run.evidence_packet.regional_measurement_provenance=run.skin_state.regional_measurement_provenance
 for(const core of Object.values(run.skin_state.core_features)){
  const a=audit.parameters[core.aggregation_details.source_parameter]
  core.measurement_provenance.comparison_version=VERSION
  core.measurement_provenance.comparison_hold_reason=a.hold_reason
  core.measurement_provenance.reference_scan_id=original.scan.scan_id
 }
 const diagnosis=buildLegacyPostDiagnosisV34({baseline,post_treatment:run});delete diagnosis.v3_4_reassessment
 const keys=Object.keys(diagnosis.reassessment)
 run.skin_state.client_display_state={}
 CLIENT_REPORT_PARAMETER_IDS.forEach((id,i)=>{
  const row=diagnosis.reassessment[keys[i]]
  if(id==='skin_type'){row.score_explanation='Skin type classification is carried forward from the reference assessment.';return}
  const rawBefore=impactToHealth(original.regional_measurements.parameters[id].impact_level),rawAfter=impactToHealth(m.parameters[id].impact_level)
  const before=original.client_display_state?.[id]??Math.round(rawBefore),after=Math.max(before,Math.round(rawAfter)),delta=after-before
  const a=audit.parameters[id]
  run.skin_state.client_display_state[id]=after
  Object.assign(row,{parameter_id:id,before_treatment_score_or_label:before,post_treatment_score_or_label:after,result:delta>0?'improved':'stable',patient_facing_change_points:delta,response_strength:delta===0?'none':delta<7?'mild':delta<13?'moderate':'strong',raw_comparison_result_internal:rawAfter>rawBefore?'improved':rawAfter<rawBefore?'declined':'stable',base_post_score_or_label_internal:rawAfter,comparison_mode:'baseline_anchored_pairwise',score_explanation:delta>0?`Visible appearance improved by ${delta} score points in this comparison.`:'The displayed score remains stable.',transient_reactivity_note:id==='vascularity_redness'&&rawAfter<rawBefore?'Post-treatment redness can be temporary; the clinic should review persistent or worsening redness.':'none'})
  row.measurement_status_internal=a.hold_reason??a.status
  row.raw={scoring_path:VERSION,score_source:'baseline_anchored_pairwise',before_health_score_1_to_100:rawBefore,after_health_score_1_to_100:rawAfter,raw_delta:rawAfter-rawBefore,shown_delta:delta,comparison_status:a.status,hold_reason:a.hold_reason,minimum_detectable_change_points:null,separate_pairwise_veto_used:false}
  row.v3_4_before_health_score_1_to_100=rawBefore
  row.v3_4_after_health_score_1_to_100=rawAfter
  row.v3_4_health_change_1_to_100=rawAfter-rawBefore
  const p=run.skin_state.derived_report_parameters[id]
  p.client_observation=a.hold_reason?'Reference score carried forward for this comparison.':a.evidence
  p.data_quality.comparison_confidence_0_1=a.confidence
  p.data_quality.comparison_hold_reason=a.hold_reason
  p.data_quality.unresolved_comparison_features=a.unresolved_supporting_features??[]
  if(a.unresolved_supporting_features?.length)p.data_quality.is_estimated=true
  p.calibration.comparison_version=VERSION
 })
 diagnosis.metadata={phase:'reassessment',evaluation_type:'post_treatment',display_policy_version:VERSION,comparison_version:VERSION,reference_rescored:false,display_policy:'Client progress scores carry forward the previous displayed score when the measured estimate is lower. Stable can therefore mean carried forward; raw signed measurements remain in the clinical record.',planning_score_source:'post_run.skin_state canonical measurements, never client_display_state'}
 return {post_run:run,post_diagnosis:diagnosis,comparative_audit:audit}
}
