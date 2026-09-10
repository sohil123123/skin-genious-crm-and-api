export const SESSION_STEP_UI_CONTRACT_VERSION =
  'aia_session_step_ui_contract_v2.0.0'

export const SESSION_STEP_EVENTS_V2 = Object.freeze({
  STEP_STARTED: 'therapist_session_step_started',
  STEP_COMPLETED: 'therapist_session_step_completed',
  STEP_STOPPED_FOR_SAFETY: 'therapist_session_step_stopped_for_safety',
  STEP_SKIPPED: 'therapist_session_step_skipped',
})

export function createStepUiContractV2({
  stepId,
  stepNumber,
  durationMinutes,
}) {
  return {
    contract_version: SESSION_STEP_UI_CONTRACT_VERSION,
    start_step_button: {
      label: 'Start Step',
      enabled_when: [
        'all required pre-step checks are complete',
        'the previous step is complete or this is step 1',
        'the session has not been stopped for safety',
      ],
      on_press: {
        set_step_status: 'in_progress',
        start_countdown_timer: true,
        countdown_duration_minutes: durationMinutes,
        emit_event: SESSION_STEP_EVENTS_V2.STEP_STARTED,
        event_payload_fields: [
          'session_id',
          'step_id',
          'step_number',
          'patient_script.script',
        ],
      },
      patient_script_field: 'patient_script.script',
      audio_or_voice_implementation: 'external_tech_plugin',
      compiler_generates_audio: false,
      notes:
        'The technology team may read the patient script and play Dr. Aakriti’s voice when Start Step is pressed. The compiler supplies text only.',
    },
    complete_step_button: {
      label: 'Complete Step',
      enabled_when: [
        'the step is in progress',
        'required execution fields are recorded',
      ],
      on_press: {
        stop_timer: true,
        set_step_status: 'completed',
        emit_event: SESSION_STEP_EVENTS_V2.STEP_COMPLETED,
      },
    },
    next_step_navigation: {
      voice_or_script_trigger: false,
      notes:
        'Moving to the next step does not trigger the patient script. Playback belongs to the Start Step action.',
    },
    safety_stop: {
      always_available_while_in_progress: true,
      emit_event:
        SESSION_STEP_EVENTS_V2.STEP_STOPPED_FOR_SAFETY,
      requires_reason: true,
    },
    identifiers: {
      step_id: stepId,
      step_number: stepNumber,
    },
  }
}
