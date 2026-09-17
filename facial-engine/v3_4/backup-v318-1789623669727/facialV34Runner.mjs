#!/usr/bin/env node
import {VERSION as COMPARISON_VERSION_V316,baselineContext as baselineContextV316,comparisonPrompt as comparisonPromptV316,comparisonFormat as comparisonFormatV316,stableComparison as stableComparisonV316,applyComparison as applyComparisonV316} from './comparativeReassessmentV316.js'
import { compactResultV391, compactPlanningContextV391 } from './payloadTransportV391.js'
import { validateWorkflowPlanV39, mergeCourseBlockV39 } from './workflowV39/workflowContractV39.js'
import { CALIBRATION_VERSION_V37 as CALIBRATION_VERSION_V36 } from './legacyScoringV37.js'
import { COMPACT_PAIRWISE_VERSION, COMPACT_PAIRWISE_PROMPT, compactPairwiseFormat, compactPairwiseInput, decodeCompactPairwise, runConcurrentReassessment } from './compactPairwiseV393.js'
import {MEASUREMENT_VERSION,FORMULA_VERSION,DEVICE_PROFILE,measurementFormat,measurementPrompt,decodeMeasurements} from './regionalMeasurementV310.js'
import {buildRegionalRun} from './regionalScoringV310.js'
import {regionalMeasurementChanges} from './regionalReassessmentV310.js'
import {cachedMeasurement} from './measurementCacheV310.mjs'
import process from 'node:process'
import { createHash } from 'node:crypto'
import {
  buildSkinStateV2,
} from './skinStateScoreMapperV2.js'
import {
  mergeVisionEvidencePacketsV2,
} from './mergeVisionEvidenceV2.js'
import {
  buildSkinAnalysisReportV3,
} from './buildSkinAnalysisReportV3.js'
import {
  optimizeZonalTreatmentV2,
} from './zonalTreatmentOptimizerV2.js'
import {
  createMultiSessionPlanV2,
} from './multiSessionPlanOrchestratorV2.js'
import {
  compileTherapistSessionV2,
} from './therapistSessionCompilerV2.js'
import {
  buildTreatmentPlanReportV3,
} from './buildTreatmentPlanReportV3.js'
import {
  runPostTreatmentReassessmentV2,
} from './postTreatmentReassessmentV2.js'
import {
  buildLegacyDiagnosisV34,
  resolveConcernFeaturesV34,
  compiledSessionToLegacyTreatmentV34,
  buildLegacyTreatmentPlanV34,
  buildLegacyPostDiagnosisV34,
} from './legacyFacialAdapterV34.js'
import {
  buildUnifiedVisionPromptV35,
  unifiedStructuredOutputFormatV35,
  decodeUnifiedVisionOutputV35,
  UNIFIED_VISION_WIRE_VERSION,
  UNIFIED_VISION_PROMPT_VERSION,
} from './unifiedVisionAdapterV35.js'
// Retained only for post-treatment reassessment compatibility in this staging
// patch. Baseline assessment no longer uses the multi-call V3.4.8 path.
import {
  buildCompactVisionPromptV34_7,
  compactModelInputV34_1,
  decodeCompactVisionOutputV34_7,
  compactStructuredOutputFormatV34_7,
  compactSpecialistShardPlanV34_7,
  mergeCompactSpecialistShardsV34_7,
} from './compactVisionAdapterV34_1.js'

const CANONICAL_MODES = [
  'red',
  'subsurface_polarized',
  'surface_polarized',
  'white',
  'woods_uv',
]
const LEGACY_COMPACT_MODULES = new Set(['morphology', 'surface', 'inflammation', 'pigment', 'structure'])

function logV35(event, data = {}) {
  process.stderr.write(`${JSON.stringify({
    event,
    ts: new Date().toISOString(),
    ...data,
  })}\n`)
}

function envInt(name, fallback) {
  const raw = process.env[name]
  if (raw === undefined || raw === null || String(raw).trim() === '') return fallback
  const value = Number(raw)
  return Number.isFinite(value) ? Math.trunc(value) : fallback
}

function readStdin() {
  if (process.stdin.isTTY) return Promise.resolve({})
  return new Promise((resolve, reject) => {
    let data = ''
    process.stdin.setEncoding('utf8')
    process.stdin.on('data', (chunk) => { data += chunk })
    process.stdin.on('end', () => {
      try { resolve(data.trim() ? JSON.parse(data) : {}) }
      catch (error) { reject(new Error(`Invalid JSON on stdin: ${error.message}`)) }
    })
    process.stdin.on('error', reject)
  })
}

