import {
  buildSkinAnalysisReportV3,
} from './buildSkinAnalysisReportV3.js'
import {
  buildTreatmentPlanReportV3,
} from './buildTreatmentPlanReportV3.js'
import {
  buildTreatmentOutcomeReportV3,
} from './buildTreatmentOutcomeReportV3.js'

export const REPORT_WORKFLOW_ROUTER_VERSION =
  'aia_report_workflow_router_v3.1.0'

export const REPORT_WORKFLOW_STAGES_V3 = Object.freeze([
  'scoring_complete',
  'treatment_mode_selected',
  'multiple_plan_generated',
  'treatment_complete_and_post_scan_scored',
])

export function routeFormalReportV3({
  stage,
  treatmentMode = null,
  inputs = {},
} = {}) {
  if (
    !REPORT_WORKFLOW_STAGES_V3.includes(
      stage,
    )
  ) {
    throw new Error(
      `Unknown report workflow stage: ${stage}`,
    )
  }

  if (stage === 'scoring_complete') {
    return {
      router_version:
        REPORT_WORKFLOW_ROUTER_VERSION,
      formal_report:
        buildSkinAnalysisReportV3(
          inputs,
        ),
      optional_screen:
        null,
    }
  }

  if (
    stage ===
    'treatment_mode_selected'
  ) {
    return {
      router_version:
        REPORT_WORKFLOW_ROUTER_VERSION,
      formal_report: null,
      optional_screen: {
        type:
          'expected_outcome_preview',
        formal_report: false,
        treatment_mode:
          treatmentMode,
        prediction:
          inputs.preSessionPrediction ??
          null,
      },
    }
  }

  if (
    stage ===
    'multiple_plan_generated'
  ) {
    if (
      treatmentMode !== 'multiple'
    ) {
      throw new Error(
        'Treatment Plan Report is generated only for Multiple plans.',
      )
    }
    return {
      router_version:
        REPORT_WORKFLOW_ROUTER_VERSION,
      formal_report:
        buildTreatmentPlanReportV3(
          inputs,
        ),
      optional_screen: null,
    }
  }

  return {
    router_version:
      REPORT_WORKFLOW_ROUTER_VERSION,
    formal_report:
      buildTreatmentOutcomeReportV3(
        inputs,
      ),
    optional_screen: null,
  }
}

export function describeReportJourneyV3(
  treatmentMode,
) {
  if (treatmentMode === 'multiple') {
    return [
      'AI Skin Analysis Report after scoring',
      'Personalised Treatment Plan Report after plan generation and package selection',
      'Treatment Outcome Report after each treated and measured session',
    ]
  }
  return [
    'AI Skin Analysis Report after scoring',
    'Treatment Outcome Report after treatment and post-treatment scoring',
  ]
}
