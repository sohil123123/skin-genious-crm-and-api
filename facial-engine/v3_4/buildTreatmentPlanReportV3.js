import {
  recommendCourseLengthV3,
} from './courseLengthRecommendationV3.js'

export const TREATMENT_PLAN_REPORT_VERSION =
  'aia_treatment_plan_report_v3.3.0'

function actionSummary(action) {
  return {
    modality_id:
      action.modality_id,
    modality_name:
      action.modality_name,
    role:
      action.plan_role,
    selected_zones:
      action.selected_zones ?? [],
    target_feature_ids:
      action.target_features?.map(
        (feature) =>
          feature.feature_id,
      ) ?? [],
    duration_minutes:
      action.estimated_duration_minutes,
  }
}

function detailedSessionByNumber(
  multiSessionPlan,
) {
  return new Map(
    (
      multiSessionPlan
        ?.current_detailed_block
        ?.detailed_sessions ?? []
    ).map((session) => [
      session.session_number,
      session,
    ]),
  )
}

function phaseForSession(
  sessionNumber,
  totalSessions,
) {
  if (sessionNumber <= 2) {
    return 'initial_correction'
  }
  if (
    sessionNumber >= totalSessions - 1
  ) {
    return 'consolidation_and_maintenance_transition'
  }
  return 'progressive_correction'
}

export function buildTreatmentPlanReportV3({
  client = {},
  skinState,
  multiSessionPlan,
  doctorOverrideSessions = null,
  clinic = {},
} = {}) {
  if (!multiSessionPlan) {
    throw new Error(
      'A generated Multiple treatment plan is required.',
    )
  }

  const courseLength =
    recommendCourseLengthV3({
      skinState,
      optimizerOrCourse:
        multiSessionPlan,
      doctorOverrideSessions,
    })
  const totalSessions =
    courseLength.recommended_sessions
  const detailed =
    detailedSessionByNumber(
      multiSessionPlan,
    )

  const sessionSummaries = Array.from(
    { length: totalSessions },
    (_, index) => {
      const sessionNumber = index + 1
      const detail =
        detailed.get(sessionNumber)
      if (detail) {
        const actions =
          detail.optimiser_plan?.plan
            ?.modality_actions ?? []
        return {
          session_number:
            sessionNumber,
          status:
            'detailed_and_released',
          phase:
            phaseForSession(
              sessionNumber,
              totalSessions,
            ),
          primary_purpose:
            actions.find(
              (action) =>
                action.plan_role ===
                'hero',
            )?.modality_name ??
            'Personalised correction',
          expected_focus_features:
            [
              ...new Set(
                actions.flatMap(
                  (action) =>
                    action.target_features?.map(
                      (feature) =>
                        feature.feature_id,
                    ) ?? [],
                ),
              ),
            ],
          actions:
            actions.map(actionSummary),
          exact_protocol_release:
            'released_for_current_block',
        }
      }

      return {
        session_number:
          sessionNumber,
        status:
          'provisional_course_summary',
        phase:
          phaseForSession(
            sessionNumber,
            totalSessions,
          ),
        primary_purpose:
          sessionNumber === totalSessions
            ? 'Consolidate results and transition to maintenance'
            : 'Address the remaining measured treatment gap after the preceding reassessment',
        expected_focus_features:
          [],
        actions: [],
        exact_protocol_release:
          'not_released_until_prior_reassessment',
      }
    },
  )

  const reassessmentAfter = []
  for (
    let session = 2;
    session < totalSessions;
    session += 2
  ) {
    reassessmentAfter.push(session)
  }

  return {
    report_version:
      TREATMENT_PLAN_REPORT_VERSION,
    formal_report_type:
      'personalised_treatment_plan_report',
    generated_after:
      'multiple_plan_selection_and_generation',
    generated_for_single_or_express:
      false,
    client: {
      client_id:
        client.client_id ?? null,
      display_name:
        client.display_name ?? null,
    },
    clinic: {
      name:
        clinic.name ??
        'AI Aesthetics',
      medical_lead:
        clinic.medical_lead ??
        'Dr. Aakriti Mehra',
      location:
        clinic.location ?? null,
    },
    plan_id:
      multiSessionPlan.plan_id,
    recommended_course: {
      total_sessions:
        totalSessions,
      course_length_reasoning:
        courseLength,
      reassessment_after_sessions:
        reassessmentAfter,
      release_policy:
        'two_sessions_detailed_at_a_time_then_reassess',
    },
    clinic_pricing_handoff: {
      handled_by_clinic: true,
      ai_pricing_used: false,
      recommended_session_count: totalSessions,
      package_price: null,
      note:
        'The clinic applies its own package price for the recommended session count.',
    },
    session_summaries:
      sessionSummaries,
    commercial_contract: {
      pricing_owned_by_clinic: true,
      ai_does_not_price_packages: true,
      future_exact_modality_mix_may_change_after_reassessment:
        true,
      package_session_count_remains_fixed_unless_formally_revised:
        true,
    },
    report_rules: {
      all_expected_sessions_are_summarised:
        true,
      only_current_two_session_block_is_exactly_detailed:
        true,
      future_settings_are_not_locked_before_reassessment:
        true,
      pricing_is_outside_ai_engine:
        true,
    },
  }
}