function stripJsonFence(value) {
  return String(value ?? '').trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim()
}

function collectResponseText(response) {
  if (typeof response?.output_text === 'string' && response.output_text.trim()) return response.output_text.trim()
  const chunks = []
  const refusals = []
  for (const item of Array.isArray(response?.output) ? response.output : []) {
    for (const part of Array.isArray(item?.content) ? item.content : []) {
      if (part?.type === 'refusal' && typeof part.refusal === 'string') refusals.push(part.refusal)
      else if (typeof part?.text === 'string' && part.text.trim()) chunks.push(part.text.trim())
    }
  }
  if (!chunks.length && refusals.length) throw new Error(`OpenAI refused the request: ${refusals.join(' ')}`)
  if (!chunks.length) throw new Error('OpenAI response contained no output text.')
  return [...new Set(chunks)].join('\n').trim()
}

function parseModelJson(response) {
  const text = stripJsonFence(collectResponseText(response))
  try { return JSON.parse(text) }
  catch (error) { throw new Error(`OpenAI returned non-JSON output: ${error.message}; preview=${text.slice(0, 300)}`) }
}

function imageFileId(value) {
  if (typeof value === 'string') return value
  if (value && typeof value === 'object') return value.file_id ?? value.fileId ?? value.openai_file_id ?? null
  return null
}

function appendModeImages(content, imagesByMode, prefix = '') {
  for (const mode of CANONICAL_MODES) {
    const fileId = imageFileId(imagesByMode?.[mode])
    if (!fileId) throw new Error(`Missing OpenAI file_id for ${prefix}${mode}`)
    content.push({ type: 'input_text', text: `${prefix ? `${prefix} ` : ''}authoritative image mode: ${mode}` })
    content.push({
      type: 'input_image',
      file_id: fileId,
      detail: String(process.env.FACIAL_V35_IMAGE_DETAIL ?? process.env.FACIAL_V34_IMAGE_DETAIL ?? 'high'),
    })
  }
}

function buildVisionContent(images, input) {
  const content = [{ type: 'input_text', text: `Backend structured input:\n${JSON.stringify(input)}` }]
  if (images?.baseline || images?.post_treatment) {
    appendModeImages(content, images.baseline, 'baseline')
    appendModeImages(content, images.post_treatment, 'post_treatment')
  } else appendModeImages(content, images)
  return content
}

