export const CLIENT_OUTCOME_NARRATIVE_PROMPT_VERSION =
  'aia_client_outcome_narrative_prompt_v2.0.0'

export const SYSTEM_CLIENT_OUTCOME_NARRATIVE_PROMPT_V2 = `
You write only patient-facing narrative text for an AI Aesthetics Client Outcome Report.

All scores, measurements, prediction ranges, treatment details, zones, time points, reliability decisions and outcome classifications have already been finalized by deterministic code.

Your task is only to explain those supplied facts in warm, natural, simple English.

STRICT RULES
1. Return strict JSON only.
2. Use only the supplied facts.
3. Do not add, remove, change, round or reinterpret any score, range, status, treatment, zone or time point.
4. Do not turn a prediction into an achieved result.
5. Do not hide a reliably measured worsening or mixed result.
6. When a change is marked not reliably measurable, say that clearly and do not invent a number.
7. Immediate visible improvements are real measured or predicted score changes. Do not dismiss them merely because some of the effect may reduce over 3–4 weeks.
8. Explain persistence through the supplied immediate, 48-hour, 1-week and 3–4-week timeline.
9. Explain temporary redness, dryness, swelling or pigment darkening when supplied.
10. Do not promise guaranteed, permanent or identical results.
11. Do not provide a new treatment recommendation. The report supplies only a factual next-step handoff.
12. Avoid internal words such as burden, utility, evidence tier, dose multiplier, pairwise engine or minimum detectable change unless the report specifically asks for a reliability explanation.
13. Keep the tone reassuring, premium and honest.
14. Each feature explanation should be 1–2 short sentences.
15. Do not generate audio, voice, TTS or UI instructions.

OUTPUT SCHEMA
{
  "report_id": "<exact supplied report_id>",
  "headline": "<short patient-facing headline>",
  "summary": "<2–4 sentences>",
  "feature_explanations": [
    {
      "feature_id": "<exact supplied feature_id>",
      "text": "<1–2 short sentences>"
    }
  ],
  "timeline_explanation": "<2–4 sentences explaining immediate, 48-hour, 1-week and 3–4-week results>",
  "next_step_explanation": "<1–3 sentences based only on the supplied handoff>"
}
`.trim()

function compactFeature(feature) {
  return {
    feature_id: feature.feature_id,
    label: feature.label,
    measured_change_status:
      feature.measured_change_status,
    outcome_vs_prediction_status:
      feature.outcome_vs_prediction_status,
    baseline: feature.baseline,
    actual: feature.actual,
    predicted_horizon:
      feature.predicted_horizon,
    predicted_horizons:
      feature.predicted_horizons,
    pairwise_summary:
      feature.pairwise_summary,
    transient_reactivity_note:
      feature.transient_reactivity_note,
  }
}

export function buildClientOutcomeNarrativeUserPromptV2(
  report,
) {
  const payload = {
    report_id: report.report_id,
    report_type: report.report_type,
    score_semantics:
      report.score_semantics,
    measurement_context:
      report.measurement_context,
    outcome_summary:
      report.outcome_summary,
    treatment: report.treatment,
    featured_results:
      report.feature_results
        .slice(0, 8)
        .map(compactFeature),
    timeline: report.timeline,
    next_optimizer_handoff:
      report.next_optimizer_handoff,
    important_notes:
      report.important_notes,
    report_rules:
      report.report_rules,
  }

  return JSON.stringify(
    payload,
    null,
    2,
  )
}

export function attachClientOutcomeNarrativeV2(
  report,
  narrativeResponse,
) {
  if (
    narrativeResponse?.report_id !==
    report.report_id
  ) {
    throw new Error(
      'Narrative report_id does not match the client outcome report.',
    )
  }

  const requestedFeatureIds = new Set(
    report.feature_results
      .slice(0, 8)
      .map((feature) => feature.feature_id),
  )
  for (const explanation of
    narrativeResponse.feature_explanations ??
    []) {
    if (
      !requestedFeatureIds.has(
        explanation.feature_id,
      )
    ) {
      throw new Error(
        `Narrative contains an unrequested feature: ${explanation.feature_id}`,
      )
    }
  }

  return {
    ...report,
    narrative_prompt_version:
      CLIENT_OUTCOME_NARRATIVE_PROMPT_VERSION,
    patient_narrative: {
      status: 'generated',
      headline:
        narrativeResponse.headline,
      summary:
        narrativeResponse.summary,
      feature_explanations:
        narrativeResponse
          .feature_explanations ?? [],
      timeline_explanation:
        narrativeResponse
          .timeline_explanation,
      next_step_explanation:
        narrativeResponse
          .next_step_explanation,
    },
  }
}
