import {
  FACE_ZONE_ATLAS_V2,
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'
import { CORE_FEATURE_IDS } from './skinStateV2.schema.js'
import {
  MODALITY_RESPONSE_LIBRARY_V2,
  getModalityResponseV2,
} from './modalityResponseLibraryV2.js'
import {
  evaluateModalityEligibilityV2,
} from './evaluateModalityEligibilityV2.js'
import {
  CLINICAL_CONSTRAINTS_V2_VERSION,
  ELIGIBILITY_STATUS,
} from './clinicalConstraintsV2.js'
import {
  ZONAL_TREATMENT_OPTIMIZER_CONFIG_VERSION,
  OPTIMIZER_OBJECTIVE_PRESETS_V2,
  OPTIMIZER_DEFAULTS_V2,
  CONCERN_TO_CORE_FEATURES_V2,
  FEATURE_FAMILY_V2,
  ELIGIBILITY_MULTIPLIER_V2,
  INTENSITY_MULTIPLIER_V2,
  RESPONSE_CONFIDENCE_MULTIPLIER_V2,
  EVIDENCE_TIER_MULTIPLIER_V2,
  CLINICAL_ROLE_MULTIPLIER_V2,
  DOWNTIME_BAND_COST_V2,
  HARD_MUTUAL_EXCLUSION_GROUPS_V2,
  SUPPORT_TRIGGER_RULES_V2,
  SPECIAL_MODALITY_GATES_V2,
  MINIMUM_DURATION_FILLER_CATEGORY_ALLOWLIST_V2_8,
  MINIMUM_DURATION_FILLER_EXCLUDED_MODALITIES_V2_8,
  assertValidFeaturePriorityMapV2,
} from './zonalTreatmentOptimizerConfigV2.js'
import {
  TREATMENT_MODE_CONTRACTS_VERSION,
  applyTreatmentModeContractV2,
  getTreatmentModeContractV2,
} from './treatmentModeContractsV2.js'
import {
  COMBINATION_PROTOCOL_REGISTRY_VERSION,
  evaluateCombinationProtocolV2,
  findApprovedCombinationProtocolV2,
  getEffectiveClinicProtocolIdsV2,
} from './approvedCombinationProtocolsV2.js'

export const ZONAL_TREATMENT_OPTIMIZER_VERSION =
  'aia_zonal_treatment_optimizer_v3.3.0'

const clamp = (value, min = 0, max = 1) =>
  Math.max(min, Math.min(max, Number(value)))

const round = (value, digits = 4) =>
  Number(Number(value).toFixed(digits))

const unique = (values) => [...new Set(values.filter(Boolean))]

const SUPPORTIVE_CATEGORIES = new Set([
  'photobiomodulation',
  'infusion',
  'manual_supportive',
  'mask',
  'mandatory_finishing_step',
  'energy_supportive',
  'supportive',
])

const ROLE_CLASS_RANK = {
  hero: 0,
  secondary: 1,
  supportive: 2,
  mandatory: 3,
}

function normalizeObjectivePreset(objectivePreset, customObjective = null) {
  const preset =
    OPTIMIZER_OBJECTIVE_PRESETS_V2[objectivePreset] ??
    OPTIMIZER_OBJECTIVE_PRESETS_V2[OPTIMIZER_DEFAULTS_V2.objective_preset]

  if (!customObjective) return { ...preset, preset_id: objectivePreset in OPTIMIZER_OBJECTIVE_PRESETS_V2 ? objectivePreset : OPTIMIZER_DEFAULTS_V2.objective_preset }

  const immediate = clamp(customObjective.immediate_weight ?? preset.immediate_weight)
  const course = clamp(customObjective.course_weight ?? preset.course_weight)
  const total = immediate + course || 1

  return {
    ...preset,
    immediate_weight: round(immediate / total),
    course_weight: round(course / total),
    downtime_penalty_multiplier:
      Number.isFinite(Number(customObjective.downtime_penalty_multiplier))
        ? Number(customObjective.downtime_penalty_multiplier)
        : preset.downtime_penalty_multiplier,
    preset_id: 'custom',
    label: customObjective.label ?? 'Custom treatment objective.',
  }
}

function normalizeConcernEntry(entry, defaultPriority) {
  if (typeof entry === 'string') {
    return { concern: entry, priority: defaultPriority }
  }
  return {
    concern: entry?.concern ?? entry?.id ?? '',
    priority: clamp(entry?.priority ?? defaultPriority, 0, 1.5),
  }
}

export function buildFeaturePriorityMapV2({
  primaryConcerns = [],
  secondaryConcerns = [],
  featurePriorities = {},
} = {}) {
  assertValidFeaturePriorityMapV2(featurePriorities)

  const priorities = Object.fromEntries(
    CORE_FEATURE_IDS.map((featureId) => [featureId, 0.72]),
  )
  const sources = Object.fromEntries(
    CORE_FEATURE_IDS.map((featureId) => [featureId, ['clinical_baseline']]),
  )

  const applyConcern = ({ concern, priority }, tier) => {
    const key = String(concern ?? '').trim().toLowerCase().replace(/[\s-]+/g, '_')
    const mapping = CONCERN_TO_CORE_FEATURES_V2[key]
    if (!mapping) return
    const tierBoost = tier === 'primary' ? 0.5 : 0.28
    for (const [featureId, mappingWeight] of Object.entries(mapping)) {
      priorities[featureId] = Math.max(
        priorities[featureId],
        clamp(0.72 + tierBoost * mappingWeight * priority, 0, 1.5),
      )
      sources[featureId].push(`${tier}:${key}`)
    }
  }

  primaryConcerns
    .map((entry) => normalizeConcernEntry(entry, 1))
    .forEach((entry) => applyConcern(entry, 'primary'))

  secondaryConcerns
    .map((entry) => normalizeConcernEntry(entry, 0.8))
    .forEach((entry) => applyConcern(entry, 'secondary'))

  for (const [featureId, value] of Object.entries(featurePriorities)) {
    priorities[featureId] = clamp(value, 0, 1.5)
    sources[featureId].push('explicit_feature_priority')
  }

  return { priorities, sources }
}

function globalFeatureScore(skinState, featureId) {
  const value =
    skinState?.core_features?.[featureId]?.global_burden_score_1_to_100
  return Number.isFinite(Number(value)) ? Number(value) : null
}

function zoneFeatureScore(skinState, featureId, zoneId) {
  const value =
    skinState?.core_features?.[featureId]?.zone_scores_1_to_100?.[zoneId]
  return Number.isFinite(Number(value)) ? Number(value) : null
}

function reliabilityMultiplier(skinState, featureId) {
  const reliability = skinState?.core_features?.[featureId]?.score_reliability
  if (!reliability) return 0.82

  const numeric =
    reliability.score_1_to_100 ??
    reliability.score ??
    reliability.reliability_score_1_to_100

  if (Number.isFinite(Number(numeric))) {
    return clamp(Number(numeric) / 100, 0.45, 1)
  }

  const tier = String(reliability.tier ?? '').toLowerCase()
  return {
    high: 1,
    medium: 0.82,
    low: 0.58,
    unreliable: 0.4,
  }[tier] ?? 0.78
}

function responseStrengthForObjective(response, objective) {
  return (
    objective.immediate_weight * response.immediate_strength_0_to_5 +
    objective.course_weight * response.course_strength_0_to_5
  ) / 5
}

function responseRoleMultiplier(response) {
  return CLINICAL_ROLE_MULTIPLIER_V2[response.clinical_role] ?? 0.55
}

function evidenceMultiplier(entry) {
  return EVIDENCE_TIER_MULTIPLIER_V2[entry.evidence?.overall_tier] ?? 0.5
}

function intensityMultiplier(intensity) {
  return INTENSITY_MULTIPLIER_V2[intensity] ?? 0.7
}

function eligibilityMultiplier(status) {
  return ELIGIBILITY_MULTIPLIER_V2[status] ?? 0
}

function getZoneGroups(zoneId) {
  return FACE_ZONE_ATLAS_V2.zones[zoneId]?.groups ?? []
}

function zoneMatchesDescriptor(zoneId, descriptor) {
  if (!descriptor) return false
  if (zoneId === descriptor) return true
  if (FACE_ZONE_ATLAS_V2.groups[descriptor]?.includes(zoneId)) return true
  if (descriptor === 'full_skin_face') return zoneId !== 'lips'
  if (descriptor === 'cheeks') return FACE_ZONE_ATLAS_V2.groups.cheeks.includes(zoneId)
  if (descriptor === 'forehead') return FACE_ZONE_ATLAS_V2.groups.forehead.includes(zoneId)
  if (descriptor === 'lower_face') return FACE_ZONE_ATLAS_V2.groups.lower_face.includes(zoneId)
  if (descriptor === 'jawline') return FACE_ZONE_ATLAS_V2.groups.jawline.includes(zoneId)
  if (descriptor === 'peri_orbital') return FACE_ZONE_ATLAS_V2.groups.peri_orbital.includes(zoneId)
  if (descriptor === 't_zone') return FACE_ZONE_ATLAS_V2.groups.t_zone.includes(zoneId)
  return false
}

function contextualPreferenceMatch(descriptor, featureId, zoneContext) {
  const family = FEATURE_FAMILY_V2[featureId]
  if (descriptor.includes('pigment') && family === 'pigment') return true
  if (descriptor.includes('acne') && family === 'acne') return true
  if (descriptor.includes('congestion') && family === 'congestion') return true
  if (descriptor.includes('scar') && ['surface', 'ageing'].includes(family)) return true
  if (descriptor.includes('texture') && family === 'surface') return true
  if (descriptor.includes('periorbital') && family === 'peri_orbital') return true
  if (descriptor.includes('superficial') && zoneContext?.pigment_depth === 'epidermal') return true
  return false
}

function preferredZoneMultiplier(entry, zoneId, featureId, zoneContext) {
  const preferences = entry.preferred_zone_groups ?? []
  if (!preferences.length) return 1

  if (preferences.some((descriptor) => zoneMatchesDescriptor(zoneId, descriptor))) {
    return 1.08
  }
  if (preferences.some((descriptor) => contextualPreferenceMatch(descriptor, featureId, zoneContext))) {
    return 1.12
  }
  return 0.84
}

function isZoneAvoidedByEntry(entry, zoneId, skinState, capabilities, zoneContext) {
  const avoid = entry.avoid_or_protection_zones ?? []
  for (const descriptor of avoid) {
    if (zoneMatchesDescriptor(zoneId, descriptor)) return true

    if (
      descriptor === 'active_inflammatory_lesions' &&
      (zoneFeatureScore(skinState, 'active_inflammatory_acne', zoneId) ?? 0) >= 20
    ) {
      return true
    }

    if (
      descriptor === 'active_inflammatory_lesions_unless_specific_sebaceous_protocol' &&
      (zoneFeatureScore(skinState, 'active_inflammatory_acne', zoneId) ?? 0) >= 20 &&
      !capabilities.mnrf_sebaceous_targeting_protocol_available
    ) {
      return true
    }

    if (
      descriptor === 'broad_melasma_pattern' &&
      ['melasma_diffuse', 'broad_melasma_pattern'].includes(zoneContext?.pigment_pattern)
    ) {
      return true
    }
  }
  return false
}

function specialGateAllows({
  modalityId,
  featureId,
  zoneId,
  zoneContext,
  capabilities,
}) {
  if (modalityId === 'q_switch_532' || modalityId === 'q_switch_755') {
    return false
  }

  if (
    modalityId === 'microneedling_rf' &&
    featureId === 'active_inflammatory_acne'
  ) {
    return Boolean(
      capabilities[
        SPECIAL_MODALITY_GATES_V2.microneedling_rf_for_active_acne.capability_flag
      ],
    )
  }

  return true
}

function pseudoEligibilityForNoProfile(entry, zones) {
  const zone_status = {}
  for (const zoneId of zones) {
    const explicitlyProtected = (entry.avoid_or_protection_zones ?? []).some(
      (descriptor) => zoneMatchesDescriptor(zoneId, descriptor),
    )
    zone_status[zoneId] = {
      status: explicitlyProtected
        ? ELIGIBILITY_STATUS.DENIED
        : 'allowed_supportive_no_profile',
      maximum_intensity: explicitlyProtected ? 'none' : 'medium',
      reasons: explicitlyProtected
        ? [`${FACE_ZONE_ATLAS_V2.zones[zoneId].label} is excluded by the modality response profile.`]
        : [],
      triggered_rules: explicitlyProtected
        ? ['V2_RESPONSE_LIBRARY_PROTECTION_ZONE']
        : [],
      required_protection: explicitlyProtected
        ? [`Protect or avoid ${FACE_ZONE_ATLAS_V2.zones[zoneId].label}.`]
        : [],
      required_support_actions: [],
    }
  }

  return {
    modality_id: null,
    modality_name: entry.name,
    overall_status: 'allowed_supportive_no_profile',
    maximum_intensity: 'medium',
    allowed_zones: zones.filter(
      (zoneId) => zone_status[zoneId].status === 'allowed_supportive_no_profile',
    ),
    caution_zones: [],
    denied_zones: zones.filter(
      (zoneId) => zone_status[zoneId].status === ELIGIBILITY_STATUS.DENIED,
    ),
    not_applicable_zones: [],
    zone_status,
    required_support_actions: [],
    planning_directives: [
      'Check the exact product ingredients and clinic protocol before compiling the session.',
    ],
    ingredient_exclusions: [],
    triggered_rules: [],
    reasons: [],
  }
}

function evaluateEntryEligibility({
  entry,
  skinState,
  patientHistory,
  zones,
  regionalTemperaturesC,
}) {
  if (!entry.eligibility_profile_id) {
    return pseudoEligibilityForNoProfile(entry, zones)
  }

  return evaluateModalityEligibilityV2({
    skinState,
    patientHistory,
    modality: entry.eligibility_profile_id,
    intendedZones: zones,
    regionalTemperaturesC,
  })
}

function classifyEntry(entry) {
  if (entry.category === 'mandatory_finishing_step') return 'mandatory'
  if (
    SUPPORTIVE_CATEGORIES.has(entry.category) ||
    entry.correction_role === 'supportive' ||
    entry.correction_role === 'mandatory_supportive' ||
    entry.correction_role === 'supportive_corrective'
  ) {
    return 'supportive'
  }
  return 'corrective'
}

function atomicContribution({
  modalityId,
  entry,
  response,
  featureId,
  burden,
  priority,
  objective,
  eligibilityStatus,
  maximumIntensity,
  reliability,
  zonePreference,
}) {
  const burdenFactor = Math.pow(clamp(burden / 100), 1.12)
  const responseStrength = responseStrengthForObjective(response, objective)
  const confidence =
    RESPONSE_CONFIDENCE_MULTIPLIER_V2[response.confidence] ?? 0.62
  const value =
    burdenFactor *
    clamp(priority, 0, 1.5) *
    responseStrength *
    confidence *
    evidenceMultiplier(entry) *
    responseRoleMultiplier(response) *
    eligibilityMultiplier(eligibilityStatus) *
    intensityMultiplier(maximumIntensity) *
    reliability *
    zonePreference

  return {
    modality_id: modalityId,
    feature_id: featureId,
    burden_score_1_to_100: burden,
    concern_priority: round(priority, 3),
    blended_response_strength_0_to_1: round(responseStrength),
    response_confidence: response.confidence,
    evidence_tier: entry.evidence?.overall_tier ?? 'D',
    clinical_role: response.clinical_role,
    expected_onset: response.expected_onset,
    response_notes: response.notes,
    raw_utility: round(value),
  }
}

function combineFeatureContributions(contributions) {
  if (!contributions.length) return 0
  const sorted = [...contributions].sort(
    (a, b) => b.raw_utility - a.raw_utility || a.feature_id.localeCompare(b.feature_id),
  )
  const weights = [1, 0.62, 0.38]
  return sorted.reduce(
    (sum, contribution, index) =>
      sum + contribution.raw_utility * (weights[index] ?? 0.18),
    0,
  )
}

function zoneAreaMultiplier(zoneId) {
  const weight = FACE_ZONE_ATLAS_V2.zones[zoneId]?.default_area_weight ?? 0.05
  return clamp(0.75 + weight / 0.12, 0.75, 1.45)
}

function estimateDurationMinutes(entry, selectedZoneCount, possibleZoneCount) {
  const minimum = Number(entry.typical_duration_minutes?.min ?? 5)
  const maximum = Number(entry.typical_duration_minutes?.max ?? minimum)
  if (maximum <= minimum) return minimum
  const fraction = clamp(
    selectedZoneCount / Math.max(1, possibleZoneCount),
    0.12,
    1,
  )
  return Math.round(minimum + (maximum - minimum) * Math.sqrt(fraction))
}

function durationPenalty(entry, sessionMaximumMinutes) {
  const midpoint =
    (Number(entry.typical_duration_minutes?.min ?? 5) +
      Number(entry.typical_duration_minutes?.max ?? 10)) /
    2
  return 0.045 * clamp(midpoint / Math.max(30, sessionMaximumMinutes))
}

function downtimePenalty(entry, objective) {
  const cost =
    DOWNTIME_BAND_COST_V2[entry.downtime_band] ??
    DOWNTIME_BAND_COST_V2.unknown
  return cost * objective.downtime_penalty_multiplier
}

function directUtilitySummary(zoneCandidates) {
  let total = 0
  let direct = 0
  for (const zone of zoneCandidates) {
    for (const contribution of zone.contributions ?? []) {
      total += Number(contribution.raw_utility ?? 0)
      if (contribution.clinical_role === 'direct') {
        direct += Number(contribution.raw_utility ?? 0)
      }
    }
  }
  return {
    total_utility: round(total),
    direct_utility: round(direct),
    direct_utility_fraction:
      total > 0 ? round(direct / total) : 0,
  }
}

function carbonFacialFitAllows({
  modalityId,
  skinState,
  selectedZones,
  directSummary,
  settings,
}) {
  if (modalityId !== 'carbon_facial') return true
  const directFeatureIds =
    SPECIAL_MODALITY_GATES_V2.carbon_facial.direct_feature_ids
  const peakBurden = Math.max(
    0,
    ...directFeatureIds.map((featureId) =>
      Math.max(
        globalFeatureScore(skinState, featureId) ?? 0,
        ...selectedZones.map(
          (zone) =>
            zoneFeatureScore(skinState, featureId, zone.zone_id) ?? 0,
        ),
      ),
    ),
  )
  return (
    peakBurden >= settings.carbon_facial_minimum_direct_feature_burden &&
    directSummary.direct_utility_fraction >=
      settings.carbon_facial_minimum_direct_utility_fraction
  )
}

function buildModalityCandidate({
  modalityId,
  entry,
  skinState,
  patientHistory,
  featurePriorityMap,
  objective,
  settings,
  regionalTemperaturesC,
  zoneContext,
  capabilities,
}) {
  if (!entry.activation_status?.startsWith('active')) return null
  if (entry.parent_modality_id && !settings.allow_component_only_selection) {
    return {
      modality_id: modalityId,
      modality_name: entry.name,
      excluded: true,
      exclusion_reason: 'component_only_entry',
      parent_modality_id: entry.parent_modality_id,
    }
  }

  const eligibility = evaluateEntryEligibility({
    entry,
    skinState,
    patientHistory,
    zones: FACE_ZONE_IDS,
    regionalTemperaturesC,
  })

  const zoneCandidates = []

  for (const zoneId of FACE_ZONE_IDS) {
    const eligibilityZone = eligibility.zone_status?.[zoneId]
    const status =
      eligibilityZone?.status ??
      (eligibility.overall_status === 'allowed_supportive_no_profile'
        ? 'allowed_supportive_no_profile'
        : eligibility.overall_status)
    const maximumIntensity =
      eligibilityZone?.maximum_intensity ?? eligibility.maximum_intensity ?? 'medium'

    if (eligibilityMultiplier(status) <= 0) continue
    if (
      isZoneAvoidedByEntry(
        entry,
        zoneId,
        skinState,
        capabilities,
        zoneContext?.[zoneId],
      )
    ) {
      continue
    }

    const contributions = []

    for (const featureId of CORE_FEATURE_IDS) {
      const response = entry.feature_response?.[featureId]
      if (!response) continue
      const objectiveStrength = responseStrengthForObjective(response, objective)
      if (objectiveStrength <= 0 || responseRoleMultiplier(response) <= 0) continue

      const burden = zoneFeatureScore(skinState, featureId, zoneId)
      if (
        burden === null ||
        burden < settings.minimum_feature_burden_to_treat
      ) {
        continue
      }

      if (
        !specialGateAllows({
          modalityId,
          featureId,
          zoneId,
          zoneContext: zoneContext?.[zoneId],
          capabilities,
        })
      ) {
        continue
      }

      contributions.push(
        atomicContribution({
          modalityId,
          entry,
          response,
          featureId,
          burden,
          priority: featurePriorityMap.priorities[featureId],
          objective,
          eligibilityStatus: status,
          maximumIntensity,
          reliability: reliabilityMultiplier(skinState, featureId),
          zonePreference: preferredZoneMultiplier(
            entry,
            zoneId,
            featureId,
            zoneContext?.[zoneId],
          ),
        }),
      )
    }

    const benefit = combineFeatureContributions(contributions)
    if (benefit <= 0) continue

    const cautionPenalty =
      status === ELIGIBILITY_STATUS.CAUTION ? 0.032 : 0
    const netUtility =
      benefit * zoneAreaMultiplier(zoneId) -
      cautionPenalty -
      downtimePenalty(entry, objective) -
      durationPenalty(
        entry,
        settings.session_target_minutes + settings.session_tolerance_minutes,
      )

    if (netUtility < settings.minimum_atomic_utility) continue

    zoneCandidates.push({
      zone_id: zoneId,
      zone_label: FACE_ZONE_ATLAS_V2.zones[zoneId].label,
      eligibility_status: status,
      maximum_intensity: maximumIntensity,
      utility: round(netUtility),
      gross_benefit: round(benefit),
      penalties: {
        caution: round(cautionPenalty),
        downtime: round(downtimePenalty(entry, objective)),
        time: round(
          durationPenalty(
            entry,
            settings.session_target_minutes +
              settings.session_tolerance_minutes,
          ),
        ),
      },
      contributions: contributions.sort(
        (a, b) =>
          b.raw_utility - a.raw_utility ||
          a.feature_id.localeCompare(b.feature_id),
      ),
      safety: {
        reasons: eligibilityZone?.reasons ?? [],
        triggered_rules: eligibilityZone?.triggered_rules ?? [],
        required_protection: eligibilityZone?.required_protection ?? [],
        required_support_actions:
          eligibilityZone?.required_support_actions ?? [],
      },
    })
  }

  if (!zoneCandidates.length) return null

  zoneCandidates.sort(
    (a, b) => b.utility - a.utility || a.zone_id.localeCompare(b.zone_id),
  )
  const peak = zoneCandidates[0].utility
  const selectedZones = zoneCandidates
    .filter(
      (zone) =>
        zone.utility >=
        Math.max(
          settings.minimum_atomic_utility,
          peak * settings.zone_inclusion_fraction_of_peak,
        ),
    )
    .slice(0, settings.maximum_zones_per_modality)

  const directUtility = directUtilitySummary(selectedZones)
  if (
    !carbonFacialFitAllows({
      modalityId,
      skinState,
      selectedZones,
      directSummary: directUtility,
      settings,
    })
  ) {
    return null
  }

  const zoneWeights = [1, 0.48, 0.28]
  const modalityUtility = selectedZones.reduce(
    (sum, zone, index) =>
      sum + zone.utility * (zoneWeights[index] ?? 0.14),
    0,
  )

  if (modalityUtility < settings.minimum_modality_utility) return null

  const targetFeatureMap = {}
  for (const zone of selectedZones) {
    for (const contribution of zone.contributions) {
      const existing = targetFeatureMap[contribution.feature_id] ?? {
        feature_id: contribution.feature_id,
        utility: 0,
        peak_burden_score_1_to_100: 0,
        zones: [],
        clinical_roles: [],
      }
      existing.utility += contribution.raw_utility
      existing.peak_burden_score_1_to_100 = Math.max(
        existing.peak_burden_score_1_to_100,
        contribution.burden_score_1_to_100,
      )
      existing.zones.push(zone.zone_id)
      existing.clinical_roles.push(contribution.clinical_role)
      targetFeatureMap[contribution.feature_id] = existing
    }
  }

  const targetFeatures = Object.values(targetFeatureMap)
    .map((feature) => ({
      ...feature,
      utility: round(feature.utility),
      zones: unique(feature.zones),
      clinical_roles: unique(feature.clinical_roles),
    }))
    .sort(
      (a, b) =>
        b.utility - a.utility || a.feature_id.localeCompare(b.feature_id),
    )

  return {
    modality_id: modalityId,
    modality_name: entry.name,
    category: entry.category,
    correction_role: entry.correction_role,
    role_class: classifyEntry(entry),
    activation_status: entry.activation_status,
    parent_modality_id: entry.parent_modality_id,
    evidence_tier: entry.evidence?.overall_tier ?? 'D',
    modality_utility: round(modalityUtility),
    direct_utility_fraction: directUtility.direct_utility_fraction,
    direct_utility: directUtility.direct_utility,
    total_contribution_utility: directUtility.total_utility,
    modality_prior_multiplier: 1,
    selected_zones: selectedZones.map((zone) => zone.zone_id),
    zone_candidates: selectedZones,
    target_features: targetFeatures,
    eligibility_summary: {
      profile_id: entry.eligibility_profile_id,
      overall_status: eligibility.overall_status,
      maximum_intensity: eligibility.maximum_intensity,
      triggered_rules: eligibility.triggered_rules ?? [],
      reasons: eligibility.reasons ?? [],
      planning_directives: eligibility.planning_directives ?? [],
      required_support_actions: eligibility.required_support_actions ?? [],
      ingredient_exclusions: eligibility.ingredient_exclusions ?? [],
    },
    estimated_duration_minutes: estimateDurationMinutes(
      entry,
      selectedZones.length,
      zoneCandidates.length,
    ),
    downtime_band: entry.downtime_band,
    patient_facing_benefit_claims: entry.patient_facing_benefit_claims,
    limitations_and_non_claims: entry.limitations_and_non_claims,
    expected_transient_effects: entry.expected_transient_effects,
    combination_logic: entry.combination_logic,
    sequence_role: entry.sequence_role,
    settings_source: entry.settings_source,
  }
}

function tokenMatchesCandidate(token, candidate) {
  const normalized = String(token ?? '').toLowerCase()
  if (!normalized) return false
  if (normalized === candidate.modality_id) return true
  if (normalized === candidate.category) return true

  if (
    ['strong_peels', 'aggressive_peels', 'other_strong_peels'].includes(
      normalized,
    )
  ) {
    return (
      candidate.category === 'chemical_peel' &&
      ['moderate_to_high', 'high'].includes(candidate.downtime_band)
    )
  }

  if (normalized === 'gentle_peels') {
    return (
      candidate.category === 'chemical_peel' &&
      ['none', 'none_to_low', 'low', 'low_to_moderate'].includes(
        candidate.downtime_band,
      )
    )
  }

  if (normalized === 'microneedling') {
    return ['microneedling', 'dermaroller'].includes(candidate.modality_id)
  }

  if (normalized === 'other_lip_energy') {
    return (
      candidate.selected_zones.includes('lips') &&
      ['laser', 'energy', 'energy_invasive'].includes(candidate.category)
    )
  }

  return false
}

function baseCompatibilityDecision(a, b) {
  if (!a || !b) return { compatible: true, conditional: false, reasons: [] }
  if (a.modality_id === b.modality_id) {
    return {
      compatible: false,
      conditional: false,
      reasons: ['Duplicate modality.'],
    }
  }

  if (
    a.parent_modality_id === b.modality_id ||
    b.parent_modality_id === a.modality_id
  ) {
    return {
      compatible: false,
      conditional: false,
      reasons: [
        'A machine bundle and one of its component steps must not be double-counted.',
      ],
    }
  }

  const sharedHydrafacialParent =
    a.parent_modality_id === 'hydrafacial_full_protocol' &&
    b.parent_modality_id === 'hydrafacial_full_protocol'

  const exfoliationCategories = new Set([
    'hydradermabrasion',
    'hydrafacial_probe',
    'mechanical_exfoliation',
  ])
  const aIsPeel = a.category === 'chemical_peel'
  const bIsPeel = b.category === 'chemical_peel'
  const aIsMechanicalExfoliation = exfoliationCategories.has(a.category)
  const bIsMechanicalExfoliation = exfoliationCategories.has(b.category)

  if (sharedHydrafacialParent) {
    return {
      compatible: true,
      conditional: true,
      reasons: [
        'Multiple individually selected Hydrafacial probes require the stored modular-probe sequence.',
      ],
    }
  }

  if (
    (aIsPeel && bIsMechanicalExfoliation) ||
    (bIsPeel && aIsMechanicalExfoliation)
  ) {
    const peel = aIsPeel ? a : b
    const strongPeel = ['moderate_to_high', 'high'].includes(
      peel.downtime_band,
    )
    return strongPeel
      ? {
          compatible: false,
          conditional: false,
          reasons: [
            'A strong peel must not be stacked with mechanical or hydradermabrasion exfoliation in the same session.',
          ],
        }
      : {
          compatible: true,
          conditional: true,
          reasons: [
            'A gentle peel plus mechanical/hydradermabrasion treatment requires a stored reduced-intensity clinic protocol.',
          ],
        }
  }

  if (aIsMechanicalExfoliation && bIsMechanicalExfoliation) {
    return {
      compatible: false,
      conditional: false,
      reasons: [
        'Two unrelated primary mechanical/hydradermabrasion exfoliation modalities are redundant and may over-exfoliate.',
      ],
    }
  }

  const aAvoids = a.combination_logic?.avoid_same_session ?? []
  const bAvoids = b.combination_logic?.avoid_same_session ?? []
  const hardReasons = []

  if (aAvoids.some((token) => tokenMatchesCandidate(token, b))) {
    hardReasons.push(
      `${a.modality_name} excludes ${b.modality_name} in the same session.`,
    )
  }
  if (bAvoids.some((token) => tokenMatchesCandidate(token, a))) {
    hardReasons.push(
      `${b.modality_name} excludes ${a.modality_name} in the same session.`,
    )
  }
  if (hardReasons.length) {
    return { compatible: false, conditional: false, reasons: hardReasons }
  }

  const aConditional = a.combination_logic?.conditional_partners ?? []
  const bConditional = b.combination_logic?.conditional_partners ?? []
  const conditional =
    aConditional.some((token) => tokenMatchesCandidate(token, b)) ||
    bConditional.some((token) => tokenMatchesCandidate(token, a))

  return {
    compatible: true,
    conditional,
    reasons: conditional
      ? [
          'The pair is usable only through a clinic-preapproved backend protocol.',
        ]
      : [],
  }
}

function pairRequiresStoredProtocol(a, b, baseCompatibility) {
  if (baseCompatibility?.conditional) return true

  const bothLasers =
    ['laser', 'laser_special_protocol'].includes(a?.category) &&
    ['laser', 'laser_special_protocol'].includes(b?.category)
  if (bothLasers) return true

  const bothPeels =
    a?.category === 'chemical_peel' &&
    b?.category === 'chemical_peel'
  if (bothPeels) return true

  const registeredProtocol = findApprovedCombinationProtocolV2(
    a,
    b,
    [
      'qswitch_multi_wavelength_zonal_v1',
      'carbon_plus_qswitch_zonal_v1',
      'multiple_peel_zonal_v1',
      'peel_plus_hydrafacial_reduced_intensity_v1',
      'rf_plus_surface_facial_v1',
    ],
  )
  return Boolean(registeredProtocol)
}

function directCompatibilityDecision(
  a,
  b,
  clinicProtocolIds = [],
) {
  const baseCompatibility = baseCompatibilityDecision(a, b)

  return evaluateCombinationProtocolV2({
    a,
    b,
    baseCompatibility,
    clinicProtocolIds,
    requiresStoredProtocol: pairRequiresStoredProtocol(
      a,
      b,
      baseCompatibility,
    ),
  })
}

function violatesHardGroup(candidate, selected, settings) {
  for (const group of HARD_MUTUAL_EXCLUSION_GROUPS_V2) {
    if (
      group.configurable_override &&
      settings[group.configurable_override] === true
    ) {
      continue
    }

    const candidateMatches = group.modality_ids
      ? group.modality_ids.includes(candidate.modality_id)
      : group.category
        ? candidate.category === group.category
        : false

    if (!candidateMatches) continue

    const selectedCount = selected.filter((item) =>
      group.modality_ids
        ? group.modality_ids.includes(item.modality_id)
        : item.category === group.category,
    ).length

    if (selectedCount >= group.maximum_per_session) {
      return {
        violated: true,
        group_id: group.id,
        reason: `Session rule ${group.id} permits at most ${group.maximum_per_session}.`,
      }
    }
  }
  return { violated: false }
}

function featureZoneKey(featureId, zoneId) {
  return `${featureId}::${zoneId}`
}

function calculateResidualUtility(candidate, selected) {
  const existingCoverage = new Map()

  for (const selectedCandidate of selected) {
    for (const zone of selectedCandidate.zone_candidates) {
      for (const contribution of zone.contributions) {
        const key = featureZoneKey(contribution.feature_id, zone.zone_id)
        existingCoverage.set(
          key,
          Math.max(
            existingCoverage.get(key) ?? 0,
            contribution.blended_response_strength_0_to_1,
          ),
        )
      }
    }
  }

  let residual = 0
  for (const zone of candidate.zone_candidates) {
    for (const contribution of zone.contributions) {
      const existing =
        existingCoverage.get(
          featureZoneKey(contribution.feature_id, zone.zone_id),
        ) ?? 0
      const residualFraction = Math.max(0.2, 1 - 0.72 * existing)
      residual += contribution.raw_utility * residualFraction
    }
  }

  const sameCategoryCount = selected.filter(
    (item) => item.category === candidate.category,
  ).length
  if (sameCategoryCount > 0) residual *= 0.72

  return round(residual)
}

function hasMeaningfulDirectContribution(candidate) {
  return candidate.zone_candidates.some((zone) =>
    zone.contributions.some(
      (contribution) =>
        contribution.clinical_role === 'direct' &&
        contribution.blended_response_strength_0_to_1 >= 0.34 &&
        contribution.raw_utility >= 0.025,
    ),
  )
}

function targetedFeatureFamilies(
  candidate,
  { directOnly = false } = {},
) {
  if (!directOnly) {
    return new Set(
      candidate.target_features.map(
        (feature) => FEATURE_FAMILY_V2[feature.feature_id],
      ),
    )
  }

  const directFeatureIds = unique(
    candidate.zone_candidates.flatMap((zone) =>
      zone.contributions
        .filter(
          (contribution) =>
            contribution.clinical_role === 'direct',
        )
        .map((contribution) => contribution.feature_id),
    ),
  )

  return new Set(
    directFeatureIds.map(
      (featureId) => FEATURE_FAMILY_V2[featureId],
    ),
  )
}

function addsDistinctFeatureFamily(
  candidate,
  selected,
  { directOnly = false } = {},
) {
  const selectedFamilies = new Set(
    selected.flatMap((item) => [
      ...targetedFeatureFamilies(item, { directOnly }),
    ]),
  )
  return [
    ...targetedFeatureFamilies(candidate, { directOnly }),
  ].some((family) => !selectedFamilies.has(family))
}


function applyCourseContextMultiplier(
  candidate,
  courseContext,
  treatmentModeContract,
) {
  if (treatmentModeContract.id !== 'multiple') return candidate

  const previousActionIds = new Set(
    courseContext?.previous_session_action_ids ?? [],
  )
  const previousHeroIds = new Set(
    courseContext?.previous_session_hero_ids ?? [],
  )

  let multiplier = 1
  let reason = null

  if (previousHeroIds.has(candidate.modality_id)) {
    multiplier =
      treatmentModeContract.course?.repeated_hero_multiplier ?? 0.88
    reason = 'repeated_previous_session_hero'
  } else if (previousActionIds.has(candidate.modality_id)) {
    multiplier =
      treatmentModeContract.course?.repeated_modality_multiplier ?? 0.82
    reason = 'repeated_previous_session_action'
  }

  if (multiplier === 1) return candidate

  return {
    ...candidate,
    modality_utility: round(candidate.modality_utility * multiplier),
    zone_candidates: candidate.zone_candidates.map((zone) => ({
      ...zone,
      utility: round(zone.utility * multiplier),
    })),
    course_repeat_adjustment: {
      multiplier,
      reason,
      repeat_remains_allowed:
        treatmentModeContract.course?.allow_repeat_when_still_best ?? true,
    },
  }
}

function applyCourseContextToCandidates(
  candidates,
  courseContext,
  treatmentModeContract,
) {
  return candidates
    .map((candidate) =>
      applyCourseContextMultiplier(
        candidate,
        courseContext,
        treatmentModeContract,
      ),
    )
    .sort(
      (a, b) =>
        b.modality_utility - a.modality_utility ||
        a.modality_id.localeCompare(b.modality_id),
    )
}

function canFitTime(
  candidate,
  selected,
  settings,
  mandatoryMinutes =
    settings.mandatory_reserved_minutes ??
    settings.mandatory_final_minutes ??
    4,
) {
  const maximumMinutes =
    settings.hard_maximum_minutes ??
    settings.session_target_minutes + settings.session_tolerance_minutes
  const selectedMinutes = selected.reduce(
    (sum, item) => sum + item.estimated_duration_minutes,
    settings.fixed_preparation_overhead_minutes + mandatoryMinutes,
  )
  return (
    selectedMinutes + candidate.estimated_duration_minutes <=
    maximumMinutes
  )
}

function selectCorrectiveCandidates(
  candidates,
  settings,
  clinicProtocolIds,
) {
  const corrective = candidates
    .filter((candidate) => candidate.role_class === 'corrective')
    .sort(
      (a, b) =>
        b.modality_utility - a.modality_utility ||
        a.modality_id.localeCompare(b.modality_id),
    )

  const selected = []
  const rejected = []

  for (const candidate of corrective) {
    if (
      selected.length === 0 &&
      Number(candidate.direct_utility_fraction ?? 0) <
        settings.minimum_direct_utility_fraction_for_primary_corrective
    ) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason: 'insufficient_direct_alignment_for_primary_corrective',
      })
      continue
    }
    if (selected.length >= settings.maximum_corrective_modalities) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason: 'maximum_corrective_modalities_reached',
      })
      continue
    }

    const hardGroup = violatesHardGroup(candidate, selected, settings)
    if (hardGroup.violated) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason: hardGroup.reason,
      })
      continue
    }

    const pairwise = selected.map((item) =>
      directCompatibilityDecision(item, candidate, clinicProtocolIds),
    )
    const incompatible = pairwise.find((decision) => !decision.compatible)
    if (incompatible) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason: incompatible.reasons.join(' '),
      })
      continue
    }

    const residualUtility =
      selected.length === 0
        ? candidate.modality_utility
        : calculateResidualUtility(candidate, selected)

    if (
      selected.length > 0 &&
      !hasMeaningfulDirectContribution(candidate) &&
      !addsDistinctFeatureFamily(candidate, selected)
    ) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason:
          'secondary_candidate_has_no_meaningful_direct_or_distinct_target',
      })
      continue
    }

    if (
      selected.length > 0 &&
      residualUtility < settings.minimum_modality_utility * 0.8
    ) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason: 'insufficient_incremental_benefit_after_overlap',
      })
      continue
    }

    if (!canFitTime(candidate, selected, settings)) {
      rejected.push({
        modality_id: candidate.modality_id,
        reason: 'session_time_budget_exceeded',
      })
      continue
    }

    selected.push({
      ...candidate,
      selection_utility: round(residualUtility),
      combination_status: pairwise.some(
        (decision) => decision.protocol_status === 'clinic_preapproved',
      )
        ? 'clinic_preapproved_protocol'
        : 'compatible',
      combination_protocol_ids: unique(
        pairwise.map((decision) => decision.protocol_id),
      ),
    })
  }

  if (
    selected.length === 0 &&
    settings.allow_supportive_corrective_fallback
  ) {
    const fallback = candidates
      .filter(
        (candidate) =>
          candidate.role_class === 'supportive' &&
          candidate.correction_role === 'supportive_corrective',
      )
      .sort(
        (a, b) =>
          b.modality_utility - a.modality_utility ||
          a.modality_id.localeCompare(b.modality_id),
      )[0]

    if (fallback && canFitTime(fallback, [], settings)) {
      selected.push({
        ...fallback,
        role_class: 'corrective',
        selection_utility: fallback.modality_utility,
        combination_status: 'supportive_corrective_fallback',
      })
    }
  }

  return { selected, rejected }
}

