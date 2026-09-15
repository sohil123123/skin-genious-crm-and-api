import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import {fileURLToPath} from 'node:url'
import {spawnSync} from 'node:child_process'
import assert from 'node:assert/strict'
import {CONTRACT,GROUPS,decodeMeasurements,measurementPrompt} from './regionalMeasurementV310.js'
import {metricBoundsV37} from './legacyMeasurementContractV37.js'
import {buildRegionalRun} from './regionalScoringV310.js'
import {cachedMeasurement} from './measurementCacheV310.mjs'
const fixture={regions:Object.fromEntries(Object.keys(GROUPS).map(g=>[g,{visible_fraction:1,obstruction_note:''}])),parameters:Object.fromEntries(Object.entries(CONTRACT).map(([id,c])=>[id,{regions:Object.fromEntries(c.regions.map(g=>[g,{values:c.observed_metrics.map(n=>{const[lo,hi]=metricBoundsV37(id,n);return lo+.35*(hi-lo)}),confidence:.9,estimated:false,landmark_reference:"Synthetic anatomical reference"}])),observation:'Synthetic contract fixture, not patient evidence.'}])),skin_type:{label:'combination',confidence:.9,observation:'Synthetic skin type'}}
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'fv310-selftest-')),file=path.join(temp,'fixture.json'),log=path.join(temp,'calls');fs.writeFileSync(file,JSON.stringify(fixture));fs.writeFileSync(log,'')
const checks=[]
try{
 const options={scanId:'assessment-0-baseline',imageSetHash:'fixture',modelVersion:'mock'}
 const run=x=>buildRegionalRun(decodeMeasurements(x),options).skin_state
 const b=run(fixture),copy=run(fixture)
 assert.deepEqual(b.derived_report_parameters,copy.derived_report_parameters);checks.push('Same primitives produce identical parameter values')
 const c=structuredClone(fixture);for(const p of Object.values(c.parameters))for(const r of Object.values(p.regions))r.confidence=.2
 const uncertain=run(c);for(const[id,p]of Object.entries(b.derived_report_parameters))if(id!=='skin_type')assert.equal(p.display_score_1_to_100,uncertain.derived_report_parameters[id].display_score_1_to_100);checks.push('Confidence does not change score')
 const lightened=structuredClone(fixture);for(const g of ['forehead_left','forehead_center','forehead_right'])for(const n of ['mean_intensity_index','contrast_to_surrounding_skin_index'])lightened.parameters.superficial_pigmentation.regions[g].values[CONTRACT.superficial_pigmentation.observed_metrics.indexOf(n)]-=.1
 assert(run(lightened).derived_report_parameters.superficial_pigmentation.display_score_1_to_100>b.derived_report_parameters.superficial_pigmentation.display_score_1_to_100);checks.push('Fixed-area forehead lightening increases score')
 assert.throws(()=>{const x=structuredClone(fixture);x.parameters.skin_hydration.regions.nose.values[0]=100;decodeMeasurements(x)});checks.push('Metric unit/range validation')
 let calls=0;await Promise.all(Array.from({length:4},()=>cachedMeasurement({directory:path.join(temp,'lock'),identity:{same:1},produce:async()=>{calls++;return fixture}})));assert.equal(calls,1);checks.push('Concurrent requests share one producer')
 const modes=['red','subsurface_polarized','surface_polarized','white','woods_uv'],images=p=>Object.fromEntries(modes.map(m=>[m,{file_id:p+m}]))
 function cli(command,payload){const p=spawnSync(process.execPath,['--import',new URL('./regionalSelfTestMockV310.mjs',import.meta.url).href,fileURLToPath(new URL('./facialV34Runner.mjs',import.meta.url)),command],{input:JSON.stringify(payload),encoding:'utf8',maxBuffer:16*1024*1024,env:{...process.env,OPENAI_API_KEY:'mock-only',FACIAL_V310_SELF_TEST:'1',FACIAL_V310_CACHE_DIR:path.join(temp,'cache'),MOCK_CALL_LOG:log,MOCK_REGIONAL_FIXTURE:file}});if(p.error)throw p.error;const d=JSON.parse(p.stdout);if(!d.ok)throw Error(d.error.message);return d.result}
 const payload={assessment_id:0,scan_id:'assessment-0-baseline',images_by_mode:images('base-'),model:'gpt-5.2'}
 const baseline=cli('assessment',payload);cli('assessment',payload);assert.equal(fs.readFileSync(log,'utf8').trim().split('\n').length,1);checks.push('Actual runner baseline/report path and immutable reuse')
 const reference={skin_state:baseline.skin_state,evidence_packet:baseline.feature_packet,imagesByMode:images('base-')}
 const request={assessment_id:0,baseline_run:reference,post_scan_id:'assessment-0-post',post_images_by_mode:images('post-'),model:'gpt-5.2'}
 const post=cli('reassessment',request);cli('reassessment',request);assert.equal(fs.readFileSync(log,'utf8').trim().split('\n').length,3);assert(post.regional_measurement_changes);checks.push('Actual runner reassessment and comparison cache')
 const older=structuredClone(reference);older.skin_state.scoring_execution.formula_config_version='prior-version'
 const migration=cli('reassessment',{...request,baseline_run:older});assert(migration.reference_rescored);checks.push('Version migration preserves audit reference')
 const compact=cli('project_transport',{result:post,assessment_id:0});assert(JSON.stringify(compact).length<50000);checks.push('Compact response remains below 50KB')
 console.log(JSON.stringify({ok:true,checks,compact_response_bytes:JSON.stringify(compact).length,prompt_chars:measurementPrompt().length,scope:'Synthetic fixtures and mocked OpenAI; no clinical accuracy, fresh-model repeatability or live Laravel validation.'},null,2))
}finally{fs.rmSync(temp,{recursive:true,force:true})}
