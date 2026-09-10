import {
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  CLIENT_OUTCOME_REPORT_SCHEMA_VERSION,
  REPORT_SCORE_SEMANTICS_V2,
  burdenToClientHealthScoreV2,
  validateClientOutcomeReportV2,
} from './clientOutcomeReportV2.schema.js'
import {
  FEATURE_DISPLAY_CONFIG_V2,
  REPORT_PARAMETER_DISPLAY_CONFIG_V2,
  REPORT_SECTION_ORDER_V2,
  REASSESSMENT_HORIZON_DISPLAY_V2,
} from './outcomeDisplayConfigV2.js'
import {
  buildReassessmentDisplayV2,
} from './buildReassessmentDisplayV2.js'
import {
  resolveOutcomeHorizonV2,
} from './resolveOutcomeHorizonV2.js'

export const CLIENT_OUTCOME_REPORT_BUILDER_VERSION =
  'aia_client_outcome_report_builder_v2.0.0'

const unique = (values) =>
  [...new Set((values ?? []).filter(Boolean))]

const round = (value, digits = 1) =>
  Number(Number(value).toFixed(digits))

function normaliseOptimizerResult(input) {
  if (!input) return null
  if (input?.plan?.modality_actions) return input
  if (input?.optimiser_plan?.plan?.modality_actions) {
    return input.optimiser_plan
  }
  return null
}

function reportType({
  reassessmentResult,
  horizonId,
  multiSessionPlan,
}) {
  if (multiSessionPlan && reassessmentResult) {
    return 'course_progress_report'
  }
  if (reassessmentResult) {
    return horizonId === 'immediate_post'
      ? 'measured_immediate_outcome_report'
      : 'measured_reassessment_report'
  }
  return 'prediction_only_session_report'
}

function plannedTreatmentSummary(
  optimizerResult,
) {
  if (!optimizerResult?.plan) {
    return {
      available: false,
      actions: [],
    }
  }

  return {
    available: true,
    treatment_mode:
      optimizerResult.plan.treatment_mode ??
      optimizerResult.input_summary
        ?.treatment_mode ??
      null,
    planned_duration_minutes:
      optimizerResult.plan
        .estimated_total_duration_minutes ??
      null,
    hero_actions:
      optimizerResult.plan.hero_actions?.map(
        (action) => ({
          modality_id: action.modality_id,
          modality_name:
            action.modality_name,
          selected_zones:
            action.selected_zones,
          target_feature_ids:
            action.target_features?.map(
              (feature) =>
                feature.feature_id,
            ) ?? [],
        }),
      ) ?? [],
    actions:
      optimizerResult.plan.modality_actions.map(
        (action) => ({
          modality_id: action.modality_id,
          modality_name:
            action.modality_name,
          plan_role: action.plan_role,
          selected_zones:
            action.selected_zones ?? [],
          estimated_duration_minutes:
            action.estimated_duration_minutes,
          target_feature_ids:
            action.target_features?.map(
              (feature) =>
                feature.feature_id,
            ) ?? [],
          mandatory:
            action.plan_role ===
            'mandatory',
        }),
      ),
    mandatory_lymphatic_present:
      optimizerResult.plan
        .mandatory_lymphatic_drainage_present ??
      optimizerResult.plan.modality_actions.some(
        (action) =>
          action.modality_id ===
          'lymphatic_drainage',
      ),
  }
}

