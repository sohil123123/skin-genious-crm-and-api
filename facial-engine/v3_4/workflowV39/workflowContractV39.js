import { CLINIC_STEP_DURATION_RULES_V3_4 } from './clinicStepDurationRulesV3_4.js'
import { OPERATIONAL_DURATIONS_V39 } from './operationalDurationsV39.js'
export const SESSION_WINDOWS_V39 = Object.freeze({single:[60,75],express:[35,45],multiple:[60,75]})
export const STEP_RULES_V39 = Object.freeze({...OPERATIONAL_DURATIONS_V39,...CLINIC_STEP_DURATION_RULES_V3_4,
 carbon_lotion_application_and_dry:{min_minutes:3,max_minutes:3,source:'clinic_v3.4_carbon_substep'},
 carbon_qswitch_laser:{min_minutes:4,max_minutes:4,source:'clinic_v3.4_carbon_substep'},
})
export function selectedConcernsV39(value) {
 let current=value
 for(let i=0;i<3&&!Array.isArray(current)&&current;i++)current=current.parameters_with_abnormal_scores
 if(!Array.isArray(current))throw new Error('The selected concern list is missing. Please return to concern selection.')
 return current.map(x=>({...x}))
}
export function workflowPromptV39() {
 return `
CURRENT PRODUCT / OUTPUT CONTRACT V3.9 — overrides older duration and score-scale wording in the treatment prompt:
Keep the existing treatment_plan.treatments array, titles, spoken scripts, preparations, concern fields, week schedule and staff step flow. A package has an estimated total of 5–8 sessions, but only the current TWO-session block is detailed. Single = 1 session of 60–75 minutes. Express = 1 session of 35–45 minutes. Multiple = estimated total of 5–8 sessions, EACH 60–75 minutes. Initially provide detailed sessions 1–2 only. After reassessment at the END of session 2, detail 3–4 using that new measured state, then repeat after 4 and 6. If only one session remains, detail only that session. Return treatment_plan.estimated_sessions and a course_outline with non-detailed future goals, never future steps. Gaps between released sessions are AI-selected using the supplied clinical constraints; initial session starts today. For continuation obey course_context.next_session_number, estimated_sessions and previous_day_offset; never regenerate completed sessions. Future sessions are planned from present evidence and must be adjusted to subsequent reassessment; do not pretend future scores are measured.
PRIORITY: maximize expected clinically appropriate visible benefit today for single/express, and across the course for multiple, guided by the client's submitted is_primary_concern values. These selections are authoritative; do not choose substitute primaries, even if all are false. Scoring confidence is backend uncertainty information, not a multiplier or ranking objective. Treatment evidence, contraindications, expected response, regional findings and client goals still matter.
SCORING: diagnosis health scores are 1–100, higher is better for ALL numeric parameters. Do not regenerate, rescale, invert, round to five bins or multiply by 20. Original 1–5 criteria are backend audit anchors only. Any old instruction using deviation>=1 or >=2 means one/two original clinical grades, NOT a one/two-point difference on the new display. Use the supplied legacy grade/index and original clinical descriptions for those old clinical gates. When no final legacy grade exists, use the actual original metric definitions; do not invent new index cutoffs. Do not infer low severity from low confidence. Treatment targets are provisional expected improvements, not guaranteed scores, and sebum health means balance, not ever-lower oil production.
Use the supplied legacy_measurements and regional evidence; never invent OpenCV values, simulated panels or a new diagnosis. Use the complete patient history/temperatures/constraints as in the existing flow. Supported appearance estimates are distinct from measured physiology.
TIMING: every step MUST include modality_id from the catalogue below, numeric duration, and consecutive step_number from 1. Preserve existing output fields. The actual sum MUST equal treatment_time, step_duration_total and timing_validation.calculated_from_steps. Select clinically useful steps to fit the time; never stretch a fixed step, shorten necessary contact/exposure, add redundant correction, or claim a longer total than the steps.
Each infusion step represents exactly ONE selected ingredient and takes 3 minutes; include its name as a nonempty infusion_ingredient string; multiple ingredients require separately timed steps. Peel-off masks take 15 minutes including application, drying and removal. Carbon can be one 7-minute carbon_facial step or the two named carbon substeps in the catalogue (3 then 4 minutes); never duplicate the full block and substeps. Begin with cleanse_and_prepare (2 minutes) and finish with ONE final_serum_moisturizer_sunscreen step (3 minutes). Retain mandatory lymphatic_drainage within its actual allowed range.
COURSE SCHEDULE: add integer day_offset and gap_days_from_previous to every session. Session 1 has both 0; continuation sessions use course_context.previous_day_offset for the first new gap. Later offsets increase and each gap equals the difference from the previous offset. Retain numeric week = 1 + day_offset/7 for compatibility and provide spacing_reason for each later session. Gaps are AI-selected clinical recommendations from the supplied constraints, not a fixed weekly default or verified appointments. Do not invent a minimum interval not present in the supplied source as if it were a validated rule.
CONCERNS: each concerns_addressed entry must retain concern/current_value/target_value and add parameter_id matching the supplied concerns/diagnosis. Address every selected primary in the session (single/express) or across the released package block, with an explicit staging/contraindication explanation for any primary deferred to a later block. If a selected primary cannot safely be treated, include it in treatment_plan.primary_concern_exceptions with parameter_id and a specific reason grounded in the supplied contraindication/context. Do not silently replace it.
CATALOGUE: ${JSON.stringify(STEP_RULES_V39)}
Return only the existing JSON treatment_plan object, with the additional fields specified. If a valid clinically appropriate plan cannot meet the product contract, return {"error":{"message":"brief explanation"}} instead of inventing timings or ignoring contraindications.
`
}
export function validateWorkflowPlanV39(output,mode,selected=[],context={}) {
 const errors=[],sessions=output?.treatment_plan?.treatments,window=SESSION_WINDOWS_V39[mode]
 if(!window)return {valid:false,errors:['Unknown product type.']}
 if(!Array.isArray(sessions)||!sessions.length)return {valid:false,errors:['No treatment sessions returned.']}
 const outline=output.treatment_plan.course_outline
 const hasSteps=x=>x&&typeof x==='object'&&(Object.hasOwn(x,'steps')||Object.values(x).some(hasSteps))
 if(hasSteps(outline)||hasSteps(output.recommended_full_plan))errors.push('Future course outlines must not contain detailed steps.')
 const start=context.next_session_number??1
 const total=mode==='multiple'?output.treatment_plan.estimated_sessions:1
 if(mode==='multiple'&&(!Number.isInteger(total)||total<5||total>8))errors.push('Course estimate must be 5–8 sessions.')
 if(context.estimated_sessions!=null&&total!==context.estimated_sessions)errors.push('A continuation must preserve the current estimated course length.')
 if(mode==='multiple'&&start>1&&(start%2!==1||context.reassessed_after_session!==start-1||!context.reassessment_scan_id))errors.push('Continuation requires reassessment at the end of the preceding two-session block.')
 const count=mode==='multiple'?Math.min(2,total-start+1):1
 if(count<1||sessions.length!==count)errors.push('Return only the current released block, including a one-session final block when needed.')
 let previousDay=context.previous_day_offset??0
 const addressed=new Set()
 for(const [index,session] of sessions.entries()) {
  const number=start+index
  const path=`Session ${number}`
  if(session.session_number!==number)errors.push(`${path}: invalid session number.`)
  const steps=session.steps
  if(!Array.isArray(steps)||!steps.length){errors.push(`${path}: missing steps.`);continue}
  let sum=0
  for(const [i,s] of steps.entries()){
   const rule=STEP_RULES_V39[s.modality_id]
   if(!rule)errors.push(`${path}, step ${i+1}: unknown modality_id.`)
   if(s.step_number!==i+1)errors.push(`${path}, step ${i+1}: invalid ordering.`)
   if(typeof s.duration!=='number'||!Number.isFinite(s.duration)||s.duration<=0){errors.push(`${path}, step ${i+1}: duration must be a positive number.`);continue}
   if(rule?.minutes_per_ingredient && (typeof s.infusion_ingredient !== 'string' || !s.infusion_ingredient.trim()))errors.push(`${path}, step ${i+1}: name the single infusion ingredient.`)
   sum+=s.duration
   if(rule&&(s.duration<rule.min_minutes||s.duration>rule.max_minutes))errors.push(`${path}, ${s.modality_id}: duration outside ${rule.min_minutes}–${rule.max_minutes} minutes.`)
  }
  const ids=steps.map(s=>s.modality_id)
  if(ids[0]!=='cleanse_and_prepare'||ids.at(-1)!=='final_serum_moisturizer_sunscreen'||ids.filter(x=>x==='final_serum_moisturizer_sunscreen').length!==1)errors.push(`${path}: required opening/final step missing or duplicated.`)
  if(!ids.includes('lymphatic_drainage'))errors.push(`${path}: mandatory lymphatic drainage missing.`)
  const carbon=ids.includes('carbon_lotion_application_and_dry')||ids.includes('carbon_qswitch_laser')
  if(carbon&&(ids.includes('carbon_facial')||ids.filter(x=>x==='carbon_lotion_application_and_dry').length!==1||ids.filter(x=>x==='carbon_qswitch_laser').length!==1||ids.indexOf('carbon_lotion_application_and_dry')>ids.indexOf('carbon_qswitch_laser')))errors.push(`${path}: invalid/duplicated carbon sequence.`)
  if(sum<window[0]||sum>window[1])errors.push(`${path}: ${sum} minutes does not meet ${window[0]}–${window[1]}.`)
  if(session.treatment_time!==sum||session.step_duration_total!==sum||session.timing_validation?.calculated_from_steps!==sum||session.timing_validation?.matches_treatment_time!==true)errors.push(`${path}: declared duration does not equal actual step sum.`)
  if(!Number.isInteger(session.day_offset)||!Number.isInteger(session.gap_days_from_previous)||(number===1&&(session.day_offset!==0||session.gap_days_from_previous!==0))||(number>1&&(session.day_offset<=previousDay||session.gap_days_from_previous!==session.day_offset-previousDay||typeof session.spacing_reason!=='string'||!session.spacing_reason.trim())))errors.push(`${path}: inconsistent course schedule.`)
  if(typeof session.week!=='number'||Math.abs(session.week-(1+session.day_offset/7))>1e-6)errors.push(`${path}: week does not match day offset.`)
  previousDay=session.day_offset
  for(const c of session.concerns_addressed??[])if(c.parameter_id)addressed.add(c.parameter_id)
 }
 const exceptions=output.treatment_plan.primary_concern_exceptions??[]
 for(const c of selected.filter(x=>x.is_primary_concern===true))if(c.parameter_id&&!addressed.has(c.parameter_id)&&!exceptions.some(x=>x.parameter_id===c.parameter_id&&typeof x.reason==='string'&&x.reason.trim()))errors.push(`Selected primary ${c.parameter_id} was omitted without an explanation.`)
 return {valid:errors.length===0,errors,scope:'Structural/timing/selection/schedule-arithmetic validation; clinical eligibility and interval appropriateness remain governed by the existing supplied planning constraints.'}
}