function highestGlobalFeatureBurden(skinState, featureId) {
  return globalFeatureScore(skinState, featureId) ?? 0
}

function triggeredSupportRules(skinState, selectedCorrective) {
  const triggered = []

  for (const rule of SUPPORT_TRIGGER_RULES_V2) {
    let selectedCategoryTriggered = false
    if (rule.selected_categories?.length) {
      selectedCategoryTriggered = selectedCorrective.some((candidate) =>
        rule.selected_categories.includes(candidate.category),
      )
    }

    let featureTriggered = false
    if (rule.feature_thresholds) {
      featureTriggered = Object.entries(rule.feature_thresholds).some(
        ([featureId, threshold]) =>
          highestGlobalFeatureBurden(skinState, featureId) >= threshold,
      )
    }

    if (selectedCategoryTriggered || featureTriggered) {
      triggered.push(rule)
    }
  }

  return triggered
}

function selectSupportiveCandidates({
  candidates,
  selectedCorrective,
  skinState,
  settings,
  clinicProtocolIds,
}) {
  const mandatoryIds = new Set(
    settings.mandatory_session_action_ids ?? [],
  )
  const supports = candidates
    .filter((candidate) => candidate.role_class === 'supportive')
    .filter(
      (candidate) =>
        !mandatoryIds.has(candidate.modality_id),
    )
    .filter(
      (candidate) =>
        !selectedCorrective.some(
          (corrective) => corrective.modality_id === candidate.modality_id,
        ),
    )

  const selected = []
  const triggers = triggeredSupportRules(skinState, selectedCorrective)

  const trySelect = (candidate, reason, requiredByRule = null) => {
    if (!candidate) return false
    if (
      selected.some((item) => item.modality_id === candidate.modality_id)
    ) {
      return true
    }
    if (selected.length >= settings.maximum_supportive_modalities) return false

    const allSelected = [...selectedCorrective, ...selected]
    const hardGroup = violatesHardGroup(candidate, allSelected, settings)
    if (hardGroup.violated) return false

    const compatibility = allSelected.map((item) =>
      directCompatibilityDecision(item, candidate, clinicProtocolIds),
    )
    if (compatibility.some((decision) => !decision.compatible)) return false

    if (!canFitTime(candidate, allSelected, settings)) return false

    selected.push({
      ...candidate,
      role_class: 'supportive',
      selection_utility: candidate.modality_utility,
      support_reason: reason,
      required_by_optimizer_rule: requiredByRule,
      must_preserve_in_session_compiler:
        settings.preserve_selected_support_in_session_compiler,
      combination_status: compatibility.some(
        (decision) =>
          decision.protocol_status === 'clinic_preapproved',
      )
        ? 'clinic_preapproved_protocol'
        : 'compatible',
      combination_protocol_ids: unique(
        compatibility.map((decision) => decision.protocol_id),
      ),
    })
    return true
  }

  for (const trigger of triggers) {
    const ranked = trigger.preferred_support_ids
      .map((modalityId) =>
        supports.find((candidate) => candidate.modality_id === modalityId),
      )
      .filter(Boolean)
      .sort(
        (a, b) =>
          b.modality_utility - a.modality_utility ||
          a.modality_id.localeCompare(b.modality_id),
      )

    for (const candidate of ranked) {
      if (trySelect(candidate, trigger.reason, trigger.id)) break
    }
  }

  const residualRanked = supports
    .filter(
      (candidate) =>
        !selected.some((item) => item.modality_id === candidate.modality_id),
    )
    .map((candidate) => ({
      candidate,
      residual: calculateResidualUtility(candidate, [
        ...selectedCorrective,
        ...selected,
      ]),
    }))
    .sort(
      (a, b) =>
        b.residual - a.residual ||
        a.candidate.modality_id.localeCompare(b.candidate.modality_id),
    )

  for (const { candidate, residual } of residualRanked) {
    if (selected.length >= settings.maximum_supportive_modalities) break
    if (residual < settings.minimum_modality_utility * 0.75) continue
    trySelect(
      candidate,
      'Adds meaningful residual benefit not fully covered by the corrective actions.',
      null,
    )
  }

  return { selected, triggers }
}