function actualTreatmentSummary(
  executionRecord,
  deliveredPrediction,
) {
  const dose =
    deliveredPrediction
      ?.delivered_treatment_dose
  const actualDurationMinutes =
    executionRecord?.steps?.reduce(
      (sum, step) =>
        sum +
        Number(
          step.actual_duration_seconds ?? 0,
        ) /
          60,
      0,
    ) ?? null

  return {
    available: Boolean(
      executionRecord || dose,
    ),
    session_id:
      executionRecord?.session_id ??
      deliveredPrediction?.session_id ??
      null,
    session_status:
      executionRecord?.session_status ??
      null,
    actual_duration_minutes:
      actualDurationMinutes === null
        ? null
        : round(actualDurationMinutes),
    stopped_for_safety:
      executionRecord
        ?.stopped_for_safety ??
      dose?.stopped_for_safety ??
      false,
    mean_delivered_dose_fraction_0_to_1:
      dose
        ?.mean_action_dose_fraction_0_to_1 ??
      null,
    actions:
      dose?.action_doses?.map(
        (action) => ({
          modality_id:
            action.modality_id,
          plan_role: action.plan_role,
          status: action.status,
          delivered_dose_fraction_0_to_1:
            action
              .overall_dose_fraction_0_to_1,
          evidence_quality:
            action.evidence_quality,
          deductions:
            action.deductions,
        }),
      ) ??
      executionRecord?.steps?.map(
        (step) => ({
          modality_id:
            step.modality_id,
          status: step.status,
          actual_zones:
            step.actual_zones,
          actual_duration_seconds:
            step.actual_duration_seconds,
        }),
      ) ??
      [],
  }
}

function predictionFeatureResult(
  prediction,
  featureId,
) {
  const feature =
    prediction?.features?.[featureId]
  if (!feature) return null
  const config =
    FEATURE_DISPLAY_CONFIG_V2[featureId]

  return {
    feature_id: featureId,
    label:
      config?.label ?? featureId,
    short_label:
      config?.short_label ?? featureId,
    category:
      config?.category ?? 'other',
    primary_mode:
      config?.primary_mode ?? 'white',
    measured_change_status: null,
    outcome_vs_prediction_status:
      'prediction_not_available',
    baseline: {
      burden_score_1_to_100:
        feature.global_prediction
          .baseline_burden_score_1_to_100,
      client_health_score_1_to_100:
        burdenToClientHealthScoreV2(
          feature.global_prediction
            .baseline_burden_score_1_to_100,
        ),
    },
    predicted_horizons:
      feature.global_prediction.horizons,
    actual: null,
    dominant_predicted_change_zones:
      feature
        .dominant_predicted_change_zones,
  }
}

function measuredFeatureResult(card) {
  return {
    feature_id: card.feature_id,
    label: card.label,
    short_label: card.short_label,
    category: card.category,
    primary_mode: card.primary_mode,
    measured_change_status:
      card.actual.change_status,
    outcome_vs_prediction_status:
      card.outcome_vs_prediction_status,
    baseline: card.baseline,
    actual: card.actual,
    predicted_horizon:
      card.prediction,
    difference_from_expected_improvement_points:
      card
        .difference_from_expected_improvement_points,
    pairwise_summary:
      card.pairwise_summary,
    transient_reactivity_note:
      card.transient_reactivity_note,
  }
}

function parameterCards({
  baselineSkinState,
  reassessmentResult,
  prediction,
  horizonId,
}) {
  const baseline =
    baselineSkinState
      ?.derived_report_parameters ?? {}
  const measured =
    reassessmentResult
      ?.derived_parameter_outcomes ?? {}
  const predicted =
    prediction
      ?.predicted_report_parameters ?? {}

  return Object.keys(
    REPORT_PARAMETER_DISPLAY_CONFIG_V2,
  ).map((parameterId) => {
    const config =
      REPORT_PARAMETER_DISPLAY_CONFIG_V2[
        parameterId
      ]
    const baselineParameter =
      baseline[parameterId]
    const measuredParameter =
      measured[parameterId]
    const predictedHorizon =
      predicted[parameterId]
        ?.horizons?.[horizonId] ??
      null

    const baselineBurden =
      baselineParameter
        ?.internal_burden_score_1_to_100 ??
      measuredParameter
        ?.before_internal_burden_score_1_to_100 ??
      null
    const actualBurden =
      measuredParameter
        ?.after_internal_burden_score_1_to_100 ??
      null

    return {
      parameter_id: parameterId,
      label: config.label,
      category: config.category,
      baseline: {
        burden_score_1_to_100:
          baselineBurden,
        client_health_score_1_to_100:
          burdenToClientHealthScoreV2(
            baselineBurden,
          ),
      },
      actual:
        actualBurden === null
          ? null
          : {
              burden_score_1_to_100:
                actualBurden,
              client_health_score_1_to_100:
                burdenToClientHealthScoreV2(
                  actualBurden,
                ),
              improvement_points:
                measuredParameter
                  ?.improvement_points ??
                (
                  baselineBurden -
                  actualBurden
                ),
              validation_status:
                measuredParameter
                  ?.validation_status ??
                null,
            },
      prediction:
        predictedHorizon
          ? {
              burden_score_1_to_100:
                predictedHorizon
                  .predicted_internal_burden_score_1_to_100,
              client_health_score_1_to_100:
                Object.fromEntries(
                  Object.entries(
                    predictedHorizon
                      .predicted_internal_burden_score_1_to_100,
                  ).map(
                    ([range, value]) => [
                      range,
                      burdenToClientHealthScoreV2(
                        value,
                      ),
                    ],
                  ),
                ),
            }
          : null,
    }
  })
}

