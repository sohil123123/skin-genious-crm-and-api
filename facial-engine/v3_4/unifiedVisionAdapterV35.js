import { legacyMeasurementSchemaV37, legacyMeasurementPromptV37, validateLegacyMeasurementsV37 } from './legacyMeasurementContractV37.js'
import { COMPONENT_ANCHOR_SPECIFICATION_V2 } from './componentAnchorSpecificationV2.js'
import {
  CORE_FEATURE_IDS,
  IMAGE_MODES,
  VISION_EVIDENCE_SCHEMA_VERSION,
} from './skinStateV2.schema.js'
import {
  FACE_ZONE_IDS,
  FACE_ZONE_ATLAS_VERSION,
} from './faceZoneAtlasV2.js'
import {
  CORE_FEATURE_FORMULAS_V2,
} from './skinStateScoreMapperV2.js'
import {
  VISION_MODULE_FEATURE_OWNERSHIP_V2,
} from './mergeVisionEvidenceV2.js'
import {
  MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION,
  MORPHOLOGY_FLAG_IDS_V3,
} from './visionMorphologyExclusionMapV3.js'

/**
 * V3.5 production inference wire.
 *
 * One model request sees the five authoritative A5 images once and returns:
 *  - one shared 18-zone morphology map; and
 *  - all 16 Core Skin State features in compact columnar form.
 *
 * The existing V3.3/V3.4 deterministic merger, morphology reconciliation,
 * scoring formulas, derived 15 parameters and treatment logic remain unchanged.
 *
 * Deliberately NOT requested from the model because they do not influence the
 * deterministic score:
 *  - per-component prose reasons;
 *  - plausible grade ranges (derived deterministically from grade+confidence);
 *  - evidence/corroboration mode name arrays (derived from formula mode roles);
 *  - per-feature artifact arrays (morphology is the single source of truth).
 */
export const UNIFIED_VISION_WIRE_VERSION = 'aia_unified_vision_wire_v3.7.0-legacy-measurements'
export const UNIFIED_VISION_PROMPT_VERSION = 'aia_unified_vision_prompt_v3.7.0-legacy-measurements'

const STATUS = ['assessable', 'partially_assessable', 'not_assessable']
const AGREEMENT = ['strong', 'partial', 'conflicting', 'single_mode_only']
const MORPH_FLAG_VALUE = ['false', 'true', 'uncertain']

const SPECIAL_RULES = Object.freeze({
  active_inflammatory_acne: [
    'Score active papules/pustules and deeper inflammatory support only; flat post-inflammatory marks are not active acne.',
    'Woods/UV may support congestion context but must not independently create active-acne severity.',
  ],
  comedonal_congestion: [
    'Distinguish true plugging/comedones from simply visible pores.',
    'Use conservative grading when pore structure is visible without clear congestion.',
  ],
  oiliness: [
    'Visible shine must be plausible sebum, not scanner glare or highlight.',
    'Pore activity alone does not imply oily skin; Woods/UV cannot create visible oiliness alone.',
  ],
  pore_visibility: [
    'Score true pore prominence and distribution, not enhanced line noise.',
    'A high nose burden is possible; high global burden requires broader distribution.',
  ],
  texture_roughness: [
    'Do not call all enhanced surface detail roughness; require coherent surface irregularity.',
  ],
  luminosity_loss: [
    'Measure loss of brightness/clarity/reflectance uniformity; avoid double-counting simple pigment or glare as dullness.',
  ],
  erythema_redness: [
    'Use the red image as support for vascular prominence but require visible/cross-mode plausibility.',
    'Do not interpret redness as treatment-related; this is absolute appearance only.',
  ],
  barrier_stress: [
    'Measure visible barrier stress such as rough/flaky/reactive appearance; do not infer molecular barrier function.',
    'Redness alone is not sufficient for a high barrier-stress score.',
  ],
  visual_dehydration: [
    'Measure visible dehydration appearance, micro-line/dull dry pattern and surface dryness; do not infer tissue water percentage.',
  ],
  visible_pigmentation: [
    'Score background and treatable visible pigment; do not count beard/stubble, ordinary contour shadow, raised lesions or scars as pigment.',
    'A few focal marks must not exaggerate diffuse background burden.',
  ],
  underlying_pigment_support: [
    'This is underlying/chronic pigment support from Woods/UV and subsurface views, not purely visible severity.',
    'Strong underlying signal may exceed visible-pigment burden when image evidence supports it.',
  ],
  peri_orbital_concern: [
    'Separate pigment darkness, vascular darkness, hollow shadow, puffiness and fine lines.',
    'Closed eyes are normal and do not make the peri-orbital zone unassessable by themselves.',
  ],
  lip_pigmentation: [
    'Separate visible lip darkness, melanin support and unevenness; do not overcall cosmetic product or vascular darkness as melanin.',
  ],
  fine_line_visibility: [
    'Score coherent persistent line patterns; random enhancement noise is not a wrinkle.',
    'Very fine dehydration-only micro-lines should remain conservative unless persistent structure is visible.',
  ],
  visible_laxity: [
    'Measure visible contour/jawline laxity only; do not infer collagen quantity.',
    'Do not call beard shadow, low lighting or pigment contour laxity.',
  ],
  firmness_appearance_loss: [
    'Measure visible softness/plumpness/firmness appearance only; do not claim elasticity or biomechanical measurement.',
  ],
})