async function openAiResponses(payload, { moduleId = 'unknown', timeoutMsOverride = null } = {}) {
  const apiKey = process.env.OPENAI_API_KEY
  if (!apiKey) throw new Error('OPENAI_API_KEY is not available to the facial runner.')

  const timeoutMs = Math.max(1000, timeoutMsOverride ?? envInt(
    'FACIAL_V35_OPENAI_TIMEOUT_MS',
    envInt('FACIAL_V34_OPENAI_TIMEOUT_MS', 165000),
  ))
  const maxRetries = Math.max(0, envInt(
    'FACIAL_V35_OPENAI_MAX_RETRIES',
    envInt('FACIAL_V34_OPENAI_MAX_RETRIES', 0),
  ))
  const maxAttempts = 1 + maxRetries
  let lastError = null

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const controller = new AbortController()
    const startedAt = Date.now()
    const timeoutId = setTimeout(
      () => controller.abort(new Error(`OpenAI [${moduleId}] request timed out after ${timeoutMs}ms`)),
      timeoutMs,
    )
    logV35('facial_v35_vision_start', {
      module_id: moduleId,
      attempt,
      timeout_ms: timeoutMs,
      model: payload?.model ?? null,
      max_output_tokens: payload?.max_output_tokens ?? null,
    })

    try {
      const response = await fetch('https://api.openai.com/v1/responses', {
        method: 'POST',
        headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        signal: controller.signal,
      })
      clearTimeout(timeoutId)
      const json = await response.json().catch(() => ({}))
      const elapsedMs = Date.now() - startedAt
      if (!response.ok) {
        const errorMsg = `OpenAI Responses API failed (${response.status}): ${json?.error?.message ?? JSON.stringify(json).slice(0, 500)}`
        logV35('facial_v35_vision_http_error', { module_id: moduleId, attempt, elapsed_ms: elapsedMs, status: response.status })
        if ((response.status === 429 || response.status >= 500) && attempt < maxAttempts) {
          lastError = new Error(errorMsg)
          await new Promise((resolve) => setTimeout(resolve, attempt * 1000))
          continue
        }
        throw new Error(errorMsg)
      }
      if (json.status === 'incomplete') throw new Error(`OpenAI response incomplete: ${json?.incomplete_details?.reason ?? 'unknown_reason'}; module=${moduleId}; limit=${payload.max_output_tokens}; output_tokens=${json?.usage?.output_tokens ?? 'unknown'}; reasoning_tokens=${json?.usage?.output_tokens_details?.reasoning_tokens ?? 'unknown'}`)
      if (json.status === 'failed' || json.status === 'cancelled') throw new Error(json?.error?.message ?? `OpenAI response ${json.status}.`)
      logV35('facial_v35_vision_complete', {
        module_id: moduleId,
        attempt,
        elapsed_ms: elapsedMs,
        elapsed_seconds: Math.round(elapsedMs / 1000),
        response_id: json?.id ?? null,
        input_tokens: json?.usage?.input_tokens ?? null,
        output_tokens: json?.usage?.output_tokens ?? null,
        reasoning_tokens: json?.usage?.output_tokens_details?.reasoning_tokens ?? null,
      })
      return json
    } catch (error) {
      clearTimeout(timeoutId)
      const elapsedMs = Date.now() - startedAt
      const causeDetails = error.cause ? ` (cause: ${error.cause?.message ?? error.cause?.code ?? String(error.cause)})` : ''
      const fullMessage = error.message.includes('cause:') ? error.message : `${error.message}${causeDetails}`
      lastError = new Error(fullMessage)
      logV35('facial_v35_vision_failed', {
        module_id: moduleId,
        attempt,
        elapsed_ms: elapsedMs,
        elapsed_seconds: Math.round(elapsedMs / 1000),
        name: error?.name ?? null,
        message: fullMessage,
      })
      const retryable = error.name === 'TypeError' || error.name === 'AbortError' || error.name === 'TimeoutError' ||
        error.message.includes('fetch failed') || error.message.includes('timed out') || error.message.includes('incomplete')
      if (attempt < maxAttempts && retryable) {
        await new Promise((resolve) => setTimeout(resolve, attempt * 1000))
        continue
      }
      throw lastError
    }
  }
  throw lastError
}

function stableCacheKey(label, version) {
  const digest = createHash('sha256').update(`${label}|${version}`).digest('hex').slice(0, 20)
  return `fv35:${label.slice(0, 24)}:${digest}`.slice(0, 64)
}

function resolveImageSetHash(imagesByMode, supplied) {
  if (supplied) return supplied
  const material = CANONICAL_MODES.map((mode) => `${mode}:${imageFileId(imagesByMode?.[mode]) ?? ''}`).join('|')
  if (material.includes(':|') || material.endsWith(':')) throw new Error('Unable to build image-set identity; missing image file_id.')
  return createHash('sha256').update(material).digest('hex')
}

async function runUnifiedAbsoluteAssessmentV35({scanId,imagesByMode,imageSetHash,model,captureType='baseline',pairedBaselineScanId=null}) {
  const hash=resolveImageSetHash(imagesByMode,imageSetHash)
  const prompt=measurementPrompt()
  const promptHash=createHash('sha256').update(prompt).digest('hex')
  const detail=String(process.env.FACIAL_V35_IMAGE_DETAIL ?? process.env.FACIAL_V34_IMAGE_DETAIL ?? 'high')
  const reasoning=String(process.env.FACIAL_V35_REASONING_EFFORT ?? process.env.FACIAL_V34_REASONING_EFFORT ?? 'none')
  const maxTokens=Math.max(8000,Math.min(32000,envInt('FACIAL_V310_MAX_OUTPUT_TOKENS',12000)))
  const cache=await cachedMeasurement({directory:process.env.FACIAL_V310_CACHE_DIR,
    identity:{scope:scanId.split('-').slice(0,2).join('-'),images:CANONICAL_MODES.map(mode=>[mode,imageFileId(imagesByMode[mode])]),hash,model,detail,reasoning,maxTokens,promptHash,measurement_version:MEASUREMENT_VERSION,formula_version:FORMULA_VERSION,device_profile:DEVICE_PROFILE.id},
    produce:async()=>{
      const response=await openAiResponses({model,input:[
        {role:'system',content:[{type:'input_text',text:prompt}]},
        {role:'user',content:buildVisionContent(imagesByMode,{task:'Measure absolute regional skin appearance',authoritative_mode_order:CANONICAL_MODES})},
      ],reasoning:{effort:reasoning},text:{verbosity:'low',format:measurementFormat()},max_output_tokens:maxTokens,prompt_cache_key:MEASUREMENT_VERSION}, {moduleId:'regional_measurement_v310'})
      const parsed=parseModelJson(response);
      decodeMeasurements(parsed);
      return parsed
    }})
  const run=buildRegionalRun(decodeMeasurements(cache.value),{scanId,imageSetHash:hash,modelVersion:model,captureType,pairedBaselineScanId})
  run.evidence_packet.model_execution.unified_wire_version=MEASUREMENT_VERSION
  run.evidence_packet.model_execution.openai_baseline_call_count=cache.cache_hit?0:1
  run.skin_state.scoring_execution.assessment_id=cache.key
  run.skin_state.scoring_execution.measurement_cache_hit=cache.cache_hit
  run.skin_state.scoring_execution.inference_architecture='one_regional_measurement_call_immutable_reuse'
  return {...run,runner_version:MEASUREMENT_VERSION,imagesByMode,assessment_contract:{assessment_id:cache.key,immutable:true,same_assessment_must_be_reused_by_diagnosis_planning_and_reporting:true}}
}