function rebalanceForTriggeredSupport({
  candidates,
  selectedCorrective,
  selectedSupport,
  triggers,
  settings,
  clinicProtocolIds,
}) {
  const corrective = [...selectedCorrective]
  const supportive = [...selectedSupport]
  const removedForSupport = []
  const unmetSupportRequirements = []

  const totalSelected = () => [...corrective, ...supportive]

  for (const trigger of triggers) {
    if (
      supportive.some((item) =>
        trigger.preferred_support_ids.includes(item.modality_id),
      )
    ) {
      continue
    }

    const preferredCandidates = trigger.preferred_support_ids
      .map((modalityId) =>
        candidates.find((candidate) => candidate.modality_id === modalityId),
      )
      .filter(Boolean)
      .sort(
        (a, b) =>
          b.modality_utility - a.modality_utility ||
          a.modality_id.localeCompare(b.modality_id),
      )

    let selectedPreferred = null

    for (const preferred of preferredCandidates) {
      while (supportive.length >= settings.maximum_supportive_modalities) {
        const removableSupport = [...supportive]
          .filter((item) => !item.required_by_optimizer_rule)
          .sort(
            (a, b) =>
              a.selection_utility - b.selection_utility ||
              b.modality_id.localeCompare(a.modality_id),
          )[0]
        if (!removableSupport) break
        supportive.splice(supportive.indexOf(removableSupport), 1)
        removedForSupport.push({
          modality_id: removableSupport.modality_id,
          reason: `Removed to reserve a required support slot for ${trigger.id}.`,
        })
      }

      const compatibleWithCurrent = () =>
        totalSelected().every(
          (item) =>
            directCompatibilityDecision(item, preferred, clinicProtocolIds).compatible,
        )

      if (!compatibleWithCurrent()) continue

      while (!canFitTime(preferred, totalSelected(), settings)) {
        const removableSupport = [...supportive]
          .filter((item) => !item.required_by_optimizer_rule)
          .sort(
            (a, b) =>
              a.selection_utility - b.selection_utility ||
              b.modality_id.localeCompare(a.modality_id),
          )[0]

        if (removableSupport) {
          supportive.splice(supportive.indexOf(removableSupport), 1)
          removedForSupport.push({
            modality_id: removableSupport.modality_id,
            reason: `Removed to preserve time for required support ${trigger.id}.`,
          })
          continue
        }

        const removableSecondary = [...corrective]
          .filter((item) => item.plan_role === 'secondary')
          .sort(
            (a, b) =>
              a.selection_utility - b.selection_utility ||
              b.modality_id.localeCompare(a.modality_id),
          )[0]

        if (!removableSecondary) break
        corrective.splice(corrective.indexOf(removableSecondary), 1)
        removedForSupport.push({
          modality_id: removableSecondary.modality_id,
          reason: `Secondary action removed to preserve clinically indicated support ${trigger.id}.`,
        })
      }

      if (
        supportive.length < settings.maximum_supportive_modalities &&
        compatibleWithCurrent() &&
        canFitTime(preferred, totalSelected(), settings)
      ) {
        selectedPreferred = {
          ...preferred,
          role_class: 'supportive',
          selection_utility: preferred.modality_utility,
          support_reason: trigger.reason,
          required_by_optimizer_rule: trigger.id,
          must_preserve_in_session_compiler:
            settings.preserve_selected_support_in_session_compiler,
          combination_status: totalSelected().some(
            (item) =>
              directCompatibilityDecision(
                item,
                preferred,
                clinicProtocolIds,
              ).protocol_status === 'clinic_preapproved',
          )
            ? 'clinic_preapproved_protocol'
            : 'compatible',
          combination_protocol_ids: unique(
            totalSelected().map(
              (item) =>
                directCompatibilityDecision(
                  item,
                  preferred,
                  clinicProtocolIds,
                ).protocol_id,
            ),
          ),
        }
        supportive.push(selectedPreferred)
        break
      }
    }

    if (!selectedPreferred) {
      unmetSupportRequirements.push({
        rule_id: trigger.id,
        reason: trigger.reason,
        preferred_support_ids: trigger.preferred_support_ids,
        explanation:
          'No compatible preferred support action could fit without removing the hero treatment.',
      })
    }
  }

  return {
    corrective,
    supportive,
    removed_for_support: removedForSupport,
    unmet_support_requirements: unmetSupportRequirements,
  }
}

