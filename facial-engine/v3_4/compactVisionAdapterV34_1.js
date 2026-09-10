import { FACE_ZONE_IDS, FACE_ZONE_ATLAS_VERSION } from './faceZoneAtlasV2.js'
import {
  IMAGE_MODES,
  ARTIFACT_FLAGS,
  CORE_FEATURE_IDS,
  VISION_EVIDENCE_SCHEMA_VERSION,
} from './skinStateV2.schema.js'
import { CORE_FEATURE_FORMULAS_V2 } from './skinStateScoreMapperV2.js'
import { VISION_MODULE_FEATURE_OWNERSHIP_V2 } from './mergeVisionEvidenceV2.js'
import {
  MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION,
  MORPHOLOGY_FLAG_IDS_V3,
} from './visionMorphologyExclusionMapV3.js'

/**
 * V3.4.8 compact wire
 * -------------------
 * Clinical semantics and downstream scoring are unchanged.
 * The specialist wire is columnar: every clinical field has its own named
 * array aligned to the authoritative applicable-zone order. This preserves
 * all measurements while dramatically reducing repeated JSON keys.
 *
 * Surface is split into six single-feature shards. Sharding changes transport only:
 * each shard sees the same five source images and shared morphology map, and
 * the decoded features are merged before the original V3.4 evidence merger and
 * deterministic Skin State mapper run.
 */
export const COMPACT_VISION_WIRE_VERSION = 'aia_compact_vision_wire_v3.4.8'

const STATUS = ['assessable', 'partially_assessable', 'not_assessable']
const AGREEMENT = ['strong', 'partial', 'conflicting', 'single_mode_only']
const MORPH_FLAG_VALUE = ['false', 'true', 'uncertain']

const MODE_TO_CODE = Object.freeze({
  red: 'r',
  subsurface_polarized: 'ss',
  surface_polarized: 'sp',
  white: 'w',
  woods_uv: 'uv',
})
const CODE_TO_MODE = Object.freeze(Object.fromEntries(
  Object.entries(MODE_TO_CODE).map(([mode, code]) => [code, mode]),
))

const ARTIFACT_TO_CODE = Object.freeze({
  makeup_or_tint: 'mk',
  lip_product: 'lp',
  beard_or_stubble: 'bd',
  hair_occlusion: 'hr',
  specular_glare: 'gl',
  deep_shadow: 'sh',
  motion_blur: 'mb',
  focus_loss: 'fl',
  exposure_mismatch: 'ex',
  mode_misclassification: 'mm',
  post_treatment_residue: 'pr',
  other: 'ot',
})
const CODE_TO_ARTIFACT = Object.freeze(Object.fromEntries(
  Object.entries(ARTIFACT_TO_CODE).map(([flag, code]) => [code, flag]),
))

const MODE_CODE_VALUES = Object.freeze(Object.values(MODE_TO_CODE))
const ARTIFACT_CODE_VALUES = Object.freeze(Object.values(ARTIFACT_TO_CODE))

function integerInRange(value, min, max, context) {
  const n = Number(value)
  if (!Number.isInteger(n) || n < min || n > max) {
    throw new Error(`${context} must be an integer ${min}..${max}; received ${value}`)
  }
  return n
}

function fiveStep(value, context) {
  const n = integerInRange(value, 0, 100, context)
  if (n % 5 !== 0) throw new Error(`${context} must be a multiple of 5; received ${value}`)
  return n
}

function expectArray(value, context) {
  if (!Array.isArray(value)) throw new Error(`${context} must be an array`)
  return value
}

function expectObject(value, context) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    throw new Error(`${context} must be an object`)
  }
  return value
}

function exactNumericKeys(value, expectedIndexes, context) {
  const object = expectObject(value, context)
  const expected = expectedIndexes.map(String)
  const actual = Object.keys(object).sort((a, b) => Number(a) - Number(b))
  if (actual.length !== expected.length || expected.some((key) => !Object.prototype.hasOwnProperty.call(object, key))) {
    throw new Error(`${context} must contain exact keys ${JSON.stringify(expected)}; received ${JSON.stringify(actual)}`)
  }
  return object
}

function exactArray(value, count, context) {
  const a = expectArray(value, context)
  if (a.length !== count) throw new Error(`${context} must contain ${count} values; received ${a.length}`)
  return a
}