export function mergeCourseBlockV39(existing,incoming) {
 const out=structuredClone(existing)
 const sessions=out.treatment_plan?.treatments
 if(!Array.isArray(sessions))throw new Error('Existing released course is missing.')
 for(const next of incoming.treatment_plan.treatments) {
  const prior=sessions.find(x=>x.session_number===next.session_number)
  if(prior)throw new Error(`Session ${next.session_number} already exists; do not regenerate or overwrite it.`)
  sessions.push(structuredClone(next))
 }
 sessions.sort((a,b)=>a.session_number-b.session_number)
 out.treatment_plan.estimated_sessions=incoming.treatment_plan.estimated_sessions
 out.treatment_plan.course_outline=incoming.treatment_plan.course_outline??out.treatment_plan.course_outline
 return out
}

// Measurement uncertainty stays in saved assessment data. It is not supplied as
// an optimization signal to the existing LLM planning conversation.
export function planningEvidenceV39(value) {
 if (Array.isArray(value)) return value.map(planningEvidenceV39)
 if (!value || typeof value !== 'object') return value
 return Object.fromEntries(Object.entries(value)
  .filter(([key]) => !/(confidence|reliability|uncertainty)/i.test(key) && !['data_quality','treatment_evidence_v37','model_execution','v3_4_native','v3_4_reassessment'].includes(key))
  .map(([key,item]) => [key,planningEvidenceV39(item)]))
}

