import assert from 'node:assert/strict'
import {readFileSync} from 'node:fs'
import {spawnSync} from 'node:child_process'
import {compactResultV391,compactPlanningContextV391} from './payloadTransportV391.js'
import {makeWire,runWire} from './legacyScoringFixturesV37.mjs'
import {buildSkinAnalysisReportV3} from './buildSkinAnalysisReportV3.js'
import {buildLegacyDiagnosisV34} from './legacyFacialAdapterV34.js'
const checks=[]
const test=async(name,fn)=>{await fn();checks.push(name)}
let original
if(process.argv[2])original=JSON.parse(readFileSync(process.argv[2],'utf8'))
else {
 const run=runWire(makeWire())
 const report=buildSkinAnalysisReportV3({skinState:run.skin_state,client:{},clinic:{}})
 original={command:'assessment',engine_version:'facial_v3_9_existing_workflow',feature_packet:run.evidence_packet,skin_state:run.skin_state,diagnosis:buildLegacyDiagnosisV34({skinAnalysisReport:report,skinState:run.skin_state}),skin_analysis_report:report,v3_4_native:run}
}
const before=JSON.stringify(original),projected=compactResultV391(original,49)
await test('Projection retains every client score, label and displayed field verbatim',()=>{
 for(const [key,card] of Object.entries(original.diagnosis.diagnosis_report)){
  const expected={...card};delete expected.data_quality;delete expected.calibration_audit
  assert.deepEqual(projected.diagnosis.diagnosis_report[key],expected)
 }
 assert.equal(JSON.stringify(original),before)
})
await test('Browser gets a small server reference; audit data remains in the unchanged server result',()=>{
 assert.equal(projected.feature_packet.evidence_storage,'server')
 assert(!projected.skin_state&&!projected.v3_4_native&&!projected.skin_analysis_report)
 assert(!projected.diagnosis.v3_4_report&&!projected.diagnosis.v3_4_skin_state)
 assert(original.feature_packet.features&&original.skin_state.core_features)
 assert(Buffer.byteLength(JSON.stringify(projected))<Buffer.byteLength(before)*.1)
})
await test('Primary selections, targets, score precision and calibration identity stay unchanged',()=>{
 const actual=projected.diagnosis.treatable_concerns_summary.parameters_with_abnormal_scores
 const expected=original.diagnosis.treatable_concerns_summary.parameters_with_abnormal_scores.map(c=>{const x={...c};delete x.treatment_data_quality;return x})
 assert.deepEqual(actual,expected)
 assert.equal(projected.feature_packet.calibration_version,original.skin_state.scoring_execution.calibration_version)
})
await test('Planning removes duplicate containers but preserves all feature/zone evidence and state',()=>{
 const input={feature_packet:original.feature_packet,skin_state:original.skin_state,diagnosis:original.diagnosis,course_context:{next_session_number:1},released_course:{}}
 const out=compactPlanningContextV391(input)
 assert.deepEqual(out.feature_packet.features,original.feature_packet.features)
 assert.deepEqual(out.feature_packet.morphology_exclusion_map,original.feature_packet.morphology_exclusion_map)
 assert.deepEqual(out.skin_state,original.skin_state)
 assert(!out.diagnosis.v3_4_report&&!out.diagnosis.v3_4_skin_state)
 assert.equal(JSON.stringify(original),before)
})
await test('Post transport retains reassessment comparisons and session reference metadata',()=>{
 const post={command:'reassessment',engine_version:original.engine_version,post_run:{skin_state:original.skin_state,evidence_packet:original.feature_packet},post_diagnosis:{metadata:{reference_source:'session_1'},reassessment:{x:{before_treatment_score_or_label:61,post_treatment_score_or_label:62}},v3_4_reassessment:{large:true}}}
 const out=compactResultV391(post,49)
 assert.deepEqual(out.post_diagnosis.reassessment,post.post_diagnosis.reassessment)
 assert.deepEqual(out.post_diagnosis.metadata,post.post_diagnosis.metadata)
 assert(!out.post_diagnosis.v3_4_reassessment)
})
await test('Transport CLI works without an API key and returns the same projection',()=>{
 const cli=spawnSync(process.execPath,[new URL('./facialV34Runner.mjs',import.meta.url).pathname,'project_transport'],{input:JSON.stringify({result:original,assessment_id:49}),encoding:'utf8',env:{...process.env,OPENAI_API_KEY:''},maxBuffer:8*1024*1024})
 assert.equal(cli.status,0,cli.stderr);assert.deepEqual(JSON.parse(cli.stdout).result,JSON.parse(JSON.stringify(projected)))
})
// Execute actual supplied frontend functions with network/store stubs.
const page=readFileSync(new URL('../../../frontend/src/pages/IndexPage.vue',import.meta.url),'utf8')
const store=readFileSync(new URL('../../../frontend/src/stores/assessmentStore.js',import.meta.url),'utf8')
const source=page.slice(page.indexOf('async function callApiForDiagnosis('),page.indexOf('async function callApiForTreatmentPlan('))
const client=(api)=>{
 const data={id:49,face_scan_machine:'5_light_modes'}
 const call=new Function('api','assessmentData','processingMessage','uploadImageFileToOpenAI',source+'; return callApiForDiagnosis;')(api,{value:data},{value:''},async()=>{})
 return {data,call}
}
const response={data:{success:true,results:projected}}
await test('Cache recovery displays completed scores without a second inference/scoring call',async()=>{
 let posts=0;const {data,call}=client({get:async()=>response,post:async()=>{posts++;throw Error('must not run')}})
 assert.deepEqual(await call(data,[]),projected.diagnosis);assert.equal(posts,0)
 assert.deepEqual(data.feature_packet,projected.feature_packet)
})
await test('Missing/stale cache runs one assessment; a real cache failure is not retried as vision',async()=>{
 for(const status of [404,409,500]){let posts=0;const {data,call}=client({get:async()=>{throw {response:{status},message:'server failed'}},post:async()=>{posts++;return response}})
 const result=await call(data,[]);assert.equal(posts,status===500?0:1);if(status===500)assert(result.error);else assert(result.diagnosis_report)}
})
await test('New uploaded images bypass cache and call the combined assessment once',async()=>{
 let posts=0;const {data,call}=client({get:async()=>{throw Error('must not read cache')},post:async()=>{posts++;return response}})
 assert.deepEqual(await call(data,[{}]),projected.diagnosis);assert.equal(posts,1)
})
const method=store.slice(store.indexOf('    async updateAssessment('),store.indexOf('    setPatientData('))
await test('Structured save sends JSON and never invokes multipart serialization',async()=>{
 let sent;const api={put:async(...args)=>{sent=args;return {data:{success:true,results:{images:[]}}}}}
 const obj=new Function('api','serialize','return ({'+method+'})')(api,()=>{throw Error('multipart called')})
 obj.assessmentData={id:49,user_id:1}
 await obj.updateAssessment({diagnosis:projected.diagnosis,feature_packet:projected.feature_packet})
 assert.equal(sent[2].headers['Content-Type'],'application/json')
 assert.equal(sent[1]._method,undefined)
 assert.deepEqual(sent[1].diagnosis,projected.diagnosis)
})
await test('JSON database save failures propagate rather than reporting success',async()=>{
 const obj=new Function('api','serialize','return ({'+method+'})')({put:async()=>{throw Error('database failure')}},()=>{})
 obj.assessmentData={id:49};await assert.rejects(()=>obj.updateAssessment({diagnosis:projected.diagnosis}),/database failure/)
})
console.log(JSON.stringify({ok:true,checks,compact_original_bytes:Buffer.byteLength(before),compact_response_bytes:Buffer.byteLength(JSON.stringify(projected)),scope:'Actual transport projections and mocked frontend network/save flows. No live Laravel/database/model execution.'},null,2))