function decodeModeCodes(value, context) {
  const result = []
  for (const [i, raw] of expectArray(value ?? [], context).entries()) {
    const code = String(raw)
    const mode = CODE_TO_MODE[code]
    if (!mode) throw new Error(`${context}[${i}] invalid mode code ${JSON.stringify(code)}`)
    if (!result.includes(mode)) result.push(mode)
  }
  return result
}

function decodeArtifactCodes(value, context) {
  const result = []
  for (const [i, raw] of expectArray(value ?? [], context).entries()) {
    const code = String(raw)
    const flag = CODE_TO_ARTIFACT[code]
    if (!flag) throw new Error(`${context}[${i}] invalid artifact code ${JSON.stringify(code)}`)
    if (!result.includes(flag)) result.push(flag)
  }
  return result
}

function regexEscape(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function commonClinicalPreamble(originalPrompt) {
  const text = String(originalPrompt ?? '')
  const marker = text.search(/\nFEATURE(?:-SPECIFIC)? RULES\s*\n/i)
  const beforeRules = marker >= 0 ? text.slice(0, marker) : text
  return beforeRules
    .replace(/JSON OUTPUT SHAPE[\s\S]*$/i, '')
    .replace(/\nROLE\s*[\s\S]*?(?=\nDO NOT output final|\nINPUTS)/i, '\n')
    .replace(/Return STRICT JSON only\./gi, '')
    .replace(/10\) For not_assessable zones,[^\n]*\n?/i, '10) For not_assessable zones, use status not_assessable and grade 0 for every required component.\n')
    .trim()
}

function selectedClinicalFeatureRules(originalPrompt, moduleId, featureIndexes) {
  const text = String(originalPrompt ?? '')
  const markerMatch = text.match(/\nFEATURE(?:-SPECIFIC)? RULES\s*\n/i)
  if (!markerMatch || markerMatch.index === undefined) return ''
  const rules = text.slice(markerMatch.index + markerMatch[0].length)
    .replace(/\nOUTPUT DISCIPLINE[\s\S]*$/i, '')
  const featureIds = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  const sections = []
  for (const fi of featureIndexes) {
    const featureId = featureIds[fi]
    const re = new RegExp(
      `(?:^|\\n)\\d+\\)\\s+${regexEscape(featureId)}\\s*\\n([\\s\\S]*?)(?=\\n\\d+\\)\\s+[a-z_]+\\s*\\n|$)`,
      'i',
    )
    const match = rules.match(re)
    if (match) sections.push(`${featureId}\n${match[1].trim()}`)
  }
  return sections.join('\n\n')
}

function featureSpecification(moduleId, featureIndexes) {
  const featureIds = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  return featureIndexes.map((featureIndex) => {
    const featureId = featureIds[featureIndex]
    const formula = CORE_FEATURE_FORMULAS_V2[featureId]
    return [
      `F${featureIndex}=${featureId}`,
      `zones_in_order=${JSON.stringify(formula.applicable_zones)}`,
      `component_keys=${JSON.stringify(Object.fromEntries(Object.keys(formula.components).map((componentId, i) => [String(i), componentId])))}`,
      `lead=${JSON.stringify(formula.lead_modes ?? [])}`,
      `support=${JSON.stringify(formula.support_modes ?? [])}`,
    ].join('\n')
  }).join('\n\n')
}

function compactSpecialistOutputContract(moduleId, featureIndexes) {
  return `
COMPACT OUTPUT OVERRIDE — THIS REPLACES EVERY EARLIER OUTPUT-SHAPE INSTRUCTION.
Return exactly one JSON object shaped as {"v":4,"f":{...}}. No prose or markdown.

This is a COLUMNAR wire, not a tuple format.
- f is keyed by the ORIGINAL feature index shown below.
- For each feature, every array position corresponds to the exact zones_in_order.
- Feature object fields: s=status[], v=visibility[], m=mode_agreement[], a=artifacts[][], c=components{}.
- Each component object contains arrays g,l,h,q,v,t,x,r,e,o aligned to the same zone order.

Codes:
- s: 0=assessable, 1=partially_assessable, 2=not_assessable
- m: 0=strong, 1=partial, 2=conflicting, 3=single_mode_only
- image modes in e/o: r=red, ss=subsurface_polarized, sp=surface_polarized, w=white, uv=woods_uv
- artifacts in a: ${Object.entries(ARTIFACT_TO_CODE).map(([flag, code]) => `${code}=${flag}`).join(', ')}

Component arrays:
- g anchored grade 0..5
- l plausible low grade 0..5
- h plausible high grade 0..5
- q assessment confidence 0..100 integer
- v coverage 0..100 in increments of 5
- t contrast 0..100 in increments of 5
- x cross-mode corroboration 0..100 in increments of 5
- r regional salience 0..100 in increments of 5
- e evidence mode codes per zone
- o corroboration mode codes per zone

Feature/zone/component mapping for THIS SHARD ONLY:
${featureSpecification(moduleId, featureIndexes)}

Exact f keys for this shard: ${JSON.stringify(featureIndexes.map(String))}.
Return every required array at exactly the zone length shown for its feature.
For s=2 at a zone, set feature visibility v=0 and component g/l/h=0 at that same array position.
For s=1, give the best visible estimate and represent uncertainty through q/l/h and visibility; do not automatically lower severity.
Clinical values remain image-grounded. The schema constrains grammar and domains only; it does not prescribe findings.`.trim()
}