function predictionTimeline(
  prediction,
) {
  if (!prediction?.features) return null

  return {
    horizons:
      prediction.horizons,
    patient_summary:
      prediction.patient_timeline_summary,
    top_feature_series:
      CORE_FEATURE_IDS.map(
        (featureId) => {
          const feature =
            prediction.features[featureId]
          const config =
            FEATURE_DISPLAY_CONFIG_V2[
              featureId
            ]
          return {
            feature_id: featureId,
            label:
              config?.label ?? featureId,
            baseline_burden_score_1_to_100:
              feature.global_prediction
                .baseline_burden_score_1_to_100,
            horizons:
              feature.global_prediction
                .horizons,
          }
        },
      )
        .sort(
          (a, b) =>
            (
              b.horizons.immediate_post
                .predicted_net_change_points
                .expected_improvement +
              b.horizons.day_28
                .predicted_net_change_points
                .expected_improvement
            ) -
              (
                a.horizons.immediate_post
                  .predicted_net_change_points
                  .expected_improvement +
                a.horizons.day_28
                  .predicted_net_change_points
                  .expected_improvement
              ) ||
            a.feature_id.localeCompare(
              b.feature_id,
            ),
        )
        .slice(0, 8),
  }
}

function courseProgress(
  multiSessionPlan,
  currentSessionNumber,
  reassessmentResult,
) {
  if (!multiSessionPlan) return null
  const block =
    multiSessionPlan
      .current_detailed_block

  return {
    plan_id:
      multiSessionPlan.plan_id,
    plan_status:
      multiSessionPlan.status,
    maximum_sessions:
      multiSessionPlan.maximum_sessions,
    completed_sessions:
      multiSessionPlan.completed_sessions ??
      [],
    current_block_number:
      multiSessionPlan.current_block_number,
    current_block_session_numbers:
      block?.session_numbers ?? [],
    current_session_number:
      currentSessionNumber,
    reassessment_gate:
      block?.reassessment_gate ?? null,
    reassessment_available:
      Boolean(reassessmentResult),
    future_blocks:
      multiSessionPlan.future_blocks?.map(
        (futureBlock) => ({
          block_number:
            futureBlock.block_number,
          session_numbers:
            futureBlock.session_numbers,
          status: futureBlock.status,
          detailed_steps_generated:
            futureBlock
              .detailed_steps_generated,
        }),
      ) ?? [],
    future_steps_and_predictions_hidden:
      true,
  }
}