function boundedInteger(minimum, maximum, multipleOf = null) {
  const schema = { type: 'integer', minimum, maximum }
  if (multipleOf !== null) schema.multipleOf = multipleOf
  return schema
}

function boundedArray(itemSchema, count) {
  return { type: 'array', minItems: count, maxItems: count, items: itemSchema }
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

function morphologyZoneSchema() {
  return {
    type: 'object',
    properties: {
      s: boundedInteger(0, 2),
      v: boundedInteger(0, 100, 5),
      f: boundedArray(boundedInteger(0, 2), MORPHOLOGY_FLAG_IDS_V3.length),
    },
    required: ['s', 'v', 'f'],
    additionalProperties: false,
  }
}

function componentColumnSchema(zCount) {
  return {
    type: 'object',
    properties: {
      g: boundedArray({ type: 'number', minimum: 0, maximum: 5 }, zCount),
      q: boundedArray(boundedInteger(0, 100), zCount),
      c: boundedArray(boundedInteger(0, 100, 5), zCount),
      t: boundedArray(boundedInteger(0, 100, 5), zCount),
      x: boundedArray(boundedInteger(0, 100, 5), zCount),
      r: boundedArray(boundedInteger(0, 100, 5), zCount),
    },
    required: ['g', 'q', 'c', 't', 'x', 'r'],
    additionalProperties: false,
  }
}

function featureSchema(featureId) {
  const formula = CORE_FEATURE_FORMULAS_V2[featureId]
  const zCount = formula.applicable_zones.length
  const componentIds = Object.keys(formula.components)
  return {
    type: 'object',
    properties: {
      s: boundedArray(boundedInteger(0, 2), zCount),
      v: boundedArray(boundedInteger(0, 100, 5), zCount),
      d: boundedArray(boundedInteger(0, 1), zCount),
      c: keyedObject(componentIds.map((_, i) => i), () => componentColumnSchema(zCount)),
    },
    required: ['s', 'v', 'd', 'c'],
    additionalProperties: false,
  }
}

export function unifiedStructuredOutputFormatV35() {
  return {
    type: 'json_schema',
    name: 'facial_v37_legacy_evidence',
    strict: true,
    schema: {
      type: 'object',
      properties: {
        v: { type: 'integer', enum: [7] },
        l: legacyMeasurementSchemaV37(),
        m: boundedArray(morphologyZoneSchema(), FACE_ZONE_IDS.length),
        f: keyedObject(CORE_FEATURE_IDS.map((_, i) => i), (i) => featureSchema(CORE_FEATURE_IDS[i])),
      },
      required: ['v', 'm', 'f', 'l'],
      additionalProperties: false,
    },
  }
}

function featureSpecification() {
  return CORE_FEATURE_IDS.map((featureId, fi) => {
    const formula = CORE_FEATURE_FORMULAS_V2[featureId]
    const componentIds = Object.keys(formula.components)
    const componentLines = componentIds.map((componentId, ci) => {
      const desc = String(formula.components[componentId]?.description ?? '').trim()
      return `  C${ci}=${componentId}${desc ? ` — ${desc}` : ''}\n${JSON.stringify(COMPONENT_ANCHOR_SPECIFICATION_V2.features[featureId].components[componentId])}`
    })
    return [
      `F${fi}=${featureId}`,
      `zones=${JSON.stringify(formula.applicable_zones)}`,
      `lead_modes=${JSON.stringify(formula.lead_modes ?? [])}`,
      `support_modes=${JSON.stringify(formula.support_modes ?? [])}`,
      'components:',
      ...componentLines,
      'rules:',
      ...(SPECIAL_RULES[featureId] ?? ['Use the component definitions and mode roles literally.']).map((rule) => `  - ${rule}`),
    ].join('\n')
  }).join('\n\n')
}

export function buildUnifiedVisionPromptV35() {
  return `
You are the AI Aesthetics unified five-mode Skin State evidence extractor for a fixed Bitmoji A5 scanner.
Analyze the five supplied images together and return structured visual evidence only. Do NOT output final 1-100 scores; deterministic backend formulas calculate all scores.

NON-NEGOTIABLE CLINICAL RULES
- Image mode labels are authoritative: red, subsurface_polarized, surface_polarized, white, woods_uv. Never infer or rename a mode.
- Use absolute image appearance only. No patient history, treatment history, dynamic questions or post-treatment assumptions.
- Grade anchors are: 0 absent, 1 minimal, 2 mild, 3 moderate, 4 marked, 5 severe. The full component-specific anchors below control the meaning of each value. Continuous grades between adjacent anchors are allowed. For borderline evidence use its best supported position; uncertainty changes confidence, not severity. These continuous-grade rules override legacy lower-integer tie-break wording.
- Use one grounded assessment. Do not simulate panels, voting or hidden repeat passes.
- Cross-mode differences caused by the physics of the five modes are expected and are NOT automatically a conflict. Set d=1 only when modes provide materially contradictory evidence for the same feature in the same zone.
- A zone is assessable when enough relevant skin is visible to make a clinically useful estimate. Mild pose, ordinary facial contour, normal hairline adjacency or expected scanner-mode appearance do not by themselves make a zone partial.
- Use partially_assessable only when occlusion/crop/glare/shadow materially reduces the usable target skin. Use not_assessable only when the zone is genuinely unobservable.
- For partially assessable zones, give the best visible estimate; lower confidence/visibility rather than automatically lowering severity.
- Do not use uncertainty as a default. When a feature is clearly visible across useful modes, confidence may be high.
- Ordinary anatomical contour shading is not an artifact and must not be confused with pigmentation, laxity or low visibility.
- Beard/stubble is not pigmentation or contour laxity. Specular glare is not oiliness or luminosity. Raised lesions/scars are not diffuse background pigment. Dehydration micro-lines are not automatically persistent wrinkles.
- Every requested feature, zone and component must be returned. For s=2, set the corresponding component grades and continuous primitives to 0.

MORPHOLOGY OUTPUT m
Zone order=${JSON.stringify(FACE_ZONE_IDS)}.
Morphology flag order=${JSON.stringify(MORPHOLOGY_FLAG_IDS_V3)}.
Each m item has s,v,f:
- s: 0 assessable, 1 partially_assessable, 2 not_assessable
- v: visible usable target-skin fraction 0..100 in steps of 5; s=2 => 0
- f: exactly ${MORPHOLOGY_FLAG_IDS_V3.length} values in the fixed flag order, each 0=false, 1=true, 2=uncertain
Morphology calibration rules:
- false is the correct value when there is no visible evidence of a flag; do NOT use uncertain merely because a flag is theoretically possible.
- structural_shadow=true only for a material illumination/anatomical shadow that could distort feature interpretation, not ordinary cheek/nose/jaw contour shading.
- specular_glare=true only when a visible highlight materially obscures texture/shine interpretation, not normal skin reflectance.
- hair_or_cosmetic_occlusion=true only when material target skin is actually covered; hair near a zone boundary is not enough.
- beard_or_stubble may be true when facial hair is visible, but it does not automatically make a zone partially assessable; reduce s/v only if it prevents useful skin assessment.
- background_pigment=true only for visible background pigment/uneven tone, not normal constitutive skin tone.
- comedonal_finding=true only when a comedonal/plugging pattern is actually visible; otherwise false or uncertain only when genuinely ambiguous.

FEATURE OUTPUT f
f is keyed by F index. For every feature:
- s[]: 0 assessable, 1 partially_assessable, 2 not_assessable, aligned to that feature's zones list
- v[]: feature-specific usable visibility 0..100 in steps of 5
- d[]: 1 only for genuine cross-mode contradiction; otherwise 0
- c{}: component objects keyed by C index
Each component contains aligned arrays:
- g continuous anchored severity 0..5 (decimals allowed, e.g. 1.7, 2.35). Use integer anchors as reference points; interpolate only when observed severity lies between them. Do not round to an integer first.
- q confidence 0..100
- c coverage 0..100 in steps of 5
- t contrast 0..100 in steps of 5
- x cross-mode corroboration 0..100 in steps of 5
- r regional salience 0..100 in steps of 5
These are visual measurement primitives, not final scores. Preserve real regional variation; do not flatten different zones to repeated values unless the images genuinely support near-uniformity.

FEATURE SPECIFICATION
${featureSpecification()}

SCORING NAMESPACES
The f fields use regional 0-5 feature anchors. The l fields use the original legacy metric definitions below. Do not confuse these scales. Backend applies legacy equations, not a new-feature interpolation bridge.
Do not claim to perform OpenCV or direct physiological measurements. Required legacy angle/reflectance/recoil fields are supported visual estimates where not directly measurable, with explicit lower confidence.
For T-zone shine and lip darkness, clearly present findings are not 'absent' merely because they are mild. Normal constitutive skin/lip tone alone is not pathology.
For jawline/firmness, beard and frontal-only limitations must be reflected in visibility/confidence; absence of visible concern is not proof of ideal anatomy.
For c/t/r: score severity evidence only; absent component g=0 requires c=t=r=0. x measures corroboration, not severity.

${legacyMeasurementPromptV37()}
Return exactly one JSON object matching the supplied schema. No prose, markdown or extra keys.
`.trim()
}

function continuousGrade(value, context) {
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0 || value > 5) throw new Error(`${context} must be a finite number 0..5`)
  return value
}

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

