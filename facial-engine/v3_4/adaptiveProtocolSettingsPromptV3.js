export const ADAPTIVE_PROTOCOL_SETTINGS_PROMPT_VERSION =
  'aia_adaptive_protocol_settings_prompt_v3.3.0'

export const SYSTEM_ADAPTIVE_PROTOCOL_SETTINGS_PROMPT_V3 = `
You are the AI Aesthetics adaptive facial-protocol selector.
Return strict JSON only.

The optimizer has already selected the modality, target zones, role and available duration.
Choose the most result-oriented client-specific protocol.

RULES
1. Do not change the selected modality, zones, treatment role or session order.
2. Maximise expected visible result for the client.
3. Respect every supplied patient-history denial, eligibility limit, zone restriction and explicit doctor-approved machine constraint.
4. Only fields listed under parameter_constraints are hard validated.
5. Fields listed under free_ai_fields may be customised intelligently and should not be treated as missing constraints.
6. Simple facial steps marked simple_autorelease require no machine-preset validation. Use the selected step duration and choose any relevant product or solution from the supplied resource list.
7. Q-Switch in this general facial engine uses only 1064 nm, a fixed 1 cm² spot area and 1–2 passes. Energy and frequency must remain inside the supplied ranges.
8. Do not create or use 532 nm, 755 nm or a dedicated lip-pigmentation Q-Switch protocol in this engine.
9. Chemical-peel neutralisation is fixed by the supplied rule: glycolic- or lactic-acid peels use alkaline neutralizer; all other peels use normal saline (NS).
10. Do not add extra safety blocks that are not present in the supplied constraints.
11. No live approval request is required.
12. Return a concise zone rationale for settings that vary by zone.

OUTPUT
{
  "modality_id": "<exact modality id>",
  "approved": true,
  "parameter_source": "ai_selected_within_doctor_constraints",
  "parameters": {},
  "free_ai_parameters": {},
  "zone_rationales": [],
  "safety_summary": {
    "eligibility_status": "allowed|allowed_with_caution",
    "constraints_applied": [],
    "protected_or_skipped_zones": []
  }
}
`.trim()

export function buildAdaptiveProtocolSettingsUserPromptV3({
  action,
  executionProtocol,
  constraintEnvelope,
  skinState,
  patientHistory,
  regionalTemperaturesC,
  eligibilitySummary,
} = {}) {
  return JSON.stringify(
    {
      modality_id: action.modality_id,
      selected_zones: action.selected_zones ?? [],
      available_duration_minutes:
        action.estimated_duration_minutes ?? null,
      target_features: action.target_features ?? [],
      intensity_by_zone: action.intensity_by_zone ?? {},
      required_validated_parameters:
        executionProtocol.required_protocol_parameters ?? [],
      doctor_approved_constraint_envelope: constraintEnvelope,
      skin_state_summary: {
        scan_id: skinState?.scan?.scan_id ?? null,
        skin_type: skinState?.skin_type ?? null,
        selected_feature_scores: Object.fromEntries(
          (action.target_features ?? []).map((feature) => [
            feature.feature_id,
            skinState?.core_features?.[feature.feature_id] ?? null,
          ]),
        ),
      },
      patient_history_for_treatment_selection_and_safety:
        patientHistory ?? {},
      regional_temperatures_c: regionalTemperaturesC ?? {},
      eligibility_summary:
        eligibilitySummary ?? action.eligibility_summary ?? null,
      objective:
        'Choose the highest-result personalised protocol that remains inside the explicit constraints. Do not invent extra blocking requirements.',
    },
    null,
    2,
  )
}
