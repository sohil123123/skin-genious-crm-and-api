import {
  CLINICAL_CONSTRAINTS_V2_VERSION,
  ELIGIBILITY_STATUS,
  FULL_SKIN_FACE_ZONES,
  INTENSITY_LEVELS,
  MODALITY_PROFILES_V2,
  PATIENT_HISTORY_RULES_V2,
  PROTECTION_ZONES,
  RULE_CATALOG_V2,
  SESSION_LEVEL_RULES_V2,
  V2_SAFETY_THRESHOLDS,
  resolveModalityIdV2,
} from './clinicalConstraintsV2.js'
import { FACE_ZONE_ATLAS_V2 } from './faceZoneAtlasV2.js'

export const ELIGIBILITY_ENGINE_VERSION = 'aia_modality_eligibility_v2.0.0'

const STATUS_RANK = {
  [ELIGIBILITY_STATUS.ALLOWED]: 0,
  [ELIGIBILITY_STATUS.CAUTION]: 1,
  [ELIGIBILITY_STATUS.DENIED]: 2,
  [ELIGIBILITY_STATUS.NOT_APPLICABLE]: 3,
}

const boolish = (value) => value === true || value === 1 || String(value).toLowerCase() === 'true' || String(value).toLowerCase() === 'yes'
const numberOrNull = (value) => Number.isFinite(Number(value)) ? Number(value) : null

function firstDefined(source, keys, fallback = undefined) {
  for (const key of keys) {
    if (source?.[key] !== undefined && source?.[key] !== null) return source[key]
  }
  return fallback
}

export function normalizePatientHistoryV2(history = {}) {
  const allergiesRaw = firstDefined(history, ['allergies', 'known_allergies'], [])
  const allergies = Array.isArray(allergiesRaw)
    ? allergiesRaw.map((x) => String(x).toLowerCase())
    : String(allergiesRaw ?? '').split(',').map((x) => x.trim().toLowerCase()).filter(Boolean)

  const daysUntilTravel = numberOrNull(firstDefined(history, ['days_until_travel', 'travel_in_days']))
  const daysUntilEvent = numberOrNull(firstDefined(history, ['days_until_social_event', 'event_in_days']))

  return {
    age: numberOrNull(firstDefined(history, ['age', 'patient_age'])),
    pregnant: boolish(firstDefined(history, ['pregnant', 'is_pregnant'], false)),
    breastfeeding: boolish(firstDefined(history, ['breastfeeding', 'is_breastfeeding'], false)),
    diabetes: boolish(firstDefined(history, ['diabetes', 'has_diabetes'], false)),
    thyroid: boolish(firstDefined(history, ['thyroid', 'thyroid_issue', 'has_thyroid'], false)),
    pcod: boolish(firstDefined(history, ['PCOD', 'pcod', 'pcos'], false)),
    on_blood_thinners: boolish(firstDefined(history, ['on_blood_thinners', 'blood_thinners'], false)),
    used_salicylic_yesterday: boolish(firstDefined(history, ['used_salicylic_yesterday', 'salicylic_last_24_hours'], false)),
    used_glycolic_yesterday: boolish(firstDefined(history, ['used_glycolic_acid_yesterday', 'used_glycolic_yesterday', 'glycolic_last_24_hours'], false)),
    used_retinol_last_24_hours: boolish(firstDefined(history, ['used_retinol_last_24_hours', 'retinol_last_24_hours'], false)),
    laser_within_last_7_days: boolish(firstDefined(history, ['laser_within_last_7_days', 'recent_laser_7_days'], false)),
    travel_within_7_days: boolish(firstDefined(history, ['travel_within_7_days'], false)) || (daysUntilTravel !== null && daysUntilTravel >= 0 && daysUntilTravel <= 7),
    social_event_within_7_days: boolish(firstDefined(history, ['social_event_within_7_days'], false)) || (daysUntilEvent !== null && daysUntilEvent >= 0 && daysUntilEvent <= 7),
    daily_sun_exposure_hours: numberOrNull(firstDefined(history, ['daily_sun_exposure_hours', 'sun_exposure_hours', 'sun_exposure_per_day_hours'])) ?? 0,
    aloe_vera_allergy: boolish(firstDefined(history, ['aloe_vera_allergy'], false)) || allergies.some((x) => x.includes('aloe')),
    vitamin_c_allergy: boolish(firstDefined(history, ['vitamin_c_allergy'], false)) || allergies.some((x) => x.includes('vitamin c') || x.includes('ascorbic')),
    allergies,
  }
}