function createSyntheticMandatoryZoneCandidatesV2_8({
  maximumIntensity = 'low',
} = {}) {
  return FACE_ZONE_ATLAS_V2.groups.full_skin_face.map((zoneId) => ({
    zone_id: zoneId,
    zone_label: FACE_ZONE_ATLAS_V2.zones[zoneId].label,
    eligibility_status: 'mandatory_session_action',
    maximum_intensity: maximumIntensity,
    utility: 0,
    gross_benefit: 0,
    penalties: {
      caution: 0,
      downtime: 0,
      time: 0,
    },
    contributions: [],
    safety: {
      reasons: [],
      triggered_rules: [],
      required_protection: [],
      required_support_actions: [],
    },
  }))
}

function createMandatoryActionV2_8({
  candidates,
  modalityId,
  durationMinutes,
  reason,
  maximumIntensity = 'low',
}) {
  const existing = candidates.find(
    (candidate) => candidate.modality_id === modalityId,
  )
  const entry = getModalityResponseV2(modalityId)

  if (existing) {
    return {
      ...existing,
      role_class: 'mandatory',
      plan_role: 'mandatory',
      estimated_duration_minutes: durationMinutes,
      selection_utility:
        existing.modality_utility ?? 0,
      support_reason: reason,
      required_by_optimizer_rule:
        `V2_8_MANDATORY_${modalityId.toUpperCase()}`,
      must_preserve_in_session_compiler: true,
      selected_zones:
        modalityId === 'lymphatic_drainage'
          ? FACE_ZONE_ATLAS_V2.groups.full_skin_face
          : existing.selected_zones,
      zone_candidates:
        modalityId === 'lymphatic_drainage'
          ? createSyntheticMandatoryZoneCandidatesV2_8({
              maximumIntensity,
            })
          : existing.zone_candidates,
    }
  }

  return {
    modality_id: modalityId,
    modality_name: entry?.name ?? modalityId,
    category:
      entry?.category ??
      (modalityId === 'lymphatic_drainage'
        ? 'manual_supportive'
        : 'mandatory_finishing_step'),
    correction_role:
      entry?.correction_role ?? 'mandatory_supportive',
    role_class: 'mandatory',
    plan_role: 'mandatory',
    activation_status: entry?.activation_status ?? 'active',
    parent_modality_id: entry?.parent_modality_id ?? null,
    evidence_tier: entry?.evidence?.overall_tier ?? 'D',
    modality_utility: 0,
    selection_utility: 0,
    selected_zones: FACE_ZONE_ATLAS_V2.groups.full_skin_face,
    zone_candidates:
      createSyntheticMandatoryZoneCandidatesV2_8({
        maximumIntensity,
      }),
    target_features: [],
    eligibility_summary: {
      profile_id: entry?.eligibility_profile_id ?? null,
      overall_status: 'mandatory_session_action',
      maximum_intensity: maximumIntensity,
      triggered_rules: [],
      reasons: [reason],
      planning_directives: [],
      required_support_actions: [],
      ingredient_exclusions: [],
    },
    estimated_duration_minutes: durationMinutes,
    downtime_band: entry?.downtime_band ?? 'none',
    patient_facing_benefit_claims:
      entry?.patient_facing_benefit_claims ?? {
        immediate_or_short_term: [],
        course_or_delayed: [],
      },
    limitations_and_non_claims:
      entry?.limitations_and_non_claims ?? [],
    expected_transient_effects:
      entry?.expected_transient_effects ?? [],
    combination_logic:
      entry?.combination_logic ?? {
        preferred_partners: [],
        conditional_partners: [],
        avoid_same_session: [],
      },
    sequence_role:
      entry?.sequence_role ?? ['support'],
    settings_source:
      entry?.settings_source ??
      'approved_protocol_library_only',
    support_reason: reason,
    required_by_optimizer_rule:
      `V2_8_MANDATORY_${modalityId.toUpperCase()}`,
    must_preserve_in_session_compiler: true,
    combination_status: 'mandatory_session_action',
    combination_protocol_ids: [],
  }
}