function exactArray(value, count, context) {
  if (!Array.isArray(value) || value.length !== count) {
    throw new Error(`${context} must contain exactly ${count} values; received ${Array.isArray(value) ? value.length : typeof value}`)
  }
  return value
}

function expectObject(value, context) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`${context} must be an object`)
  return value
}

function exactNumericKeys(value, count, context) {
  const object = expectObject(value, context)
  const expected = Array.from({ length: count }, (_, i) => String(i))
  const actual = Object.keys(object).sort((a, b) => Number(a) - Number(b))
  if (actual.length !== expected.length || expected.some((k) => !Object.prototype.hasOwnProperty.call(object, k))) {
    throw new Error(`${context} must contain exact keys ${JSON.stringify(expected)}; received ${JSON.stringify(actual)}`)
  }
  return object
}

function plausibleRange(grade, confidence) {
  // Audit range only; the score mapper uses grade + continuous primitives.
  const spread = confidence >= 80 ? 0 : confidence >= 50 ? 1 : 2
  return {
    min: Math.max(0, Math.floor(grade - spread)),
    max: Math.min(5, Math.ceil(grade + spread)),
  }
}

function derivedModeAgreement(conflictFlag, componentRows) {
  if (conflictFlag === 1) return 'conflicting'
  const xs = componentRows.map((row) => row.x).filter(Number.isFinite)
  const mean = xs.length ? xs.reduce((a, b) => a + b, 0) / xs.length : 0
  if (mean >= 70) return 'strong'
  if (mean >= 40) return 'partial'
  return 'single_mode_only'
}

