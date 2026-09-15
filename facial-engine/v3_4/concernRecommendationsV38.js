import { optimizeZonalTreatmentV2 } from './zonalTreatmentOptimizerV2.js'
import { buildPreSessionPredictionV2 } from './predictedOutcomeEngineV2.js'
import { DERIVED_REPORT_FORMULAS_V2 } from './skinStateScoreMapperV2.js'

/** Preselection suggestions, not a substitute for the client's choices or final eligibility checks. */
export function recommendConcernsV38(skinState, {treatmentMode='single', patientHistory={}, regionalTemperaturesC={}, capabilities={}}={}) {
 const suggestions=[]
 for(const [id,definition] of Object.entries(DERIVED_REPORT_FORMULAS_V2)) {
  const parameter=skinState.derived_report_parameters[id]
  if(!parameter || parameter.internal_burden_score_1_to_100<=1)continue
  const features=Object.keys(definition.components)
  const preview=optimizeZonalTreatmentV2({skinState,treatmentMode,primaryConcerns:features,patientHistory,regionalTemperaturesC,capabilities})
  const prediction=buildPreSessionPredictionV2({baselineSkinState:skinState,optimizerResult:preview})
  const horizon=treatmentMode==='multiple'?'day_28':'immediate_post'
  const outcome=prediction.predicted_report_parameters[id]?.horizons?.[horizon]
  const target=outcome?.predicted_display_score_1_to_100?.expected ?? parameter.display_score_1_to_100
  const gain=Math.max(0,target-parameter.display_score_1_to_100)
 const forecast=Object.fromEntries(Object.entries(prediction.predicted_report_parameters[id]?.horizons??{}).map(([h,x])=>[h,x.predicted_display_score_1_to_100]))
 const fmt=x=>Math.round(x*10)/10
 const later=forecast.day_7?.expected??target
 const potentialText=id==='skin_sebum' ? 'Balance target will be estimated with the selected treatment.'
   : gain>0 && fmt(target)===parameter.display_score_1_to_100 ? 'Small potential improvement (less than 0.1 point).'
   : gain>0 ? `${fmt(target)} estimated immediately after treatment`
   : later>parameter.display_score_1_to_100 ? `${fmt(later)} estimated at day 7; no immediate increase predicted`
   : 'Maintenance expected; no numerical gain predicted by this preview.' 
  const actions=preview.plan?.modality_actions??[]
  const relevant=actions.filter(a=>(a.target_features??[]).some(f=>features.includes(typeof f==='string'?f:f.feature_id)))
  const benefit=relevant.reduce((sum,a)=>sum+Number(a.modality_utility??a.selection_utility??0),0)
  // No observed treatment fit => do not recommend an unrelated concern simply because its score is low.
  if(gain<=0 && benefit<=0)continue
  suggestions.push({parameter_id:id,current_score:parameter.display_score_1_to_100,target_single_session_score:Math.round(Math.max(parameter.display_score_1_to_100,target)),
   potential_target_text:potentialText, forecast_by_horizon:forecast, target_unrounded:target,
   preview_scope:'Independent focused-treatment preview, not simultaneous promised gains',
   expected_improvement_points:gain,expected_benefit_utility:benefit,
   is_primary_concern:false,ranking_basis:'expected_visible_benefit_within_session_contract',
   score_confidence_used_for_ranking:false,confidence_0_1:parameter.data_quality?.confidence_0_1??null,
   target_status:'provisional_treatment_response_estimate_not_guarantee',preview_modality_ids:relevant.map(a=>a.modality_id)})
 }
 suggestions.sort((a,b)=>b.expected_improvement_points-a.expected_improvement_points||b.expected_benefit_utility-a.expected_benefit_utility||a.parameter_id.localeCompare(b.parameter_id))
 return suggestions.map((x,i)=>({...x,is_primary_concern:i<3}))
}