function createMandatoryActions(candidates, settings) {
  const mandatory = []

  if (settings.always_include_lymphatic_drainage) {
    mandatory.push(
      createMandatoryActionV2_8({
        candidates,
        modalityId: 'lymphatic_drainage',
        durationMinutes:
          settings.lymphatic_drainage_minimum_minutes ?? 5,
        maximumIntensity: 'low',
        reason:
          'Mandatory face massage and lymphatic drainage must appear in every session.',
      }),
    )
  }

  if (settings.always_include_final_skin_protection) {
    mandatory.push(
      createMandatoryActionV2_8({
        candidates,
        modalityId:
          'final_serum_moisturizer_sunscreen',
        durationMinutes:
          settings.mandatory_final_minutes ?? 4,
        maximumIntensity: 'medium',
        reason:
          'Mandatory closure: serum, moisturiser and sunscreen must remain in every session.',
      }),
    )
  }

  return mandatory
}

function modalityMaximumDurationV2_8(modalityId, fallback) {
  const entry = getModalityResponseV2(modalityId)
  return Number(
    entry?.typical_duration_minutes?.max ??
    fallback ??
    0,
  )
}

function candidateCanBeMinimumDurationFillerV2_8(
  candidate,
) {
  if (!candidate) return false
  if (
    MINIMUM_DURATION_FILLER_EXCLUDED_MODALITIES_V2_8.includes(
      candidate.modality_id,
    )
  ) {
    return false
  }
  if (
    !MINIMUM_DURATION_FILLER_CATEGORY_ALLOWLIST_V2_8.includes(
      candidate.category,
    )
  ) {
    return false
  }
  return (
    candidate.modality_utility > 0 &&
    (candidate.target_features?.length ?? 0) > 0
  )
}