function lowerIntensity(current, steps = 1) {
  const index = INTENSITY_LEVELS.indexOf(current)
  return INTENSITY_LEVELS[Math.max(0, index - steps)]
}

function minIntensity(a, b) {
  return INTENSITY_LEVELS[Math.min(INTENSITY_LEVELS.indexOf(a), INTENSITY_LEVELS.indexOf(b))]
}

function addUnique(array, value) {
  if (!array.includes(value)) array.push(value)
}

function addRule(target, ruleId, reasonOverride) {
  addUnique(target.triggered_rules, ruleId)
  addUnique(target.reasons, reasonOverride ?? RULE_CATALOG_V2[ruleId] ?? ruleId)
}

function escalate(target, status) {
  if (STATUS_RANK[status] > STATUS_RANK[target.status]) target.status = status
}

function deny(target, ruleId, reason) {
  escalate(target, ELIGIBILITY_STATUS.DENIED)
  target.maximum_intensity = 'none'
  addRule(target, ruleId, reason)
}

function caution(target, ruleId, reason, reduceBy = 1) {
  if (target.status !== ELIGIBILITY_STATUS.DENIED) {
    escalate(target, ELIGIBILITY_STATUS.CAUTION)
    target.maximum_intensity = lowerIntensity(target.maximum_intensity, reduceBy)
  }
  addRule(target, ruleId, reason)
}

function globalFeature(skinState, featureId) {
  return skinState?.core_features?.[featureId]?.global_burden_score_1_to_100 ?? null
}

function zoneFeature(skinState, featureId, zoneId) {
  return skinState?.core_features?.[featureId]?.zone_scores_1_to_100?.[zoneId] ?? null
}

function featureReliability(skinState, featureId) {
  const reliability = skinState?.core_features?.[featureId]?.score_reliability
  if (!reliability) return null
  return reliability.score_1_to_100 ?? reliability.score ?? null
}

function isAggressive(profile) {
  return profile.risk_class === 'aggressive' || profile.invasive || profile.laser_based || ['medium_to_high', 'high'].includes(profile.exfoliation_strength)
}

function isStrongPeel(profile) {
  return profile.category === 'chemical_peel' && ['medium_to_high', 'high'].includes(profile.exfoliation_strength)
}

function isMediumOrStrongerPeel(profile) {
  return profile.category === 'chemical_peel' && ['medium', 'medium_to_high', 'high'].includes(profile.exfoliation_strength)
}

function isGentleBloodThinnerCompatible(profile) {
  if (profile.invasive || profile.laser_based || profile.heat_based) return false
  if (profile.category === 'chemical_peel') return ['low', 'low_to_medium'].includes(profile.exfoliation_strength)
  return profile.risk_class === 'gentle'
}

function calculateTemperatureContext(temperatures = {}) {
  const forehead = numberOrNull(firstDefined(temperatures, ['forehead_surface_c', 'forehead']))
  const left = numberOrNull(firstDefined(temperatures, ['left_cheek_surface_c', 'left_cheek']))
  const right = numberOrNull(firstDefined(temperatures, ['right_cheek_surface_c', 'right_cheek']))
  if ([forehead, left, right].some((value) => value === null)) {
    return { available: false, forehead_surface_c: forehead, left_cheek_surface_c: left, right_cheek_surface_c: right }
  }
  const avgCheek = (left + right) / 2
  return {
    available: true,
    forehead_surface_c: forehead,
    left_cheek_surface_c: left,
    right_cheek_surface_c: right,
    avg_facial_surface_temp_c: Number(((forehead + left + right) / 3).toFixed(2)),
    avg_cheek_surface_temp_c: Number(avgCheek.toFixed(2)),
    left_right_delta_c: Number(Math.abs(left - right).toFixed(2)),
    hotter_cheek: left > right ? 'left' : right > left ? 'right' : 'equal',
    forehead_minus_avg_cheek_delta_c: Number((forehead - avgCheek).toFixed(2)),
  }
}

