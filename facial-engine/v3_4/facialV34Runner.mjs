#!/usr/bin/env node
import { CALIBRATION_VERSION_V36 } from './clinicalCalibrationV36.js'
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
      if (json.status === 'incomplete') throw new Error(`OpenAI response incomplete: ${json?.incomplete_details?.reason ?? 'unknown_reason'}`)
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

async function runUnifiedAbsoluteAssessmentV35({ scanId, imagesByMode, imageSetHash, model, captureType = 'baseline', pairedBaselineScanId = null }) {
  const resolvedImageSetHash = resolveImageSetHash(imagesByMode, imageSetHash)
  const prompt = buildUnifiedVisionPromptV35()
  const maxOutputTokens = Math.max(8000, Math.min(24000, envInt('FACIAL_V35_MAX_OUTPUT_TOKENS', 18000)))
  const reasoningEffort = String(process.env.FACIAL_V35_REASONING_EFFORT ?? process.env.FACIAL_V34_REASONING_EFFORT ?? 'none')
  const request = {
    model,
    input: [
      { role: 'system', content: [{ type: 'input_text', text: prompt }] },
      {
        role: 'user',
        content: buildVisionContent(imagesByMode, {
          task: 'Produce one unified absolute five-mode Skin State evidence packet.',
          scan_id: scanId,
          image_set_hash: resolvedImageSetHash,
          authoritative_mode_order: CANONICAL_MODES,
          fixed_device_capture: true,
          patient_history_available: false,
          treatment_history_available: false,
          dynamic_questions_available: false,
        }),
      },
    ],
    reasoning: { effort: reasoningEffort },
    text: {
      verbosity: String(process.env.FACIAL_V35_TEXT_VERBOSITY ?? 'low'),
      format: unifiedStructuredOutputFormatV35(),
    },
    max_output_tokens: maxOutputTokens,
    metadata: {
      pipeline_version: 'facial_v3_6_calibrated_unified',
      stage: 'unified_assessment',
      prompt_version: UNIFIED_VISION_PROMPT_VERSION,
      wire_version: UNIFIED_VISION_WIRE_VERSION,
    },
    prompt_cache_key: stableCacheKey('unified_assessment', UNIFIED_VISION_PROMPT_VERSION),
  }

  logV35('facial_v35_unified_contract', {
    openai_call_count: 1,
    system_prompt_chars: prompt.length,
    max_output_tokens: maxOutputTokens,
    reasoning_effort: reasoningEffort,
    image_detail: String(process.env.FACIAL_V35_IMAGE_DETAIL ?? process.env.FACIAL_V34_IMAGE_DETAIL ?? 'high'),
  })

  const response = await openAiResponses(request, { moduleId: 'unified_assessment' })
  const parsed = parseModelJson(response)
  const decoded = decodeUnifiedVisionOutputV35(parsed, {
    scanId,
    imageSetHash: resolvedImageSetHash,
    modelVersion: model,
  })

  const evidencePacket = mergeVisionEvidencePacketsV2(decoded.moduleOutputs, {
    morphologyExclusionMap: decoded.morphology,
    scanOverride: {
      scan_id: scanId,
      image_set_hash: resolvedImageSetHash,
      capture_type: captureType,
      paired_baseline_scan_id: pairedBaselineScanId,
    },
    modelVersion: model,
  })
  evidencePacket.model_execution.prompt_version = UNIFIED_VISION_PROMPT_VERSION
  evidencePacket.model_execution.unified_wire_version = UNIFIED_VISION_WIRE_VERSION
  evidencePacket.model_execution.openai_baseline_call_count = 1

  const skinState = buildSkinStateV2(evidencePacket)
  const assessmentId = createHash('sha256')
    .update([scanId, resolvedImageSetHash, model, UNIFIED_VISION_PROMPT_VERSION, skinState.scoring_execution.formula_config_version].join('|'))
    .digest('hex')
  skinState.scoring_execution.assessment_id = assessmentId
  skinState.scoring_execution.immutable_assessment = true
  skinState.scoring_execution.history_excluded_from_image_scoring = true
  skinState.scoring_execution.dynamic_questions_used = false
  skinState.scoring_execution.external_qc_layer_used = false
  skinState.scoring_execution.inference_architecture = 'single_unified_five_mode_call'
  skinState.scoring_execution.unified_wire_version = UNIFIED_VISION_WIRE_VERSION

  return {
    runner_version: 'aia_skin_state_runner_v3.6.0_calibrated_unified',
    evidence_packet: evidencePacket,
    skin_state: skinState,
    assessment_contract: {
      assessment_id: assessmentId,
      immutable: true,
      same_assessment_must_be_reused_by_diagnosis_planning_and_reporting: true,
      patient_context_received_but_excluded_from_vision: false,
    },
    imagesByMode,
    raw_unified_output: process.env.FACIAL_V35_INCLUDE_RAW === '1' ? parsed : undefined,
  }
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
    engine_version: 'facial_v3_6_calibrated',
    command: 'assessment',
    latency_profile: {
      inference_architecture: 'single_unified_five_mode_call',
      baseline_openai_call_count: 1,
      unified_wire_version: UNIFIED_VISION_WIRE_VERSION,
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
  if (!payload.baseline_run?.skin_state || !payload.baseline_run?.evidence_packet) {
    throw new Error('reassessment requires the stored baseline run.')
  }
  const baseline = payload.baseline_run
  if (baseline.skin_state.scoring_execution?.calibration_version !== CALIBRATION_VERSION_V36) {
    throw new Error('Baseline uses a different calibration. Re-run the original baseline images with v3.6-calibrated before comparing; keep the original report for audit.')
  }
  if (baseline.evidence_packet.model_execution?.model_version !== model ||
      baseline.evidence_packet.model_execution?.prompt_version !== UNIFIED_VISION_PROMPT_VERSION) {
    throw new Error('Baseline model/prompt differs from v3.6 reassessment. Use the same model and re-run original baseline images before comparing.')
  }
  if (!baseline.imagesByMode) throw new Error('Original baseline images are required for visual pairwise verification.')
  const postRun = await runUnifiedAbsoluteAssessmentV35({
    scanId: String(payload.post_scan_id ?? `post-${payload.assessment_id ?? Date.now()}`),
    imagesByMode: payload.post_images_by_mode,
    imageSetHash: payload.post_image_set_hash ?? null,
    model,
    captureType: 'post_treatment',
    pairedBaselineScanId: baseline.skin_state.scan.scan_id,
  })
  const result = await runPostTreatmentReassessmentV2({
    baseline,
    post: postRun,
    pairwiseCall: createLegacyVisionCall(model),
    modelVersion: model,
    includeRawPairwiseOutput: payload.include_raw_pairwise_output === true,
  })
  return {
    engine_version: 'facial_v3_6_calibrated',
    command: 'reassessment',
    post_feature_packet: result.post_treatment?.skin_state ?? null,
    post_diagnosis: buildLegacyPostDiagnosisV34(result),
    reassessment_result: result,
    outcome_report: null,
    outcome_report_status: 'not_generated_until_verified_execution_record_is_persisted',
  }
}

async function main() {
  const command = process.argv[2]
  const payload = command === 'self_test' ? {} : await readStdin()
  const model = String(payload.model ?? process.env.FACIAL_V35_MODEL ?? process.env.FACIAL_V34_MODEL ?? process.env.FACIAL_V34_OPENAI_MODEL ?? 'gpt-5.2')
  let result
  if (command === 'assessment') result = await assessmentCommand(payload, model)
  else if (command === 'treatment_plan') result = await treatmentPlanCommand(payload)
  else if (command === 'reassessment') result = await reassessmentCommand(payload, model)
  else if (command === 'self_test') {
    result = {
      ok: true,
      engine_version: 'facial_v3_6_calibrated',
      node_version: process.version,
      canonical_modes: CANONICAL_MODES,
      inference_architecture: 'single_unified_five_mode_call',
      baseline_openai_call_count: 1,
      unified_wire_version: UNIFIED_VISION_WIRE_VERSION,
      unified_prompt_version: UNIFIED_VISION_PROMPT_VERSION,
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