// Legacy V3.4 compact caller retained only so existing post-treatment
// reassessment keeps working while baseline V3.5 is validated in staging.
function buildLegacyPromptCacheKey(moduleId, featureIndexes, promptVersion) {
  const shard = featureIndexes?.length ? featureIndexes.join('-') : 'full'
  const digest = createHash('sha256').update(`${moduleId}|${shard}|${String(promptVersion ?? '')}|legacy`).digest('hex').slice(0, 16)
  return `fv35l:${String(moduleId).slice(0, 16)}:${String(shard).slice(0, 10)}:${digest}`.slice(0, 64)
}

function createLegacyVisionCall(model) {
  return async ({ module_id, system_prompt, prompt_version, images, input }) => {
    const moduleId = String(module_id)
    if (moduleId === 'pairwise_outcome') {
      const compactInput = compactPairwiseInput(input)
      const cached = await cachedMeasurement({directory:process.env.FACIAL_V310_CACHE_DIR,
        identity:{kind:'pairwise',images,input:compactInput,model,prompt_version:COMPACT_PAIRWISE_VERSION,measurement_version:MEASUREMENT_VERSION,reasoning:String(process.env.FACIAL_V34_REASONING_EFFORT ?? 'none'),detail:String(process.env.FACIAL_V35_IMAGE_DETAIL ?? process.env.FACIAL_V34_IMAGE_DETAIL ?? 'high')},
        produce:async()=>{
        const response = await openAiResponses({
        model,
        input: [
          {role:'system',content:[{type:'input_text',text:COMPACT_PAIRWISE_PROMPT}]},
          {role:'user',content:buildVisionContent(images,compactInput)},
        ],
        reasoning:{effort:String(process.env.FACIAL_V34_REASONING_EFFORT ?? 'none')},
        text:{verbosity:'low',format:compactPairwiseFormat()},
        max_output_tokens:Math.max(6000,Math.min(32000,envInt('FACIAL_V393_PAIRWISE_MAX_OUTPUT_TOKENS',8000))),
        prompt_cache_key:COMPACT_PAIRWISE_VERSION,
      },{moduleId})
        const value=parseModelJson(response);decodeCompactPairwise(value,compactInput);return value
      }})
      return decodeCompactPairwise(cached.value,compactInput)
    }
    const useCompact = LEGACY_COMPACT_MODULES.has(moduleId)
    const compactInput = useCompact ? compactModelInputV34_1(moduleId, input) : input
    const reasoningEffort = String(process.env.FACIAL_V34_REASONING_EFFORT ?? 'none')

    async function runOne(featureIndexes = null, shardIndex = 0, shardCount = 1) {
      const label = featureIndexes && shardCount > 1 ? `${moduleId}#${shardIndex + 1}of${shardCount}` : moduleId
      const prompt = buildCompactVisionPromptV34_7(moduleId, system_prompt, featureIndexes)
      const response = await openAiResponses({
        model,
        input: [
          { role: 'system', content: [{ type: 'input_text', text: prompt }] },
          { role: 'user', content: buildVisionContent(images, compactInput) },
        ],
        reasoning: { effort: reasoningEffort },
        text: { verbosity: 'low', format: compactStructuredOutputFormatV34_7(moduleId, featureIndexes) },
        max_output_tokens: 12000,
        prompt_cache_key: buildLegacyPromptCacheKey(moduleId, featureIndexes, prompt_version),
      }, { moduleId: label })
      return parseModelJson(response)
    }

    if (useCompact) {
      const plan = moduleId === 'morphology' ? [null] : compactSpecialistShardPlanV34_7(moduleId)
      const values = await Promise.all(plan.map((idxs, i) => runOne(idxs, i, plan.length)))
      const parsed = plan.length === 1 ? values[0] : mergeCompactSpecialistShardsV34_7(moduleId, values)
      return decodeCompactVisionOutputV34_7(moduleId, parsed, { input, modelVersion: model, promptVersion: String(prompt_version) })
    }

    const response = await openAiResponses({
      model,
      input: [
        { role: 'system', content: [{ type: 'input_text', text: system_prompt }] },
        { role: 'user', content: buildVisionContent(images, input) },
      ],
      reasoning: { effort: reasoningEffort },
      text: { verbosity: 'low', format: { type: 'json_object' } },
      max_output_tokens: 12000,
      prompt_cache_key: buildLegacyPromptCacheKey(moduleId, null, prompt_version),
    }, { moduleId })
    return parseModelJson(response)
  }
}

