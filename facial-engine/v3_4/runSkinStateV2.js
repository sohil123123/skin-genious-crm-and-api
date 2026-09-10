import { createHash } from 'node:crypto'
import {
  IMAGE_MODES,
  VISION_EVIDENCE_SCHEMA_VERSION,
} from './skinStateV2.schema.js'
import {
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'
import {
  buildSkinStateV2,
  scanCacheKeyParts,
} from './skinStateScoreMapperV2.js'
import {
  mergeVisionEvidencePacketsV2,
  VISION_PROMPT_SUITE_VERSION,
  parseStrictJsonOutput,
} from './mergeVisionEvidenceV2.js'
import {
  SYSTEM_PROMPT_MORPHOLOGY_EXCLUSION_MAP_V3,
  MORPHOLOGY_EXCLUSION_PROMPT_VERSION,
} from './visionMorphologyExclusionMapV3.js'
import {
  SYSTEM_PROMPT_VISION_EVIDENCE_SURFACE_V2,
  VISION_EVIDENCE_SURFACE_V2_VERSION,
} from './visionEvidenceSurfaceV2.js'
import {
  SYSTEM_PROMPT_VISION_EVIDENCE_INFLAMMATION_V2,
  VISION_EVIDENCE_INFLAMMATION_V2_VERSION,
} from './visionEvidenceInflammationV2.js'
import {
  SYSTEM_PROMPT_VISION_EVIDENCE_PIGMENT_V2,
  VISION_EVIDENCE_PIGMENT_V2_VERSION,
} from './visionEvidencePigmentV2.js'
import {
  SYSTEM_PROMPT_VISION_EVIDENCE_STRUCTURE_V2,
  VISION_EVIDENCE_STRUCTURE_V2_VERSION,
} from './visionEvidenceStructureV2.js'

export const SKIN_STATE_RUNNER_VERSION =
  'aia_skin_state_runner_v3.3.0'

export const VISION_MODULES_V2 = Object.freeze({
  surface: {
    promptVersion: VISION_EVIDENCE_SURFACE_V2_VERSION,
    systemPrompt: SYSTEM_PROMPT_VISION_EVIDENCE_SURFACE_V2,
  },
  inflammation: {
    promptVersion: VISION_EVIDENCE_INFLAMMATION_V2_VERSION,
    systemPrompt: SYSTEM_PROMPT_VISION_EVIDENCE_INFLAMMATION_V2,
  },
  pigment: {
    promptVersion: VISION_EVIDENCE_PIGMENT_V2_VERSION,
    systemPrompt: SYSTEM_PROMPT_VISION_EVIDENCE_PIGMENT_V2,
  },
  structure: {
    promptVersion: VISION_EVIDENCE_STRUCTURE_V2_VERSION,
    systemPrompt: SYSTEM_PROMPT_VISION_EVIDENCE_STRUCTURE_V2,
  },
})

function isByteLike(value) {
  return Buffer.isBuffer(value) || value instanceof Uint8Array
}

function imageBytesOrHash(image, mode) {
  if (isByteLike(image)) return { bytes: Buffer.from(image) }
  if (image && typeof image === 'object') {
    if (isByteLike(image.bytes)) return { bytes: Buffer.from(image.bytes) }
    const suppliedHash = image.sha256 ?? image.content_hash ?? image.contentHash
    if (typeof suppliedHash === 'string' && suppliedHash.length > 0) {
      return { suppliedHash }
    }
  }
  throw new Error(
    `Cannot derive a content hash for ${mode}. Provide imageSetHash, byte data, or a per-image sha256/content_hash.`,
  )
}

export function computeImageSetHashV2(imagesByMode) {
  if (!imagesByMode || typeof imagesByMode !== 'object') {
    throw new TypeError('imagesByMode must be an object keyed by image mode')
  }
  const setHasher = createHash('sha256')
  for (const mode of IMAGE_MODES) {
    if (!(mode in imagesByMode)) throw new Error(`Missing image mode: ${mode}`)
    const material = imageBytesOrHash(imagesByMode[mode], mode)
    const modeHash = material.suppliedHash
      ? material.suppliedHash
      : createHash('sha256').update(material.bytes).digest('hex')
    setHasher.update(`${mode}:${modeHash}\n`)
  }
  return setHasher.digest('hex')
}

export function buildSkinStateCacheKeyV2({ imageSetHash, modelVersion }) {
  const parts = scanCacheKeyParts({
    imageSetHash,
    modelVersion,
    promptVersion: VISION_PROMPT_SUITE_VERSION,
  })
  return createHash('sha256')
    .update([SKIN_STATE_RUNNER_VERSION, ...parts].join('|'))
    .digest('hex')
}

function assertVisionCall(visionCall) {
  if (typeof visionCall !== 'function') {
    throw new TypeError(
      'visionCall must be an async function receiving {module_id, system_prompt, prompt_version, images, input}',
    )
  }
}

function buildAbsoluteImageInput(scan, morphologyExclusionMap = null) {
  return {
    task:
      'Populate Skin State evidence from the five supplied images using absolute image appearance only.',
    scoring_context: {
      absolute_image_measurement: true,
      patient_history_available: false,
      treatment_history_available: false,
      dynamic_questions_available: false,
      capture_type_available_to_model: false,
      external_qc_layer_used: false,
      fixed_device_capture_assumption: true,
    },
    scan_meta: {
      scan_id: scan.scan_id,
      image_set_hash: scan.image_set_hash,
      modes_received: [...IMAGE_MODES],
      authoritative_mode_order: [...IMAGE_MODES],
      canonical_zones: [...FACE_ZONE_IDS],
    },
    morphology_exclusion_map: morphologyExclusionMap,
    output_schema_version: VISION_EVIDENCE_SCHEMA_VERSION,
  }
}

async function cacheGet(cache, key) {
  if (!cache || typeof cache.get !== 'function') return null
  return (await cache.get(key)) ?? null
}

async function cacheSet(cache, key, value) {
  if (!cache || typeof cache.set !== 'function') return
  await cache.set(key, value)
}

async function persistImmutable(persistence, assessmentId, value) {
  if (!persistence) return { persisted: false, reason: 'no_persistence_adapter' }
  if (typeof persistence.createImmutable !== 'function') {
    throw new TypeError(
      'persistence must expose async createImmutable(assessmentId, value)',
    )
  }
  await persistence.createImmutable(assessmentId, value)
  return { persisted: true, assessment_id: assessmentId }
}

export async function runSkinStateV2({
  scanId,
  captureType = 'baseline',
  pairedBaselineScanId = null,
  imagesByMode,
  imageSetHash = null,
  modelVersion,
  visionCall,
  // Retained only for backward API compatibility. It is deliberately ignored
  // by image scoring and may be used later by treatment selection.
  patientContext = null,
  cache = null,
  persistence = null,
  includeRawModuleOutputs = false,
  forceRefresh = false,
  concurrency = 'parallel',
} = {}) {
  assertVisionCall(visionCall)
  if (!scanId || typeof scanId !== 'string') throw new Error('scanId is required')
  if (!['baseline', 'post_treatment'].includes(captureType)) {
    throw new Error('captureType must be baseline or post_treatment')
  }
  if (!modelVersion || typeof modelVersion !== 'string') {
    throw new Error('modelVersion is required')
  }
  if (!imagesByMode || typeof imagesByMode !== 'object') {
    throw new Error('imagesByMode is required')
  }
  for (const mode of IMAGE_MODES) {
    if (!(mode in imagesByMode)) throw new Error(`Missing image mode: ${mode}`)
  }

  const resolvedImageSetHash =
    imageSetHash || computeImageSetHashV2(imagesByMode)
  const cacheKey = buildSkinStateCacheKeyV2({
    imageSetHash: resolvedImageSetHash,
    modelVersion,
  })

  if (!forceRefresh) {
    const cached = await cacheGet(cache, cacheKey)
    if (cached) {
      return {
        ...cached,
        cache: { key: cacheKey, hit: true },
      }
    }
  }

  const authoritativeScan = {
    scan_id: scanId,
    image_set_hash: resolvedImageSetHash,
    capture_type: captureType,
    paired_baseline_scan_id: pairedBaselineScanId,
  }

  const morphologyRaw = await visionCall({
    module_id: 'morphology',
    system_prompt: SYSTEM_PROMPT_MORPHOLOGY_EXCLUSION_MAP_V3,
    prompt_version: MORPHOLOGY_EXCLUSION_PROMPT_VERSION,
    images: imagesByMode,
    input: {
      ...buildAbsoluteImageInput(authoritativeScan),
      task:
        'Create the shared morphology and exclusion map before specialist feature scoring.',
    },
  })
  const morphologyExclusionMap = parseStrictJsonOutput(
    morphologyRaw,
    'morphology vision output',
  )

  const moduleInput = buildAbsoluteImageInput(
    authoritativeScan,
    morphologyExclusionMap,
  )

  const runModule = async ([moduleId, definition]) => {
    const output = await visionCall({
      module_id: moduleId,
      system_prompt: definition.systemPrompt,
      prompt_version: definition.promptVersion,
      images: imagesByMode,
      input: moduleInput,
    })
    return [moduleId, output]
  }

  const entries = Object.entries(VISION_MODULES_V2)
  const moduleResults =
    concurrency === 'sequential'
      ? await entries.reduce(async (promise, entry) => {
          const accumulated = await promise
          accumulated.push(await runModule(entry))
          return accumulated
        }, Promise.resolve([]))
      : await Promise.all(entries.map(runModule))

  const moduleOutputs = Object.fromEntries(moduleResults)
  const evidencePacket = mergeVisionEvidencePacketsV2(moduleOutputs, {
    morphologyExclusionMap,
    scanOverride: authoritativeScan,
    modelVersion,
  })
  const skinState = buildSkinStateV2(evidencePacket)

  const assessmentId = createHash('sha256')
    .update(
      [
        scanId,
        resolvedImageSetHash,
        modelVersion,
        VISION_PROMPT_SUITE_VERSION,
        skinState.scoring_execution.formula_config_version,
      ].join('|'),
    )
    .digest('hex')

  skinState.scoring_execution.assessment_id = assessmentId
  skinState.scoring_execution.immutable_assessment = true
  skinState.scoring_execution.history_excluded_from_image_scoring = true
  skinState.scoring_execution.dynamic_questions_used = false
  skinState.scoring_execution.external_qc_layer_used = false

  const result = {
    runner_version: SKIN_STATE_RUNNER_VERSION,
    evidence_packet: evidencePacket,
    skin_state: skinState,
    assessment_contract: {
      assessment_id: assessmentId,
      immutable: true,
      same_assessment_must_be_reused_by_diagnosis_planning_and_reporting: true,
      patient_context_received_but_excluded_from_vision:
        patientContext !== null,
    },
    cache: { key: cacheKey, hit: false },
  }

  if (includeRawModuleOutputs) {
    result.raw_module_outputs = {
      morphology: morphologyRaw,
      ...moduleOutputs,
    }
  }

  result.persistence = await persistImmutable(
    persistence,
    assessmentId,
    result,
  )
  await cacheSet(cache, cacheKey, result)
  return result
}