function compactMorphologyOutputContract() {
  return `
COMPACT OUTPUT OVERRIDE — THIS REPLACES THE EARLIER OUTPUT SHAPE.
Return exactly {"v":4,"z":[...]} and nothing else.
Zone order: ${JSON.stringify(FACE_ZONE_IDS)}.
Morphology flag order: ${JSON.stringify(MORPHOLOGY_FLAG_IDS_V3)}.
Core-feature exclusion indexes: ${JSON.stringify(Object.fromEntries(CORE_FEATURE_IDS.map((feature, i) => [String(i), feature])))}.
Each zone is an OBJECT with exactly s,v,f,x:
- s: 0=assessable, 1=partially_assessable, 2=not_assessable
- v: visibility 0..100 in increments of 5; s=2 => v=0
- f: exactly ${MORPHOLOGY_FLAG_IDS_V3.length} flag values in fixed order; 0=false, 1=true, 2=uncertain
- x: zero or more Core Skin State feature indexes to exclude in that zone
Return exactly ${FACE_ZONE_IDS.length} zones. No prose, markdown or extra keys.`.trim()
}

export function compactSpecialistShardPlanV34_7(moduleId) {
  const featureIds = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  if (!featureIds) return null
  // Surface is the only module large enough to be a demonstrated latency bottleneck.
  // The groupings balance required component objects while preserving all features.
  if (moduleId === 'surface') return [[0], [1], [2], [3], [4], [5]]
  return [featureIds.map((_, i) => i)]
}

export function buildCompactVisionPromptV34_7(moduleId, originalPrompt, featureIndexes = null) {
  if (moduleId === 'morphology') {
    const clinical = String(originalPrompt ?? '').replace(/OUTPUT SHAPE[\s\S]*$/i, '').trim()
    return `${clinical}\n\n${compactMorphologyOutputContract()}`
  }
  const all = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  if (!all) throw new Error(`No compact feature specification for module ${moduleId}`)
  const indexes = featureIndexes ?? all.map((_, i) => i)
  const selectedRules = selectedClinicalFeatureRules(originalPrompt, moduleId, indexes)
  return [
    commonClinicalPreamble(originalPrompt),
    selectedRules ? `FEATURE RULES FOR THIS SHARD\n${selectedRules}` : '',
    compactSpecialistOutputContract(moduleId, indexes),
  ].filter(Boolean).join('\n\n')
}

// Old export retained for any stale caller; it emits the full module contract.
export function buildCompactVisionPromptV34_1(moduleId, originalPrompt) {
  return buildCompactVisionPromptV34_7(moduleId, originalPrompt)
}

export function compactModelInputV34_1(moduleId, input = {}) {
  if (moduleId === 'morphology') {
    return {
      scan_id: input?.scan_meta?.scan_id ?? '',
      fixed_device_capture: true,
      authoritative_mode_order: IMAGE_MODES,
    }
  }

  const morphology = input?.morphology_exclusion_map
  const compactMorph = morphology?.zones
    ? FACE_ZONE_IDS.map((zoneId) => {
        const zone = morphology.zones?.[zoneId] ?? {}
        const statusIndex = Math.max(0, STATUS.indexOf(zone.assessment_status))
        return {
          s: statusIndex,
          v: Number(zone.visibility_fraction_0_to_100 ?? 100),
          f: MORPHOLOGY_FLAG_IDS_V3.map((flagId) => {
            const index = MORPH_FLAG_VALUE.indexOf(zone.flags?.[flagId] ?? 'uncertain')
            return index < 0 ? 2 : index
          }),
          x: (zone.exclude_from_features ?? [])
            .map((featureId) => CORE_FEATURE_IDS.indexOf(featureId))
            .filter((index) => index >= 0),
        }
      })
    : null

  return {
    scan_id: input?.scan_meta?.scan_id ?? '',
    morphology_zone_order: FACE_ZONE_IDS,
    morphology_flag_order: MORPHOLOGY_FLAG_IDS_V3,
    morphology: compactMorph,
  }
}

