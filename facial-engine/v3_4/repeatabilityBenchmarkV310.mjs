// Offline acceptance tool: pass >=3 independently obtained raw v3.10 model
// response JSON files for the SAME five images. Cached copies are not evidence.
import fs from 'node:fs'
import {decodeMeasurements} from './regionalMeasurementV310.js'
import {buildRegionalRun} from './regionalScoringV310.js'
const paths=process.argv.slice(2)
if(paths.length<3){console.error('Usage: node repeatabilityBenchmarkV310.mjs fresh-run1.json fresh-run2.json fresh-run3.json [...]');process.exit(2)}
const runs=paths.map(p=>buildRegionalRun(decodeMeasurements(JSON.parse(fs.readFileSync(p))),{scanId:'benchmark',imageSetHash:'caller-must-verify-same-images',modelVersion:'caller-must-verify-same-model'}).skin_state.derived_report_parameters)
const parameters=Object.fromEntries(Object.keys(runs[0]).filter(id=>id!=='skin_type').map(id=>{const scores=runs.map(r=>r[id].display_score_1_to_100),range=Math.max(...scores)-Math.min(...scores);return[id,{scores,range,within_two_points:range<=2}]}))
const pass=Object.values(parameters).every(p=>p.within_two_points)
console.log(JSON.stringify({pass,scope:'Fresh model outputs supplied by operator; caller must verify independent inference, image identity and settings. Does not validate population accuracy.',parameters},null,2));process.exitCode=pass?0:1