function makeTarget(defaultIntensity) {
  return {
    status: ELIGIBILITY_STATUS.ALLOWED,
    maximum_intensity: defaultIntensity,
    reasons: [],
    triggered_rules: [],
    required_protection: [],
    required_support_actions: [],
  }
}

function applyHistoryRules(globalTarget, profile, modalityId, history, output) {
  if (history.pregnant && !PATIENT_HISTORY_RULES_V2.pregnancy.allowed_modality_ids.includes(modalityId)) {
    deny(globalTarget, 'V2_PREGNANCY_DENY')
  }

  if (modalityId === 'hifu') {
    const { min_age, max_age } = PATIENT_HISTORY_RULES_V2.hifu_age_window
    if (history.age === null || history.age < min_age || history.age > max_age) {
      deny(globalTarget, 'V2_HIFU_AGE_DENY')
    }
  }

  if (history.on_blood_thinners && !isGentleBloodThinnerCompatible(profile)) {
    deny(globalTarget, 'V2_BLOOD_THINNER_AGGRESSIVE_DENY')
  }

  if (history.laser_within_last_7_days && (profile.laser_based || profile.heat_based || profile.invasive)) {
    deny(globalTarget, 'V2_RECENT_LASER_DENY')
  }

  if (history.used_retinol_last_24_hours) {
    if (profile.laser_based) deny(globalTarget, 'V2_RECENT_RETINOL_QSWITCH_DENY')
    else if (isStrongPeel(profile) || profile.invasive) deny(globalTarget, 'V2_RECENT_RETINOL_AGGRESSIVE_DENY')
    else if (profile.energy_based) caution(globalTarget, 'V2_RECENT_RETINOL_ENERGY_CAUTION', undefined, 2)
    else if (profile.category === 'chemical_peel') caution(globalTarget, 'V2_RECENT_RETINOL_AGGRESSIVE_DENY', 'Recent retinol restricts this peel to a gentler approach or deferral.', 2)
  }

  const recentAcid = history.used_salicylic_yesterday || history.used_glycolic_yesterday
  if (recentAcid) {
    if (profile.laser_based) deny(globalTarget, 'V2_RECENT_ACID_QSWITCH_DENY')
    else if (isStrongPeel(profile)) deny(globalTarget, 'V2_RECENT_ACID_STRONG_PEEL_DENY')
    else if (isMediumOrStrongerPeel(profile)) caution(globalTarget, 'V2_RECENT_ACID_STRONG_PEEL_DENY', 'Recent acid use requires reducing strength or deferring the peel.', 2)
  }

  if (history.daily_sun_exposure_hours > 2) {
    if (profile.laser_based) deny(globalTarget, 'V2_HIGH_SUN_QSWITCH_DENY')
    if (isMediumOrStrongerPeel(profile)) deny(globalTarget, 'V2_HIGH_SUN_STRONG_PEEL_DENY')
  } else if (history.daily_sun_exposure_hours >= 1 && isStrongPeel(profile)) {
    deny(globalTarget, 'V2_MODERATE_SUN_STRONG_PEEL_DENY')
  }

  if (history.travel_within_7_days || history.social_event_within_7_days) {
    if (profile.laser_based) deny(globalTarget, 'V2_TRAVEL_EVENT_QSWITCH_DENY')
    if (isStrongPeel(profile)) deny(globalTarget, 'V2_TRAVEL_EVENT_STRONG_PEEL_DENY')
  }

  if (history.aloe_vera_allergy) {
    output.ingredient_exclusions.push(...PATIENT_HISTORY_RULES_V2.ingredient_exclusions.aloe_vera_allergy)
  }
  if (history.vitamin_c_allergy) {
    output.ingredient_exclusions.push(...PATIENT_HISTORY_RULES_V2.ingredient_exclusions.vitamin_c_allergy)
  }
  if (history.breastfeeding) {
    output.ingredient_exclusions.push(...PATIENT_HISTORY_RULES_V2.ingredient_exclusions.breastfeeding)
    addUnique(output.planning_directives, 'Exclude retinoid-containing peels and products while breastfeeding.')
  }

  if (history.diabetes) addUnique(output.planning_directives, 'Consider diabetes-related acanthotic/pigment patterns when setting prognosis; do not overpromise response.')
  if (history.thyroid) addUnique(output.planning_directives, 'Consider thyroid-associated melasma/pigmentation when setting prognosis.')
  if (history.pcod) addUnique(output.planning_directives, 'Consider PCOD/PCOS-related acne persistence when setting improvement expectations.')
}

