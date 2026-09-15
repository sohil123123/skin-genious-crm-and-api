import assert from 'node:assert/strict'
import {validateWorkflowPlanV39 as validate, mergeCourseBlockV39 as merge, STEP_RULES_V39, selectedConcernsV39, planningEvidenceV39} from './workflowV39/workflowContractV39.js'
const checks=[]
const test=(name,fn)=>{fn();checks.push(name)}
const concerns=[{parameter_id:'skin_hydration',is_primary_concern:true}]
// Synthetic structural fixtures, not clinically recommended treatments.
function session(n,time=60) {
 const specs=[['cleanse_and_prepare',2],['lymphatic_drainage',10],['hydrating_mask',15],['q_switch_1064',20],['hydrafacial_cutin_spatula',7],['jet_oxygen_infusion',3],['final_serum_moisturizer_sunscreen',3]]
 if(time===40)specs.splice(3,3,['hydrafacial_cutin_spatula',7],['jet_oxygen_infusion',3])
 else specs[1][1]+=time-60
 return {session_number:n,week:n,day_offset:(n-1)*7,gap_days_from_previous:n===1?0:7,spacing_reason:'Synthetic schedule only',treatment_time:time,step_duration_total:time,timing_validation:{calculated_from_steps:time,matches_treatment_time:true},concerns_addressed:[{parameter_id:'skin_hydration'}],steps:specs.map(([modality_id,duration],i)=>({modality_id,duration,step_number:i+1,infusion_ingredient:'synthetic_fixture'}))}
}
const block=(start,total=7,time=60)=>({treatment_plan:{estimated_sessions:total,treatments:Array.from({length:Math.min(2,total-start+1)},(_,i)=>session(start+i,time))}})
const context=(start,total)=>({next_session_number:start,estimated_sessions:total,reassessed_after_session:start-1,reassessment_scan_id:`post-${start-1}`,previous_day_offset:(start-2)*7})
test('5, 6, 7 and 8-session courses release only the next pair; odd final block has one session',()=>{
 for(const total of [5,6,7,8]) {let saved=block(1,total);assert(validate(saved,'multiple',concerns).valid)
 for(let start=3;start<=total;start+=2){const next=block(start,total);assert(validate(next,'multiple',concerns,context(start,total)).valid);const old=structuredClone(saved.treatment_plan.treatments);saved=merge(saved,next);assert.deepEqual(saved.treatment_plan.treatments.slice(0,old.length),old)}
 assert.equal(saved.treatment_plan.treatments.length,total)}
})
test('Continuation requires the immediately preceding even-session reassessment',()=>{
 assert(!validate(block(3),'multiple',concerns,{}).valid)
 assert(!validate(block(3),'multiple',concerns,{...context(3,7),reassessment_scan_id:null}).valid)
 assert(!validate(block(3),'multiple',concerns,{...context(3,7),reassessed_after_session:1}).valid)
 assert(!validate(block(4),'multiple',concerns,context(4,7)).valid)
})
test('Cannot overwrite a released session or prematurely detail the full course',()=>{
 assert.throws(()=>merge(block(1),block(1)),/already exists/)
 const all=block(1);all.treatment_plan.treatments.push(session(3));assert(!validate(all,'multiple',concerns).valid)
})
test('Package minimum 55 and maximum 65 are accepted; 54 and 66 rejected',()=>{
 for(const t of [55,60,65])assert(validate(block(1,7,t),'multiple',concerns).valid,`${t}`)
 for(const t of [54,66])assert(!validate(block(1,7,t),'multiple',concerns).valid)
})
test('Single remains 65–70; Express exactly 40',()=>{
 const single={treatment_plan:{treatments:[session(1,65)]}}
 assert(validate(single,'single',concerns).valid)
 assert(!validate({treatment_plan:{treatments:[session(1,60)]}},'single',concerns).valid)
 assert(validate({treatment_plan:{treatments:[session(1,40)]}},'express',concerns).valid)
 assert(!validate(single,'express',concerns).valid)
})
test('Misstated totals, stretched fixed steps and missing client primaries fail',()=>{
 const p=block(1);p.treatment_plan.treatments[0].steps[0].duration=3;assert(!validate(p,'multiple',concerns).valid)
 const q=block(1);q.treatment_plan.treatments[0].treatment_time=65;assert(!validate(q,'multiple',concerns).valid)
 assert(!validate(block(1),'multiple',[{parameter_id:'lip_pigmentation',is_primary_concern:true}]).valid)
 assert(validate(block(1),'multiple',[{parameter_id:'lip_pigmentation',is_primary_concern:false}]).valid)
})
test('AI-selected gaps are checked arithmetically without hardcoding a treatment interval',()=>{
 const p=block(1);p.treatment_plan.treatments[1].day_offset=10;p.treatment_plan.treatments[1].gap_days_from_previous=10;p.treatment_plan.treatments[1].week=1+10/7;assert(validate(p,'multiple',concerns).valid)
 p.treatment_plan.treatments[1].gap_days_from_previous=7;assert(!validate(p,'multiple',concerns).valid)
})
test('Clinic fixed step timings and nested concern selection are preserved',()=>{
 for(const [id,n] of [['cleanse_and_prepare',2],['carbon_facial',7],['jet_oxygen_infusion',3],['hydrating_mask',15],['salicylic_spot',2],['final_serum_moisturizer_sunscreen',3]]){assert.equal(STEP_RULES_V39[id].min_minutes,n);assert.equal(STEP_RULES_V39[id].max_minutes,n)}
 assert.deepEqual(selectedConcernsV39({parameters_with_abnormal_scores:concerns}),concerns)
})
test('Measurement confidence is removed from planning inputs while clinical findings remain',()=>{
 const a={skin_state:{core_features:{pore:{score:65,confidence:.9,data_quality:{confidence:.9}}}},diagnosis:{score:65,data_quality:{confidence:.9}}}
 const b=structuredClone(a);b.skin_state.core_features.pore.confidence=.1;b.skin_state.core_features.pore.data_quality.confidence=.1;b.diagnosis.data_quality.confidence=.1
 assert.deepEqual(planningEvidenceV39(a),planningEvidenceV39(b))
 assert.equal(planningEvidenceV39(a).skin_state.core_features.pore.score,65)
 assert.equal(a.skin_state.core_features.pore.confidence,.9)
})
test('Future course outlines cannot hide detailed treatment steps',()=>{
 const p=block(1);p.treatment_plan.course_outline=[{session_number:3,steps:[]}];assert(!validate(p,'multiple',concerns).valid)
})
console.log(JSON.stringify({ok:true,checks,scope:'Offline structural fixtures; no live vision, clinical interval validation or database execution.'},null,2))
