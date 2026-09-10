#!/usr/bin/env node
import process from 'node:process'
import {
  runSkinStateV2,
} from './runSkinStateV2.js'
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

const CANONICAL_MODES = [
  'red',
  'subsurface_polarized',
  'surface_polarized',
  'white',
  'woods_uv',
]

function logV34(event, data = {}) {
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
  if (process.stdin.isTTY) {
    return Promise.resolve({})
  }
  return new Promise((resolve, reject) => {
    let data = ''
    process.stdin.setEncoding('utf8')
    process.stdin.on('data', (chunk) => { data += chunk })
    process.stdin.on('end', () => {
      try {
        resolve(data.trim() ? JSON.parse(data) : {})
      } catch (error) {
        reject(new Error(`Invalid JSON on stdin: ${error.message}`))
      }
    })
    process.stdin.on('error', reject)
  })
}

function stripJsonFence(value) {
  return String(value ?? '')
    .trim()
    .replace(/^```(?:json)?\s*/i, '')
    .replace(/\s*```$/i, '')
    .trim()
}

function collectResponseText(response) {
  if (typeof response?.output_text === 'string' && response.output_text.trim()) {
    return response.output_text.trim()
  }
  const chunks = []
  const refusals = []
  for (const item of Array.isArray(response?.output) ? response.output : []) {
    for (const part of Array.isArray(item?.content) ? item.content : []) {
      if (part?.type === 'refusal' && typeof part.refusal === 'string') {
        refusals.push(part.refusal)
      } else if (typeof part?.text === 'string' && part.text.trim()) {
        chunks.push(part.text.trim())
      }
    }
  }
  if (!chunks.length && refusals.length) {
    throw new Error(`OpenAI refused the request: ${refusals.join(' ')}`)
  }
  if (!chunks.length) throw new Error('OpenAI response contained no output text.')
  return [...new Set(chunks)].join('\n').trim()
}

function parseModelJson(response) {
  const text = stripJsonFence(collectResponseText(response))
  try {
    return JSON.parse(text)
  } catch (error) {
    throw new Error(`OpenAI returned non-JSON output: ${error.message}; preview=${text.slice(0, 300)}`)
  }
}

function imageFileId(value) {
  if (typeof value === 'string') return value
  if (value && typeof value === 'object') {
    return value.file_id ?? value.fileId ?? value.openai_file_id ?? null
  }
  return null
}

function appendModeImages(content, imagesByMode, prefix = '') {
  for (const mode of CANONICAL_MODES) {
    const fileId = imageFileId(imagesByMode?.[mode])
    if (!fileId) throw new Error(`Missing OpenAI file_id for ${prefix}${mode}`)
    content.push({
      type: 'input_text',
      text: `${prefix ? `${prefix} ` : ''}authoritative image mode: ${mode}`,
    })
    content.push({
      type: 'input_image',
      file_id: fileId,
      detail: 'auto',
    })
  }
}

function buildVisionContent(images, input) {
  const content = [
    {
      type: 'input_text',
      text: `Backend structured input:\n${JSON.stringify(input)}`,
    },
  ]

  if (images?.baseline || images?.post_treatment) {
    appendModeImages(content, images.baseline, 'baseline')
    appendModeImages(content, images.post_treatment, 'post_treatment')
  } else {
    appendModeImages(content, images)
  }
  return content
}