export function decodeCompactMorphologyV34_1(value, input = {}) {
  const zones = exactArray(value?.z, FACE_ZONE_IDS.length, 'compact morphology.z')
  return {
    schema_version: MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION,
    scan_id: input?.scan_meta?.scan_id ?? input?.scan_id ?? '',
    zones: Object.fromEntries(FACE_ZONE_IDS.map((zoneId, zi) => {
      const item = expectObject(zones[zi], `morphology.z[${zi}]`)
      const s = integerInRange(item.s, 0, 2, `morphology.z[${zi}].s`)
      const visibility = fiveStep(item.v, `morphology.z[${zi}].v`)
      const flags = exactArray(item.f, MORPHOLOGY_FLAG_IDS_V3.length, `morphology.z[${zi}].f`)
      const exclusions = expectArray(item.x ?? [], `morphology.z[${zi}].x`)
        .map((v, i) => CORE_FEATURE_IDS[integerInRange(v, 0, CORE_FEATURE_IDS.length - 1, `morphology.z[${zi}].x[${i}]`)])
      return [zoneId, {
        assessment_status: STATUS[s],
        visibility_fraction_0_to_100: s === 2 ? 0 : visibility,
        flags: Object.fromEntries(MORPHOLOGY_FLAG_IDS_V3.map((flagId, fi) => [
          flagId,
          MORPH_FLAG_VALUE[integerInRange(flags[fi], 0, 2, `morphology.z[${zi}].f[${fi}]`)],
        ])),
        exclude_from_features: [...new Set(exclusions)],
        notes: '',
      }]
    })),
  }
}

function normalizePlausibleRange(grade, low, high) {
  return { min: Math.min(grade, low, high), max: Math.max(grade, low, high) }
}

