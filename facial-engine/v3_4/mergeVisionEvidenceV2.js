import {
  CORE_FEATURE_IDS,
  IMAGE_MODES,
  VISION_EVIDENCE_SCHEMA_VERSION,
} from './skinStateV2.schema.js'
import {
  FACE_ZONE_ATLAS_VERSION,
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'
import { CORE_FEATURE_FORMULAS_V2 } from './skinStateScoreMapperV2.js'
import {
  MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION,
  MORPHOLOGY_FLAG_IDS_V3,
} from './visionMorphologyExclusionMapV3.js'
import {
  reconcileVisionEvidenceWithMorphologyV3,
} from './reconcileVisionEvidenceV3.js'

export const VISION_EVIDENCE_MERGER_VERSION =
  'aia_vision_evidence_merger_v3.3.0'
export const VISION_PROMPT_SUITE_VERSION =
  'aia_vision_prompt_suite_v3.3.0'

export const VISION_MODULE_FEATURE_OWNERSHIP_V2 = Object.freeze({
  surface: [
    'active_inflammatory_acne',
    'comedonal_congestion',
    'oiliness',
    'pore_visibility',
    'texture_roughness',
    'luminosity_loss',
  ],
  inflammation: [
    'erythema_redness',
    'barrier_stress',
    'visual_dehydration',
  ],
  pigment: [
    'visible_pigmentation',
    'underlying_pigment_support',
    'peri_orbital_concern',
    'lip_pigmentation',
  ],
  structure: [
    'fine_line_visibility',
    'visible_laxity',
    'firmness_appearance_loss',
  ],
})

const ASSESSMENT_STATUS_VALUES = new Set([
  'assessable',
  'partially_assessable',
  'not_assessable',
])
const MODE_AGREEMENT_VALUES = new Set([
  'strong',
  'partial',
  'conflicting',
  'single_mode_only',
])
const MORPHOLOGY_FLAG_VALUES = new Set(['true', 'false', 'uncertain'])

const clone = (value) => JSON.parse(JSON.stringify(value))

export function parseStrictJsonOutput(value, context = 'model output') {
  if (value && typeof value === 'object') return clone(value)
  if (typeof value !== 'string') {
    throw new TypeError(`${context} must be a JSON object or JSON string`)
  }
  const unwrapped = value
    .trim()
    .replace(/^```(?:json)?\s*/i, '')
    .replace(/\s*```$/i, '')
    .trim()
  try {
    return JSON.parse(unwrapped)
  } catch (error) {
    throw new Error(`${context} is not valid JSON: ${error.message}`)
  }
}

function assertStringArray(value, context) {
  if (!Array.isArray(value) || value.some((item) => typeof item !== 'string')) {
    throw new Error(`${context} must be an array of strings`)
  }
}

function validateFiveIncrement(value, context, { allowNull = false } = {}) {
  if (allowNull && (value === null || value === undefined)) return
  if (
    !Number.isInteger(value) ||
    value < 0 ||
    value > 100 ||
    value % 5 !== 0
  ) {
    throw new Error(`${context} must be an integer from 0 to 100 in increments of 5`)
  }
}

function normalizeMeasurementPrimitives(component) {
  const supplied = component.measurement_primitives ?? {}
  const defaults = {
    coverage_0_to_100: component.grade_0_to_5 * 20,
    contrast_0_to_100: component.grade_0_to_5 * 20,
    cross_mode_corroboration_0_to_100:
      component.corroboration_modes?.length > 0 ? 70 : 40,
    regional_salience_0_to_100: component.grade_0_to_5 * 20,
  }
  return {
    ...defaults,
    ...supplied,
  }
}

function validateComponent(component, context) {
  if (!component || typeof component !== 'object') {
    throw new Error(`${context} must be an object`)
  }
  const grade = component.grade_0_to_5
  if (!Number.isFinite(grade) || grade < 0 || grade > 5) {
    throw new Error(`${context}.grade_0_to_5 must be a finite number from 0 to 5`)
  }

  component.plausible_grade_range_0_to_5 ??= {
    min: Math.floor(grade),
    max: Math.ceil(grade),
  }
  const range = component.plausible_grade_range_0_to_5
  if (
    !Number.isInteger(range.min) ||
    !Number.isInteger(range.max) ||
    range.min < 0 ||
    range.max > 5 ||
    range.min > range.max ||
    grade < range.min ||
    grade > range.max
  ) {
    throw new Error(`${context}.plausible_grade_range_0_to_5 is invalid`)
  }

  component.assessment_confidence_0_to_100 ??= 80
  const confidence = component.assessment_confidence_0_to_100
  if (!Number.isFinite(Number(confidence)) || confidence < 0 || confidence > 100) {
    throw new Error(`${context}.assessment_confidence_0_to_100 is invalid`)
  }

  component.measurement_primitives = normalizeMeasurementPrimitives(component)
  for (const [field, value] of Object.entries(component.measurement_primitives)) {
    validateFiveIncrement(value, `${context}.measurement_primitives.${field}`)
  }

  assertStringArray(component.evidence_modes ?? [], `${context}.evidence_modes`)
  assertStringArray(
    component.corroboration_modes ?? [],
    `${context}.corroboration_modes`,
  )
  if (typeof component.reason !== 'string') {
    throw new Error(`${context}.reason must be a string`)
  }
}

function normalizeAndValidateFeature(featureId, feature, moduleId) {
  const formula = CORE_FEATURE_FORMULAS_V2[featureId]
  if (!formula) throw new Error(`Unknown core feature ${featureId}`)
  if (!feature || typeof feature !== 'object') {
    throw new Error(`${moduleId} did not return feature ${featureId}`)
  }
  if (feature.feature_id && feature.feature_id !== featureId) {
    throw new Error(
      `${moduleId}.${featureId}.feature_id must equal ${featureId}; received ${feature.feature_id}`,
    )
  }

  const normalized = clone(feature)
  normalized.feature_id = featureId
  normalized.applicable_zones = [...formula.applicable_zones]
  normalized.lead_modes = [...formula.lead_modes]
  normalized.support_modes = [...(formula.support_modes ?? [])]
  normalized.treatment_relevance =
    formula.treatment_relevance ?? normalized.treatment_relevance ?? 'direct'
  normalized.global_artifact_flags ??= []
  normalized.global_evidence_summary ??= ''

  assertStringArray(
    normalized.global_artifact_flags,
    `${moduleId}.${featureId}.global_artifact_flags`,
  )
  if (typeof normalized.global_evidence_summary !== 'string') {
    throw new Error(`${moduleId}.${featureId}.global_evidence_summary must be a string`)
  }
  if (!normalized.zones || typeof normalized.zones !== 'object') {
    throw new Error(`${moduleId}.${featureId}.zones must be an object`)
  }

  for (const zoneId of formula.applicable_zones) {
    const zone = normalized.zones[zoneId]
    const context = `${moduleId}.${featureId}.${zoneId}`
    if (!zone || typeof zone !== 'object') {
      throw new Error(`Missing required zone evidence: ${context}`)
    }
    if (!ASSESSMENT_STATUS_VALUES.has(zone.assessment_status)) {
      throw new Error(`${context}.assessment_status is invalid`)
    }
    if (!MODE_AGREEMENT_VALUES.has(zone.mode_agreement)) {
      throw new Error(`${context}.mode_agreement is invalid`)
    }
    zone.visibility_fraction_0_to_100 ??=
      zone.assessment_status === 'assessable'
        ? 100
        : zone.assessment_status === 'partially_assessable'
          ? 60
          : 0
    validateFiveIncrement(
      zone.visibility_fraction_0_to_100,
      `${context}.visibility_fraction_0_to_100`,
    )
    assertStringArray(zone.artifact_flags ?? [], `${context}.artifact_flags`)
    if (typeof zone.evidence_summary !== 'string') {
      throw new Error(`${context}.evidence_summary must be a string`)
    }
    if (!zone.components || typeof zone.components !== 'object') {
      throw new Error(`${context}.components must be an object`)
    }
    for (const componentId of Object.keys(formula.components)) {
      validateComponent(
        zone.components[componentId],
        `${context}.components.${componentId}`,
      )
    }
  }

  return normalized
}

function validatePacketHeader(packet, moduleId) {
  if (packet.schema_version !== VISION_EVIDENCE_SCHEMA_VERSION) {
    throw new Error(
      `${moduleId}: expected schema ${VISION_EVIDENCE_SCHEMA_VERSION}; received ${packet.schema_version}`,
    )
  }
  if (packet.zone_atlas_version !== FACE_ZONE_ATLAS_VERSION) {
    throw new Error(
      `${moduleId}: expected zone atlas ${FACE_ZONE_ATLAS_VERSION}; received ${packet.zone_atlas_version}`,
    )
  }
  if (!packet.features || typeof packet.features !== 'object') {
    throw new Error(`${moduleId}.features must be an object`)
  }
}

export function validateMorphologyExclusionMapV3(value) {
  const map = parseStrictJsonOutput(value, 'morphology exclusion map')
  if (map.schema_version !== MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION) {
    throw new Error(
      `Expected morphology schema ${MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION}; received ${map.schema_version}`,
    )
  }
  if (!map.zones || typeof map.zones !== 'object') {
    throw new Error('morphology exclusion map must contain zones')
  }
  for (const zoneId of FACE_ZONE_IDS) {
    const zone = map.zones[zoneId]
    if (!zone) throw new Error(`Morphology map missing zone: ${zoneId}`)
    if (!ASSESSMENT_STATUS_VALUES.has(zone.assessment_status)) {
      throw new Error(`Morphology ${zoneId}.assessment_status is invalid`)
    }
    validateFiveIncrement(
      zone.visibility_fraction_0_to_100,
      `Morphology ${zoneId}.visibility_fraction_0_to_100`,
    )
    zone.flags ??= {}
    for (const flagId of MORPHOLOGY_FLAG_IDS_V3) {
      zone.flags[flagId] ??= 'uncertain'
      if (!MORPHOLOGY_FLAG_VALUES.has(zone.flags[flagId])) {
        throw new Error(`Morphology ${zoneId}.${flagId} is invalid`)
      }
    }
    zone.exclude_from_features ??= []
    assertStringArray(
      zone.exclude_from_features,
      `Morphology ${zoneId}.exclude_from_features`,
    )
    zone.notes ??= ''
  }
  return map
}

function unionStrings(values) {
  return [...new Set(values.flat().filter((value) => typeof value === 'string'))]
}

export function mergeVisionEvidencePacketsV2(
  moduleOutputs,
  {
    morphologyExclusionMap,
    scanOverride = {},
    modelVersion = '',
    createdAtIso = new Date().toISOString(),
    includeModuleAudit = true,
    rejectUnexpectedFeatures = true,
  } = {},
) {
  if (!moduleOutputs || typeof moduleOutputs !== 'object') {
    throw new TypeError('moduleOutputs must be an object')
  }
  const morphologyMap = validateMorphologyExclusionMapV3(
    morphologyExclusionMap,
  )

  const parsed = {}
  for (const moduleId of Object.keys(VISION_MODULE_FEATURE_OWNERSHIP_V2)) {
    if (!(moduleId in moduleOutputs)) {
      throw new Error(`Missing vision module output: ${moduleId}`)
    }
    const packet = parseStrictJsonOutput(
      moduleOutputs[moduleId],
      `${moduleId} vision output`,
    )
    validatePacketHeader(packet, moduleId)
    parsed[moduleId] = packet

    if (rejectUnexpectedFeatures) {
      const allowed = new Set(VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId])
      const unexpected = Object.keys(packet.features).filter(
        (featureId) => !allowed.has(featureId),
      )
      if (unexpected.length) {
        throw new Error(
          `${moduleId} returned unexpected features: ${unexpected.join(', ')}`,
        )
      }
    }
  }

  const rawFeatures = {}
  for (const [moduleId, featureIds] of Object.entries(
    VISION_MODULE_FEATURE_OWNERSHIP_V2,
  )) {
    for (const featureId of featureIds) {
      if (rawFeatures[featureId]) {
        throw new Error(`Duplicate feature ownership detected: ${featureId}`)
      }
      rawFeatures[featureId] = normalizeAndValidateFeature(
        featureId,
        parsed[moduleId].features[featureId],
        moduleId,
      )
    }
  }

  const missingFeatures = CORE_FEATURE_IDS.filter(
    (featureId) => !rawFeatures[featureId],
  )
  if (missingFeatures.length) {
    throw new Error(`Merged packet is missing: ${missingFeatures.join(', ')}`)
  }

  const packets = Object.values(parsed)
  const receivedModes = unionStrings(
    packets.map((packet) => packet.scan?.modes_received ?? []),
  )
  for (const mode of IMAGE_MODES) {
    if (!receivedModes.includes(mode)) {
      throw new Error(`Merged evidence is missing required mode: ${mode}`)
    }
  }

  const reconciliation = reconcileVisionEvidenceWithMorphologyV3(
    rawFeatures,
    morphologyMap,
  )

  const merged = {
    schema_version: VISION_EVIDENCE_SCHEMA_VERSION,
    zone_atlas_version: FACE_ZONE_ATLAS_VERSION,
    scan: {
      scan_id: scanOverride.scan_id ?? packets[0].scan?.scan_id ?? '',
      image_set_hash:
        scanOverride.image_set_hash ?? packets[0].scan?.image_set_hash ?? '',
      capture_type: scanOverride.capture_type ?? 'baseline',
      paired_baseline_scan_id:
        scanOverride.paired_baseline_scan_id ?? null,
      modes_received: [...IMAGE_MODES],
      fixed_device_capture_assumption: true,
      external_qc_layer_used: false,
    },
    model_execution: {
      model_version:
        modelVersion ||
        unionStrings(
          packets.map((packet) => [packet.model_execution?.model_version ?? '']),
        )
          .filter(Boolean)
          .join('+'),
      prompt_version: VISION_PROMPT_SUITE_VERSION,
      merger_version: VISION_EVIDENCE_MERGER_VERSION,
      reconciliation_version: reconciliation.reconciliation_version,
      created_at_iso: createdAtIso,
    },
    morphology_exclusion_map: morphologyMap,
    reconciliation_audit: reconciliation.audit,
    features: reconciliation.features,
  }

  if (includeModuleAudit) {
    merged.model_execution.modules = Object.fromEntries(
      Object.entries(parsed).map(([moduleId, packet]) => [
        moduleId,
        {
          prompt_version: packet.model_execution?.prompt_version ?? '',
          model_version: packet.model_execution?.model_version ?? '',
          created_at_iso: packet.model_execution?.created_at_iso ?? '',
          feature_ids: [...VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]],
        },
      ]),
    )
    merged.model_execution.modules.morphology = {
      prompt_version: morphologyMap.prompt_version ?? '',
      feature_ids: [],
      purpose: 'shared morphology and exclusion map',
    }
  }

  return merged
}