export function normalizeWorkflowPlanV392(value, mode) {
 const plan = structuredClone(value?.treatment_plan ? value : Array.isArray(value?.treatments) ? {treatment_plan:value} : value)
 if (!plan?.treatment_plan?.treatments) return plan
 const number=x=>typeof x==='string'&&/^\d+(?:\.\d+)?(?:\s*(?:mins?|minutes?))?$/i.test(x.trim()) ? Number.parseFloat(x) : x
 if(plan.treatment_plan.estimated_sessions!=null)plan.treatment_plan.estimated_sessions=number(plan.treatment_plan.estimated_sessions)
 for(const session of plan.treatment_plan.treatments) {
  for(const key of ['session_number','week','day_offset','gap_days_from_previous'])if(session[key]!=null)session[key]=number(session[key])
  if(mode!=='multiple') {
   session.session_number??=1;session.day_offset??=0;session.gap_days_from_previous??=0;session.week??=1
  }
  if(!Array.isArray(session.steps))continue
  session.steps.forEach((step,i)=>{step.duration=number(step.duration);step.step_number=i+1})
  if(session.steps.every(x=>typeof x.duration==='number'&&Number.isFinite(x.duration))) {
   const total=session.steps.reduce((n,x)=>n+x.duration,0)
   session.treatment_time=total;session.step_duration_total=total
   session.timing_validation={...session.timing_validation,calculated_from_steps:total,matches_treatment_time:true}
  }
 }
 return plan
}
