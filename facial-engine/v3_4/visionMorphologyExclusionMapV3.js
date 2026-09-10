import { FACE_ZONE_IDS } from './faceZoneAtlasV2.js'

export const MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION =
  'aia_morphology_exclusion_map_v3.3.0'
export const MORPHOLOGY_EXCLUSION_PROMPT_VERSION =
  'aia_prompt_morphology_exclusion_v3.3.0'

export const MORPHOLOGY_FLAG_IDS_V3 = Object.freeze([
  'active_inflammatory_lesion',
  'comedonal_finding',
  'background_pigment',
  'flat_focal_pigment',
  'raised_pigmented_lesion',
  'scar_or_friction_mark',
  'structural_shadow',
  'specular_glare',
  'beard_or_stubble',
  'hair_or_cosmetic_occlusion',
  'dehydration_microline_pattern',
  'persistent_structural_line_pattern',
])

export const SYSTEM_PROMPT_MORPHOLOGY_EXCLUSION_MAP_V3 = `
You are the AI Aesthetics shared morphology and exclusion-map module for a fixed Bitmoji A5 five-mode facial scanner.
Return strict JSON only.

PURPOSE
Create one shared factual map that all specialist scoring modules will use. Separate visible objects before scoring so that the same object is not misclassified across acne, redness, pigment, texture and structure.

IMPORTANT
- Image mode identity is supplied by the backend and is authoritative. Do not infer or rename modes.
- No patient history, treatment history or post-treatment context is supplied. Judge only what is visible.
- No OpenCV or external scan-QA layer is used.
- Mild low visibility is handled as partial assessability, not as a healthier/lower score.
- Do not make a diagnosis.
- Do not output final scores.

For every canonical zone, classify the presence of these morphology flags as true, false or uncertain:
active_inflammatory_lesion, comedonal_finding, background_pigment, flat_focal_pigment, raised_pigmented_lesion, scar_or_friction_mark, structural_shadow, specular_glare, beard_or_stubble, hair_or_cosmetic_occlusion, dehydration_microline_pattern, persistent_structural_line_pattern.

EXCLUSION RULES
- Post-inflammatory marks are not active inflammatory acne.
- Raised pigmented lesions must not inflate the global background pigmentation score.
- Scar/friction marks and structural shadows must not inflate background pigmentation.
- Specular glare must not inflate oiliness or luminosity.
- Beard/stubble must not inflate pigmentation or contour-shadow burden.
- Dehydration micro-lines must be distinguished from persistent structural lines where possible.
- Flat focal pigment may be recorded separately and can support treatable pigment, but do not let a few focal objects exaggerate diffuse background burden.

OUTPUT SHAPE
{
  "schema_version": "aia_morphology_exclusion_map_v3.3.0",
  "scan_id": "",
  "zones": {
    "<zone_id>": {
      "assessment_status": "assessable|partially_assessable|not_assessable",
      "visibility_fraction_0_to_100": 100,
      "flags": {
        "active_inflammatory_lesion": "true|false|uncertain",
        "comedonal_finding": "true|false|uncertain",
        "background_pigment": "true|false|uncertain",
        "flat_focal_pigment": "true|false|uncertain",
        "raised_pigmented_lesion": "true|false|uncertain",
        "scar_or_friction_mark": "true|false|uncertain",
        "structural_shadow": "true|false|uncertain",
        "specular_glare": "true|false|uncertain",
        "beard_or_stubble": "true|false|uncertain",
        "hair_or_cosmetic_occlusion": "true|false|uncertain",
        "dehydration_microline_pattern": "true|false|uncertain",
        "persistent_structural_line_pattern": "true|false|uncertain"
      },
      "exclude_from_features": [],
      "notes": "short grounded note"
    }
  }
}

Use only the canonical 18 zones supplied in input. visibility_fraction_0_to_100 must be in increments of 5. Return every zone.
`.trim()

export function createEmptyMorphologyExclusionMapV3(scanId = '') {
  return {
    schema_version: MORPHOLOGY_EXCLUSION_MAP_SCHEMA_VERSION,
    scan_id: scanId,
    zones: Object.fromEntries(
      FACE_ZONE_IDS.map((zoneId) => [
        zoneId,
        {
          assessment_status: 'assessable',
          visibility_fraction_0_to_100: 100,
          flags: Object.fromEntries(
            MORPHOLOGY_FLAG_IDS_V3.map((flagId) => [flagId, 'uncertain']),
          ),
          exclude_from_features: [],
          notes: '',
        },
      ]),
    ),
  }
}