function decodeSpecialistColumnar(moduleId, value, { input = {}, modelVersion = '', promptVersion = '' } = {}) {
  const featureIds = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  if (!featureIds) throw new Error(`Unknown specialist module ${moduleId}`)
  const allIndexes = featureIds.map((_, i) => i)
  const featureObject = exactNumericKeys(value?.f, allIndexes, `${moduleId}.f`)
  const features = {}

  for (let fi = 0; fi < featureIds.length; fi++) {
    const featureId = featureIds[fi]
    const formula = CORE_FEATURE_FORMULAS_V2[featureId]
    const zCount = formula.applicable_zones.length
    const f = expectObject(featureObject[String(fi)], `${moduleId}.${featureId}`)
    const sA = exactArray(f.s, zCount, `${moduleId}.${featureId}.s`)
    const vA = exactArray(f.v, zCount, `${moduleId}.${featureId}.v`)
    const mA = exactArray(f.m, zCount, `${moduleId}.${featureId}.m`)
    const aA = exactArray(f.a, zCount, `${moduleId}.${featureId}.a`)
    const componentIds = Object.keys(formula.components)
    const cObj = exactNumericKeys(f.c, componentIds.map((_, i) => i), `${moduleId}.${featureId}.c`)

    const componentColumns = componentIds.map((componentId, ci) => {
      const c = expectObject(cObj[String(ci)], `${moduleId}.${featureId}.${componentId}`)
      return {
        id: componentId,
        g: exactArray(c.g, zCount, `${componentId}.g`),
        l: exactArray(c.l, zCount, `${componentId}.l`),
        h: exactArray(c.h, zCount, `${componentId}.h`),
        q: exactArray(c.q, zCount, `${componentId}.q`),
        v: exactArray(c.v, zCount, `${componentId}.v`),
        t: exactArray(c.t, zCount, `${componentId}.t`),
        x: exactArray(c.x, zCount, `${componentId}.x`),
        r: exactArray(c.r, zCount, `${componentId}.r`),
        e: exactArray(c.e, zCount, `${componentId}.e`),
        o: exactArray(c.o, zCount, `${componentId}.o`),
      }
    })

    const zones = {}
    for (let zi = 0; zi < zCount; zi++) {
      const zoneId = formula.applicable_zones[zi]
      const statusIndex = integerInRange(sA[zi], 0, 2, `${moduleId}.${featureId}.${zoneId}.s`)
      const visibility = fiveStep(vA[zi], `${moduleId}.${featureId}.${zoneId}.v`)
      const agreement = integerInRange(mA[zi], 0, 3, `${moduleId}.${featureId}.${zoneId}.m`)
      const components = {}
      for (const col of componentColumns) {
        let grade = integerInRange(col.g[zi], 0, 5, `${col.id}.g[${zi}]`)
        let low = integerInRange(col.l[zi], 0, 5, `${col.id}.l[${zi}]`)
        let high = integerInRange(col.h[zi], 0, 5, `${col.id}.h[${zi}]`)
        if (statusIndex === 2) grade = low = high = 0
        components[col.id] = {
          grade_0_to_5: grade,
          plausible_grade_range_0_to_5: normalizePlausibleRange(grade, low, high),
          assessment_confidence_0_to_100: integerInRange(col.q[zi], 0, 100, `${col.id}.q[${zi}]`),
          measurement_primitives: {
            coverage_0_to_100: fiveStep(col.v[zi], `${col.id}.v[${zi}]`),
            contrast_0_to_100: fiveStep(col.t[zi], `${col.id}.t[${zi}]`),
            cross_mode_corroboration_0_to_100: fiveStep(col.x[zi], `${col.id}.x[${zi}]`),
            regional_salience_0_to_100: fiveStep(col.r[zi], `${col.id}.r[${zi}]`),
          },
          evidence_modes: decodeModeCodes(col.e[zi], `${col.id}.e[${zi}]`),
          corroboration_modes: decodeModeCodes(col.o[zi], `${col.id}.o[${zi}]`),
          reason: 'compact_columnar_wire_v3_4_7',
        }
      }
      zones[zoneId] = {
        assessment_status: STATUS[statusIndex],
        components,
        mode_agreement: AGREEMENT[agreement],
        artifact_flags: decodeArtifactCodes(aA[zi], `${moduleId}.${featureId}.${zoneId}.a`),
        visibility_fraction_0_to_100: statusIndex === 2 ? 0 : visibility,
        evidence_summary: '',
      }
    }

    features[featureId] = {
      feature_id: featureId,
      zones,
      global_artifact_flags: [],
      global_evidence_summary: '',
    }
  }

  return {
    schema_version: VISION_EVIDENCE_SCHEMA_VERSION,
    zone_atlas_version: FACE_ZONE_ATLAS_VERSION,
    scan: {
      scan_id: input?.scan_meta?.scan_id ?? input?.scan_id ?? '',
      image_set_hash: input?.scan_meta?.image_set_hash ?? '',
      modes_received: [...IMAGE_MODES],
    },
    model_execution: {
      model_version: modelVersion,
      prompt_version: promptVersion,
      created_at_iso: new Date().toISOString(),
    },
    features,
  }
}

function boundedInteger(minimum, maximum, multipleOf = null) {
  const schema = { type: 'integer', minimum, maximum }
  if (multipleOf !== null) schema.multipleOf = multipleOf
  return schema
}
function enumString(values) { return { type: 'string', enum: [...values] } }
function boundedArray(itemSchema, minItems, maxItems) {
  return { type: 'array', minItems, maxItems, items: itemSchema }
}
function keyedObject(indexes, valueSchemaFactory) {
  const keys = indexes.map(String)
  return {
    type: 'object',
    properties: Object.fromEntries(indexes.map((index) => [String(index), valueSchemaFactory(index)])),
    required: keys,
    additionalProperties: false,
  }
}

function componentColumnSchema(zCount) {
  return {
    type: 'object',
    properties: {
      g: boundedArray(boundedInteger(0, 5), zCount, zCount),
      l: boundedArray(boundedInteger(0, 5), zCount, zCount),
      h: boundedArray(boundedInteger(0, 5), zCount, zCount),
      q: boundedArray(boundedInteger(0, 100), zCount, zCount),
      v: boundedArray(boundedInteger(0, 100, 5), zCount, zCount),
      t: boundedArray(boundedInteger(0, 100, 5), zCount, zCount),
      x: boundedArray(boundedInteger(0, 100, 5), zCount, zCount),
      r: boundedArray(boundedInteger(0, 100, 5), zCount, zCount),
      e: boundedArray(boundedArray(enumString(MODE_CODE_VALUES), 0, IMAGE_MODES.length), zCount, zCount),
      o: boundedArray(boundedArray(enumString(MODE_CODE_VALUES), 0, IMAGE_MODES.length), zCount, zCount),
    },
    required: ['g', 'l', 'h', 'q', 'v', 't', 'x', 'r', 'e', 'o'],
    additionalProperties: false,
  }
}