async function openAiResponses(payload, { moduleId = 'unknown' } = {}) {
  const apiKey = process.env.OPENAI_API_KEY
  if (!apiKey) throw new Error('OPENAI_API_KEY is not available to the V3.4 runner.')

  // IMPORTANT: use the same FACIAL_V34_* variable names documented for Laravel staging.
  // maxRetries means retries AFTER the first attempt; 0 = exactly one request.
  const timeoutMs = Math.max(1000, envInt('FACIAL_V34_OPENAI_TIMEOUT_MS', 120000))
  const maxRetries = Math.max(0, envInt('FACIAL_V34_OPENAI_MAX_RETRIES', 0))
  const maxAttempts = 1 + maxRetries

  let lastError = null
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const controller = new AbortController()
    const startedAt = Date.now()
    const timeoutId = setTimeout(
      () => controller.abort(new Error(`OpenAI request timed out after ${timeoutMs}ms`)),
      timeoutMs,
    )

    logV34('facial_v34_vision_start', {
      module_id: moduleId,
      attempt,
      max_attempts: maxAttempts,
      timeout_ms: timeoutMs,
      model: payload?.model ?? null,
    })

    try {
      const response = await fetch('https://api.openai.com/v1/responses', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${apiKey}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
        signal: controller.signal,
      })
      clearTimeout(timeoutId)

      const json = await response.json().catch(() => ({}))
      const elapsedMs = Date.now() - startedAt

      if (!response.ok) {
        const errorMsg = `OpenAI Responses API failed (${response.status}): ${json?.error?.message ?? JSON.stringify(json).slice(0, 500)}`
        logV34('facial_v34_vision_http_error', {
          module_id: moduleId,
          attempt,
          elapsed_ms: elapsedMs,
          status: response.status,
        })
        if ((response.status === 429 || response.status >= 500) && attempt < maxAttempts) {
          lastError = new Error(errorMsg)
          await new Promise((resolve) => setTimeout(resolve, attempt * 1000))
          continue
        }
        throw new Error(errorMsg)
      }

      if (json.status === 'incomplete') {
        const reason = json?.incomplete_details?.reason ?? 'unknown_reason'
        throw new Error(`OpenAI response incomplete: ${reason}`)
      }
      if (json.status === 'failed' || json.status === 'cancelled') {
        throw new Error(json?.error?.message ?? `OpenAI response ${json.status}.`)
      }

      logV34('facial_v34_vision_complete', {
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

      logV34('facial_v34_vision_failed', {
        module_id: moduleId,
        attempt,
        elapsed_ms: elapsedMs,
        elapsed_seconds: Math.round(elapsedMs / 1000),
        name: error?.name ?? null,
        message: fullMessage,
      })

      const retryable =
        error.name === 'TypeError' ||
        error.name === 'AbortError' ||
        error.name === 'TimeoutError' ||
        error.message.includes('fetch failed') ||
        error.message.includes('timed out') ||
        error.message.includes('incomplete')

      if (attempt < maxAttempts && retryable) {
        await new Promise((resolve) => setTimeout(resolve, attempt * 1000))
        continue
      }
      throw lastError
    }
  }

  throw lastError
}

function createVisionCall(model) {
  return async ({
    module_id,
    system_prompt,
    prompt_version,
    images,
    input,
  }) => {
    const maxOutputTokens = Math.max(
      1000,
      envInt('FACIAL_V34_MAX_OUTPUT_TOKENS', 55000),
    )
    const reasoningEffort = String(
      process.env.FACIAL_V34_REASONING_EFFORT ?? 'medium',
    )

    const response = await openAiResponses({
      model,
      input: [
        {
          role: 'system',
          content: [{ type: 'input_text', text: system_prompt }],
        },
        {
          role: 'user',
          content: buildVisionContent(images, input),
        },
      ],
      reasoning: { effort: reasoningEffort },
      max_output_tokens: maxOutputTokens,
      metadata: {
        pipeline_version: 'facial_v3_4',
        stage: String(module_id),
        prompt_version: String(prompt_version),
      },
    }, { moduleId: String(module_id) })
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
  const assessmentStartedAt = Date.now()
  const visionCall = createVisionCall(model)
  const concurrency = payload.concurrency ?? 'parallel'
  logV34('facial_v34_assessment_start', {
    assessment_id: payload.assessment_id ?? null,
    model,
    concurrency,
  })
  const run = await runSkinStateV2({
    scanId: String(payload.scan_id ?? `assessment-${payload.assessment_id ?? Date.now()}`),
    imagesByMode: payload.images_by_mode,
    imageSetHash: payload.image_set_hash ?? null,
    modelVersion: model,
    visionCall,
    includeRawModuleOutputs: payload.include_raw_module_outputs === true,
    concurrency,
  })
  const report = buildSkinAnalysisReportV3({
    client: clientMeta(payload),
    clinic: clinicMeta(payload),
    skinState: run.skin_state,
    imageAssets: payload.image_assets ?? null,
    statedConcerns: payload.stated_concerns ?? [],
  })
  const diagnosis = buildLegacyDiagnosisV34({
    skinAnalysisReport: report,
    skinState: run.skin_state,
  })
  logV34('facial_v34_assessment_complete', {
    assessment_id: payload.assessment_id ?? null,
    elapsed_ms: Date.now() - assessmentStartedAt,
    elapsed_seconds: Math.round((Date.now() - assessmentStartedAt) / 1000),
  })
  return {
    engine_version: 'facial_v3_4',
    command: 'assessment',
    feature_packet: run.evidence_packet,
    diagnosis,
    skin_state: run.skin_state,
    skin_analysis_report: report,
    assessment_contract: run.assessment_contract,
    v3_4_native: run,
  }
}

async function treatmentPlanCommand(payload) {
  const skinState = payload.skin_state
  if (!skinState?.core_features) {
    throw new Error('treatment_plan requires skin_state from the V3.4 baseline assessment.')
  }
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
    const multi = createMultiSessionPlanV2({
      ...common,
      maximumSessions: Number(payload.maximum_sessions ?? 8),
    })
    const compiled = []
    for (const session of multi.current_detailed_block?.detailed_sessions ?? []) {
      const compiledSession = compileTherapistSessionV2({
        optimizerResult: session.optimiser_plan,
        sessionNumber: session.session_number,
        planId: multi.plan_id,
        skinState,
        patientHistory: payload.patient_history ?? {},
        regionalTemperaturesC: payload.regional_temperatures_c ?? {},
        patientId: payload.client?.client_id ?? null,
      })
      compiled.push(compiledSession)
    }
    const treatments = compiled.map((session, index) =>
      compiledSessionToLegacyTreatmentV34(
        session,
        multi.current_detailed_block?.session_numbers?.[index] ?? index + 1,
      ),
    )
    const report = buildTreatmentPlanReportV3({
      client: clientMeta(payload),
      clinic: clinicMeta(payload),
      skinState,
      multiSessionPlan: multi,
      doctorOverrideSessions: payload.doctor_override_sessions ?? null,
    })
    return buildLegacyTreatmentPlanV34({
      treatments,
      treatmentMode,
      recommendedFullPlan: report,
      native: {
        concern_resolution: concerns,
        multi_session_plan: multi,
        compiled_sessions: compiled,
        treatment_plan_report: report,
      },
    })
  }

  const optimizer = optimizeZonalTreatmentV2({
    ...common,
    treatmentMode,
  })
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
    treatments: [treatment],
    treatmentMode,
    native: {
      concern_resolution: concerns,
      optimizer_result: optimizer,
      compiled_session: compiled,
    },
  })
}