function completionCandidateCompatibleV2_8(
  candidate,
  selected,
  clinicProtocolIds,
) {
  return selected.every(
    (existing) =>
      directCompatibilityDecision(
        existing,
        candidate,
        clinicProtocolIds,
      ).compatible,
  )
}

function buildMinimumDurationFillerCandidatesV2_8({
  ids,
  existingCandidates,
  skinState,
  patientHistory,
  featurePriorityMap,
  objective,
  settings,
  regionalTemperaturesC,
  zoneContext,
  capabilities,
}) {
  const existingById = new Map(
    existingCandidates.map((candidate) => [
      candidate.modality_id,
      candidate,
    ]),
  )
  const completionSettings = {
    ...settings,
    minimum_feature_burden_to_treat: 1,
    minimum_atomic_utility: 0.0001,
    minimum_modality_utility: 0.0001,
  }

  for (const modalityId of ids) {
    if (existingById.has(modalityId)) continue
    const entry =
      MODALITY_RESPONSE_LIBRARY_V2[modalityId]
    if (!entry) continue
    if (
      !MINIMUM_DURATION_FILLER_CATEGORY_ALLOWLIST_V2_8.includes(
        entry.category,
      )
    ) {
      continue
    }
    if (
      MINIMUM_DURATION_FILLER_EXCLUDED_MODALITIES_V2_8.includes(
        modalityId,
      )
    ) {
      continue
    }

    const candidate = buildModalityCandidate({
      modalityId,
      entry,
      skinState,
      patientHistory,
      featurePriorityMap,
      objective,
      settings: completionSettings,
      regionalTemperaturesC,
      zoneContext,
      capabilities,
    })
    if (candidate && !candidate.excluded) {
      existingById.set(modalityId, candidate)
    }
  }

  return [...existingById.values()]
}


function completeSessionToMinimumDurationV2_8({
  candidates,
  corrective,
  supportive,
  mandatory,
  settings,
  clinicProtocolIds,
}) {
  const selectedCorrective = [...corrective]
  const selectedSupportive = [...supportive]
  const selectedMandatory = [...mandatory]
  const addedScoreImprovingSteps = []

  const allSelected = () => [
    ...selectedCorrective,
    ...selectedSupportive,
    ...selectedMandatory,
  ]

  const totalDuration = () =>
    calculateTotalDuration(allSelected(), settings)

  const minimumMinutes =
    settings.hard_minimum_minutes ?? 0
  const maximumMinutes =
    settings.hard_maximum_minutes ??
    settings.session_target_minutes +
      settings.session_tolerance_minutes

  if (
    !settings.minimum_duration_completion_enabled ||
    totalDuration() >= minimumMinutes
  ) {
    return {
      corrective: selectedCorrective,
      supportive: selectedSupportive,
      mandatory: selectedMandatory,
      completion: {
        required: false,
        minimum_minutes: minimumMinutes,
        initial_minutes: totalDuration(),
        final_minutes: totalDuration(),
        added_score_improving_steps: [],
        lymphatic_minutes_added: 0,
        minimum_reached:
          totalDuration() >= minimumMinutes,
      },
    }
  }

  const initialMinutes = totalDuration()
  const selectedIds = new Set(
    allSelected().map((item) => item.modality_id),
  )
  const rankedFillers = candidates
    .filter(candidateCanBeMinimumDurationFillerV2_8)
    .filter(
      (candidate) =>
        !selectedIds.has(candidate.modality_id),
    )
    .map((candidate) => ({
      candidate,
      residual: calculateResidualUtility(
        candidate,
        allSelected(),
      ),
    }))
    .filter(
      ({ candidate, residual }) =>
        residual > 0 ||
        candidate.modality_utility > 0,
    )
    .sort(
      (a, b) =>
        b.residual - a.residual ||
        b.candidate.modality_utility -
          a.candidate.modality_utility ||
        a.candidate.modality_id.localeCompare(
          b.candidate.modality_id,
        ),
    )

  let fillerCount = 0
  for (const { candidate, residual } of rankedFillers) {
    if (totalDuration() >= minimumMinutes) break
    if (
      fillerCount >=
      (settings.minimum_duration_completion_maximum_filler_actions ??
        3)
    ) {
      break
    }
    if (
      !completionCandidateCompatibleV2_8(
        candidate,
        allSelected(),
        clinicProtocolIds,
      )
    ) {
      continue
    }

    const remainingToMinimum =
      minimumMinutes - totalDuration()
    const maximumCandidateDuration =
      modalityMaximumDurationV2_8(
        candidate.modality_id,
        candidate.estimated_duration_minutes,
      )
    const availableBeforeMaximum =
      maximumMinutes - totalDuration()
    const selectedDuration = Math.min(
      maximumCandidateDuration,
      availableBeforeMaximum,
      Math.max(
        candidate.estimated_duration_minutes,
        remainingToMinimum,
      ),
    )

    if (selectedDuration <= 0) continue

    const completionAction = {
      ...candidate,
      role_class: 'supportive',
      plan_role: 'supportive',
      estimated_duration_minutes:
        selectedDuration,
      selection_utility: round(
        residual || candidate.modality_utility,
      ),
      support_reason:
        'Added because it provides the highest remaining eligible score-improving benefit while completing the required session duration.',
      required_by_optimizer_rule:
        'V2_8_MINIMUM_DURATION_SCORE_IMPROVING_FILLER',
      must_preserve_in_session_compiler: true,
      minimum_duration_completion_action: true,
      combination_status: allSelected().some(
        (item) =>
          directCompatibilityDecision(
            item,
            candidate,
            clinicProtocolIds,
          ).protocol_status ===
          'clinic_preapproved',
      )
        ? 'clinic_preapproved_protocol'
        : 'compatible',
      combination_protocol_ids: unique(
        allSelected().map(
          (item) =>
            directCompatibilityDecision(
              item,
              candidate,
              clinicProtocolIds,
            ).protocol_id,
        ),
      ),
    }

    selectedSupportive.push(completionAction)
    selectedIds.add(candidate.modality_id)
    addedScoreImprovingSteps.push({
      modality_id: candidate.modality_id,
      duration_minutes: selectedDuration,
      residual_utility: round(residual),
    })
    fillerCount += 1
  }

  let lymphaticMinutesAdded = 0
  if (totalDuration() < minimumMinutes) {
    const lymphatic = selectedMandatory.find(
      (item) =>
        item.modality_id === 'lymphatic_drainage',
    )
    if (lymphatic) {
      const currentDuration = Number(
        lymphatic.estimated_duration_minutes ?? 5,
      )
      const maximumLymphatic =
        settings.lymphatic_drainage_maximum_minutes ??
        15
      const availableIncrease = Math.max(
        0,
        maximumLymphatic - currentDuration,
      )
      const requiredIncrease = Math.max(
        0,
        minimumMinutes - totalDuration(),
      )
      lymphaticMinutesAdded = Math.min(
        availableIncrease,
        requiredIncrease,
        Math.max(0, maximumMinutes - totalDuration()),
      )
      lymphatic.estimated_duration_minutes =
        currentDuration + lymphaticMinutesAdded
      lymphatic.support_reason =
        lymphaticMinutesAdded > 0
          ? 'Mandatory lymphatic drainage; extended within the approved 5–15 minute range to complete the required session duration.'
          : lymphatic.support_reason
    }
  }

  return {
    corrective: selectedCorrective,
    supportive: selectedSupportive,
    mandatory: selectedMandatory,
    completion: {
      required: true,
      minimum_minutes: minimumMinutes,
      maximum_minutes: maximumMinutes,
      initial_minutes: initialMinutes,
      final_minutes: totalDuration(),
      added_score_improving_steps:
        addedScoreImprovingSteps,
      lymphatic_minutes_added:
        lymphaticMinutesAdded,
      minimum_reached:
        totalDuration() >= minimumMinutes,
      completion_order_used: [
        'score_improving_steps',
        'lymphatic_drainage_extension',
      ],
      high_risk_padding_used: false,
    },
  }
}


function assignRoles(
  selectedCorrective,
  settings,
) {
  if (!selectedCorrective.length) return []

  const peakUtility = Math.max(
    ...selectedCorrective.map(
      (candidate) =>
        candidate.selection_utility ?? candidate.modality_utility,
    ),
  )
  const relativeHeroFloor =
    peakUtility *
    (settings.hero_relative_utility_floor ?? 0.56)
  const absoluteHeroFloor =
    settings.hero_minimum_selection_utility ?? 0.04

  const heroesSoFar = []
  const assigned = selectedCorrective.map((candidate, index) => {
    const utility =
      candidate.selection_utility ?? candidate.modality_utility
    const directEnough =
      !settings.hero_must_have_meaningful_direct_benefit ||
      hasMeaningfulDirectContribution(candidate)
    const distinctFromExistingHeroes =
      heroesSoFar.length === 0 ||
      addsDistinctFeatureFamily(candidate, heroesSoFar, { directOnly: true })

    const qualifiesByStrength =
      utility >= relativeHeroFloor
    const qualifiesAsDistinctHero =
      utility >= absoluteHeroFloor &&
      directEnough &&
      distinctFromExistingHeroes

    const qualifiesAsHero =
      index === 0 ||
      (
        settings.allow_multiple_heroes &&
        directEnough &&
        (
          qualifiesByStrength ||
          qualifiesAsDistinctHero
        )
      )

    const planRole =
      qualifiesAsHero && !settings.exactly_one_hero
        ? 'hero'
        : index === 0
          ? 'hero'
          : 'secondary'

    const result = {
      ...candidate,
      plan_role: planRole,
      hero_qualification: {
        peak_selection_utility: round(peakUtility),
        relative_floor: round(relativeHeroFloor),
        absolute_floor: round(absoluteHeroFloor),
        candidate_utility: round(utility),
        meaningful_direct_benefit: directEnough,
        distinct_feature_family:
          distinctFromExistingHeroes,
      },
    }

    if (planRole === 'hero') heroesSoFar.push(result)
    return result
  })

  if (settings.exactly_one_hero) {
    return assigned.slice(0, 1).map((candidate) => ({
      ...candidate,
      plan_role: 'hero',
    }))
  }

  if (!settings.allow_secondary_actions) {
    return assigned.filter(
      (candidate) => candidate.plan_role === 'hero',
    )
  }

  return assigned
}