function applyGlobalSkinStateRules(globalTarget, profile, skinState, output) {
  const scores = {
    barrier_stress: globalFeature(skinState, 'barrier_stress'),
    erythema_redness: globalFeature(skinState, 'erythema_redness'),
    visual_dehydration: globalFeature(skinState, 'visual_dehydration'),
    active_inflammatory_acne: globalFeature(skinState, 'active_inflammatory_acne'),
  }
  output.relevant_skin_state.global = scores

  const aggressive = isAggressive(profile)
  if (aggressive && scores.barrier_stress !== null && scores.barrier_stress >= V2_SAFETY_THRESHOLDS.global.barrier_stress.deny_aggressive) {
    deny(globalTarget, 'V2_GLOBAL_BARRIER_DENY')
  } else if (scores.barrier_stress !== null && scores.barrier_stress >= V2_SAFETY_THRESHOLDS.global.barrier_stress.caution) {
    caution(globalTarget, 'V2_SKIN_STATE_CAUTION', `Global barrier stress score ${scores.barrier_stress} requires reduced intensity and barrier-supportive recovery.`)
  }

  if (aggressive && scores.erythema_redness !== null && scores.erythema_redness >= V2_SAFETY_THRESHOLDS.global.erythema_redness.deny_aggressive) {
    deny(globalTarget, 'V2_GLOBAL_ERYTHEMA_DENY')
  } else if (scores.erythema_redness !== null && scores.erythema_redness >= V2_SAFETY_THRESHOLDS.global.erythema_redness.caution) {
    caution(globalTarget, 'V2_SKIN_STATE_CAUTION', `Global erythema score ${scores.erythema_redness} requires a calmer treatment strategy.`)
  }

  if (aggressive && scores.visual_dehydration !== null && scores.visual_dehydration >= V2_SAFETY_THRESHOLDS.global.visual_dehydration.deny_aggressive) {
    deny(globalTarget, 'V2_GLOBAL_DEHYDRATION_DENY')
  } else if (scores.visual_dehydration !== null && scores.visual_dehydration >= V2_SAFETY_THRESHOLDS.global.visual_dehydration.caution) {
    caution(globalTarget, 'V2_SKIN_STATE_CAUTION', `Visual dehydration burden ${scores.visual_dehydration} requires reduced intensity and hydration support.`)
  }

  if (!['sali_ds_peel', 'salicylic_30_peel', 'salicylic_20_peel', 'combination_peel', 'carbon_facial', 'high_frequency'].includes(output.modality_id)
      && scores.active_inflammatory_acne !== null
      && scores.active_inflammatory_acne >= V2_SAFETY_THRESHOLDS.global.active_inflammatory_acne.caution_for_non_acne_energy
      && aggressive) {
    caution(globalTarget, 'V2_SKIN_STATE_CAUTION', 'High active-acne burden requires avoiding indiscriminate aggressive passes over inflamed lesions.')
  }

  const relevantFeatures = ['barrier_stress', 'erythema_redness', 'visual_dehydration']
  const reliabilities = relevantFeatures.map((f) => featureReliability(skinState, f)).filter((x) => x !== null)
  const minReliability = reliabilities.length ? Math.min(...reliabilities) : null
  output.relevant_skin_state.minimum_relevant_reliability_1_to_100 = minReliability
  if (minReliability !== null && minReliability < V2_SAFETY_THRESHOLDS.scan_reliability.caution_below) {
    caution(globalTarget, 'V2_SCAN_RELIABILITY_CAUTION', `Minimum relevant feature reliability is ${minReliability}; retain treatment access but reduce confidence and consider recapture.`)
    addUnique(output.required_support_actions, 'Consider scan recapture if the low-reliability feature materially affects modality safety.')
  }
}

