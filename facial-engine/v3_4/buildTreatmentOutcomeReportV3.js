import {
  buildClientOutcomeReportV2,
} from './buildClientOutcomeReportV2.js'

export const TREATMENT_OUTCOME_REPORT_VERSION =
  'aia_treatment_outcome_report_v3.1.0'

export function buildTreatmentOutcomeReportV3(
  input = {},
) {
  if (
    !input.executionRecord ||
    !input.reassessmentResult
  ) {
    throw new Error(
      'Treatment Outcome Report requires an execution record and post-treatment reassessment.',
    )
  }

  const report =
    buildClientOutcomeReportV2(input)

  return {
    ...report,
    report_version:
      TREATMENT_OUTCOME_REPORT_VERSION,
    formal_report_type:
      'treatment_outcome_report',
    generated_after:
      'treatment_execution_and_post_treatment_scoring',
    primary_client_assessment: {
      parameter_count: 15,
      parameter_cards:
        report.report_parameter_cards,
    },
    clinical_support_layer: {
      visible_to_client_by_default:
        false,
      core_feature_count:
        report.feature_results.length,
      core_feature_results:
        report.feature_results,
      zone_map:
        report.reassessment_display
          ?.zone_map ?? null,
    },
    feature_results:
      report.feature_results,
    report_rules: {
      ...report.report_rules,
      original_15_parameters_are_primary:
        true,
      sixteen_core_features_are_internal_support:
        true,
      formal_outcome_report_requires_treatment_completion:
        true,
    },
  }
}