function deriveHydrafacialComponents(action, allCandidates) {
  if (action.modality_id !== 'hydrafacial_full_protocol') return []

  const featureIds = new Set(action.target_features.map((item) => item.feature_id))
  const componentIds = []

  if (
    featureIds.has('comedonal_congestion') ||
    featureIds.has('pore_visibility') ||
    featureIds.has('oiliness')
  ) {
    componentIds.push('hydrafacial_suction_extraction')
  }
  if (
    featureIds.has('texture_roughness') ||
    featureIds.has('luminosity_loss')
  ) {
    componentIds.push('hydrafacial_cutin_spatula')
  }
  if (
    featureIds.has('visual_dehydration') ||
    featureIds.has('luminosity_loss')
  ) {
    componentIds.push('jet_oxygen_infusion', 'ultrasound_infusion_face')
  }
  if (
    featureIds.has('peri_orbital_concern') &&
    action.selected_zones.some((zoneId) =>
      FACE_ZONE_ATLAS_V2.groups.peri_orbital.includes(zoneId),
    )
  ) {
    componentIds.push('ultrasound_infusion_ocular')
  }
  if (
    featureIds.has('erythema_redness') ||
    featureIds.has('barrier_stress')
  ) {
    componentIds.push('hydrafacial_ice_probe')
  }

  return unique(componentIds)
    .map((modalityId) => {
      const candidate = allCandidates.find(
        (item) => item.modality_id === modalityId,
      )
      const entry = MODALITY_RESPONSE_LIBRARY_V2[modalityId]
      return {
        modality_id: modalityId,
        modality_name: entry?.name ?? modalityId,
        reason: 'Component selected to deliver the target-specific part of the Hydrafacial protocol.',
        settings_source: entry?.settings_source ?? 'approved_protocol_library_only',
        selected_zones:
          candidate?.selected_zones?.filter((zoneId) =>
            action.selected_zones.includes(zoneId),
          ) ?? action.selected_zones,
      }
    })
    .filter((component) => component.selected_zones.length > 0)
}

function createModalityActions(selected, allCandidates) {
  return selected.map((candidate) => ({
    modality_id: candidate.modality_id,
    modality_name: candidate.modality_name,
    plan_role:
      candidate.plan_role ??
      (candidate.role_class === 'mandatory'
        ? 'mandatory'
        : candidate.role_class),
    category: candidate.category,
    selected_zones: candidate.selected_zones,
    target_features: candidate.target_features,
    intensity_by_zone: Object.fromEntries(
      candidate.zone_candidates.map((zone) => [
        zone.zone_id,
        zone.maximum_intensity,
      ]),
    ),
    zone_delivery: candidate.zone_candidates.map((zone) => ({
      zone_id: zone.zone_id,
      zone_label: zone.zone_label,
      utility: zone.utility,
      targets: zone.contributions.map((contribution) => ({
        feature_id: contribution.feature_id,
        burden_score_1_to_100: contribution.burden_score_1_to_100,
        expected_onset: contribution.expected_onset,
        clinical_role: contribution.clinical_role,
        response_notes: contribution.response_notes,
      })),
      maximum_intensity: zone.maximum_intensity,
      safety: zone.safety,
    })),
    selection_utility: candidate.selection_utility,
    estimated_duration_minutes: candidate.estimated_duration_minutes,
    downtime_band: candidate.downtime_band,
    combination_status: candidate.combination_status ?? 'compatible',
    support_reason: candidate.support_reason ?? null,
    required_by_optimizer_rule:
      candidate.required_by_optimizer_rule ?? null,
    must_preserve_in_session_compiler:
      candidate.must_preserve_in_session_compiler ?? false,
    included_components: deriveHydrafacialComponents(
      candidate,
      allCandidates,
    ),
    hydrafacial_delivery_mode:
      candidate.parent_modality_id === 'hydrafacial_full_protocol'
        ? 'modular_probe'
        : candidate.modality_id === 'hydrafacial_full_protocol'
          ? 'optional_bundle_using_only_selected_components'
          : null,
    combination_protocol_ids:
      candidate.combination_protocol_ids ?? [],
    settings_source: candidate.settings_source,
    patient_facing_benefit_claims:
      candidate.patient_facing_benefit_claims,
    limitations_and_non_claims:
      candidate.limitations_and_non_claims,
    expected_transient_effects:
      candidate.expected_transient_effects,
    eligibility_summary: candidate.eligibility_summary,
  }))
}

function buildZoneActionMap(skinState, modalityActions, candidates) {
  const map = {}

  for (const zoneId of FACE_ZONE_IDS) {
    const selectedActions = modalityActions
      .filter((action) => action.selected_zones.includes(zoneId))
      .map((action) => {
        const zoneDelivery = action.zone_delivery.find(
          (zone) => zone.zone_id === zoneId,
        )
        return {
          modality_id: action.modality_id,
          modality_name: action.modality_name,
          plan_role: action.plan_role,
          maximum_intensity: zoneDelivery?.maximum_intensity ?? 'medium',
          target_features: zoneDelivery?.targets ?? [],
          safety: zoneDelivery?.safety ?? {},
          must_preserve_in_session_compiler:
            action.must_preserve_in_session_compiler,
        }
      })

    const topBurdenFeatures = CORE_FEATURE_IDS
      .map((featureId) => ({
        feature_id: featureId,
        burden_score_1_to_100: zoneFeatureScore(
          skinState,
          featureId,
          zoneId,
        ),
      }))
      .filter((item) => item.burden_score_1_to_100 !== null)
      .sort(
        (a, b) =>
          b.burden_score_1_to_100 - a.burden_score_1_to_100 ||
          a.feature_id.localeCompare(b.feature_id),
      )
      .slice(0, 5)

    const deniedRelevantCandidates = candidates
      .filter(
        (candidate) =>
          candidate?.eligibility_summary &&
          candidate.eligibility_summary.overall_status ===
            ELIGIBILITY_STATUS.DENIED,
      )
      .slice(0, 3)
      .map((candidate) => ({
        modality_id: candidate.modality_id,
        reason:
          candidate.eligibility_summary.reasons?.[0] ??
          'Not eligible for the current session.',
      }))

    map[zoneId] = {
      zone_id: zoneId,
      zone_label: FACE_ZONE_ATLAS_V2.zones[zoneId].label,
      top_burden_features: topBurdenFeatures,
      selected_actions: selectedActions,
      treatment_status:
        selectedActions.length > 0
          ? 'targeted'
          : FACE_ZONE_ATLAS_V2.zones[zoneId].protection_zone
            ? 'protected_or_no_action'
            : 'no_selected_action',
      denied_or_deferred_examples: deniedRelevantCandidates,
    }
  }

  return map
}

function calculateTotalDuration(actions, settings) {
  return (
    settings.fixed_preparation_overhead_minutes +
    actions.reduce(
      (sum, action) => sum + Number(action.estimated_duration_minutes ?? 0),
      0,
    )
  )
}

function calculateOverallDowntime(actions) {
  const rank = [
    'none',
    'none_to_low',
    'low',
    'low_to_moderate',
    'moderate',
    'moderate_to_high',
    'high',
    'unknown',
  ]
  return actions.reduce((highest, action) => {
    const current = action.downtime_band ?? 'unknown'
    return rank.indexOf(current) > rank.indexOf(highest)
      ? current
      : highest
  }, 'none')
}

function buildUnaddressedConcerns({
  skinState,
  featurePriorityMap,
  zoneActionMap,
  settings,
}) {
  const unaddressed = []

  for (const zoneId of FACE_ZONE_IDS) {
    for (const featureId of CORE_FEATURE_IDS) {
      const burden = zoneFeatureScore(skinState, featureId, zoneId)
      if (
        burden === null ||
        burden < Math.max(
          settings.minimum_feature_burden_to_treat + 12,
          35,
        )
      ) {
        continue
      }
      if (featurePriorityMap.priorities[featureId] < 0.65) continue

      const addressed = zoneActionMap[zoneId].selected_actions.some(
        (action) =>
          action.target_features.some(
            (target) => target.feature_id === featureId,
          ),
      )
      if (!addressed) {
        unaddressed.push({
          zone_id: zoneId,
          feature_id: featureId,
          burden_score_1_to_100: burden,
          reason:
            'No compatible eligible action fit the current session objective, safety rules or time budget.',
        })
      }
    }
  }

  return unaddressed.sort(
    (a, b) =>
      b.burden_score_1_to_100 - a.burden_score_1_to_100 ||
      a.zone_id.localeCompare(b.zone_id) ||
      a.feature_id.localeCompare(b.feature_id),
  )
}

function buildClinicalInputFlags({
  skinState,
  zoneContext,
  capabilities,
}) {
  const flags = []

  const activeAcne = globalFeatureScore(
    skinState,
    'active_inflammatory_acne',
  )
  if (
    activeAcne >= 35 &&
    !capabilities.mnrf_sebaceous_targeting_protocol_available
  ) {
    flags.push({
      id: 'mnrf_acne_capability_unconfirmed',
      message:
        'MNRF acne benefit was not credited because a sebaceous-targeting probe/protocol was not confirmed.',
    })
  }

  return flags
}

function buildAlternatives(candidates, selectedIds, rejected, maximum = 8) {
  const rejectionById = Object.fromEntries(
    rejected.map((item) => [item.modality_id, item.reason]),
  )

  return candidates
    .filter((candidate) => !selectedIds.has(candidate.modality_id))
    .filter((candidate) => candidate.role_class !== 'mandatory')
    .sort(
      (a, b) =>
        b.modality_utility - a.modality_utility ||
        a.modality_id.localeCompare(b.modality_id),
    )
    .slice(0, maximum)
    .map((candidate) => ({
      modality_id: candidate.modality_id,
      modality_name: candidate.modality_name,
      utility: candidate.modality_utility,
      best_zones: candidate.selected_zones.slice(0, 5),
      target_features: candidate.target_features
        .slice(0, 5)
        .map((item) => item.feature_id),
      reason_not_selected:
        rejectionById[candidate.modality_id] ??
        'Lower incremental utility than the selected combination.',
    }))
}