function featureColumnSchema(featureId) {
  const formula = CORE_FEATURE_FORMULAS_V2[featureId]
  const zCount = formula.applicable_zones.length
  const componentIndexes = Object.keys(formula.components).map((_, i) => i)
  return {
    type: 'object',
    properties: {
      s: boundedArray(boundedInteger(0, 2), zCount, zCount),
      v: boundedArray(boundedInteger(0, 100, 5), zCount, zCount),
      m: boundedArray(boundedInteger(0, 3), zCount, zCount),
      a: boundedArray(boundedArray(enumString(ARTIFACT_CODE_VALUES), 0, ARTIFACT_FLAGS.length), zCount, zCount),
      c: keyedObject(componentIndexes, () => componentColumnSchema(zCount)),
    },
    required: ['s', 'v', 'm', 'a', 'c'],
    additionalProperties: false,
  }
}

function morphologyZoneSchema() {
  return {
    type: 'object',
    properties: {
      s: boundedInteger(0, 2),
      v: boundedInteger(0, 100, 5),
      f: boundedArray(boundedInteger(0, 2), MORPHOLOGY_FLAG_IDS_V3.length, MORPHOLOGY_FLAG_IDS_V3.length),
      x: boundedArray(boundedInteger(0, CORE_FEATURE_IDS.length - 1), 0, CORE_FEATURE_IDS.length),
    },
    required: ['s', 'v', 'f', 'x'],
    additionalProperties: false,
  }
}

export function compactStructuredOutputFormatV34_7(moduleId, featureIndexes = null) {
  const base = {
    type: 'json_schema',
    name: `facial_v34_${String(moduleId).replace(/[^a-zA-Z0-9_-]/g, '_')}_compact`,
    strict: true,
  }
  if (moduleId === 'morphology') {
    return {
      ...base,
      schema: {
        type: 'object',
        properties: {
          v: { type: 'integer', enum: [4] },
          z: boundedArray(morphologyZoneSchema(), FACE_ZONE_IDS.length, FACE_ZONE_IDS.length),
        },
        required: ['v', 'z'],
        additionalProperties: false,
      },
    }
  }
  const featureIds = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  if (!featureIds) throw new Error(`Unknown compact structured-output module ${moduleId}`)
  const indexes = featureIndexes ?? featureIds.map((_, i) => i)
  return {
    ...base,
    name: `${base.name}_${indexes.join('_')}`,
    schema: {
      type: 'object',
      properties: {
        v: { type: 'integer', enum: [4] },
        f: keyedObject(indexes, (fi) => featureColumnSchema(featureIds[fi])),
      },
      required: ['v', 'f'],
      additionalProperties: false,
    },
  }
}

// Old export retained for stale runner compatibility; full module only.
export function compactStructuredOutputFormatV34_2(moduleId) {
  return compactStructuredOutputFormatV34_7(moduleId)
}

export function mergeCompactSpecialistShardsV34_7(moduleId, shardValues) {
  const featureIds = VISION_MODULE_FEATURE_OWNERSHIP_V2[moduleId]
  if (!featureIds) throw new Error(`Unknown specialist module ${moduleId}`)
  const merged = {}
  for (const [si, shard] of shardValues.entries()) {
    if (Number(shard?.v) !== 4) throw new Error(`${moduleId} shard ${si} must have v=4`)
    const f = expectObject(shard.f, `${moduleId} shard ${si}.f`)
    for (const [key, value] of Object.entries(f)) {
      if (Object.prototype.hasOwnProperty.call(merged, key)) throw new Error(`${moduleId} feature key ${key} returned by more than one shard`)
      merged[key] = value
    }
  }
  exactNumericKeys(merged, featureIds.map((_, i) => i), `${moduleId}.merged.f`)
  return { v: 4, f: merged }
}

export function decodeCompactVisionOutputV34_7(moduleId, value, context = {}) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`${moduleId} compact output must be an object`)
  if (Number(value.v) !== 4) throw new Error(`${moduleId} compact output must have v=4; received ${value.v}`)
  if (moduleId === 'morphology') return decodeCompactMorphologyV34_1(value, context.input)
  return decodeSpecialistColumnar(moduleId, value, context)
}

// Old export name retained so other code paths do not break.
export function decodeCompactVisionOutputV34_1(moduleId, value, context = {}) {
  return decodeCompactVisionOutputV34_7(moduleId, value, context)
}
