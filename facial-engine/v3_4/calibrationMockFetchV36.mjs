// Test-only module. Never imported by the production runner.
import assert from 'node:assert/strict'
import {makeWire} from './calibrationTestFixturesV36.mjs'
import {CORE_FEATURE_IDS} from './skinStateV2.schema.js'
import {PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION} from './pairwiseOutcomeEvidenceV2.js'
process.env.OPENAI_API_KEY='offline-test-placeholder'
globalThis.fetch=async (url,request)=>{
 assert.equal(url,'https://api.openai.com/v1/responses')
 const body=JSON.parse(request.body),user=body.input[1].content
 const images=user.filter(p=>p.type==='input_image')
 let output
 if(body.text.format.name==='facial_v36_calibrated_evidence'){
   assert.equal(images.length,5)
   assert(body.input[0].content[0].text.includes('One trace or equivocal inflammatory spot'))
   output=makeWire(2.35)
 }else{
   assert.equal(images.length,10,'pairwise must receive 5 baseline plus 5 post images')
   output={pairwise_outcome_version:PAIRWISE_OUTCOME_EVIDENCE_V2_VERSION,comparison_meta:{pair_quality:'good'},feature_comparisons:Object.fromEntries(CORE_FEATURE_IDS.map(id=>[id,{global_change_direction:'stable'}]))}
 }
 return {ok:true,status:200,json:async()=>({status:'completed',output_text:JSON.stringify(output)})}
}