function applyTemperatureRules(globalTarget, profile, temperatureContext, output) {
  output.temperature_context = temperatureContext
  if (!temperatureContext.available) return
  const t = V2_SAFETY_THRESHOLDS.temperature_c
  if (temperatureContext.avg_facial_surface_temp_c >= t.deny_aggressive_global && (profile.heat_based || isAggressive(profile))) {
    deny(globalTarget, 'V2_TEMPERATURE_GLOBAL_DENY')
    addUnique(output.required_support_actions, 'Use soothing/barrier-repair care only until temperature/reactivity normalises.')
  } else if (temperatureContext.avg_facial_surface_temp_c >= t.caution_global) {
    caution(globalTarget, 'V2_TEMPERATURE_GLOBAL_CAUTION')
    addUnique(output.required_support_actions, 'Monitor skin response during the session and shorten stimulating steps.')
  }
}

function applyZoneRules(zoneTarget, profile, modalityId, zoneId, skinState, temperatureContext) {
  if (!profile.allowed_zones.includes(zoneId)) {
    zoneTarget.status = ELIGIBILITY_STATUS.NOT_APPLICABLE
    zoneTarget.maximum_intensity = 'none'
    addRule(zoneTarget, 'V2_ZONE_NOT_SUPPORTED')
    return
  }

  if ((profile.protection_zones ?? []).includes(zoneId)) {
    deny(zoneTarget, 'V2_PROTECTION_ZONE')
    addUnique(zoneTarget.required_protection, `Protect or avoid ${FACE_ZONE_ATLAS_V2.zones[zoneId].label}.`)
  } else if (PROTECTION_ZONES.includes(zoneId) && isAggressive(profile)) {
    caution(zoneTarget, 'V2_PROTECTION_ZONE', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} is a protection-sensitive zone; use a conservative zone-specific protocol.`, 2)
    addUnique(zoneTarget.required_protection, `Use explicit protection and reduced settings for ${FACE_ZONE_ATLAS_V2.zones[zoneId].label}.`)
  }

  const barrier = zoneFeature(skinState, 'barrier_stress', zoneId)
  const redness = zoneFeature(skinState, 'erythema_redness', zoneId)
  const dehydration = zoneFeature(skinState, 'visual_dehydration', zoneId)
  const acne = zoneFeature(skinState, 'active_inflammatory_acne', zoneId)
  zoneTarget.skin_state = { barrier_stress: barrier, erythema_redness: redness, visual_dehydration: dehydration, active_inflammatory_acne: acne }

  const aggressive = isAggressive(profile)
  if (aggressive && barrier !== null && barrier >= V2_SAFETY_THRESHOLDS.zone.barrier_stress.deny_aggressive) {
    deny(zoneTarget, 'V2_ZONE_BARRIER_DENY', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} barrier stress score ${barrier} is too high for aggressive treatment.`)
  } else if (barrier !== null && barrier >= V2_SAFETY_THRESHOLDS.zone.barrier_stress.caution) {
    caution(zoneTarget, 'V2_SKIN_STATE_CAUTION', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} barrier stress score ${barrier} requires reduced local intensity.`)
  }

  if (aggressive && redness !== null && redness >= V2_SAFETY_THRESHOLDS.zone.erythema_redness.deny_aggressive) {
    deny(zoneTarget, 'V2_ZONE_ERYTHEMA_DENY', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} erythema score ${redness} is too high for aggressive treatment.`)
  } else if (redness !== null && redness >= V2_SAFETY_THRESHOLDS.zone.erythema_redness.caution) {
    caution(zoneTarget, 'V2_SKIN_STATE_CAUTION', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} erythema score ${redness} requires reduced local intensity.`)
  }

  if (aggressive && dehydration !== null && dehydration >= V2_SAFETY_THRESHOLDS.zone.visual_dehydration.deny_aggressive) {
    deny(zoneTarget, 'V2_ZONE_DEHYDRATION_DENY', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} dehydration burden ${dehydration} is too high for aggressive treatment.`)
  } else if (dehydration !== null && dehydration >= V2_SAFETY_THRESHOLDS.zone.visual_dehydration.caution) {
    caution(zoneTarget, 'V2_SKIN_STATE_CAUTION', `${FACE_ZONE_ATLAS_V2.zones[zoneId].label} dehydration burden ${dehydration} requires hydration support and reduced intensity.`)
  }

  if (modalityId === 'q_switch_laser') {
    if (acne !== null && acne >= V2_SAFETY_THRESHOLDS.zone.active_inflammatory_acne.q_switch_deny) {
      deny(zoneTarget, 'V2_QSWITCH_ACTIVE_ACNE_ZONE_DENY')
    } else if (acne !== null && acne >= V2_SAFETY_THRESHOLDS.zone.active_inflammatory_acne.q_switch_caution) {
      caution(zoneTarget, 'V2_QSWITCH_ACTIVE_ACNE_ZONE_CAUTION', undefined, 2)
      addUnique(zoneTarget.required_protection, 'Avoid direct passes over active inflammatory lesions.')
    }
  }

  if (temperatureContext.available) {
    const t = V2_SAFETY_THRESHOLDS.temperature_c
    const leftSide = ['malar_medial_left', 'cheek_lateral_left', 'peri_orbital_left', 'jawline_left'].includes(zoneId)
    const rightSide = ['malar_medial_right', 'cheek_lateral_right', 'peri_orbital_right', 'jawline_right'].includes(zoneId)
    const hotterSideMatches = (temperatureContext.hotter_cheek === 'left' && leftSide) || (temperatureContext.hotter_cheek === 'right' && rightSide)
    if (hotterSideMatches && temperatureContext.left_right_delta_c >= t.significant_cheek_asymmetry) {
      caution(zoneTarget, 'V2_TEMPERATURE_LOCAL_CAUTION', 'This is the hotter cheek side; reduce local intensity and avoid aggressive repeated passes.', 2)
    } else if (hotterSideMatches && temperatureContext.left_right_delta_c >= t.moderate_cheek_asymmetry) {
      caution(zoneTarget, 'V2_TEMPERATURE_LOCAL_CAUTION', 'Moderate cheek-temperature asymmetry requires a mild local intensity reduction.', 1)
    }

    if (temperatureContext.forehead_minus_avg_cheek_delta_c >= t.relative_zone_delta && ['forehead_left', 'forehead_center', 'forehead_right', 'glabella'].includes(zoneId)) {
      caution(zoneTarget, 'V2_TEMPERATURE_LOCAL_CAUTION', 'Forehead is relatively warmer; avoid overheating this zone.', 1)
    }
    if (temperatureContext.forehead_minus_avg_cheek_delta_c <= -t.relative_zone_delta && (leftSide || rightSide)) {
      caution(zoneTarget, 'V2_TEMPERATURE_LOCAL_CAUTION', 'Cheeks are relatively warmer; prioritise anti-inflammatory/barrier support and reduce exfoliation.', 1)
    }
  }
}