function nextOptimizerHandoff({
  reassessmentDisplay,
  deliveredPrediction,
  executionRecord,
  multiSessionPlan,
}) {
  if (!reassessmentDisplay) {
    return {
      available: false,
      reason:
        'A measured reassessment is required before creating an outcome-based optimizer handoff.',
    }
  }

  const cards =
    reassessmentDisplay.score_cards
  const residual = cards
    .filter(
      (card) =>
        Number(
          card.actual
            .burden_score_1_to_100,
        ) >= 45,
    )
    .map((card) => ({
      feature_id: card.feature_id,
      actual_burden_score_1_to_100:
        card.actual
          .burden_score_1_to_100,
      client_health_score_1_to_100:
        card.actual
          .client_health_score_1_to_100,
      outcome_vs_prediction_status:
        card
          .outcome_vs_prediction_status,
    }))

  const belowPrediction = cards
    .filter((card) =>
      [
        'below_predicted_range',
        'within_predicted_range_below_expected',
      ].includes(
        card
          .outcome_vs_prediction_status,
      ),
    )
    .map((card) => card.feature_id)

  const worsened = cards
    .filter(
      (card) =>
        card.actual.change_status ===
        'worsened',
    )
    .map((card) => card.feature_id)

  const unreliable = cards
    .filter(
      (card) =>
        card.actual.change_status ===
        'not_reliably_measurable',
    )
    .map((card) => card.feature_id)

  const doseGaps =
    deliveredPrediction
      ?.delivered_treatment_dose
      ?.action_doses
      ?.filter(
        (action) =>
          action
            .overall_dose_fraction_0_to_1 <
          0.8,
      )
      .map((action) => ({
        modality_id:
          action.modality_id,
        delivered_dose_fraction_0_to_1:
          action
            .overall_dose_fraction_0_to_1,
        status: action.status,
        deductions:
          action.deductions,
      })) ?? []

  return {
    available: true,
    residual_high_burden_features:
      residual,
    below_prediction_feature_ids:
      belowPrediction,
    worsened_feature_ids: worsened,
    unreliable_feature_ids:
      unreliable,
    delivered_dose_gaps: doseGaps,
    safety_stop_occurred:
      executionRecord
        ?.stopped_for_safety ??
      false,
    next_block_reassessment_gate:
      multiSessionPlan
        ?.current_detailed_block
        ?.reassessment_gate ??
      null,
    decision_owner:
      'Zonal Treatment Optimizer V2 after clinical safety and reassessment inputs',
    report_does_not_choose_next_treatment:
      true,
  }
}

function summaryFacts({
  reassessmentDisplay,
  prediction,
}) {
  if (reassessmentDisplay) {
    const cards =
      reassessmentDisplay.score_cards
    return {
      headline:
        reassessmentDisplay
          .top_outcome_banner.headline,
      measured_improvements: cards
        .filter(
          (card) =>
            card.actual.change_status ===
            'improved',
        )
        .slice(0, 5)
        .map((card) => ({
          feature_id:
            card.feature_id,
          label: card.label,
          improvement_points:
            card.actual
              .improvement_points_for_display,
        })),
      stable_or_unmeasurable: cards
        .filter((card) =>
          [
            'stable',
            'not_reliably_measurable',
          ].includes(
            card.actual.change_status,
          ),
        )
        .slice(0, 5)
        .map((card) => ({
          feature_id:
            card.feature_id,
          label: card.label,
          status:
            card.actual.change_status,
        })),
      needs_attention: cards
        .filter((card) =>
          [
            'worsened',
            'mixed',
          ].includes(
            card.actual.change_status,
          ),
        )
        .slice(0, 5)
        .map((card) => ({
          feature_id:
            card.feature_id,
          label: card.label,
          status:
            card.actual.change_status,
        })),
    }
  }

  const immediate =
    prediction
      ?.patient_timeline_summary
      ?.immediate_post
  const day28 =
    prediction
      ?.patient_timeline_summary
      ?.day_28

  return {
    headline:
      'Here is the visible improvement expected from this personalised session.',
    measured_improvements: [],
    immediate_expected:
      immediate
        ?.top_expected_visible_improvements ??
      [],
    three_to_four_week_expected:
      day28
        ?.top_expected_visible_improvements ??
      [],
    temporary_effects_to_expect:
      immediate
        ?.temporary_effects_to_explain ??
      [],
  }
}