function decodeMorphology(value, scanId) {
  const rows = exactArray(value, FACE_ZONE_IDS.length, 'unified.m')
  return {
    schema_version: MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION,
    prompt_version: UNIFIED_VISION_PROMPT_VERSION,
    scan_id: scanId,
    zones: Object.fromEntries(FACE_ZONE_IDS.map((zoneId, zi) => {
      const row = expectObject(rows[zi], `unified.m[${zi}]`)
      const s = integerInRange(row.s, 0, 2, `unified.m[${zi}].s`)
      const flags = exactArray(row.f, MORPHOLOGY_FLAG_IDS_V3.length, `unified.m[${zi}].f`)
      return [zoneId, {
        assessment_status: STATUS[s],
        visibility_fraction_0_to_100: s === 2 ? 0 : fiveStep(row.v, `unified.m[${zi}].v`),
        flags: Object.fromEntries(MORPHOLOGY_FLAG_IDS_V3.map((flagId, fi) => [
          flagId,
          MORPH_FLAG_VALUE[integerInRange(flags[fi], 0, 2, `unified.m[${zi}].f[${fi}]`)],
        ])),
        // Exclusion decisions are no longer delegated to the model. The existing
        // deterministic reconciliation rules consume the morphology flags.
        exclude_from_features: [],
        notes: '',
      }]
    })),
  }
}