export function optimizeZonalTreatmentV2({
  skinState,
  patientHistory = {},
  primaryConcerns = [],
  secondaryConcerns = [],
  featurePriorities = {},
  treatmentMode = 'single',
  objectivePreset = null,
  customObjective = null,
  sessionConstraints = {},
  regionalTemperaturesC = {},
  zoneContext = {},
  capabilities = {},
  candidateModalityIds = null,
  clinicProtocolIds = [],
  courseContext = {},
} = {}) {
  if (!skinState?.core_features) {
    throw new Error('Skin State V2 with core_features is required.')
  }

  const {
    contract: treatmentModeContract,
    settings: treatmentModeSettings,
  } = applyTreatmentModeContractV2(
    treatmentMode,
    sessionConstraints,
  )
  const settings = {
    ...OPTIMIZER_DEFAULTS_V2,
    ...treatmentModeSettings,
  }
  const effectiveObjectivePreset =
    objectivePreset ??
    treatmentModeContract.optimizer_objective_preset
  const objective = normalizeObjectivePreset(
    effectiveObjectivePreset,
    customObjective,
  )
  const effectiveClinicProtocolIds =
    getEffectiveClinicProtocolIdsV2(clinicProtocolIds)
  const featurePriorityMap = buildFeaturePriorityMapV2({
    primaryConcerns,
    secondaryConcerns,
    featurePriorities,
  })

  const ids = candidateModalityIds?.length
    ? candidateModalityIds
    : Object.keys(MODALITY_RESPONSE_LIBRARY_V2)

  const excluded = []
  const candidates = []

  for (const modalityId of ids) {
    const entry = MODALITY_RESPONSE_LIBRARY_V2[modalityId]
    if (!entry) {
      excluded.push({
        modality_id: modalityId,
        reason: 'unknown_modality_response_entry',
      })
      continue
    }

    const candidate = buildModalityCandidate({
      modalityId,
      entry,
      skinState,
      patientHistory,
      featurePriorityMap,
      objective,
      settings,
      regionalTemperaturesC,
      zoneContext,
      capabilities,
    })

    if (!candidate) {
      excluded.push({
        modality_id: modalityId,
        reason: entry.activation_status?.startsWith('active')
          ? 'no_actionable_eligible_zone_response'
          : `activation_status:${entry.activation_status}`,
      })
      continue
    }

    if (candidate.excluded) {
      excluded.push({
        modality_id: modalityId,
        reason: candidate.exclusion_reason,
        parent_modality_id: candidate.parent_modality_id,
      })
      continue
    }

    candidates.push(candidate)
  }

  const adjustedCandidates = applyCourseContextToCandidates(
    candidates,
    courseContext,
    treatmentModeContract,
  )
  const minimumDurationFillerCandidates =
    buildMinimumDurationFillerCandidatesV2_8({
      ids,
      existingCandidates: adjustedCandidates,
      skinState,
      patientHistory,
      featurePriorityMap,
      objective,
      settings,
      regionalTemperaturesC,
      zoneContext,
      capabilities,
    })

  const mandatoryActions = createMandatoryActions(
    adjustedCandidates,
    settings,
  )
  const correctiveSelection = selectCorrectiveCandidates(
    adjustedCandidates,
    settings,
    effectiveClinicProtocolIds,
  )
  const initiallyAssignedCorrective = assignRoles(
    correctiveSelection.selected,
    settings,
  )
  const supportSelection = selectSupportiveCandidates({
    candidates: adjustedCandidates,
    selectedCorrective: initiallyAssignedCorrective,
    skinState,
    settings,
    clinicProtocolIds: effectiveClinicProtocolIds,
  })
  const supportRebalance = rebalanceForTriggeredSupport({
    candidates: adjustedCandidates,
    selectedCorrective: initiallyAssignedCorrective,
    selectedSupport: supportSelection.selected,
    triggers: supportSelection.triggers,
    settings,
    clinicProtocolIds: effectiveClinicProtocolIds,
  })
  const initiallyCompletedCorrective = assignRoles(
    supportRebalance.corrective,
    settings,
  )
  const minimumDurationCompletion =
    completeSessionToMinimumDurationV2_8({
      candidates: minimumDurationFillerCandidates,
      corrective: initiallyCompletedCorrective,
      supportive: supportRebalance.supportive,
      mandatory: mandatoryActions,
      settings,
      clinicProtocolIds:
        effectiveClinicProtocolIds,
    })
  const correctiveActions = assignRoles(
    minimumDurationCompletion.corrective,
    settings,
  )
  const supportiveActions =
    minimumDurationCompletion.supportive
  const completedMandatoryActions =
    minimumDurationCompletion.mandatory

  const selectedCandidates = [
    ...correctiveActions,
    ...supportiveActions,
    ...completedMandatoryActions,
  ]
  const modalityActions = createModalityActions(
    selectedCandidates,
    adjustedCandidates,
  )
  modalityActions.sort(
    (a, b) =>
      ROLE_CLASS_RANK[
        a.plan_role === 'hero'
          ? 'hero'
          : a.plan_role === 'secondary'
            ? 'secondary'
            : a.plan_role
      ] -
        ROLE_CLASS_RANK[
          b.plan_role === 'hero'
            ? 'hero'
            : b.plan_role === 'secondary'
              ? 'secondary'
              : b.plan_role
        ] ||
      a.modality_id.localeCompare(b.modality_id),
  )

  const zoneActionMap = buildZoneActionMap(
    skinState,
    modalityActions,
    adjustedCandidates,
  )
  const totalDuration = calculateTotalDuration(
    modalityActions,
    settings,
  )
  const selectedIds = new Set(
    modalityActions.map((action) => action.modality_id),
  )

  return {
    optimizer_version: ZONAL_TREATMENT_OPTIMIZER_VERSION,
    optimizer_config_version:
      ZONAL_TREATMENT_OPTIMIZER_CONFIG_VERSION,
    clinical_constraints_version: CLINICAL_CONSTRAINTS_V2_VERSION,
    input_summary: {
      scan_id: skinState.scan?.scan_id ?? null,
      treatment_mode: treatmentModeContract.id,
      treatment_mode_contract_version:
        TREATMENT_MODE_CONTRACTS_VERSION,
      treatment_mode_contract: treatmentModeContract,
      objective,
      session_constraints: settings,
      clinic_protocol_registry_version:
        COMBINATION_PROTOCOL_REGISTRY_VERSION,
      clinic_preapproved_protocol_ids:
        effectiveClinicProtocolIds,
      course_context: courseContext,
      primary_concerns: primaryConcerns,
      secondary_concerns: secondaryConcerns,
      feature_priorities: featurePriorityMap,
      regional_temperatures_c: regionalTemperaturesC,
      capabilities,
    },
    plan: {
      treatment_mode: treatmentModeContract.id,
      hero_actions: modalityActions.filter(
        (action) => action.plan_role === 'hero',
      ),
      primary_hero:
        modalityActions.find((action) => action.plan_role === 'hero') ??
        null,
      hero_action:
        modalityActions.find((action) => action.plan_role === 'hero') ??
        null,
      secondary_actions: modalityActions.filter(
        (action) => action.plan_role === 'secondary',
      ),
      supportive_actions: modalityActions.filter(
        (action) => action.plan_role === 'supportive',
      ),
      mandatory_actions: modalityActions.filter(
        (action) => action.plan_role === 'mandatory',
      ),
      modality_actions: modalityActions,
      zone_action_map: zoneActionMap,
      estimated_total_duration_minutes: totalDuration,
      duration_target_minutes:
        treatmentModeContract.duration.target_minutes,
      duration_minimum_minutes:
        treatmentModeContract.duration.hard_minimum_minutes,
      duration_maximum_minutes:
        treatmentModeContract.duration.hard_maximum_minutes,
      duration_within_tolerance:
        totalDuration <=
        treatmentModeContract.duration.hard_maximum_minutes,
      duration_padding_required:
        totalDuration <
        treatmentModeContract.duration.hard_minimum_minutes,
      duration_padding_minutes: Math.max(
        0,
        treatmentModeContract.duration.hard_minimum_minutes -
          totalDuration,
      ),
      minimum_duration_completion:
        minimumDurationCompletion.completion,
      mandatory_lymphatic_drainage_present:
        modalityActions.some(
          (action) =>
            action.modality_id ===
            'lymphatic_drainage' &&
            action.plan_role === 'mandatory',
        ),
      mandatory_lymphatic_duration_minutes:
        modalityActions.find(
          (action) =>
            action.modality_id ===
            'lymphatic_drainage',
        )?.estimated_duration_minutes ?? 0,
      overall_downtime_band: calculateOverallDowntime(modalityActions),
      support_trigger_rules: supportSelection.triggers.map((rule) => ({
        id: rule.id,
        reason: rule.reason,
      })),
      session_compiler_contract: {
        all_selected_actions_are_locked: true,
        preserve_supportive_actions:
          settings.preserve_selected_support_in_session_compiler,
        exact_settings_source:
          'approved_protocol_library_only',
        voice_guidance_required_for_every_compiled_step: true,
        next_button_must_not_drop_supportive_steps: true,
        no_live_permission_or_approval_prompt: true,
        combination_protocols_are_backend_preapproved: true,
        treatment_mode: treatmentModeContract.id,
        duration_contract: treatmentModeContract.duration,
        hydrafacial_is_modular: true,
        one_probe_does_not_require_all_other_probes: true,
        mandatory_lymphatic_drainage_required: true,
        minimum_session_duration_enforced:
          ['single', 'multiple'].includes(
            treatmentModeContract.id,
          )
            ? 55
            : treatmentModeContract.duration
                .hard_minimum_minutes,
        session_compiler_must_not_invent_duration_fillers: true,
        future_multi_session_steps_must_not_be_compiled_before_reassessment:
          treatmentModeContract.id === 'multiple',
      },
    },
    alternatives: buildAlternatives(
      adjustedCandidates,
      selectedIds,
      correctiveSelection.rejected,
    ),
    unaddressed_concerns: buildUnaddressedConcerns({
      skinState,
      featurePriorityMap,
      zoneActionMap,
      settings,
    }),
    clinical_input_flags: buildClinicalInputFlags({
      skinState,
      zoneContext,
      capabilities,
    }),
    audit: {
      response_entries_considered: ids.length,
      actionable_candidates: adjustedCandidates.length,
      selected_action_count: modalityActions.length,
      excluded_entries: excluded,
      rejected_corrective_candidates:
        correctiveSelection.rejected,
      actions_removed_to_preserve_required_support:
        supportRebalance.removed_for_support,
      unmet_support_requirements:
        supportRebalance.unmet_support_requirements,
      minimum_duration_completion:
        minimumDurationCompletion.completion,
      minimum_duration_filler_candidate_count:
        minimumDurationFillerCandidates.length,
      mandatory_lymphatic_drainage_enforced: true,
      deterministic_tie_breaker:
        'utility_descending_then_modality_id_ascending',
      treatment_mode_contract_enforced:
        treatmentModeContract.id,
      multiple_heroes_allowed:
        treatmentModeContract.selection.allow_multiple_heroes,
      modular_hydrafacial_enabled:
        settings.allow_component_only_selection,
      live_permission_prompts_used: false,
      effective_clinic_protocol_ids:
        effectiveClinicProtocolIds,
      free_form_llm_strategy_used: false,
    },
  }
}
