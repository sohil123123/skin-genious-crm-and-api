import {
  CLIENT_REPORT_PARAMETER_IDS,
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  REPORT_PARAMETER_DISPLAY_CONFIG_V2,
} from './outcomeDisplayConfigV2.js'
import {
  burdenToClientHealthScoreV2,
} from './clientOutcomeReportV2.schema.js'

export const SKIN_ANALYSIS_REPORT_VERSION =
  'aia_skin_analysis_report_v3.6.0-calibrated'

function parameterCard(
  skinState,
  parameterId,
) {
  if (parameterId === 'skin_type') {
    return {
      parameter_id: 'skin_type',
      label: 'Skin Type',
      value_type: 'label',
      display_label:
        skinState.skin_type?.label ??
        skinState
          .derived_report_parameters
          ?.skin_type?.display_label ??
        'not_assessed',
      modifiers:
        skinState.skin_type?.modifiers ??
        [],
    }
  }

  const parameter =
    skinState
      .derived_report_parameters?.[
      parameterId
    ]
  if (!parameter) {
    throw new Error(
      `Missing derived report parameter: ${parameterId}`,
    )
  }
  const burden =
    parameter
      .internal_burden_score_1_to_100

  return {
    parameter_id: parameterId,
    label:
      REPORT_PARAMETER_DISPLAY_CONFIG_V2[
        parameterId
      ]?.label ?? parameterId,
    category:
      REPORT_PARAMETER_DISPLAY_CONFIG_V2[
        parameterId
      ]?.category ?? 'other',
    value_type: 'score',
    concern_burden_score_1_to_100:
      burden,
    client_health_score_1_to_100:
      burdenToClientHealthScoreV2(
        burden,
      ),
    display_polarity:
      'client_health_higher_is_better',
    original_parameter:
      parameter,
  }
}

export function buildSkinAnalysisReportV3({
  client = {},
  skinState,
  imageAssets = null,
  statedConcerns = [],
  clinic = {},
} = {}) {
  if (
    !skinState?.derived_report_parameters
  ) {
    throw new Error(
      'Scored Skin State V2 is required.',
    )
  }

  const primaryParameterCards =
    CLIENT_REPORT_PARAMETER_IDS.map(
      (parameterId) =>
        parameterCard(
          skinState,
          parameterId,
        ),
    )

  const numericCards =
    primaryParameterCards.filter(
      (card) =>
        card.value_type === 'score',
    )
  const overallHealth =
    numericCards.length
      ? Math.round(
          numericCards.reduce(
            (sum, card) =>
              sum +
              card
                .client_health_score_1_to_100,
            0,
          ) / numericCards.length,
        )
      : null

  const strongest = [...numericCards]
    .sort(
      (a, b) =>
        b.client_health_score_1_to_100 -
          a.client_health_score_1_to_100 ||
        a.parameter_id.localeCompare(
          b.parameter_id,
        ),
    )
    .slice(0, 4)
  const opportunities = [...numericCards]
    .sort(
      (a, b) =>
        a.client_health_score_1_to_100 -
          b.client_health_score_1_to_100 ||
        a.parameter_id.localeCompare(
          b.parameter_id,
        ),
    )
    .slice(0, 5)

  return {
    report_version:
      SKIN_ANALYSIS_REPORT_VERSION,
    formal_report_type:
      'ai_skin_analysis_report',
    generated_after:
      'skin_state_scoring',
    treatment_selection_required:
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
    scan: skinState.scan,
    overall_skin_health_score_1_to_100:
      overallHealth,
    stated_concerns:
      statedConcerns,
    primary_client_assessment: {
      parameter_count: 15,
      parameter_cards:
        primaryParameterCards,
      strongest_parameters: strongest,
      highest_opportunities:
        opportunities,
    },
    five_mode_images:
      imageAssets,
    clinical_support_layer: {
      visible_to_client_by_default:
        false,
      core_feature_count:
        CORE_FEATURE_IDS.length,
      purpose:
        'Internal zonal scoring, treatment selection, explanation and reassessment support.',
      core_features:
        skinState.core_features,
      calibration_version: skinState.scoring_execution?.calibration_version,
      parameter_calibration: Object.fromEntries(Object.entries(skinState.derived_report_parameters).filter(([, p]) => p.calibration).map(([id, p]) => [id, p.calibration])),
    },
    report_rules: {
      original_15_parameters_are_primary:
        true,
      sixteen_core_features_are_not_additional_client_scores:
        true,
      report_generated_before_treatment_choice:
        true,
      optimizer_dependency: false,
      prediction_dependency: false,
    },
  }
}