function featureOwner(featureId) {
  for (const [moduleId, featureIds] of Object.entries(VISION_MODULE_FEATURE_OWNERSHIP_V2)) {
    if (featureIds.includes(featureId)) return moduleId
  }
  throw new Error(`No module owner for ${featureId}`)
}

function decodeFeature(value, featureId, fi) {
  const formula = CORE_FEATURE_FORMULAS_V2[featureId]
  const zCount = formula.applicable_zones.length
  const f = expectObject(value, `unified.f.${fi}`)
  const sA = exactArray(f.s, zCount, `${featureId}.s`)
  const vA = exactArray(f.v, zCount, `${featureId}.v`)
  const dA = exactArray(f.d, zCount, `${featureId}.d`)
  const componentIds = Object.keys(formula.components)
  const cObj = exactNumericKeys(f.c, componentIds.length, `${featureId}.c`)
  const columns = componentIds.map((componentId, ci) => {
    const c = expectObject(cObj[String(ci)], `${featureId}.c.${ci}`)
    return {
      componentId,
      g: exactArray(c.g, zCount, `${featureId}.${componentId}.g`),
      q: exactArray(c.q, zCount, `${featureId}.${componentId}.q`),
      c: exactArray(c.c, zCount, `${featureId}.${componentId}.c`),
      t: exactArray(c.t, zCount, `${featureId}.${componentId}.t`),
      x: exactArray(c.x, zCount, `${featureId}.${componentId}.x`),
      r: exactArray(c.r, zCount, `${featureId}.${componentId}.r`),
    }
  })

  const zones = {}
  for (let zi = 0; zi < zCount; zi++) {
    const zoneId = formula.applicable_zones[zi]
    const statusIndex = integerInRange(sA[zi], 0, 2, `${featureId}.${zoneId}.s`)
    const visibility = statusIndex === 2 ? 0 : fiveStep(vA[zi], `${featureId}.${zoneId}.v`)
    const conflict = integerInRange(dA[zi], 0, 1, `${featureId}.${zoneId}.d`)
    const componentRows = []
    const components = {}

    for (const col of columns) {
      let grade = continuousGrade(col.g[zi], `${featureId}.${zoneId}.${col.componentId}.g`)
      const confidence = integerInRange(col.q[zi], 0, 100, `${featureId}.${zoneId}.${col.componentId}.q`)
      const coverage = fiveStep(col.c[zi], `${featureId}.${zoneId}.${col.componentId}.c`)
      const contrast = fiveStep(col.t[zi], `${featureId}.${zoneId}.${col.componentId}.t`)
      const crossMode = fiveStep(col.x[zi], `${featureId}.${zoneId}.${col.componentId}.x`)
      const salience = fiveStep(col.r[zi], `${featureId}.${zoneId}.${col.componentId}.r`)
      if (statusIndex === 2) grade = 0
      componentRows.push({ x: crossMode })
      const range = plausibleRange(grade, confidence)
      components[col.componentId] = {
        grade_0_to_5: grade,
        plausible_grade_range_0_to_5: range,
        assessment_confidence_0_to_100: confidence,
        measurement_primitives: {
          coverage_0_to_100: statusIndex === 2 ? 0 : coverage,
          contrast_0_to_100: statusIndex === 2 ? 0 : contrast,
          cross_mode_corroboration_0_to_100: statusIndex === 2 ? 0 : crossMode,
          regional_salience_0_to_100: statusIndex === 2 ? 0 : salience,
        },
        // Mode roles are known deterministically from the formula. Cross-mode
        // strength is measured separately in the x primitive and mode_agreement.
        evidence_modes: [...(formula.lead_modes ?? [])],
        corroboration_modes: crossMode >= 40 ? [...(formula.support_modes ?? [])] : [],
        reason: 'unified_v3_7_continuous_measurement',
      }
    }

    zones[zoneId] = {
      assessment_status: STATUS[statusIndex],
      components,
      mode_agreement: derivedModeAgreement(conflict, componentRows),
      // Morphology is now the single artifact source. Avoid double-penalising
      // the same visibility problem in reliability calculation.
      artifact_flags: [],
      visibility_fraction_0_to_100: visibility,
      evidence_summary: '',
    }
  }

  return {
    feature_id: featureId,
    zones,
    global_artifact_flags: [],
    global_evidence_summary: '',
  }
}