function finalOverallStatus(globalTarget, zoneStatus) {
  if (globalTarget.status === ELIGIBILITY_STATUS.DENIED) return ELIGIBILITY_STATUS.DENIED
  const actionable = Object.values(zoneStatus).filter((z) => z.status !== ELIGIBILITY_STATUS.NOT_APPLICABLE)
  if (actionable.length === 0) return ELIGIBILITY_STATUS.NOT_APPLICABLE
  if (actionable.every((z) => z.status === ELIGIBILITY_STATUS.DENIED)) return ELIGIBILITY_STATUS.DENIED
  if (globalTarget.status === ELIGIBILITY_STATUS.CAUTION || actionable.some((z) => z.status === ELIGIBILITY_STATUS.CAUTION || z.status === ELIGIBILITY_STATUS.DENIED)) {
    return ELIGIBILITY_STATUS.CAUTION
  }
  return ELIGIBILITY_STATUS.ALLOWED
}

export function evaluateModalityEligibilityV2({
  skinState,
  patientHistory = {},
  modality,
  intendedZones = null,
  regionalTemperaturesC = {},
} = {}) {
  const modalityId = resolveModalityIdV2(modality)
  if (!modalityId) {
    return {
      eligibility_engine_version: ELIGIBILITY_ENGINE_VERSION,
      constraints_version: CLINICAL_CONSTRAINTS_V2_VERSION,
      modality_id: null,
      modality_name: String(modality ?? ''),
      overall_status: ELIGIBILITY_STATUS.DENIED,
      maximum_intensity: 'none',
      triggered_rules: ['V2_UNKNOWN_MODALITY'],
      reasons: [RULE_CATALOG_V2.V2_UNKNOWN_MODALITY],
      allowed_zones: [], caution_zones: [], denied_zones: [], not_applicable_zones: [], zone_status: {},
      required_support_actions: [], planning_directives: [], ingredient_exclusions: [],
    }
  }

  const profile = MODALITY_PROFILES_V2[modalityId]
  const history = normalizePatientHistoryV2(patientHistory)
  const zones = [...new Set((intendedZones?.length ? intendedZones : profile.allowed_zones).filter((z) => FACE_ZONE_ATLAS_V2.zones[z]))]
  const temperatureContext = calculateTemperatureContext(regionalTemperaturesC)
  const globalTarget = makeTarget(profile.default_max_intensity)
  const output = {
    eligibility_engine_version: ELIGIBILITY_ENGINE_VERSION,
    constraints_version: CLINICAL_CONSTRAINTS_V2_VERSION,
    modality_id: modalityId,
    modality_name: profile.name,
    modality_profile: {
      category: profile.category,
      risk_class: profile.risk_class,
      energy_based: profile.energy_based,
      laser_based: profile.laser_based,
      heat_based: profile.heat_based,
      invasive: profile.invasive,
      exfoliation_strength: profile.exfoliation_strength,
    },
    normalized_patient_history: history,
    overall_status: ELIGIBILITY_STATUS.ALLOWED,
    maximum_intensity: profile.default_max_intensity,
    triggered_rules: [],
    reasons: [],
    allowed_zones: [],
    caution_zones: [],
    denied_zones: [],
    not_applicable_zones: [],
    zone_status: {},
    required_support_actions: [],
    planning_directives: [],
    ingredient_exclusions: [],
    relevant_skin_state: { global: {} },
    temperature_context: temperatureContext,
    session_level_rules: SESSION_LEVEL_RULES_V2,
  }

  applyHistoryRules(globalTarget, profile, modalityId, history, output)
  applyGlobalSkinStateRules(globalTarget, profile, skinState, output)
  applyTemperatureRules(globalTarget, profile, temperatureContext, output)

  for (const zoneId of zones) {
    const zoneTarget = makeTarget(minIntensity(profile.default_max_intensity, globalTarget.maximum_intensity))
    if (globalTarget.status === ELIGIBILITY_STATUS.DENIED) {
      deny(zoneTarget, globalTarget.triggered_rules[0] ?? 'V2_SKIN_STATE_CAUTION', globalTarget.reasons[0] ?? 'Globally denied.')
    } else {
      applyZoneRules(zoneTarget, profile, modalityId, zoneId, skinState, temperatureContext)
    }
    output.zone_status[zoneId] = zoneTarget
    if (zoneTarget.status === ELIGIBILITY_STATUS.ALLOWED) output.allowed_zones.push(zoneId)
    else if (zoneTarget.status === ELIGIBILITY_STATUS.CAUTION) output.caution_zones.push(zoneId)
    else if (zoneTarget.status === ELIGIBILITY_STATUS.DENIED) output.denied_zones.push(zoneId)
    else output.not_applicable_zones.push(zoneId)
  }

  output.overall_status = finalOverallStatus(globalTarget, output.zone_status)
  const usableZoneIntensities = Object.values(output.zone_status)
    .filter((z) => [ELIGIBILITY_STATUS.ALLOWED, ELIGIBILITY_STATUS.CAUTION].includes(z.status))
    .map((z) => z.maximum_intensity)
  output.maximum_intensity = usableZoneIntensities.length
    ? usableZoneIntensities.reduce((min, current) => minIntensity(min, current), globalTarget.maximum_intensity)
    : 'none'

  output.triggered_rules = [...new Set([
    ...globalTarget.triggered_rules,
    ...Object.values(output.zone_status).flatMap((z) => z.triggered_rules),
  ])]
  output.reasons = [...new Set([
    ...globalTarget.reasons,
    ...Object.values(output.zone_status).flatMap((z) => z.reasons),
  ])]
  output.required_support_actions = [...new Set([
    ...output.required_support_actions,
    ...globalTarget.required_support_actions,
    ...Object.values(output.zone_status).flatMap((z) => z.required_support_actions),
  ])]
  output.ingredient_exclusions = [...new Set(output.ingredient_exclusions)]

  const acneGlobal = globalFeature(skinState, 'active_inflammatory_acne')
  if (acneGlobal !== null && acneGlobal >= 25) {
    addUnique(output.planning_directives, SESSION_LEVEL_RULES_V2.active_acne_spot_support.instruction)
  }
  if (output.denied_zones.length > 0 && output.overall_status !== ELIGIBILITY_STATUS.DENIED) {
    addUnique(output.planning_directives, 'Use zone-specific delivery: treat eligible zones and explicitly protect/skip denied zones.')
  }
  if (output.overall_status === ELIGIBILITY_STATUS.CAUTION) {
    addUnique(output.planning_directives, 'Use lower initial settings, fewer passes, active endpoint monitoring, and avoid unnecessary stacking.')
  }

  return output
}

