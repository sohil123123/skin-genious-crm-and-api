export const THERAPIST_SESSION_SCHEMA_VERSION =
  'aia_therapist_session_v2.0.0'

export const SESSION_COMPILE_STATUS = Object.freeze({
  RELEASE_READY: 'release_ready',
  DRAFT_MISSING_PROTOCOL_INPUTS: 'draft_missing_protocol_inputs',
  BLOCKED_INVALID_OPTIMIZER_INPUT: 'blocked_invalid_optimizer_input',
  BLOCKED_UNRELEASED_MULTI_SESSION: 'blocked_unreleased_multi_session',
})

export const STEP_STATUS = Object.freeze({
  NOT_STARTED: 'not_started',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
  SKIPPED_WITH_REASON: 'skipped_with_reason',
  STOPPED_FOR_SAFETY: 'stopped_for_safety',
})

export const STEP_TYPES = Object.freeze({
  FOUNDATION: 'foundation',
  CORRECTIVE: 'corrective',
  SUPPORTIVE: 'supportive',
  MANDATORY_FINAL: 'mandatory_final',
})

export const SESSION_STEP_REQUIRED_FIELDS = Object.freeze([
  'step_id',
  'step_number',
  'step_type',
  'title',
  'duration_minutes',
  'target_zones',
  'ingredients_equipments',
  'therapist_instruction',
  'patient_script',
  'script_status',
  'ui_contract',
])

export function createEmptyPatientScriptV2() {
  return {
    script: null,
    language: 'en-IN',
    status: 'pending_generation',
    generated_by_prompt_version: null,
  }
}

export function validateCompiledSessionShapeV2(session) {
  const errors = []

  if (session?.schema_version !== THERAPIST_SESSION_SCHEMA_VERSION) {
    errors.push('Invalid or missing therapist session schema version.')
  }
  if (!Array.isArray(session?.steps) || session.steps.length === 0) {
    errors.push('Compiled session must contain steps.')
  }

  for (const [index, step] of (session?.steps ?? []).entries()) {
    for (const field of SESSION_STEP_REQUIRED_FIELDS) {
      if (!(field in step)) {
        errors.push(`Step ${index + 1} missing ${field}.`)
      }
    }
    if (step.step_number !== index + 1) {
      errors.push(
        `Step numbering must be continuous; expected ${index + 1}, received ${step.step_number}.`,
      )
    }
    if (!Number.isFinite(Number(step.duration_minutes))) {
      errors.push(`Step ${index + 1} has invalid duration.`)
    }
  }

  const calculated = (session?.steps ?? []).reduce(
    (sum, step) => sum + Number(step.duration_minutes || 0),
    0,
  )
  if (
    Number(session?.timing_validation?.calculated_from_steps) !==
    calculated
  ) {
    errors.push('Timing validation does not match the step sum.')
  }

  return {
    valid: errors.length === 0,
    errors,
  }
}