export function decodeUnifiedVisionOutputV35(value, {
  scanId,
  imageSetHash,
  modelVersion,
  createdAtIso = new Date().toISOString(),
} = {}) {
  if (Number(value?.v) !== 7) throw new Error(`Unified wire version must be 7; received ${value?.v}`)
  const morphology = decodeMorphology(value.m, scanId)
  const fObj = exactNumericKeys(value.f, CORE_FEATURE_IDS.length, 'unified.f')
  const decodedFeatures = Object.fromEntries(CORE_FEATURE_IDS.map((featureId, fi) => [
    featureId,
    decodeFeature(fObj[String(fi)], featureId, fi),
  ]))

  const moduleOutputs = {}
  for (const [moduleId, featureIds] of Object.entries(VISION_MODULE_FEATURE_OWNERSHIP_V2)) {
    moduleOutputs[moduleId] = {
      schema_version: VISION_EVIDENCE_SCHEMA_VERSION,
      zone_atlas_version: FACE_ZONE_ATLAS_VERSION,
      scan: {
        scan_id: scanId,
        image_set_hash: imageSetHash,
        modes_received: [...IMAGE_MODES],
      },
      model_execution: {
        model_version: modelVersion,
        prompt_version: UNIFIED_VISION_PROMPT_VERSION,
        created_at_iso: createdAtIso,
      },
      features: Object.fromEntries(featureIds.map((featureId) => [featureId, decodedFeatures[featureId]])),
    }
  }

  return { morphology, moduleOutputs, decodedFeatures, legacyMeasurements: validateLegacyMeasurementsV37(value.l) }
}

// Test helper: encode an existing valid V3 evidence packet into the V3.5 wire.
// This is intentionally exported only for regression testing / migration audit.
export function encodeEvidencePacketToUnifiedWireV35(evidencePacket) {
  const morphology = evidencePacket.morphology_exclusion_map
  const m = FACE_ZONE_IDS.map((zoneId) => {
    const z = morphology.zones[zoneId]
    return {
      s: Math.max(0, STATUS.indexOf(z.assessment_status)),
      v: Number(z.visibility_fraction_0_to_100 ?? 100),
      f: MORPHOLOGY_FLAG_IDS_V3.map((flagId) => Math.max(0, MORPH_FLAG_VALUE.indexOf(z.flags?.[flagId] ?? 'uncertain'))),
    }
  })

  const f = Object.fromEntries(CORE_FEATURE_IDS.map((featureId, fi) => {
    const formula = CORE_FEATURE_FORMULAS_V2[featureId]
    const src = evidencePacket.features[featureId]
    const zones = formula.applicable_zones
    const componentIds = Object.keys(formula.components)
    return [String(fi), {
      s: zones.map((zoneId) => Math.max(0, STATUS.indexOf(src.zones[zoneId].assessment_status))),
      v: zones.map((zoneId) => Number(src.zones[zoneId].visibility_fraction_0_to_100 ?? 100)),
      d: zones.map((zoneId) => src.zones[zoneId].mode_agreement === 'conflicting' ? 1 : 0),
      c: Object.fromEntries(componentIds.map((componentId, ci) => [String(ci), {
        g: zones.map((zoneId) => Number(src.zones[zoneId].components[componentId].grade_0_to_5)),
        q: zones.map((zoneId) => Number(src.zones[zoneId].components[componentId].assessment_confidence_0_to_100)),
        c: zones.map((zoneId) => Number(src.zones[zoneId].components[componentId].measurement_primitives.coverage_0_to_100)),
        t: zones.map((zoneId) => Number(src.zones[zoneId].components[componentId].measurement_primitives.contrast_0_to_100)),
        x: zones.map((zoneId) => Number(src.zones[zoneId].components[componentId].measurement_primitives.cross_mode_corroboration_0_to_100)),
        r: zones.map((zoneId) => Number(src.zones[zoneId].components[componentId].measurement_primitives.regional_salience_0_to_100)),
      }])),
    }]
  }))

  return { v: 7, m, f, l: validateLegacyMeasurementsV37(evidencePacket.legacy_measurements) }
}