function normalizeTreatmentMode(value) {
  const mode = String(value ?? 'single').toLowerCase()
  if (mode === 'full' || mode === 'multiple' || mode === 'plan') return 'multiple'
  if (mode === 'express') return 'express'
  return 'single'
}

function clientMeta(payload) {
  return {
    client_id: payload.client?.client_id ?? payload.assessment_id ?? null,
    display_name: payload.client?.display_name ?? payload.client?.name ?? null,
  }
}

function clinicMeta(payload) {
  return {
    name: payload.clinic?.name ?? 'AI Aesthetics',
    medical_lead: payload.clinic?.medical_lead ?? 'Dr. Aakriti Mehra',
    location: payload.clinic?.location ?? null,
  }
}

async function assessmentCommand(payload, model) {
  const startedAt = Date.now()
  const scanId = String(payload.scan_id ?? `assessment-${payload.assessment_id ?? Date.now()}-baseline`)
  logV35('facial_v35_assessment_start', { assessment_id: payload.assessment_id ?? null, model, architecture: 'single_unified_call' })
  const run = await runUnifiedAbsoluteAssessmentV35({
    scanId,
    imagesByMode: payload.images_by_mode,
    imageSetHash: payload.image_set_hash ?? null,
    model,
  })
  const report = buildSkinAnalysisReportV3({
    client: clientMeta(payload),
    clinic: clinicMeta(payload),
    skinState: run.skin_state,
    imageAssets: payload.image_assets ?? null,
    statedConcerns: payload.stated_concerns ?? [],
  })
  const diagnosis = buildLegacyDiagnosisV34({ skinAnalysisReport: report, skinState: run.skin_state })
  const elapsedMs = Date.now() - startedAt
  logV35('facial_v35_assessment_complete', {
    assessment_id: payload.assessment_id ?? null,
    elapsed_ms: elapsedMs,
    elapsed_seconds: Math.round(elapsedMs / 1000),
  })
  return {
    engine_version: 'facial_v3_17_visible_appearance',
    command: 'assessment',
    latency_profile: {
      inference_architecture: 'one_regional_measurement_call_immutable_reuse',
      baseline_openai_call_count: run.evidence_packet.model_execution.openai_baseline_call_count,
      unified_wire_version: MEASUREMENT_VERSION,
      elapsed_ms: elapsedMs,
    },
    feature_packet: run.evidence_packet,
    diagnosis,
    skin_state: run.skin_state,
    skin_analysis_report: report,
    assessment_contract: run.assessment_contract,
    v3_4_native: run, // key retained for Laravel storage/backward compatibility
  }
}

