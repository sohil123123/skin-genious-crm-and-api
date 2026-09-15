import assert from 'node:assert/strict'
import {readFileSync} from 'node:fs'
import {makeWire,runWire} from './legacyScoringFixturesV37.mjs'
import {optimizeZonalTreatmentV2} from './zonalTreatmentOptimizerV2.js'
import {compileTherapistSessionV2} from './therapistSessionCompilerV2.js'
import {recommendConcernsV38} from './concernRecommendationsV38.js'
import {resolveConcernFeaturesV34} from './legacyFacialAdapterV34.js'
import {getClinicStepDurationRuleV3_4 as timing} from './clinicStepDurationRulesV3_4.js'
import {recommendCourseLengthV3} from './courseLengthRecommendationV3.js'
const a=runWire(makeWire()).skin_state,w=makeWire()
for(const p of Object.values(w.l.parameters)){p.estimation_reason='Synthetic lower confidence';for(const m of Object.values(p.metrics)){m.confidence_0_1=.2;m.basis='supported_estimate'}}
const b=runWire(w).skin_state
const summarize=state=>recommendConcernsV38(state).map(x=>({id:x.parameter_id,target:x.target_single_session_score,primary:x.is_primary_concern,gain:x.expected_improvement_points,utility:x.expected_benefit_utility}))
assert.deepEqual(summarize(a),summarize(b))
const checks=['Changing only score confidence does not change concern ranking, recommended targets or primary flags']
for(const mode of ['single','express','multiple']) {
 const plan=state=>optimizeZonalTreatmentV2({skinState:state,treatmentMode:mode,primaryConcerns:['pore_visibility']})
 const p=plan(a),q=plan(b)
 assert.deepEqual(p.plan.modality_actions,q.plan.modality_actions)
 const c=compileTherapistSessionV2({skinState:a,optimizerResult:p})
 assert(c.release_ready);assert(c.timing_validation.valid)
 assert.equal(c.step_duration_total,c.steps.reduce((s,x)=>s+x.duration_minutes,0))
 assert(mode==='express'?c.step_duration_total===40:mode==='multiple'?c.step_duration_total>=55&&c.step_duration_total<=65:c.step_duration_total>=65&&c.step_duration_total<=70)
 for(const step of c.steps){const rule=timing(step.modality_id);if(rule&&!rule.execution_substeps){assert(step.duration_minutes>=rule.min_minutes,step.modality_id);assert(step.duration_minutes<=rule.max_minutes,step.modality_id)}}
 checks.push(`${mode}: confidence-invariant plan, valid step sum and product duration`)
}
const selected=resolveConcernFeaturesV34([{parameter_id:'lip_pigmentation',is_primary_concern:true},{parameter_id:'skin_hydration',is_primary_concern:false}],a)
assert.deepEqual(selected.primaryConcerns,['lip_pigmentation'])
assert(selected.secondaryConcerns.includes('visual_dehydration'))
assert.deepEqual(resolveConcernFeaturesV34([{parameter_id:'lip_pigmentation',is_primary_concern:false}],a).primaryConcerns,[])
assert.deepEqual(resolveConcernFeaturesV34(['lip_pigmentation'],a).primaryConcerns,['lip_pigmentation'])
checks.push('Explicit client choices and explicit no-primary selection are not replaced')
assert.equal(recommendCourseLengthV3({skinState:a}).recommended_sessions>=5,true)
assert.equal(recommendCourseLengthV3({skinState:a}).recommended_sessions<=8,true)
checks.push('Course recommendation remains within 5–8 sessions; full dated course workflow not covered')
console.log(JSON.stringify({ok:true,checks,scope:'Synthetic software verification. Does not verify the missing production treatment controller or full course calendar.'},null,2))
