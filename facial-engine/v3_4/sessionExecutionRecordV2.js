import {
  STEP_STATUS,
} from './therapistSessionV2.schema.js'
import {
  SESSION_STEP_EVENTS_V2,
} from './startStepUiContractV2.js'

export const SESSION_EXECUTION_RECORD_VERSION =
  'aia_session_execution_record_v2.1.0'

const nowIso = (provided) =>
  provided ?? new Date().toISOString()

export function createSessionExecutionRecordV2(
  compiledSession,
) {
  return {
    execution_record_version:
      SESSION_EXECUTION_RECORD_VERSION,
    session_id: compiledSession.session_id,
    plan_id: compiledSession.plan_id ?? null,
    treatment_mode: compiledSession.treatment_mode,
    session_number: compiledSession.session_number,
    compile_version: compiledSession.compiler_version,
    session_status: 'not_started',
    started_at_iso: null,
    completed_at_iso: null,
    stopped_for_safety: false,
    general_notes: [],
    deviations: [],
    steps: compiledSession.steps.map((step) => ({
      step_id: step.step_id,
      step_number: step.step_number,
      modality_id: step.modality_id,
      source_action_modality_id:
        step.source_action_modality_id ?? step.modality_id,
      planned_duration_minutes: step.duration_minutes,
      planned_zones: step.target_zones,
      planned_protocol_reference:
        step.protocol_reference,
      status: STEP_STATUS.NOT_STARTED,
      started_at_iso: null,
      completed_at_iso: null,
      actual_duration_seconds: null,
      actual_zones: [],
      actual_settings: {},
      actual_passes_or_shots: {},
      actual_products: [],
      endpoint_observed: null,
      endpoint_status: null,
      delivered_dose_fraction_0_to_1: null,
      delivered_intensity_fraction_0_to_1: null,
      zone_completion_fraction_0_to_1: {},
      tolerance: null,
      safety_signals_observed: [],
      stop_reason: null,
      therapist_notes: [],
    })),
    event_log: [],
  }
}

function findStep(record, stepId) {
  const step = record.steps.find(
    (item) => item.step_id === stepId,
  )
  if (!step) throw new Error(`Unknown step_id: ${stepId}`)
  return step
}

function previousStepsComplete(record, step) {
  return record.steps
    .filter(
      (item) => item.step_number < step.step_number,
    )
    .every(
      (item) =>
        item.status === STEP_STATUS.COMPLETED ||
        item.status === STEP_STATUS.SKIPPED_WITH_REASON,
    )
}

export function startSessionStepV2(
  executionRecord,
  {
    stepId,
    timestampIso = null,
    patientScript = null,
  },
) {
  const record = structuredClone(executionRecord)
  const step = findStep(record, stepId)

  if (!previousStepsComplete(record, step)) {
    throw new Error(
      'Previous steps must be completed before this step can start.',
    )
  }
  if (record.stopped_for_safety) {
    throw new Error(
      'Session is stopped for safety and cannot start another step.',
    )
  }
  if (step.status !== STEP_STATUS.NOT_STARTED) {
    throw new Error(
      `Step ${stepId} cannot start from status ${step.status}.`,
    )
  }

  const timestamp = nowIso(timestampIso)
  step.status = STEP_STATUS.IN_PROGRESS
  step.started_at_iso = timestamp
  record.session_status = 'in_progress'
  record.started_at_iso ??= timestamp
  record.event_log.push({
    event: SESSION_STEP_EVENTS_V2.STEP_STARTED,
    timestamp_iso: timestamp,
    step_id: stepId,
    step_number: step.step_number,
    patient_script: patientScript,
    audio_implementation: 'external_tech_plugin',
  })

  return record
}

export function completeSessionStepV2(
  executionRecord,
  {
    stepId,
    timestampIso = null,
    actualDurationSeconds = null,
    actualZones = [],
    actualSettings = {},
    actualPassesOrShots = {},
    actualProducts = [],
    endpointObserved = null,
    endpointStatus = null,
    deliveredDoseFraction0To1 = null,
    deliveredIntensityFraction0To1 = null,
    zoneCompletionFraction0To1 = {},
    tolerance = null,
    therapistNotes = [],
  },
) {
  const record = structuredClone(executionRecord)
  const step = findStep(record, stepId)

  if (step.status !== STEP_STATUS.IN_PROGRESS) {
    throw new Error(
      `Step ${stepId} must be in progress before completion.`,
    )
  }

  const timestamp = nowIso(timestampIso)
  step.status = STEP_STATUS.COMPLETED
  step.completed_at_iso = timestamp
  step.actual_duration_seconds =
    actualDurationSeconds
  step.actual_zones = actualZones
  step.actual_settings = actualSettings
  step.actual_passes_or_shots =
    actualPassesOrShots
  step.actual_products = actualProducts
  step.endpoint_observed = endpointObserved
  step.endpoint_status = endpointStatus
  step.delivered_dose_fraction_0_to_1 =
    deliveredDoseFraction0To1
  step.delivered_intensity_fraction_0_to_1 =
    deliveredIntensityFraction0To1
  step.zone_completion_fraction_0_to_1 =
    zoneCompletionFraction0To1
  step.tolerance = tolerance
  step.therapist_notes = therapistNotes

  record.event_log.push({
    event: SESSION_STEP_EVENTS_V2.STEP_COMPLETED,
    timestamp_iso: timestamp,
    step_id: stepId,
    step_number: step.step_number,
  })

  if (
    record.steps.every(
      (item) =>
        item.status === STEP_STATUS.COMPLETED ||
        item.status === STEP_STATUS.SKIPPED_WITH_REASON,
    )
  ) {
    record.session_status = 'completed'
    record.completed_at_iso = timestamp
  }

  return record
}

export function stopSessionStepForSafetyV2(
  executionRecord,
  {
    stepId,
    reason,
    signalsObserved = [],
    timestampIso = null,
  },
) {
  if (!reason) {
    throw new Error(
      'A safety-stop reason is required.',
    )
  }

  const record = structuredClone(executionRecord)
  const step = findStep(record, stepId)
  const timestamp = nowIso(timestampIso)

  step.status = STEP_STATUS.STOPPED_FOR_SAFETY
  step.completed_at_iso = timestamp
  step.stop_reason = reason
  step.safety_signals_observed =
    signalsObserved

  record.session_status = 'stopped_for_safety'
  record.stopped_for_safety = true
  record.completed_at_iso = timestamp
  record.event_log.push({
    event:
      SESSION_STEP_EVENTS_V2.STEP_STOPPED_FOR_SAFETY,
    timestamp_iso: timestamp,
    step_id: stepId,
    reason,
    signals_observed: signalsObserved,
  })

  return record
}

export function recordSessionDeviationV2(
  executionRecord,
  deviation,
) {
  if (!deviation?.reason) {
    throw new Error(
      'A deviation reason is required.',
    )
  }
  const record = structuredClone(executionRecord)
  record.deviations.push({
    ...deviation,
    recorded_at_iso: nowIso(
      deviation.recorded_at_iso,
    ),
  })
  return record
}
