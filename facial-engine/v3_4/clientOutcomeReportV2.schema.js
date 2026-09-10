export const CLIENT_OUTCOME_REPORT_SCHEMA_VERSION =
  'aia_client_outcome_report_v2.0.0'

export const REASSESSMENT_DISPLAY_SCHEMA_VERSION =
  'aia_reassessment_display_v2.0.0'

export const CLIENT_OUTCOME_REPORT_TYPES_V2 = Object.freeze([
  'prediction_only_session_report',
  'measured_immediate_outcome_report',
  'measured_reassessment_report',
  'course_progress_report',
])

export const OUTCOME_COMPARISON_STATUSES_V2 = Object.freeze([
  'above_predicted_range',
  'within_predicted_range_above_expected',
  'within_predicted_range',
  'within_predicted_range_below_expected',
  'below_predicted_range',
  'temporarily_obscured_by_expected_reactivity',
  'not_reliably_measurable',
  'prediction_not_available',
])

export const MEASURED_CHANGE_STATUSES_V2 = Object.freeze([
  'improved',
  'stable',
  'worsened',
  'mixed',
  'not_reliably_measurable',
])

export const REPORT_SCORE_SEMANTICS_V2 = Object.freeze({
  internal_burden_score:
    '1–100; lower is better',
  client_health_score:
    '1–100; higher is better',
  improvement_points:
    'positive means improvement; negative means worsening',
  predicted_range:
    'best_case, expected and conservative are not guaranteed outcomes',
})

export function burdenToClientHealthScoreV2(
  burdenScore,
) {
  const numeric = Number(burdenScore)
  if (!Number.isFinite(numeric)) return null
  return Math.max(
    1,
    Math.min(100, 101 - Math.round(numeric)),
  )
}

export function validateClientOutcomeReportV2(
  report,
) {
  const errors = []

  if (
    report?.schema_version !==
    CLIENT_OUTCOME_REPORT_SCHEMA_VERSION
  ) {
    errors.push(
      'Invalid client-outcome report schema version.',
    )
  }
  if (
    !CLIENT_OUTCOME_REPORT_TYPES_V2.includes(
      report?.report_type,
    )
  ) {
    errors.push('Invalid client-outcome report type.')
  }
  if (!report?.score_semantics) {
    errors.push('Score semantics are missing.')
  }
  if (!Array.isArray(report?.sections)) {
    errors.push('Report sections are missing.')
  }
  if (!Array.isArray(report?.feature_results)) {
    errors.push('Feature results are missing.')
  }

  for (const result of report?.feature_results ?? []) {
    if (!result.feature_id) {
      errors.push(
        'A feature result is missing feature_id.',
      )
    }
    if (
      result.measured_change_status &&
      !MEASURED_CHANGE_STATUSES_V2.includes(
        result.measured_change_status,
      )
    ) {
      errors.push(
        `${result.feature_id} has invalid measured_change_status.`,
      )
    }
    if (
      result.outcome_vs_prediction_status &&
      !OUTCOME_COMPARISON_STATUSES_V2.includes(
        result.outcome_vs_prediction_status,
      )
    ) {
      errors.push(
        `${result.feature_id} has invalid prediction-comparison status.`,
      )
    }
  }

  return {
    valid: errors.length === 0,
    errors,
  }
}

export function validateReassessmentDisplayV2(
  display,
) {
  const errors = []

  if (
    display?.schema_version !==
    REASSESSMENT_DISPLAY_SCHEMA_VERSION
  ) {
    errors.push(
      'Invalid reassessment-display schema version.',
    )
  }
  if (!display?.measurement_context) {
    errors.push(
      'Reassessment measurement context is missing.',
    )
  }
  if (!Array.isArray(display?.score_cards)) {
    errors.push('Score cards are missing.')
  }
  if (!display?.zone_map) {
    errors.push('Zone map is missing.')
  }
  if (!display?.timeline) {
    errors.push('Outcome timeline is missing.')
  }

  return {
    valid: errors.length === 0,
    errors,
  }
}
