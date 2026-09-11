import { normalizeContinuousGradeV36, deriveCalibratedParameterV36, deriveSkinTypeV36, CALIBRATION_VERSION_V36, CALIBRATION_STATUS_V36 } from './clinicalCalibrationV36.js'
import {
  FACE_ZONE_ATLAS_V2,
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'
import {
  CORE_FEATURE_IDS,
  IMAGE_MODES,
  SKIN_STATE_SCHEMA_VERSION,
  VISION_EVIDENCE_SCHEMA_VERSION,
} from './skinStateV2.schema.js'

export const SCORE_MAPPER_VERSION = 'aia_skin_state_mapper_v3.6.0-calibrated'
export const FORMULA_CONFIG_VERSION = 'aia_skin_state_formulas_v3.6.0-calibrated'

const FULL_SKIN_FACE = FACE_ZONE_ATLAS_V2.groups.full_skin_face
const CHEEKS = FACE_ZONE_ATLAS_V2.groups.cheeks
const FOREHEAD = FACE_ZONE_ATLAS_V2.groups.forehead
const T_ZONE = FACE_ZONE_ATLAS_V2.groups.t_zone

/**
 * Component grade anchors are deliberately ordinal. The vision model chooses a
 * grade; the backend converts it to a normalized value.
 *
 * The non-linear spacing reserves more score range for clinically meaningful
 * movement from moderate to marked/severe while still allowing fine global
 * scores after zones/components are aggregated.
 */
export const GRADE_TO_NORMALIZED = {
  0: 0.0,
  1: 0.12,
  2: 0.30,
  3: 0.50,
  4: 0.73,
  5: 1.0,
}

export const CONTINUOUS_PRIMITIVE_ENABLED_FEATURES_V3 = new Set([
  'active_inflammatory_acne',
  'comedonal_congestion',
  'oiliness',
  'erythema_redness',
  'barrier_stress',
  'visual_dehydration',
  'pore_visibility',
  'texture_roughness',
  'visible_pigmentation',
  'underlying_pigment_support',
  'luminosity_loss',
  'fine_line_visibility',
  'peri_orbital_concern', 'lip_pigmentation', 'visible_laxity', 'firmness_appearance_loss',
])

const PRIMITIVE_WEIGHTS_V3 = Object.freeze({
  coverage_0_to_100: 0.34,
  contrast_0_to_100: 0.34,
  // Corroboration affects reliability; it must not create/increase pathology.
  regional_salience_0_to_100: 0.12,
})

const COMPONENT = (weight, description) => ({ weight, description })

/**
 * Core feature formulas.
 *
 * Every feature declares:
 * - the image modes that should lead and support assessment;
 * - the valid anatomical zones;
 * - component weights within each zone;
 * - global aggregation behaviour across zones.
 */
export const CORE_FEATURE_FORMULAS_V2 = {
  active_inflammatory_acne: {
    label: 'Active inflammatory acne',
    lead_modes: ['white', 'subsurface_polarized'],
    support_modes: ['red', 'surface_polarized'],
    support_only_modes: ['woods_uv'],
    applicable_zones: [
      ...FOREHEAD,
      'glabella',
      'temple_left',
      'temple_right',
      'nose',
      ...CHEEKS,
      'perioral',
      'chin',
      'jawline_left',
      'jawline_right',
    ],
    components: {
      inflammatory_lesion_prominence: COMPONENT(
        0.38,
        'Visibility and prominence of papules/pustules in white and subsurface views.',
      ),
      inflammatory_lesion_density: COMPONENT(
        0.27,
        'Regional number/density category, not an invented exact lesion count.',
      ),
      inflammatory_clustering: COMPONENT(
        0.2,
        'Whether lesions form clinically meaningful clusters.',
      ),
      deep_lesion_support: COMPONENT(
        0.15,
        'Subsurface evidence of deeper inflammatory lesions.',
      ),
    },
    aggregation: { mean: 0.5, top_zones: 0.35, extent: 0.15, top_zone_count: 3 },
    guardrails: {
      woods_uv_cannot_raise_active_acne_alone: true,
      high_burden_requires_visible_or_subsurface_lesions: true,
    },
  },

  comedonal_congestion: {
    label: 'Comedonal and follicular congestion',
    lead_modes: ['surface_polarized', 'white'],
    support_modes: ['woods_uv'],
    support_only_modes: ['red'],
    applicable_zones: [
      ...FOREHEAD,
      'glabella',
      'nose',
      ...CHEEKS,
      'perioral',
      'chin',
    ],
    components: {
      open_comedone_prominence: COMPONENT(0.25, 'Visible open comedonal burden.'),
      closed_comedone_prominence: COMPONENT(0.3, 'Visible closed comedonal burden.'),
      follicular_congestion: COMPONENT(0.3, 'Follicular plugging/congestion pattern.'),
      distribution_extent: COMPONENT(0.15, 'How broadly congestion affects the zone.'),
    },
    aggregation: { mean: 0.58, top_zones: 0.27, extent: 0.15, top_zone_count: 3 },
  },

  oiliness: {
    label: 'Visible oiliness and follicular activity',
    lead_modes: ['white', 'surface_polarized'],
    support_modes: ['woods_uv'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      visible_shine_intensity: COMPONENT(0.42, 'Visible non-artifactual oil shine.'),
      shine_coverage: COMPONENT(0.28, 'Regional extent of visible shine.'),
      follicular_oil_activity: COMPONENT(0.2, 'Follicular activity support.'),
      oil_film_uniformity_loss: COMPONENT(0.1, 'Patchy/uneven oil distribution.'),
    },
    aggregation: { mean: 0.72, top_zones: 0.18, extent: 0.1, top_zone_count: 3 },
    guardrails: {
      specular_glare_is_not_oil: true,
      porphyrin_support_is_not_visible_oil: true,
    },
  },

  erythema_redness: {
    label: 'Erythema and vascular redness',
    lead_modes: ['red'],
    support_modes: ['white', 'subsurface_polarized'],
    support_only_modes: ['woods_uv'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      diffuse_erythema_intensity: COMPONENT(0.36, 'Diffuse erythema pattern, not red image brightness.'),
      vascular_pattern_prominence: COMPONENT(0.24, 'Recognisable vessel/telangiectatic morphology.'),
      affected_coverage: COMPONENT(0.22, 'Extent of clinically coherent redness.'),
      inflammatory_hotspots: COMPONENT(0.18, 'Focal inflammatory redness/hotspots.'),
    },
    aggregation: { mean: 0.62, top_zones: 0.25, extent: 0.13, top_zone_count: 3 },
    guardrails: {
      red_illumination_intensity_is_not_erythema: true,
      high_score_requires_coherent_morphology_or_corroboration: true,
    },
  },

  barrier_stress: {
    label: 'Visible barrier stress',
    lead_modes: ['surface_polarized', 'woods_uv'],
    support_modes: ['white', 'subsurface_polarized', 'red'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      flaking_or_scaling: COMPONENT(0.3, 'Visible flaking/scaling pattern.'),
      surface_disruption: COMPONENT(0.25, 'Micro-roughness or barrier surface disruption.'),
      patchy_hydration_signal: COMPONENT(0.25, 'Patchy dry/low-hydration appearance.'),
      reactive_erythema_support: COMPONENT(0.2, 'Reactive redness supporting barrier stress.'),
    },
    aggregation: { mean: 0.7, top_zones: 0.2, extent: 0.1, top_zone_count: 3 },
  },

  visual_dehydration: {
    label: 'Visual dehydration burden',
    lead_modes: ['surface_polarized', 'subsurface_polarized', 'white'],
    support_modes: ['woods_uv'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      plumpness_deficit: COMPONENT(0.3, 'Reduced plump, hydrated appearance.'),
      dehydration_micro_lines: COMPONENT(0.25, 'Fine dehydration-related micro-lines.'),
      reflectance_dullness: COMPONENT(0.2, 'Dull/uneven reflectance consistent with dehydration.'),
      dry_patch_signal: COMPONENT(0.25, 'Visible or UV-supported dry patch pattern.'),
    },
    aggregation: { mean: 0.76, top_zones: 0.14, extent: 0.1, top_zone_count: 3 },
  },

  pore_visibility: {
    label: 'Pore visibility',
    lead_modes: ['surface_polarized'],
    support_modes: ['white'],
    support_only_modes: ['woods_uv'],
    applicable_zones: [
      ...FOREHEAD,
      'glabella',
      'nose',
      ...CHEEKS,
      'perioral',
      'chin',
    ],
    components: {
      pore_prominence: COMPONENT(0.48, 'How prominent pores appear in the zone.'),
      pore_distribution_extent: COMPONENT(0.24, 'How broadly visible pores extend.'),
      pore_edge_clarity_loss: COMPONENT(0.16, 'Irregular/blurred pore-edge appearance.'),
      congestion_support: COMPONENT(0.12, 'Contribution of follicular congestion.'),
    },
    aggregation: { mean: 0.7, top_zones: 0.2, extent: 0.1, top_zone_count: 3 },
  },

  texture_roughness: {
    label: 'Surface texture roughness',
    lead_modes: ['surface_polarized'],
    support_modes: ['white', 'woods_uv'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      surface_roughness: COMPONENT(0.42, 'Visible rough surface texture.'),
      microrelief_irregularity: COMPONENT(0.28, 'Irregular microrelief/surface transitions.'),
      keratin_or_flaking_support: COMPONENT(0.15, 'Keratin/flaking contribution.'),
      texture_uniformity_loss: COMPONENT(0.15, 'Loss of regional texture uniformity.'),
    },
    aggregation: { mean: 0.76, top_zones: 0.14, extent: 0.1, top_zone_count: 3 },
  },

  visible_pigmentation: {
    label: 'Visible pigmentation burden',
    lead_modes: ['white', 'subsurface_polarized'],
    support_modes: ['surface_polarized'],
    support_only_modes: ['woods_uv'],
    applicable_zones: [...FULL_SKIN_FACE, 'lips'],
    components: {
      pigment_intensity: COMPONENT(0.34, 'Visible pigment darkness/intensity.'),
      contrast_to_surrounding_skin: COMPONENT(0.25, 'Contrast against adjacent skin.'),
      pigment_coverage: COMPONENT(0.25, 'Regional coverage.'),
      tone_unevenness: COMPONENT(0.16, 'Mottling/uneven tone contribution.'),
    },
    aggregation: { mean: 0.63, top_zones: 0.22, extent: 0.15, top_zone_count: 3 },
    guardrails: {
      beard_shadow_is_not_pigmentation: true,
      woods_uv_cannot_define_visible_severity_alone: true,
    },
  },

  underlying_pigment_support: {
    label: 'Underlying pigment support',
    lead_modes: ['woods_uv', 'subsurface_polarized'],
    support_modes: ['white'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      underlying_signal_intensity: COMPONENT(0.42, 'Enhanced-mode pigment support intensity.'),
      underlying_signal_coverage: COMPONENT(0.33, 'Enhanced-mode pigment support coverage.'),
      depth_or_chronicity_support: COMPONENT(0.25, 'Pattern supporting deeper/chronic pigment.'),
    },
    aggregation: { mean: 0.63, top_zones: 0.22, extent: 0.15, top_zone_count: 3 },
    treatment_relevance: 'subtype_and_planning',
  },

  luminosity_loss: {
    label: 'Luminosity and glow loss',
    lead_modes: ['white'],
    support_modes: ['surface_polarized', 'subsurface_polarized'],
    applicable_zones: [...FULL_SKIN_FACE],
    components: {
      brightness_loss: COMPONENT(0.25, 'Reduced visible brightness.'),
      reflectance_uniformity_loss: COMPONENT(0.36, 'Uneven reflectance/glow distribution.'),
      surface_dullness: COMPONENT(0.23, 'Visible surface dullness.'),
      tone_clarity_loss: COMPONENT(0.16, 'Reduced clarity from uneven tone/texture.'),
    },
    aggregation: { mean: 0.8, top_zones: 0.1, extent: 0.1, top_zone_count: 3 },
  },

  fine_line_visibility: {
    label: 'Visible fine lines and superficial wrinkles',
    lead_modes: ['surface_polarized', 'white'],
    support_modes: ['woods_uv'],
    applicable_zones: [
      ...FOREHEAD,
      'glabella',
      'temple_left',
      'temple_right',
      'peri_orbital_left',
      'peri_orbital_right',
      'perioral',
      'cheek_lateral_left',
      'cheek_lateral_right',
    ],
    components: {
      line_prominence: COMPONENT(0.38, 'Visibility/depth appearance of lines.'),
      line_density: COMPONENT(0.24, 'Regional density category.'),
      cross_mode_persistence: COMPONENT(0.22, 'Persistence beyond a single enhanced view.'),
      distribution_extent: COMPONENT(0.16, 'Extent across the zone.'),
    },
    aggregation: { mean: 0.63, top_zones: 0.27, extent: 0.1, top_zone_count: 3 },
  },

  visible_laxity: {
    label: 'Visible contour laxity',
    lead_modes: ['white', 'surface_polarized'],
    support_modes: ['subsurface_polarized'],
    applicable_zones: ['cheek_lateral_left', 'cheek_lateral_right', 'jawline_left', 'jawline_right', 'chin'],
    components: {
      jawline_definition_loss: COMPONENT(0.34, 'Loss of mandibular contour definition.'),
      pre_jowl_or_contour_shadow: COMPONENT(0.24, 'Pre-jowl/contour shadow pattern.'),
      visible_tissue_descent: COMPONENT(0.27, 'Visible lower-face tissue descent.'),
      asymmetry: COMPONENT(0.15, 'Clinically meaningful side-to-side difference.'),
    },
    aggregation: { mean: 0.64, top_zones: 0.31, extent: 0.05, top_zone_count: 2 },
    guardrails: {
      no_biomechanical_or_collagen_claims: true,
    },
  },

  firmness_appearance_loss: {
    label: 'Visible firmness appearance loss',
    lead_modes: ['white', 'surface_polarized'],
    support_modes: ['subsurface_polarized'],
    applicable_zones: [...CHEEKS, 'temple_left', 'temple_right', 'jawline_left', 'jawline_right', 'chin'],
    components: {
      micro_laxity_appearance: COMPONENT(0.38, 'Subtle visible laxity appearance.'),
      contour_softness: COMPONENT(0.29, 'Loss of crisp surface contour.'),
      plumpness_loss: COMPONENT(0.2, 'Visible plumpness loss.'),
      regional_uniformity_loss: COMPONENT(0.13, 'Uneven firmness appearance.'),
    },
    aggregation: { mean: 0.72, top_zones: 0.23, extent: 0.05, top_zone_count: 2 },
    guardrails: {
      measure_appearance_not_elastic_recoil: true,
    },
  },

  peri_orbital_concern: {
    label: 'Peri-orbital concern burden',
    lead_modes: ['white', 'surface_polarized', 'subsurface_polarized'],
    support_modes: ['red', 'woods_uv'],
    applicable_zones: ['peri_orbital_left', 'peri_orbital_right'],
    components: {
      pigment_darkness: COMPONENT(0.28, 'Pigment-related darkness.'),
      vascular_darkness: COMPONENT(0.14, 'Vascular contribution.'),
      hollow_shadow: COMPONENT(0.2, 'Structural/shadow contribution.'),
      puffiness: COMPONENT(0.15, 'Visible puffiness.'),
      fine_lines: COMPONENT(0.23, 'Peri-orbital fine-line appearance.'),
    },
    aggregation: { mean: 0.76, top_zones: 0.24, extent: 0.0, top_zone_count: 1 },
  },

  lip_pigmentation: {
    label: 'Lip pigmentation',
    lead_modes: ['white', 'woods_uv'],
    support_modes: ['red'],
    applicable_zones: ['lips'],
    components: {
      visible_lip_darkness: COMPONENT(0.46, 'Visible lip pigmentation/darkness.'),
      melanin_support: COMPONENT(0.34, 'Melanin-dominant support.'),
      pigment_unevenness: COMPONENT(0.2, 'Uneven lip pigmentation.'),
    },
    aggregation: { mean: 1.0, top_zones: 0.0, extent: 0.0, top_zone_count: 1 },
  },
}

/**
 * Derived report parameters. Treatment planning should generally consume the
 * core features rather than these composites.
 */
export const DERIVED_REPORT_FORMULAS_V2 = {
  barrier_health_sensitivity: {
    components: {
      barrier_stress: 0.55,
      visual_dehydration: 0.25,
      erythema_redness: 0.2,
    },
    display_polarity: 'higher_is_better',
  },
  visual_acne: {
    components: {
      active_inflammatory_acne: 0.72,
      comedonal_congestion: 0.28,
    },
    display_polarity: 'higher_is_better',
  },
  skin_sebum: {
    components: { oiliness: 0.5, visual_dehydration: 0.5 },
    nonlinear_balance: true,
    display_polarity: 'higher_is_better',
  },
  vascularity_redness: {
    components: { erythema_redness: 1.0 },
    display_polarity: 'higher_is_better',
  },
  skin_hydration: {
    components: { visual_dehydration: 1.0 },
    display_polarity: 'higher_is_better',
  },
  skin_luminosity_glow: {
    components: { luminosity_loss: 1.0 },
    display_polarity: 'higher_is_better',
  },
  superficial_pigmentation: {
    components: {
      visible_pigmentation: 0.78,
      underlying_pigment_support: 0.22,
    },
    display_polarity: 'higher_is_better',
  },
  peri_orbital_health: {
    components: { peri_orbital_concern: 1.0 },
    display_polarity: 'higher_is_better',
  },
  lip_pigmentation: {
    components: { lip_pigmentation: 1.0 },
    display_polarity: 'higher_is_better',
  },
  texture_open_pores: {
    components: {
      pore_visibility: 0.56,
      texture_roughness: 0.44,
    },
    display_polarity: 'higher_is_better',
  },
  superficial_wrinkles: {
    components: { fine_line_visibility: 1.0 },
    display_polarity: 'higher_is_better',
  },
  jawline_sagging: {
    components: { visible_laxity: 1.0 },
    display_polarity: 'higher_is_better',
  },
  skin_firmness_elasticity: {
    components: { firmness_appearance_loss: 1.0 },
    display_polarity: 'higher_is_better',
  },
  textural_radiance: {
    components: {
      texture_roughness: 0.58,
      luminosity_loss: 0.42,
    },
    display_polarity: 'higher_is_better',
  },
}

const clamp01 = (value) => Math.max(0, Math.min(1, value))
const score1To100 = (normalizedBurden) => 1 + Math.round(99 * clamp01(normalizedBurden))

function validateGrade(grade, context) {
  if (!Number.isFinite(grade) || grade < 0 || grade > 5) {
    throw new Error(`${context}: grade must be a finite number from 0 to 5`)
  }
}

function normalizeWeights(entries, context) {
  const total = entries.reduce((sum, [, weight]) => sum + weight, 0)
  if (Math.abs(total - 1) > 0.0001) {
    throw new Error(`${context}: weights must total 1; received ${total}`)
  }
}

function componentPrimitiveBurden(componentEvidence) {
  const primitives = componentEvidence.measurement_primitives
  if (!primitives || typeof primitives !== 'object') return null
  let total = 0
  let weightTotal = 0
  for (const [field, weight] of Object.entries(PRIMITIVE_WEIGHTS_V3)) {
    const value = Number(primitives[field])
    if (!Number.isFinite(value)) continue
    total += weight * clamp01(value / 100)
    weightTotal += weight
  }
  return weightTotal > 0 ? clamp01(total / weightTotal) : null
}

function componentBurden(zoneEvidence, formula, featureId, zoneId) {
  const componentEntries = Object.entries(formula.components)
  normalizeWeights(
    componentEntries.map(([key, value]) => [key, value.weight]),
    `${featureId}.components`,
  )

  let total = 0
  for (const [componentId, componentDefinition] of componentEntries) {
    const componentEvidence = zoneEvidence.components?.[componentId]
    if (!componentEvidence) {
      throw new Error(`Missing ${featureId}.${zoneId}.${componentId}`)
    }
    const grade = componentEvidence.grade_0_to_5
    validateGrade(grade, `${featureId}.${zoneId}.${componentId}`)
    const gradeBurden = normalizeContinuousGradeV36(grade)
    const primitiveBurden = CONTINUOUS_PRIMITIVE_ENABLED_FEATURES_V3.has(featureId)
      ? componentPrimitiveBurden(componentEvidence)
      : null
    const blended = grade === 0 ? 0 : primitiveBurden === null
      ? gradeBurden
      : 0.70 * gradeBurden + 0.30 * primitiveBurden
    total += componentDefinition.weight * blended
  }
  return clamp01(total)
}

function zoneAreaWeight(zoneId, zoneEvidence = null) {
  const base = FACE_ZONE_ATLAS_V2.zones[zoneId]?.default_area_weight ?? 0
  if (!zoneEvidence) return base
  if (zoneEvidence.assessment_status === 'not_assessable') return 0
  const visibility = Number(zoneEvidence.visibility_fraction_0_to_100)
  if (zoneEvidence.assessment_status === 'partially_assessable') {
    return base * clamp01(Number.isFinite(visibility) ? Math.max(35, visibility) / 100 : 0.6)
  }
  return base
}

function weightedMean(zoneValues) {
  const totalWeight = zoneValues.reduce((sum, item) => sum + item.weight, 0)
  if (totalWeight <= 0) return 0
  return zoneValues.reduce((sum, item) => sum + item.value * item.weight, 0) / totalWeight
}

function topZoneMean(zoneValues, count) {
  if (zoneValues.length === 0) return 0
  const sorted = [...zoneValues].sort((a, b) => b.value - a.value)
  return sorted.slice(0, Math.max(1, count)).reduce((sum, item) => sum + item.value, 0) /
    Math.min(sorted.length, Math.max(1, count))
}

/**
 * Continuous extent proxy derived from ordinal zone burdens.
 * Grade 3 (moderate) or above counts as fully affected, while minimal/mild
 * zones contribute proportionally. This avoids unstable binary coverage jumps.
 */
function continuousExtent(zoneValues) {
  const weighted = zoneValues.map((item) => ({
    ...item,
    value: clamp01(item.value / GRADE_TO_NORMALIZED[3]),
  }))
  return weightedMean(weighted)
}

function reliabilityFromEvidence(featureEvidence, formula) {
  let score = 100
  const reasons = []

  const zoneRows = formula.applicable_zones
    .map((zoneId) => ({
      zoneId,
      zone: featureEvidence.zones?.[zoneId],
      area: Number(FACE_ZONE_ATLAS_V2.zones[zoneId]?.default_area_weight ?? 0),
    }))
    .filter((row) => row.zone)

  const totalArea = zoneRows.reduce((sum, row) => sum + row.area, 0) || 1
  const visibilityFactor = (zone) => {
    if (zone.assessment_status === 'not_assessable') return 0
    const visible = clamp01(Number(zone.visibility_fraction_0_to_100 ?? 100) / 100)
    return visible
  }

  // Reliability should reflect how much clinically relevant facial area is
  // actually usable, not raw zone count. Missing a small jawline/temple zone
  // must not penalise a full-face feature as heavily as losing a central cheek.
  const usableArea = zoneRows.reduce(
    (sum, row) => sum + row.area * visibilityFactor(row.zone),
    0,
  )
  const usableFraction = clamp01(usableArea / totalArea)
  const visibilityPenalty = Math.round((1 - usableFraction) * 45)
  score -= visibilityPenalty

  const partialArea = zoneRows
    .filter((row) => row.zone.assessment_status === 'partially_assessable')
    .reduce((sum, row) => sum + row.area, 0)
  const notAssessableArea = zoneRows
    .filter((row) => row.zone.assessment_status === 'not_assessable')
    .reduce((sum, row) => sum + row.area, 0)
  if (partialArea > 0) reasons.push('partially_assessable_area')
  if (notAssessableArea > 0) reasons.push('not_assessable_area')

  // Genuine cross-mode contradictions reduce trust in proportion to the
  // anatomical area affected, rather than a flat deduction per zone.
  const conflictArea = zoneRows
    .filter((row) => row.zone.mode_agreement === 'conflicting')
    .reduce((sum, row) => sum + row.area * Math.max(0.35, visibilityFactor(row.zone)), 0)
  const conflictFraction = clamp01(conflictArea / totalArea)
  const conflictPenalty = Math.round(25 * conflictFraction)
  score -= conflictPenalty
  if (conflictArea > 0) reasons.push('mode_conflict')

  // Artifact flags are a secondary reliability signal. Penalise by affected
  // area, not by the number of strings attached to a zone. This prevents one
  // zone with several labels from overwhelming the reliability score.
  const artifactArea = zoneRows
    .filter((row) => (row.zone.artifact_flags?.length ?? 0) > 0)
    .reduce((sum, row) => sum + row.area * Math.max(0.35, visibilityFactor(row.zone)), 0)
  const artifactFraction = clamp01(artifactArea / totalArea)
  const artifactPenalty = Math.round(12 * artifactFraction)
  score -= artifactPenalty
  if (artifactArea > 0) reasons.push('artifact_affected_area')

  // Confidence is area/visibility weighted. Not-assessable zones are already
  // handled by the visibility term and are not double-penalised with zero
  // confidence values.
  let confidenceWeighted = 0
  let confidenceWeight = 0
  for (const row of zoneRows) {
    const vf = visibilityFactor(row.zone)
    if (vf <= 0) continue
    const values = Object.values(row.zone.components ?? {})
      .map((component) => Number(component.assessment_confidence_0_to_100))
      .filter(Number.isFinite)
    if (!values.length) continue
    const zoneMean = values.reduce((sum, value) => sum + value, 0) / values.length
    const weight = row.area * vf
    confidenceWeighted += zoneMean * weight
    confidenceWeight += weight
  }
  const meanConfidence = confidenceWeight > 0
    ? confidenceWeighted / confidenceWeight
    : 0
  const confidencePenalty = Math.max(0, Math.round((80 - meanConfidence) * 0.45))
  score -= confidencePenalty
  if (meanConfidence < 70) reasons.push('low_component_confidence')

  const final = Math.max(1, Math.min(100, score))
  return {
    score_1_to_100: final,
    tier: final >= 85 ? 'high' : final >= 70 ? 'moderate' : 'low',
    reasons,
    audit: {
      usable_area_fraction_0_to_1: Number(usableFraction.toFixed(3)),
      mean_component_confidence_0_to_100: Math.round(meanConfidence),
      visibility_penalty: visibilityPenalty,
      conflict_penalty: conflictPenalty,
      artifact_penalty: artifactPenalty,
      confidence_penalty: confidencePenalty,
    },
  }
}

function applyFeatureGuardrails(featureId, normalized, featureEvidence) {
  let adjusted = normalized
  const applied = []

  if (featureId === 'active_inflammatory_acne') {
    const visibleSupport = Object.values(featureEvidence.zones ?? {}).some((zone) => {
      const prominence = zone.components?.inflammatory_lesion_prominence?.grade_0_to_5 ?? 0
      const deep = zone.components?.deep_lesion_support?.grade_0_to_5 ?? 0
      return prominence >= 2 || deep >= 2
    })
    if (!visibleSupport && adjusted > 0.3) {
      adjusted = 0.3
      applied.push('active_acne_capped_without_visible_or_subsurface_lesion_support')
    }
  }

  if (featureId === 'erythema_redness') {
    const coherentSupport = Object.values(featureEvidence.zones ?? {}).some((zone) => {
      const diffuse = zone.components?.diffuse_erythema_intensity?.grade_0_to_5 ?? 0
      const vascular = zone.components?.vascular_pattern_prominence?.grade_0_to_5 ?? 0
      const coverage = zone.components?.affected_coverage?.grade_0_to_5 ?? 0
      return (diffuse >= 2 && coverage >= 2) || vascular >= 3
    })
    if (!coherentSupport && adjusted > 0.4) {
      adjusted = 0.4
      applied.push('redness_capped_without_coherent_erythema_or_vascular_pattern')
    }
  }

  return { normalized: adjusted, applied }
}

export function scoreCoreFeature(featureId, featureEvidence) {
  const formula = CORE_FEATURE_FORMULAS_V2[featureId]
  if (!formula) throw new Error(`No formula for core feature: ${featureId}`)

  const zoneValues = []
  const zoneScores = {}
  const zoneNormalized = {}

  for (const zoneId of formula.applicable_zones) {
    const zoneEvidence = featureEvidence.zones?.[zoneId]
    if (!zoneEvidence || zoneEvidence.assessment_status === 'not_assessable') continue

    const burden = componentBurden(zoneEvidence, formula, featureId, zoneId)
    zoneValues.push({ zoneId, value: burden, weight: zoneAreaWeight(zoneId, zoneEvidence) })
    zoneScores[zoneId] = score1To100(burden)
    zoneNormalized[zoneId] = burden
  }

  if (!zoneValues.length) throw new Error(`Cannot assess ${featureId}: no usable zones. Recapture the relevant skin.`)

  const aggregationEntries = Object.entries(formula.aggregation).filter(
    ([key]) => ['mean', 'top_zones', 'extent'].includes(key),
  )
  normalizeWeights(aggregationEntries, `${featureId}.aggregation`)

  const mean = weightedMean(zoneValues)
  const top = topZoneMean(zoneValues, formula.aggregation.top_zone_count)
  const extent = continuousExtent(zoneValues)
  const raw =
    formula.aggregation.mean * mean +
    formula.aggregation.top_zones * top +
    formula.aggregation.extent * extent

  const guardrailResult = applyFeatureGuardrails(featureId, raw, featureEvidence)
  const sortedZones = [...zoneValues].sort((a, b) => b.value - a.value)

  return {
    feature_id: featureId,
    global_burden_score_1_to_100: score1To100(guardrailResult.normalized),
    raw_normalized_burden_0_to_1: Number(raw.toFixed(4)),
    guarded_normalized_burden_0_to_1: guardrailResult.normalized,
    zone_scores_1_to_100: zoneScores,
    zone_normalized_burdens_0_to_1: zoneNormalized,
    dominant_zones: sortedZones.slice(0, 3).map((item) => item.zoneId),
    peak_zone: sortedZones[0]?.zoneId ?? null,
    aggregation_details: {
      area_weighted_mean_0_to_1: Number(mean.toFixed(4)),
      top_zone_mean_0_to_1: Number(top.toFixed(4)),
      continuous_extent_0_to_1: Number(extent.toFixed(4)),
    },
    guardrails_applied: guardrailResult.applied,
    score_reliability: reliabilityFromEvidence(featureEvidence, formula),
    measurement_sensitivity: CONTINUOUS_PRIMITIVE_ENABLED_FEATURES_V3.has(featureId)
      ? 'continuous_anchored_grade_plus_severity_primitives'
      : 'anchored_grade',
  }
}

function derivedBurden(coreFeatures, definition, parameterId) {
  const entries = Object.entries(definition.components)
  normalizeWeights(entries, `${parameterId}.derived_components`)

  return entries.reduce((sum, [featureId, weight]) => {
    const feature = coreFeatures[featureId]
    if (!feature) throw new Error(`Missing core feature ${featureId} for ${parameterId}`)
    const normalized = (feature.global_burden_score_1_to_100 - 1) / 99
    return sum + weight * normalized
  }, 0)
}

function displayBand(score, polarity) {
  const value = polarity === 'higher_is_better' ? score : 101 - score
  if (value >= 85) return 'excellent'
  if (value >= 70) return 'good'
  if (value >= 50) return 'moderate'
  if (value >= 30) return 'needs_attention'
  return 'high_concern'
}

function deriveSkinType(coreFeatures) {
  const oiliness = coreFeatures.oiliness
  const dehydration = coreFeatures.visual_dehydration

  const zone = (feature, zoneId) => feature.zone_scores_1_to_100?.[zoneId] ?? 1
  const average = (values) => values.reduce((sum, value) => sum + value, 0) / values.length

  const tZoneOil = average(T_ZONE.map((zoneId) => zone(oiliness, zoneId)))
  const cheekOil = average(CHEEKS.map((zoneId) => zone(oiliness, zoneId)))
  const dehydrationBurden = dehydration.global_burden_score_1_to_100

  let label = 'balanced'
  if (tZoneOil >= 60 && cheekOil >= 50) label = 'oily'
  else if (tZoneOil >= 52 && cheekOil <= 42) label = 'combination'
  else if (dehydrationBurden >= 62 && tZoneOil <= 40 && cheekOil <= 35) label = 'dry'
  else if (Math.abs(tZoneOil - cheekOil) >= 18) label = 'combination'

  const modifiers = []
  if (dehydrationBurden >= 55) modifiers.push('dehydrated')
  if (coreFeatures.erythema_redness.global_burden_score_1_to_100 >= 55) {
    modifiers.push('redness_prone')
  }
  if (coreFeatures.barrier_stress.global_burden_score_1_to_100 >= 55) {
    modifiers.push('barrier_stressed')
  }

  return {
    label,
    modifiers,
    evidence: {
      t_zone_oiliness_1_to_100: Math.round(tZoneOil),
      cheek_oiliness_1_to_100: Math.round(cheekOil),
      dehydration_burden_1_to_100: dehydrationBurden,
      regional_pattern: Math.abs(tZoneOil - cheekOil) >= 18 ? 'mixed' : 'uniform',
    },
  }
}

export function buildSkinStateV2(visionEvidencePacket) {
  if (visionEvidencePacket.schema_version !== VISION_EVIDENCE_SCHEMA_VERSION) {
    throw new Error(
      `Expected evidence schema ${VISION_EVIDENCE_SCHEMA_VERSION}; received ${visionEvidencePacket.schema_version}`,
    )
  }

  const receivedModes = visionEvidencePacket.scan?.modes_received ?? []
  for (const mode of IMAGE_MODES) {
    if (!receivedModes.includes(mode)) throw new Error(`Missing scan mode: ${mode}`)
  }

  const coreFeatures = {}
  for (const featureId of CORE_FEATURE_IDS) {
    const evidence = visionEvidencePacket.features?.[featureId]
    if (!evidence) throw new Error(`Missing vision evidence feature: ${featureId}`)
    coreFeatures[featureId] = scoreCoreFeature(
      featureId,
      evidence,
    )
  }

  const derivedReportParameters = {}
  for (const [parameterId, definition] of Object.entries(DERIVED_REPORT_FORMULAS_V2)) {
    derivedReportParameters[parameterId] = deriveCalibratedParameterV36(parameterId, coreFeatures, definition, CORE_FEATURE_FORMULAS_V2)
  }

  const skinType = deriveSkinTypeV36(coreFeatures)
  derivedReportParameters.skin_type = {
    parameter_id: 'skin_type',
    display_polarity: 'label_only',
    display_label: skinType.label,
    modifiers: skinType.modifiers,
  }

  return {
    schema_version: SKIN_STATE_SCHEMA_VERSION,
    source_evidence_schema_version: visionEvidencePacket.schema_version,
    zone_atlas_version: visionEvidencePacket.zone_atlas_version,
    scan: {
      scan_id: visionEvidencePacket.scan.scan_id,
      image_set_hash: visionEvidencePacket.scan.image_set_hash,
      capture_type: visionEvidencePacket.scan.capture_type,
      paired_baseline_scan_id: visionEvidencePacket.scan.paired_baseline_scan_id,
    },
    scoring_execution: {
      mapper_version: SCORE_MAPPER_VERSION,
      calibration_version: CALIBRATION_VERSION_V36,
      calibration_status: CALIBRATION_STATUS_V36,
      formula_config_version: FORMULA_CONFIG_VERSION,
      created_at_iso: new Date().toISOString(),
      immutable_assessment: true,
      history_excluded_from_image_scoring: true,
      external_qc_layer_used: false,
    },
    morphology_exclusion_summary: {
      map_schema_version:
        visionEvidencePacket.morphology_exclusion_map?.schema_version ?? null,
      reconciliation_action_count:
        visionEvidencePacket.reconciliation_audit?.length ?? 0,
      reconciliation_audit:
        visionEvidencePacket.reconciliation_audit ?? [],
    },
    core_features: coreFeatures,
    derived_report_parameters: derivedReportParameters,
    skin_type: skinType,
  }
}

/**
 * Exact repeatability for an identical image set should be enforced through
 * storage/cache lookup. Hash construction lives in the application layer so it
 * can use the project's selected cryptographic library.
 */
export function scanCacheKeyParts({
  imageSetHash,
  modelVersion,
  promptVersion,
}) {
  if (!imageSetHash || !modelVersion || !promptVersion) {
    throw new Error('imageSetHash, modelVersion and promptVersion are required')
  }
  return [
    imageSetHash,
    modelVersion,
    promptVersion,
    VISION_EVIDENCE_SCHEMA_VERSION,
    FACE_ZONE_ATLAS_V2.version,
    SCORE_MAPPER_VERSION,
    FORMULA_CONFIG_VERSION,
  ]
}

export function assertFormulaIntegrity() {
  for (const featureId of CORE_FEATURE_IDS) {
    const formula = CORE_FEATURE_FORMULAS_V2[featureId]
    if (!formula) throw new Error(`Missing formula: ${featureId}`)
    normalizeWeights(
      Object.entries(formula.components).map(([key, value]) => [key, value.weight]),
      `${featureId}.components`,
    )
    normalizeWeights(
      Object.entries(formula.aggregation).filter(([key]) =>
        ['mean', 'top_zones', 'extent'].includes(key),
      ),
      `${featureId}.aggregation`,
    )
    for (const zoneId of formula.applicable_zones) {
      if (!FACE_ZONE_IDS.includes(zoneId)) {
        throw new Error(`${featureId}: unknown zone ${zoneId}`)
      }
    }
  }
  return true
}
