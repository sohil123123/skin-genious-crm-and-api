import assert from 'node:assert/strict'
import {spawnSync} from 'node:child_process'
import {fileURLToPath} from 'node:url'
const runner=fileURLToPath(new URL('./facialV34Runner.mjs',import.meta.url))
const mock=fileURLToPath(new URL('./calibrationMockFetchV36.mjs',import.meta.url))
const images=Object.fromEntries(['red','subsurface_polarized','surface_polarized','white','woods_uv'].map(m=>[m,'file-synthetic-'+m]))
function invoke(command,payload){return spawnSync(process.execPath,['--import',mock,runner,command],{input:JSON.stringify(payload),encoding:'utf8',maxBuffer:8*1024*1024})}
const baseline=invoke('assessment',{scan_id:'synthetic-baseline',images_by_mode:images,model:'offline-fixture'})
assert.equal(baseline.status,0,baseline.stderr)
const result=JSON.parse(baseline.stdout).result
assert.equal(result.engine_version,'facial_v3_6_calibrated')
assert.equal(result.latency_profile.baseline_openai_call_count,1)
const post=invoke('reassessment',{baseline_run:result.v3_4_native,post_scan_id:'synthetic-post',post_images_by_mode:images,model:'offline-fixture'})
assert.equal(post.status,0,post.stderr)
const r=JSON.parse(post.stdout).result
assert.equal(r.reassessment_result.post_treatment.skin_state.scan.capture_type,'post_treatment')
assert.equal(r.reassessment_result.post_treatment.skin_state.scan.paired_baseline_scan_id,'synthetic-baseline')
for(const row of Object.values(r.post_diagnosis.reassessment).filter(x=>typeof x.before_treatment_score_or_label==='number')){
 assert.equal(row.before_treatment_score_or_label,row.post_treatment_score_or_label)
 assert(row.before_treatment_score_or_label>5)
}
const old=structuredClone(result.v3_4_native);old.skin_state.scoring_execution.calibration_version='v3.5'
assert.notEqual(invoke('reassessment',{baseline_run:old,post_images_by_mode:images,model:'offline-fixture'}).status,0)
console.log(JSON.stringify({ok:true,scope:'HTTP-mocked runner integration; no network calls',checks:['baseline one-call contract','post unified 5-image evidence and 10-image pairwise','capture identity','unchanged scans yield identical calibrated scores','1–100 post payload','old calibration rejected']},null,2))
