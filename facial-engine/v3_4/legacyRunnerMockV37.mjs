// Test preload. Replaces fetch completely; never contacts an API.
import assert from 'node:assert/strict'
import {makeWire} from './legacyScoringFixturesV37.mjs'
let count=0
process.env.OPENAI_API_KEY='offline-test-placeholder'
globalThis.fetch=async(url,options)=>{
 count++
 const request=JSON.parse(options.body)
 assert(request.text.format.schema.required.includes('l'))
 const images=request.input.flatMap(x=>x.content).filter(x=>x.type==='input_image')
 assert.equal(images.length,5)
 for(const image of images)assert.equal(image.detail,'high')
 const wire=makeWire()
 if(process.env.V37_TEST_RETRY==='1'&&count===1)wire.l.parameters.jawline_sagging.metrics.legacy_grade_continuous.confidence_0_1=0
 return {ok:true,status:200,json:async()=>({status:'completed',id:'offline-test',output_text:JSON.stringify(wire)})}
}
