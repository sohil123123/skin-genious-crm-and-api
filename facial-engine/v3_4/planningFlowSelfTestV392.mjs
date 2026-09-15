import assert from 'node:assert/strict'
import fs from 'node:fs'
import {normalizeWorkflowPlanV392,validateWorkflowPlanV39,selectedConcernsV39,planningEvidenceV39,workflowPromptV39} from './workflowV39/workflowContractV39.js'
const page=fs.readFileSync(new URL('../../../frontend/src/pages/IndexPage.vue',import.meta.url),'utf8')
const checks=[];const test=async(n,f)=>{await f();checks.push(n)}
const valid=()=>({treatment_plan:{treatments:[{session_number:1,steps:[['cleanse_and_prepare',2],['lymphatic_drainage',15],['hydrating_mask',15],['q_switch_1064',20],['hydrafacial_cutin_spatula',7],['jet_oxygen_infusion',3],['final_serum_moisturizer_sunscreen',3]].map(([modality_id,duration])=>({modality_id,duration:String(duration)+' mins',infusion_ingredient:'fixture'}))}]},workflow_v39:{}})
await test('Formatting normalization handles strings, wrapper, sum and standalone calendar without changing chosen steps',()=>{
 const p=normalizeWorkflowPlanV392(valid().treatment_plan,'single');assert(validateWorkflowPlanV39(p,'single',[]).valid);assert.equal(p.treatment_plan.treatments[0].treatment_time,65)
 const bad=valid();bad.treatment_plan.treatments[0].steps[0].duration=3;assert(!validateWorkflowPlanV39(normalizeWorkflowPlanV392(bad,'single'),'single',[]).valid)
})
const generation=page.slice(page.indexOf('async function callApiForTreatmentPlan('),page.indexOf('async function callApiForPostDiagnosis('))
await test('An invalid generated plan makes one model call and returns validation errors',async()=>{
 let calls=0
 const make=new Function('Loading','QSpinnerFacebook','assessmentData','selectedConcernsV39','planningEvidenceV39','getOrCreateConversation','submit','getFacialPrompts','workflowPromptV39','encode','api','runResponse','normalizeWorkflowPlanV392','validateWorkflowPlanV39',generation+';return callApiForTreatmentPlan')
 const call=make({show(){},hide(){}},{},{value:{id:49,face_scan_machine:'5_light_modes'}},selectedConcernsV39,planningEvidenceV39,async()=>1,async()=>{},async()=>({SYSTEM_TREATMENT_PLAN_PROMPT:'',constraints:{}}),workflowPromptV39,JSON.stringify,{post:async()=>({data:{success:true,results:{course_context:{}}}})},async()=>{calls++;return {treatment_plan:{treatments:[]}}},normalizeWorkflowPlanV392,validateWorkflowPlanV39)
 const result=await call([],'single');assert.equal(calls,1);assert(result.error.validation_errors.length)
})
const handler=page.slice(page.indexOf('const handleGenerateTreatment ='),page.indexOf('const renumberSteps ='))
function setup({saveFail=false,lostResponse=false}={}) {
 const state={value:{id:49,face_scan_machine:'5_light_modes'}};let generated=0,saved=0,next=0,server={treatment_sessions:{treatments:[]}},errors=[]
 const store={updateAssessment:async(payload)=>{saved++;if(lostResponse||!saveFail)server={treatment_plans:payload.treatment_plans,treatment_sessions:{treatments:[{id:9,session_number:1}]}};if(saveFail){saveFail=false;throw Error('save failed')}return server}}
 let release;const wait=new Promise(r=>release=r)
 const generate=async()=>{generated++;await wait;return normalizeWorkflowPlanV392(valid(),'single')}
 const make=new Function('process','treatmentGenerationBusy','treatment_type','assessmentData','callApiForTreatmentPlan','api','store','Notify','route','goNext', 'let pendingTreatmentDraft=null;'+handler+';return handleGenerateTreatment')
 const call=make({env:{}},{value:false},{value:null},state,generate,{get:async()=>({data:{results:server}})},store,{create:e=>errors.push(e)},{params:{}},()=>next++)
 return {call,release,stats:()=>({generated,saved,next,errors})}
}
await test('Duplicate clicks share one generation; initial plan reaches assessment update once',async()=>{
 const x=setup();const first=x.call([],'single');await x.call([],'single');x.release();await first;assert.deepEqual({...x.stats(),errors:[]},{generated:1,saved:1,next:1,errors:[]})
})
await test('A failed save retries the retained plan, without a second model call',async()=>{
 const x=setup({saveFail:true});x.release();await x.call([],'single');await x.call([],'single');assert.equal(x.stats().generated,1);assert.equal(x.stats().saved,2);assert.equal(x.stats().next,1)
})
await test('A lost successful-save response is recovered by generation id without another write',async()=>{
 const x=setup({saveFail:true,lostResponse:true});x.release();await x.call([],'single');await x.call([],'single');assert.equal(x.stats().generated,1);assert.equal(x.stats().saved,1);assert.equal(x.stats().next,1)
})
console.log(JSON.stringify({ok:true,checks,scope:'Actual frontend functions with mocked network/store; no live database/model execution.'},null,2))