export function evaluateAllModalitiesV2({
  skinState,
  patientHistory = {},
  intendedZonesByModality = {},
  regionalTemperaturesC = {},
  modalityIds = Object.keys(MODALITY_PROFILES_V2),
} = {}) {
  const results = {}
  for (const modalityId of modalityIds) {
    results[modalityId] = evaluateModalityEligibilityV2({
      skinState,
      patientHistory,
      modality: modalityId,
      intendedZones: intendedZonesByModality[modalityId] ?? null,
      regionalTemperaturesC,
    })
  }
  return {
    eligibility_engine_version: ELIGIBILITY_ENGINE_VERSION,
    constraints_version: CLINICAL_CONSTRAINTS_V2_VERSION,
    results,
    summary: {
      allowed: Object.values(results).filter((r) => r.overall_status === ELIGIBILITY_STATUS.ALLOWED).map((r) => r.modality_id),
      allowed_with_caution: Object.values(results).filter((r) => r.overall_status === ELIGIBILITY_STATUS.CAUTION).map((r) => r.modality_id),
      denied: Object.values(results).filter((r) => r.overall_status === ELIGIBILITY_STATUS.DENIED).map((r) => r.modality_id),
      not_applicable: Object.values(results).filter((r) => r.overall_status === ELIGIBILITY_STATUS.NOT_APPLICABLE).map((r) => r.modality_id),
    },
  }
}
