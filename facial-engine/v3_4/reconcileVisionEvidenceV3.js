export const VISION_EVIDENCE_RECONCILIATION_VERSION =
  'aia_vision_evidence_reconciliation_v3.3.0'

const clone = (value) => JSON.parse(JSON.stringify(value))

function capComponent(component, maximumGrade, reason) {
  if (!component) return false
  if (Number(component.grade_0_to_5 ?? 0) <= maximumGrade) return false
  component.grade_0_to_5 = maximumGrade
  if (component.plausible_grade_range_0_to_5) {
    component.plausible_grade_range_0_to_5.max = Math.min(
      Number(component.plausible_grade_range_0_to_5.max ?? maximumGrade),
      maximumGrade,
    )
    component.plausible_grade_range_0_to_5.min = Math.min(
      Number(component.plausible_grade_range_0_to_5.min ?? maximumGrade),
      maximumGrade,
    )
  }
  component.reason = `${component.reason || ''} Reconciled: ${reason}`.trim()
  return true
}

function capAllComponents(zone, maximumGrade, reason) {
  let changed = false
  for (const component of Object.values(zone?.components ?? {})) {
    changed = capComponent(component, maximumGrade, reason) || changed
  }
  return changed
}

function flagIs(zoneMorphology, flagId, value = 'true') {
  return zoneMorphology?.flags?.[flagId] === value
}

export function reconcileVisionEvidenceWithMorphologyV3(
  features,
  morphologyMap,
) {
  const reconciled = clone(features)
  const audit = []

  for (const [zoneId, morphology] of Object.entries(
    morphologyMap?.zones ?? {},
  )) {
    const activeAcneZone =
      reconciled.active_inflammatory_acne?.zones?.[zoneId]
    if (
      activeAcneZone &&
      flagIs(morphology, 'active_inflammatory_lesion', 'false') &&
      (
        flagIs(morphology, 'flat_focal_pigment') ||
        flagIs(morphology, 'scar_or_friction_mark')
      )
    ) {
      if (
        capAllComponents(
          activeAcneZone,
          1,
          'post-inflammatory/focal marks are not active inflammatory acne',
        )
      ) {
        audit.push({
          zone_id: zoneId,
          feature_id: 'active_inflammatory_acne',
          action: 'capped_to_minimal',
          reason: 'morphology_excludes_active_lesion',
        })
      }
    }

    const visiblePigmentZone =
      reconciled.visible_pigmentation?.zones?.[zoneId]
    const onlyExcludedPigmentObjects =
      flagIs(morphology, 'background_pigment', 'false') &&
      !flagIs(morphology, 'flat_focal_pigment', 'true') &&
      (
        flagIs(morphology, 'raised_pigmented_lesion', 'true') ||
        flagIs(morphology, 'scar_or_friction_mark', 'true') ||
        flagIs(morphology, 'structural_shadow', 'true') ||
        flagIs(morphology, 'beard_or_stubble', 'true')
      )

    if (visiblePigmentZone && onlyExcludedPigmentObjects) {
      if (
        capAllComponents(
          visiblePigmentZone,
          1,
          'raised lesions, scars, shadows and beard/stubble do not define background pigmentation',
        )
      ) {
        audit.push({
          zone_id: zoneId,
          feature_id: 'visible_pigmentation',
          action: 'capped_to_minimal',
          reason: 'excluded_focal_or_shadow_morphology',
        })
      }
    }

    const oilZone = reconciled.oiliness?.zones?.[zoneId]
    if (
      oilZone &&
      flagIs(morphology, 'specular_glare', 'true') &&
      !flagIs(morphology, 'comedonal_finding', 'true')
    ) {
      let changed = false
      changed =
        capComponent(
          oilZone.components?.visible_shine_intensity,
          2,
          'specular glare cannot independently define oiliness',
        ) || changed
      changed =
        capComponent(
          oilZone.components?.shine_coverage,
          2,
          'specular glare cannot independently define oiliness',
        ) || changed
      if (changed) {
        audit.push({
          zone_id: zoneId,
          feature_id: 'oiliness',
          action: 'glare_cap',
          reason: 'specular_glare',
        })
      }
    }

    const fineLineZone = reconciled.fine_line_visibility?.zones?.[zoneId]
    if (
      fineLineZone &&
      flagIs(morphology, 'dehydration_microline_pattern', 'true') &&
      flagIs(morphology, 'persistent_structural_line_pattern', 'false')
    ) {
      if (
        capAllComponents(
          fineLineZone,
          2,
          'line pattern is predominantly dehydration-linked rather than persistent structural wrinkling',
        )
      ) {
        audit.push({
          zone_id: zoneId,
          feature_id: 'fine_line_visibility',
          action: 'dehydration_line_cap',
          reason: 'dehydration_microline_pattern',
        })
      }
    }

    for (const feature of Object.values(reconciled)) {
      const zone = feature?.zones?.[zoneId]
      if (!zone) continue
      if (morphology.assessment_status === 'partially_assessable') {
        zone.assessment_status = 'partially_assessable'
        zone.visibility_fraction_0_to_100 =
          morphology.visibility_fraction_0_to_100 ??
          zone.visibility_fraction_0_to_100 ??
          60
      }
      if (morphology.assessment_status === 'not_assessable') {
        zone.assessment_status = 'not_assessable'
        zone.visibility_fraction_0_to_100 = 0
      }
    }
  }

  return {
    features: reconciled,
    audit,
    reconciliation_version: VISION_EVIDENCE_RECONCILIATION_VERSION,
  }
}