async function treatmentPlanCommand(payload) {
  const skinState = payload.skin_state
  if (!skinState?.core_features) throw new Error('treatment_plan requires skin_state from the baseline assessment.')
  if (![CALIBRATION_VERSION_V36,FORMULA_VERSION].includes(skinState.scoring_execution?.calibration_version)) throw new Error('Treatment planning requires a supported saved baseline calibration (current appearance baseline or legacy V3.7). Retain old reports for audit.')
  const treatmentMode = normalizeTreatmentMode(payload.treatment_mode)
  const concerns = resolveConcernFeaturesV34(payload.selected_concerns, skinState)
  const common = {
    skinState,
    patientHistory: payload.patient_history ?? {},
    primaryConcerns: concerns.primaryConcerns,
    secondaryConcerns: concerns.secondaryConcerns,
    regionalTemperaturesC: payload.regional_temperatures_c ?? {},
    zoneContext: payload.zone_context ?? {},
    capabilities: payload.capabilities ?? {},
  }

  if (treatmentMode === 'multiple') {
    const multi = createMultiSessionPlanV2({ ...common, maximumSessions: Number(payload.maximum_sessions ?? 8) })
    const compiled = []
    for (const session of multi.current_detailed_block?.detailed_sessions ?? []) {
      compiled.push(compileTherapistSessionV2({
        optimizerResult: session.optimiser_plan,
        sessionNumber: session.session_number,
        planId: multi.plan_id,
        skinState,
        patientHistory: payload.patient_history ?? {},
        regionalTemperaturesC: payload.regional_temperatures_c ?? {},
        patientId: payload.client?.client_id ?? null,
      }))
    }
    const treatments = compiled.map((session, index) => compiledSessionToLegacyTreatmentV34(
      session,
      multi.current_detailed_block?.session_numbers?.[index] ?? index + 1,
    ))
    const report = buildTreatmentPlanReportV3({
      client: clientMeta(payload), clinic: clinicMeta(payload), skinState, multiSessionPlan: multi,
      doctorOverrideSessions: payload.doctor_override_sessions ?? null,
    })
    return buildLegacyTreatmentPlanV34({
      treatments, treatmentMode, recommendedFullPlan: report,
      native: { concern_resolution: concerns, multi_session_plan: multi, compiled_sessions: compiled, treatment_plan_report: report },
    })
  }

  const optimizer = optimizeZonalTreatmentV2({ ...common, treatmentMode })
  const compiled = compileTherapistSessionV2({
    optimizerResult: optimizer,
    sessionNumber: 1,
    planId: `assessment-${payload.assessment_id ?? 'unknown'}-${treatmentMode}`,
    skinState,
    patientHistory: payload.patient_history ?? {},
    regionalTemperaturesC: payload.regional_temperatures_c ?? {},
    patientId: payload.client?.client_id ?? null,
  })
  const treatment = compiledSessionToLegacyTreatmentV34(compiled, 1)
  return buildLegacyTreatmentPlanV34({
    treatments: [treatment], treatmentMode,
    native: { concern_resolution: concerns, optimizer_result: optimizer, compiled_session: compiled },
  })
}

