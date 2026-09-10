export const PATIENT_SCRIPT_PROMPT_VERSION =
  'aia_patient_script_prompt_v2.0.0'

export const SYSTEM_PATIENT_SCRIPT_PROMPT_V2 = `
You write only the patient-facing spoken script for already-finalized treatment steps at AI Aesthetics.

The clinical strategy, step order, zones, duration, equipment, settings, therapist instructions, endpoints and safety rules have already been finalized by deterministic code and approved protocol data.

Your only task is to write the "script" field for each supplied step.

Rules:
1. Return strict JSON only.
2. Return exactly one script for every supplied step_id.
3. Do not add, remove, merge, split, reorder or rename any step.
4. Do not change the duration.
5. Do not provide therapist instructions, settings, passes, contact times, contraindications or clinical decisions.
6. Use simple everyday English suitable for natural speech.
7. Explain:
   - what is being done,
   - what client concern it is helping,
   - the visible benefit being aimed for,
   - and the approximate duration in a natural way.
8. Keep each script to 2–4 short sentences.
9. Sound warm, reassuring, premium and natural—not like a report.
10. Avoid technical dermatology, anatomy, ingredient and device-mechanism jargon unless unavoidable.
11. Do not mention hidden scores, internal severity grades, utility calculations, AI confidence or protocol IDs.
12. Do not promise guaranteed results or imply permanent change from one session.
13. For delayed-response treatments, clearly say that improvement develops gradually rather than claiming an immediate structural change.
14. Do not write audio, voice, TTS, plugin or button instructions. The technology team handles playback separately.
15. The script is intended to be available when the therapist presses the Start Step button.

Output schema:
{
  "session_id": "<exact supplied session_id>",
  "scripts": [
    {
      "step_id": "<exact supplied step_id>",
      "script": "<2–4 short spoken sentences>"
    }
  ]
}
`.trim()

export function buildPatientScriptUserPromptV2(compiledSession) {
  const payload = {
    session_id: compiledSession.session_id,
    treatment_mode: compiledSession.treatment_mode,
    session_number: compiledSession.session_number,
    steps: compiledSession.steps.map((step) => ({
      step_id: step.step_id,
      step_number: step.step_number,
      title: step.title,
      duration_minutes: step.duration_minutes,
      target_zones: step.target_zones,
      patient_script_context: step.patient_script_context,
      target_features: step.target_features,
      expected_timing: step.expected_timing,
      limitations_and_non_claims:
        step.limitations_and_non_claims,
    })),
  }

  return JSON.stringify(payload, null, 2)
}

export function attachPatientScriptsV2(
  compiledSession,
  scriptResponse,
) {
  if (
    scriptResponse?.session_id !==
    compiledSession.session_id
  ) {
    throw new Error('Patient script response session_id mismatch.')
  }

  const expectedStepIds = compiledSession.steps.map(
    (step) => step.step_id,
  )
  const scripts = scriptResponse?.scripts ?? []
  const receivedStepIds = scripts.map((item) => item.step_id)

  if (
    expectedStepIds.length !== receivedStepIds.length ||
    expectedStepIds.some(
      (stepId, index) => stepId !== receivedStepIds[index],
    )
  ) {
    throw new Error(
      'Patient scripts must match the compiled step IDs and order exactly.',
    )
  }

  const scriptByStep = Object.fromEntries(
    scripts.map((item) => [item.step_id, item.script]),
  )

  return {
    ...compiledSession,
    patient_script_prompt_version:
      PATIENT_SCRIPT_PROMPT_VERSION,
    script_generation_status: 'complete',
    steps: compiledSession.steps.map((step) => ({
      ...step,
      patient_script: {
        script: scriptByStep[step.step_id],
        language: 'en-IN',
        status: 'generated',
        generated_by_prompt_version:
          PATIENT_SCRIPT_PROMPT_VERSION,
      },
    })),
  }
}
