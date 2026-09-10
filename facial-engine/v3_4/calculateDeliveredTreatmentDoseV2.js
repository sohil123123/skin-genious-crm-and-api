import {
  DELIVERED_DOSE_DEFAULTS_V2,
} from './predictedOutcomeRulesV2.js'

export const DELIVERED_TREATMENT_DOSE_VERSION =
  'aia_delivered_treatment_dose_v2.0.0'

const clamp = (value, min = 0, max = 1) =>
  Math.max(min, Math.min(max, Number(value)))

const unique = (values) =>
  [...new Set((values ?? []).filter(Boolean))]

function normaliseEndpointStatus(step) {
  const explicit = String(
    step?.endpoint_status ??
      step?.actual_settings?.endpoint_status ??
      '',
  ).toLowerCase()

  if (
    ['reached', 'complete', 'completed'].includes(
      explicit,
    )
  ) {
    return 'reached'
  }
  if (
    ['partial', 'partially_reached'].includes(
      explicit,
    )
  ) {
    return 'partial'
  }
  if (
    ['not_reached', 'none'].includes(explicit)
  ) {
    return 'not_reached'
  }

  const observed = String(
    step?.endpoint_observed ?? '',
  ).trim()
  return observed ? 'reached_or_documented' : 'unknown'
}

function statusDoseBase(step) {
  const status = step?.status
  if (status === 'completed') {
    const endpointStatus =
      normaliseEndpointStatus(step)
    if (
      endpointStatus === 'reached' ||
      endpointStatus === 'reached_or_documented'
    ) {
      return DELIVERED_DOSE_DEFAULTS_V2
        .completed_with_endpoint_reached
    }
    if (endpointStatus === 'partial') {
      return DELIVERED_DOSE_DEFAULTS_V2
        .partial_endpoint
    }
    return DELIVERED_DOSE_DEFAULTS_V2
      .completed_without_explicit_dose
  }
  if (status === 'stopped_for_safety') {
    return Math.min(
      DELIVERED_DOSE_DEFAULTS_V2
        .stopped_for_safety_ceiling,
      durationCompletionFraction(step),
    )
  }
  if (status === 'skipped_with_reason') {
    return DELIVERED_DOSE_DEFAULTS_V2.skipped
  }
  if (status === 'in_progress') {
    return DELIVERED_DOSE_DEFAULTS_V2.in_progress
  }
  return DELIVERED_DOSE_DEFAULTS_V2.not_started
}

function durationCompletionFraction(step) {
  const plannedSeconds =
    Number(step?.planned_duration_minutes ?? 0) * 60
  const actualSeconds = Number(
    step?.actual_duration_seconds ?? 0,
  )
  if (plannedSeconds <= 0) return 1
  if (actualSeconds <= 0) {
    return step?.status === 'completed' ? 0.9 : 0
  }
  return clamp(actualSeconds / plannedSeconds, 0, 1.15)
}

function explicitDoseFraction(step) {
  const value =
    step?.delivered_dose_fraction_0_to_1 ??
    step?.actual_settings
      ?.delivered_dose_fraction_0_to_1
  return Number.isFinite(Number(value))
    ? clamp(value)
    : null
}

function intensityDeliveryFraction(step) {
  const value =
    step?.delivered_intensity_fraction_0_to_1 ??
    step?.actual_settings
      ?.delivered_intensity_fraction_0_to_1
  return Number.isFinite(Number(value))
    ? clamp(value)
    : 1
}

function plannedZonesForAction(action) {
  return action?.selected_zones ?? []
}

function actualZonesForStep(step) {
  return unique(step?.actual_zones ?? [])
}

function findExecutionStepForAction(
  action,
  executionRecord,
) {
  const exact = (executionRecord?.steps ?? []).find(
    (step) =>
      step.modality_id === action.modality_id &&
      (
        step.source_action_modality_id ===
          action.modality_id ||
        !step.source_action_modality_id
      ),
  )
  if (exact) return exact

  return (executionRecord?.steps ?? []).find(
    (step) =>
      step.source_action_modality_id ===
      action.modality_id,
  )
}