async function reassessmentCommand(payload, model) {
  const start=Date.now(), baseline=payload.baseline_run
  if(!baseline?.skin_state||!baseline?.evidence_packet)throw Error('Stored baseline run required')
  const context=baselineContextV316(baseline.skin_state)
  const before=baseline.imagesByMode,after=payload.post_images_by_mode
  const ids=images=>CANONICAL_MODES.map(mode=>{const id=imageFileId(images?.[mode]);if(!id)throw Error(`Missing paired capture ${mode}`);return [mode,id]})
  const beforeIds=ids(before),afterIds=ids(after)
  const identical=JSON.stringify(beforeIds)===JSON.stringify(afterIds)
  const prompt=comparisonPromptV316(),detail=String(process.env.FACIAL_V35_IMAGE_DETAIL??process.env.FACIAL_V34_IMAGE_DETAIL??'high')
  const reasoning=String(process.env.FACIAL_V316_REASONING_EFFORT??'none')
  const maxTokens=Math.max(6000,Math.min(16000,envInt('FACIAL_V316_MAX_OUTPUT_TOKENS',12000)))
  const options={scanId:String(payload.post_scan_id??`assessment-${payload.assessment_id}-post`),imageSetHash:resolveImageSetHash(after,payload.post_image_set_hash),modelVersion:model,captureType:'post_treatment',pairedBaselineScanId:baseline.skin_state.scan.scan_id}
  const cached=identical?{value:stableComparisonV316(baseline.skin_state),cache_hit:true,key:null}:await cachedMeasurement({directory:process.env.FACIAL_V310_CACHE_DIR,waitMs:20000,
    identity:{version:COMPARISON_VERSION_V316,assessment_id:payload.assessment_id,reference:context,client_display_state:baseline.skin_state.client_display_state??null,before:beforeIds,after:afterIds,model,prompt,detail,reasoning,maxTokens},
    produce:async()=>{
      const response=await openAiResponses({model,input:[{role:'system',content:[{type:'input_text',text:prompt}]},{role:'user',content:buildVisionContent({baseline:before,post_treatment:after},context)}],reasoning:{effort:reasoning},text:{verbosity:'low',format:comparisonFormatV316()},max_output_tokens:maxTokens,prompt_cache_key:COMPARISON_VERSION_V316},{moduleId:'comparative_reassessment_v316'})
      const wire=parseModelJson(response)
      applyComparisonV316(baseline,wire,options) // Reject malformed/inconsistent output before caching.
      return wire
    }})
  const result=applyComparisonV316(baseline,cached.value,options)
  result.post_run.skin_state.scoring_execution.measurement_cache_hit=cached.cache_hit
  result.post_run.skin_state.scoring_execution.assessment_id=cached.key
  return {engine_version:'facial_v3_17_comparative',command:'reassessment',...result,post_feature_packet:result.post_run.evidence_packet,
    reference_rescored:false,reference_run:null,latency_profile:{elapsed_ms:Date.now()-start,vision_calls:cached.cache_hit?0:1,cache_hit:cached.cache_hit,identical_capture_shortcut:identical,architecture:'one_paired_comparison_no_absolute_rescore'},outcome_report:null,outcome_report_status:'not_generated_until_verified_execution_record_is_persisted'}
}

async function main() {
  const command = process.argv[2]
  const payload = command === 'self_test' ? {} : await readStdin()
  const requestedModel = String(payload.model ?? process.env.FACIAL_V35_MODEL ?? process.env.FACIAL_V34_MODEL ?? process.env.FACIAL_V34_OPENAI_MODEL ?? 'gpt-5.2')
  const model = requestedModel === 'gpt-5.2' ? 'gpt-5.2-2025-12-11' : requestedModel
  let result
  if (command === 'assessment') result = await assessmentCommand(payload, model)
  else if (command === 'treatment_plan') result = await treatmentPlanCommand(payload)
  else if (command === 'reassessment') result = await reassessmentCommand(payload, model)
  else if (command === 'validate_course_block') {
    const check = validateWorkflowPlanV39(payload.plan, payload.mode, payload.selected_concerns, payload.course_context)
    if (!check.valid) throw new Error(check.errors.join(' '))
    result = { ...check, merged_plan: payload.existing_plan ? mergeCourseBlockV39(payload.existing_plan, payload.plan) : payload.plan }
  }
  else if (command === 'project_transport') result = compactResultV391(payload.result, payload.assessment_id)
  else if (command === 'refresh_concern_preview') {
    const saved = payload.result
    result = {...saved, diagnosis:buildLegacyDiagnosisV34({skinAnalysisReport:saved.skin_analysis_report,skinState:saved.skin_state})}
  }
  else if (command === 'project_planning_context') result = compactPlanningContextV391(payload.context)
  else if (command === 'self_test') {
    result = {
      ok: true,
      engine_version: 'facial_v3_17_visible_appearance',
      node_version: process.version,
      canonical_modes: CANONICAL_MODES,
      inference_architecture: 'one_regional_measurement_call_immutable_reuse',
      baseline_openai_call_count: 1,
      unified_wire_version: MEASUREMENT_VERSION,
      unified_prompt_version: MEASUREMENT_VERSION,
      default_reasoning_effort: process.env.FACIAL_V35_REASONING_EFFORT ?? process.env.FACIAL_V34_REASONING_EFFORT ?? 'none',
      default_image_detail: process.env.FACIAL_V35_IMAGE_DETAIL ?? process.env.FACIAL_V34_IMAGE_DETAIL ?? 'high',
    }
  } else throw new Error(`Unknown command: ${command}`)
  process.stdout.write(`${JSON.stringify({ ok: true, result })}\n`)
}

main().catch((error) => {
  process.stderr.write(`${error?.stack ?? error}\n`)
  process.stdout.write(`${JSON.stringify({ ok: false, error: { message: error?.message ?? String(error), stack: process.env.FACIAL_V35_DEBUG === '1' ? error?.stack ?? null : null } })}\n`)
  process.exitCode = 1
})