function buildSections({
  summary,
  plannedTreatment,
  actualTreatment,
  parameterCardsValue,
  timeline,
  reassessmentDisplay,
  course,
  handoff,
  notes,
}) {
  const dataBySection = {
    outcome_summary: summary,
    treatment_delivered: {
      planned: plannedTreatment,
      actual: actualTreatment,
    },
    score_change_cards: {
      parameter_cards:
        parameterCardsValue,
      feature_cards:
        reassessmentDisplay
          ?.featured_score_cards ??
        [],
    },
    prediction_timeline: timeline,
    zone_outcomes:
      reassessmentDisplay?.zone_map ??
      null,
    before_after_images:
      reassessmentDisplay
        ?.before_after_image_cards ??
      [],
    course_progress: course,
    next_session_handoff: handoff,
    important_notes: notes,
  }

  return REPORT_SECTION_ORDER_V2.map(
    (sectionId) => ({
      section_id: sectionId,
      visible:
        dataBySection[sectionId] !==
          null &&
        (
          !Array.isArray(
            dataBySection[sectionId],
          ) ||
          dataBySection[sectionId]
            .length > 0
        ),
      data:
        dataBySection[sectionId],
    }),
  )
}

export function buildClientOutcomeReportV2({
  client = {},
  baselineSkinState,
  optimizerResult: rawOptimizerResult = null,
  compiledSession = null,
  executionRecord = null,
  preSessionPrediction = null,
  deliveredPrediction = null,
  reassessmentResult = null,
  multiSessionPlan = null,
  treatmentCompletedAtIso = null,
  scanCapturedAtIso = null,
  explicitHorizonId = null,
  fallbackHorizonId = null,
  imageAssets = null,
  clinic = {},
} = {}) {
  if (!baselineSkinState?.core_features) {
    throw new Error(
      'Baseline Skin State V2 is required.',
    )
  }

  const optimizerResult =
    normaliseOptimizerResult(
      rawOptimizerResult,
    )
  const prediction =
    deliveredPrediction ??
    preSessionPrediction ??
    null

  const preliminaryHorizon =
    resolveOutcomeHorizonV2({
      explicitHorizonId,
      treatmentCompletedAtIso:
        treatmentCompletedAtIso ??
        executionRecord
          ?.completed_at_iso ??
        null,
      scanCapturedAtIso,
      fallbackHorizonId:
        reassessmentResult
          ? fallbackHorizonId
          : (
              fallbackHorizonId ??
              'immediate_post'
            ),
    })

  const reassessmentDisplay =
    reassessmentResult
      ? buildReassessmentDisplayV2({
          reassessmentResult,
          deliveredPrediction,
          preSessionPrediction,
          executionRecord,
          treatmentCompletedAtIso,
          scanCapturedAtIso,
          explicitHorizonId,
          fallbackHorizonId,
          imageAssets,
        })
      : null

  const horizonId =
    reassessmentDisplay
      ?.measurement_context
      ?.horizon_resolution
      ?.horizon_id ??
    preliminaryHorizon.horizon_id

  const type = reportType({
    reassessmentResult,
    horizonId,
    multiSessionPlan,
  })

  const featureResults =
    reassessmentDisplay
      ? reassessmentDisplay.score_cards.map(
          measuredFeatureResult,
        )
      : CORE_FEATURE_IDS.map(
          (featureId) =>
            predictionFeatureResult(
              prediction,
              featureId,
            ),
        ).filter(Boolean)

  const plannedTreatment =
    plannedTreatmentSummary(
      optimizerResult,
    )
  const actualTreatment =
    actualTreatmentSummary(
      executionRecord,
      deliveredPrediction,
    )
  const parameters = parameterCards({
    baselineSkinState,
    reassessmentResult,
    prediction,
    horizonId,
  })
  const timeline =
    reassessmentDisplay?.timeline ??
    predictionTimeline(prediction)
  const course = courseProgress(
    multiSessionPlan,
    executionRecord
      ?.session_number ??
      compiledSession
        ?.session_number ??
      null,
    reassessmentResult,
  )
  const handoff = nextOptimizerHandoff({
    reassessmentDisplay,
    deliveredPrediction,
    executionRecord,
    multiSessionPlan,
  })
  const summary = summaryFacts({
    reassessmentDisplay,
    prediction,
  })

  const notes = unique([
    reassessmentDisplay
      ?.measurement_notes,
    'Actual measured scores are authoritative. Predictions are comparison ranges, not achieved results.',
    'Immediate visible improvements are genuine score changes even when part of the effect gradually reduces over the following weeks.',
    reassessmentResult
      ? 'Numeric changes are hidden when the scan pair or pairwise evidence cannot reliably support them.'
      : 'This report contains expected ranges only because a post-treatment measurement is not attached.',
  ].flat())

  const report = {
    schema_version:
      CLIENT_OUTCOME_REPORT_SCHEMA_VERSION,
    builder_version:
      CLIENT_OUTCOME_REPORT_BUILDER_VERSION,
    report_id:
      `${client.client_id ?? 'client'}-${executionRecord?.session_id ?? optimizerResult?.input_summary?.scan_id ?? 'session'}-outcome-v2`,
    report_type: type,
    report_status:
      reassessmentResult
        ? 'measured_outcome_ready'
        : 'prediction_only_ready',
    generated_at_iso:
      new Date().toISOString(),
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
    client: {
      client_id:
        client.client_id ?? null,
      display_name:
        client.display_name ?? null,
    },
    session: {
      session_id:
        executionRecord?.session_id ??
        compiledSession?.session_id ??
        null,
      plan_id:
        multiSessionPlan?.plan_id ??
        executionRecord?.plan_id ??
        compiledSession?.plan_id ??
        null,
      treatment_mode:
        optimizerResult?.plan
          ?.treatment_mode ??
        executionRecord?.treatment_mode ??
        null,
      session_number:
        executionRecord
          ?.session_number ??
        compiledSession
          ?.session_number ??
        null,
    },
    score_semantics:
      REPORT_SCORE_SEMANTICS_V2,
    measurement_context:
      reassessmentDisplay
        ?.measurement_context ??
      {
        baseline_scan_id:
          baselineSkinState.scan
            ?.scan_id ?? null,
        post_scan_id: null,
        horizon_resolution:
          preliminaryHorizon,
        actual_measurement_is_authoritative:
          true,
        prediction_is_comparison_only:
          true,
      },
    outcome_summary: summary,
    treatment: {
      planned: plannedTreatment,
      actual: actualTreatment,
    },
    feature_results:
      featureResults,
    report_parameter_cards:
      parameters,
    timeline,
    reassessment_display:
      reassessmentDisplay,
    course_progress: course,
    next_optimizer_handoff:
      handoff,
    sections: buildSections({
      summary,
      plannedTreatment,
      actualTreatment,
      parameterCardsValue:
        parameters,
      timeline,
      reassessmentDisplay,
      course,
      handoff,
      notes,
    }),
    patient_narrative: {
      status:
        'pending_generation',
      headline: null,
      summary: null,
      feature_explanations: [],
      timeline_explanation: null,
      next_step_explanation: null,
    },
    report_rules: {
      actual_measurement_is_authoritative:
        true,
      prediction_is_never_presented_as_achieved:
        true,
      visible_immediate_score_change_is_valid:
        true,
      worsening_is_not_hidden:
        true,
      unreliable_numeric_change_is_suppressed:
        true,
      future_multi_session_placeholders_are_hidden:
        true,
      report_does_not_select_treatment:
        true,
    },
    important_notes: notes,
  }

  const validation =
    validateClientOutcomeReportV2(
      report,
    )
  if (!validation.valid) {
    throw new Error(
      `Client outcome report validation failed: ${validation.errors.join(' ')}`,
    )
  }

  return {
    ...report,
    validation,
  }
}