async function reassessmentCommand(payload, model) {
  if (!payload.baseline_run?.skin_state || !payload.baseline_run?.evidence_packet) {
    throw new Error('reassessment requires the stored V3.4 baseline run.')
  }
  const visionCall = createVisionCall(model)
  const result = await runPostTreatmentReassessmentV2({
    baseline: payload.baseline_run,
    post: {
      scanId: String(payload.post_scan_id ?? `post-${payload.assessment_id ?? Date.now()}`),
      imagesByMode: payload.post_images_by_mode,
      imageSetHash: payload.post_image_set_hash ?? null,
    },
    visionCall,
    pairwiseCall: visionCall,
    modelVersion: model,
    includeRawPairwiseOutput: payload.include_raw_pairwise_output === true,
  })
  return {
    engine_version: 'facial_v3_4',
    command: 'reassessment',
    post_feature_packet: result.post_treatment?.skin_state ?? null,
    post_diagnosis: buildLegacyPostDiagnosisV34(result),
    reassessment_result: result,
    outcome_report: null,
    outcome_report_status:
      'not_generated_in_staging_adapter_because_a_verified_execution_record_is_not_yet_persisted_by_the_existing_step_monitor',
  }
}

async function main() {
  const command = process.argv[2]
  const payload = command === 'self_test' ? {} : await readStdin()
  const model = String(payload.model ?? process.env.FACIAL_V34_MODEL ?? 'gpt-5.2')
  let result
  if (command === 'assessment') {
    result = await assessmentCommand(payload, model)
  } else if (command === 'treatment_plan') {
    result = await treatmentPlanCommand(payload)
  } else if (command === 'reassessment') {
    result = await reassessmentCommand(payload, model)
  } else if (command === 'self_test') {
    result = {
      ok: true,
      engine_version: 'facial_v3_4',
      node_version: process.version,
      canonical_modes: CANONICAL_MODES,
    }
  } else {
    throw new Error(`Unknown command: ${command}`)
  }
  process.stdout.write(`${JSON.stringify({ ok: true, result })}\n`)
}

main().catch((error) => {
  process.stderr.write(`${error?.stack ?? error}\n`)
  process.stdout.write(`${JSON.stringify({
    ok: false,
    error: {
      message: error?.message ?? String(error),
      stack: process.env.FACIAL_V34_DEBUG === '1' ? error?.stack ?? null : null,
    },
  })}\n`)
  process.exitCode = 1
})