function zoneDoseMap({
  action,
  step,
  overallDose,
}) {
  const plannedZones =
    plannedZonesForAction(action)
  const actualZones = actualZonesForStep(step)
  const explicitZoneCompletion =
    step?.zone_completion_fraction_0_to_1 ??
    step?.actual_settings
      ?.zone_completion_fraction_0_to_1 ??
    {}

  const actualZoneSet = new Set(actualZones)
  const actualZonesMissing =
    step?.status === 'completed' &&
    actualZones.length === 0

  return Object.fromEntries(
    plannedZones.map((zoneId) => {
      const explicit =
        explicitZoneCompletion?.[zoneId]
      if (Number.isFinite(Number(explicit))) {
        return [
          zoneId,
          {
            dose_fraction_0_to_1:
              clamp(explicit) * overallDose,
            evidence:
              'explicit_zone_completion_fraction',
          },
        ]
      }

      if (actualZonesMissing) {
        return [
          zoneId,
          {
            dose_fraction_0_to_1:
              overallDose * 0.9,
            evidence:
              'completed_step_but_actual_zones_not_recorded',
          },
        ]
      }

      return [
        zoneId,
        {
          dose_fraction_0_to_1:
            actualZoneSet.has(zoneId)
              ? overallDose
              : 0,
          evidence: actualZoneSet.has(zoneId)
            ? 'actual_zone_recorded'
            : 'planned_zone_not_delivered',
        },
      ]
    }),
  )
}

export function calculateDeliveredTreatmentDoseV2({
  optimizerResult,
  executionRecord,
} = {}) {
  if (!optimizerResult?.plan?.modality_actions) {
    throw new Error(
      'Optimizer result with modality actions is required.',
    )
  }
  if (!executionRecord?.steps) {
    throw new Error(
      'Session execution record is required.',
    )
  }

  const actions =
    optimizerResult.plan.modality_actions.map(
      (action) => {
        const step = findExecutionStepForAction(
          action,
          executionRecord,
        )

        if (!step) {
          return {
            modality_id: action.modality_id,
            plan_role: action.plan_role,
            planned_zones:
              plannedZonesForAction(action),
            execution_step_id: null,
            status: 'missing_execution_step',
            overall_dose_fraction_0_to_1:
              DELIVERED_DOSE_DEFAULTS_V2
                .missing_execution_step,
            zone_dose: Object.fromEntries(
              plannedZonesForAction(action).map(
                (zoneId) => [
                  zoneId,
                  {
                    dose_fraction_0_to_1: 0,
                    evidence:
                      'missing_execution_step',
                  },
                ],
              ),
            ),
            evidence_quality: 'low',
            deductions: [
              'No matching execution-record step was found.',
            ],
          }
        }

        const explicit = explicitDoseFraction(step)
        const statusBase = statusDoseBase(step)
        const durationFactor =
          durationCompletionFraction(step)
        const intensityFactor =
          intensityDeliveryFraction(step)

        const calculated = explicit ?? clamp(
          statusBase *
            Math.min(1, durationFactor) *
            intensityFactor,
        )
        const evidenceQuality = explicit !== null
          ? 'high'
          : step.actual_duration_seconds &&
              actualZonesForStep(step).length
            ? 'medium_high'
            : step.status === 'completed'
              ? 'medium'
              : 'low'

        return {
          modality_id: action.modality_id,
          plan_role: action.plan_role,
          planned_zones:
            plannedZonesForAction(action),
          execution_step_id: step.step_id,
          status: step.status,
          endpoint_status:
            normaliseEndpointStatus(step),
          overall_dose_fraction_0_to_1:
            Number(calculated.toFixed(4)),
          dose_components: {
            status_base:
              Number(statusBase.toFixed(4)),
            duration_completion_fraction:
              Number(durationFactor.toFixed(4)),
            intensity_delivery_fraction:
              Number(intensityFactor.toFixed(4)),
            explicit_dose_used:
              explicit !== null,
          },
          zone_dose: zoneDoseMap({
            action,
            step,
            overallDose: calculated,
          }),
          evidence_quality: evidenceQuality,
          deductions: [
            ...(explicit === null
              ? [
                  'Delivered dose was inferred from completion, duration, zones and endpoint because no explicit dose fraction was recorded.',
                ]
              : []),
            ...(actualZonesForStep(step).length ===
              0
              ? [
                  'Actual zones were not recorded; planned zones were used conservatively.',
                ]
              : []),
          ],
        }
      },
    )

  const meanDose =
    actions.length > 0
      ? actions.reduce(
          (sum, action) =>
            sum +
            action.overall_dose_fraction_0_to_1,
          0,
        ) / actions.length
      : 0

  return {
    version:
      DELIVERED_TREATMENT_DOSE_VERSION,
    session_id:
      executionRecord.session_id ?? null,
    treatment_mode:
      executionRecord.treatment_mode ?? null,
    action_doses: actions,
    mean_action_dose_fraction_0_to_1:
      Number(meanDose.toFixed(4)),
    all_planned_actions_completed:
      actions.every(
        (action) =>
          action.status === 'completed',
      ),
    stopped_for_safety:
      executionRecord.stopped_for_safety ===
      true,
  }
}

export function findActionDoseV2(
  deliveredDose,
  modalityId,
) {
  return (
    deliveredDose?.action_doses?.find(
      (item) =>
        item.modality_id === modalityId,
    ) ?? null
  )
}
