import {
  CORE_FEATURE_IDS,
} from './skinStateV2.schema.js'
import {
  FACE_ZONE_ATLAS_V2,
  FACE_ZONE_IDS,
} from './faceZoneAtlasV2.js'
import {
  REASSESSMENT_DISPLAY_SCHEMA_VERSION,
  REPORT_SCORE_SEMANTICS_V2,
  burdenToClientHealthScoreV2,
  validateReassessmentDisplayV2,
} from './clientOutcomeReportV2.schema.js'
import {
  FEATURE_DISPLAY_CONFIG_V2,
  REASSESSMENT_HORIZON_DISPLAY_V2,
  OUTCOME_STATUS_DISPLAY_V2,
} from './outcomeDisplayConfigV2.js'
import {
  resolveOutcomeHorizonV2,
} from './resolveOutcomeHorizonV2.js'
import {
  compareActualOutcomeToPredictionV2,
  compareZoneActualToPredictionV2,
} from './compareActualOutcomeToPredictionV2.js'

export const REASSESSMENT_DISPLAY_BUILDER_VERSION =
  'aia_reassessment_display_builder_v2.0.0'

const unique = (values) =>
  [...new Set((values ?? []).filter(Boolean))]

const round = (value, digits = 1) =>
  Number(Number(value).toFixed(digits))

function predictionSelection({
  deliveredPrediction,
  preSessionPrediction,
}) {
  if (deliveredPrediction?.features) {
    return {
      prediction: deliveredPrediction,
      basis: 'delivered_treatment',
      fallback_used: false,
    }
  }
  if (preSessionPrediction?.features) {
    return {
      prediction: preSessionPrediction,
      basis: 'pre_session_plan',
      fallback_used: true,
    }
  }
  return {
    prediction: null,
    basis: null,
    fallback_used: false,
  }
}

function outcomePriority(outcome) {
  const baseline =
    outcome?.before_burden_score_1_to_100 ??
    1
  const change = Math.abs(
    outcome?.improvement_points ?? 0,
  )
  const reliable =
    outcome?.display_numeric_change ? 1 : 0.35
  return baseline * 0.28 + change * 3 * reliable
}

function createScoreCard({
  featureId,
  measuredOutcome,
  predictionFeature,
  horizonId,
  predictionBasis,
}) {
  const display =
    FEATURE_DISPLAY_CONFIG_V2[featureId]
  const comparison =
    compareActualOutcomeToPredictionV2({
      featureId,
      measuredOutcome,
      predictionFeature,
      horizonId,
      predictionBasis,
    })

  const actualChange =
    comparison.actual.improvement_points
  const displayNumeric =
    comparison.actual.display_numeric_change &&
    comparison.actual
      .change_status !==
      'not_reliably_measurable'

  return {
    feature_id: featureId,
    label: display?.label ?? featureId,
    short_label:
      display?.short_label ?? featureId,
    category:
      display?.category ?? 'other',
    primary_mode:
      display?.primary_mode ?? 'white',
    baseline: comparison.baseline,
    actual: {
      ...comparison.actual,
      improvement_points_for_display:
        displayNumeric
          ? actualChange
          : null,
      improvement_label:
        comparison.actual.change_status ===
        'improved'
          ? 'Improved'
          : comparison.actual
                .change_status === 'worsened'
            ? 'Needs Attention'
            : comparison.actual
                  .change_status === 'mixed'
              ? 'Mixed Response'
              : comparison.actual
                    .change_status ===
                    'not_reliably_measurable'
                ? 'Not Reliably Measurable'
                : 'Stable',
    },
    prediction:
      comparison.predicted,
    outcome_vs_prediction_status:
      comparison
        .outcome_vs_prediction_status,
    outcome_vs_prediction_display:
      OUTCOME_STATUS_DISPLAY_V2[
        comparison
          .outcome_vs_prediction_status
      ] ?? null,
    difference_from_expected_improvement_points:
      comparison
        .difference_from_expected_improvement_points,
    pairwise_summary:
      comparison.actual.pairwise_summary,
    transient_reactivity_note:
      comparison.actual
        .transient_reactivity_note,
    score_semantics:
      REPORT_SCORE_SEMANTICS_V2,
    priority_score:
      round(
        outcomePriority(
          measuredOutcome,
        ),
        2,
      ),
  }
}

function zoneOutcomeStatus(featureResults) {
  const improved = featureResults.filter(
    (item) =>
      item.actual_improvement_points > 0 &&
      item.pairwise_direction !== 'worsened',
  )
  const worsened = featureResults.filter(
    (item) =>
      item.actual_improvement_points < 0 ||
      item.pairwise_direction === 'worsened',
  )

  if (improved.length && worsened.length) {
    return 'mixed'
  }
  if (worsened.length) return 'worsened'
  if (improved.length) return 'improved'
  return 'stable'
}

function buildZoneMap({
  reassessmentResult,
  prediction,
  horizonId,
}) {
  const zones = {}

  for (const zoneId of FACE_ZONE_IDS) {
    const featureResults = []

    for (const featureId of CORE_FEATURE_IDS) {
      const measured =
        reassessmentResult
          .core_feature_outcomes?.[
          featureId
        ]
      const zone =
        measured?.zone_changes?.[zoneId]
      if (!zone) continue

      const comparison =
        compareZoneActualToPredictionV2({
          featureId,
          zoneId,
          measuredOutcome: measured,
          predictionFeature:
            prediction?.features?.[
              featureId
            ],
          horizonId,
        })

      if (!comparison.comparable) {
        continue
      }

      const threshold =
        measured
          .minimum_detectable_change_points ??
        5
      const reliableChange =
        Math.abs(
          comparison.actual_improvement_points ??
            0,
        ) >= threshold ||
        comparison.pairwise_direction

      if (!reliableChange) continue

      featureResults.push({
        ...comparison,
        label:
          FEATURE_DISPLAY_CONFIG_V2[
            featureId
          ]?.short_label ?? featureId,
      })
    }

    const sorted = featureResults.sort(
      (a, b) =>
        Math.abs(
          b.actual_improvement_points ?? 0,
        ) -
          Math.abs(
            a.actual_improvement_points ?? 0,
          ) ||
        a.feature_id.localeCompare(
          b.feature_id,
        ),
    )

    zones[zoneId] = {
      zone_id: zoneId,
      zone_label:
        FACE_ZONE_ATLAS_V2.zones[zoneId]
          ?.label ?? zoneId,
      outcome_status:
        zoneOutcomeStatus(sorted),
      improved_features: sorted
        .filter(
          (item) =>
            item.actual_improvement_points >
              0 &&
            item.pairwise_direction !==
              'worsened',
        )
        .map((item) => item.feature_id),
      worsened_features: sorted
        .filter(
          (item) =>
            item.actual_improvement_points <
              0 ||
            item.pairwise_direction ===
              'worsened',
        )
        .map((item) => item.feature_id),
      feature_results: sorted.slice(0, 6),
      has_reliable_change:
        sorted.length > 0,
    }
  }

  return {
    atlas_version:
      FACE_ZONE_ATLAS_V2.version,
    zones,
    improved_zone_ids: Object.values(zones)
      .filter(
        (zone) =>
          zone.outcome_status ===
          'improved',
      )
      .map((zone) => zone.zone_id),
    worsened_zone_ids: Object.values(zones)
      .filter(
        (zone) =>
          zone.outcome_status ===
          'worsened',
      )
      .map((zone) => zone.zone_id),
    mixed_zone_ids: Object.values(zones)
      .filter(
        (zone) =>
          zone.outcome_status === 'mixed',
      )
      .map((zone) => zone.zone_id),
  }
}

function buildTimeline({
  scoreCards,
  prediction,
  horizonResolution,
}) {
  const horizonOrder = [
    'immediate_post',
    'hours_48',
    'day_7',
    'day_28',
  ]

  return {
    selected_actual_horizon_id:
      horizonResolution.horizon_id,
    horizon_resolution:
      horizonResolution,
    horizons: horizonOrder.map(
      (horizonId) => ({
        horizon_id: horizonId,
        label:
          REASSESSMENT_HORIZON_DISPLAY_V2[
            horizonId
          ]?.label ?? horizonId,
        actual_measurement_attached:
          horizonId ===
          horizonResolution.horizon_id,
      }),
    ),
    feature_series: scoreCards.map(
      (card) => {
        const predictionFeature =
          prediction?.features?.[
            card.feature_id
          ]
        return {
          feature_id: card.feature_id,
          label: card.label,
          baseline_burden_score_1_to_100:
            card.baseline
              .burden_score_1_to_100,
          baseline_client_health_score_1_to_100:
            card.baseline
              .client_health_score_1_to_100,
          predicted: Object.fromEntries(
            horizonOrder.map(
              (horizonId) => {
                const horizon =
                  predictionFeature
                    ?.global_prediction
                    ?.horizons?.[
                    horizonId
                  ]
                return [
                  horizonId,
                  horizon
                    ? {
                        burden_score_1_to_100:
                          horizon
                            .predicted_burden_score_1_to_100,
                        client_health_score_1_to_100:
                          Object.fromEntries(
                            Object.entries(
                              horizon
                                .predicted_burden_score_1_to_100,
                            ).map(
                              ([
                                range,
                                value,
                              ]) => [
                                range,
                                burdenToClientHealthScoreV2(
                                  value,
                                ),
                              ],
                            ),
                          ),
                        improvement_points:
                          horizon
                            .predicted_net_change_points,
                        transient_burden_points:
                          horizon
                            .transient_burden_adjustment_points
                            ?.expected ?? 0,
                      }
                    : null,
                ]
              },
            ),
          ),
          actual:
            horizonResolution.horizon_id
              ? {
                  horizon_id:
                    horizonResolution.horizon_id,
                  burden_score_1_to_100:
                    card.actual
                      .burden_score_1_to_100,
                  client_health_score_1_to_100:
                    card.actual
                      .client_health_score_1_to_100,
                  improvement_points:
                    card.actual
                      .improvement_points_for_display,
                  measurement_status:
                    card.actual
                      .change_status,
                }
              : null,
        }
      },
    ),
  }
}

function assetForMode(
  imageAssets,
  scanType,
  mode,
) {
  return (
    imageAssets?.[scanType]?.[mode] ??
    null
  )
}

function buildImageComparisonCards({
  scoreCards,
  imageAssets,
  pairQuality,
}) {
  return scoreCards
    .filter(
      (card) =>
        card.actual.change_status !==
        'not_reliably_measurable',
    )
    .sort(
      (a, b) =>
        b.priority_score -
          a.priority_score ||
        a.feature_id.localeCompare(
          b.feature_id,
        ),
    )
    .slice(0, 6)
    .map((card) => {
      const config =
        FEATURE_DISPLAY_CONFIG_V2[
          card.feature_id
        ]
      const modes =
        config?.comparison_modes ??
        ['white']

      return {
        feature_id: card.feature_id,
        label: card.label,
        recommended_primary_mode:
          config?.primary_mode ?? 'white',
        mode_pairs: modes.map(
          (mode) => ({
            mode,
            baseline_asset:
              assetForMode(
                imageAssets,
                'baseline',
                mode,
              ),
            post_asset:
              assetForMode(
                imageAssets,
                'post_treatment',
                mode,
              ),
            available: Boolean(
              assetForMode(
                imageAssets,
                'baseline',
                mode,
              ) &&
              assetForMode(
                imageAssets,
                'post_treatment',
                mode,
              ),
            ),
          }),
        ),
        pair_quality: pairQuality,
        display_caution:
          pairQuality === 'poor'
            ? 'Image comparison should not be shown as proof because capture matching is poor.'
            : card
                .transient_reactivity_note ||
              null,
      }
    })
}

function treatmentDeliverySummary({
  deliveredPrediction,
  executionRecord,
}) {
  const delivered =
    deliveredPrediction
      ?.delivered_treatment_dose
  const actionDoses =
    delivered?.action_doses ?? []

  return {
    session_id:
      executionRecord?.session_id ??
      deliveredPrediction?.session_id ??
      null,
    session_status:
      executionRecord?.session_status ??
      null,
    completed_action_count:
      actionDoses.filter(
        (action) =>
          action.status === 'completed',
      ).length,
    skipped_action_count:
      actionDoses.filter(
        (action) =>
          action.status ===
          'skipped_with_reason',
      ).length,
    stopped_action_count:
      actionDoses.filter(
        (action) =>
          action.status ===
          'stopped_for_safety',
      ).length,
    mean_delivered_dose_fraction_0_to_1:
      delivered
        ?.mean_action_dose_fraction_0_to_1 ??
      null,
    actions: actionDoses.map(
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
    ),
    stopped_for_safety:
      delivered?.stopped_for_safety ??
      executionRecord
        ?.stopped_for_safety ??
      false,
  }
}

function headlineFromCards(scoreCards) {
  const reliable = scoreCards.filter(
    (card) =>
      card.actual.change_status !==
      'not_reliably_measurable',
  )
  const improved = reliable.filter(
    (card) =>
      card.actual.change_status ===
      'improved',
  )
  const worsened = reliable.filter(
    (card) =>
      card.actual.change_status ===
      'worsened',
  )
  const mixed = reliable.filter(
    (card) =>
      card.actual.change_status ===
      'mixed',
  )

  if (!reliable.length) {
    return {
      status: 'not_reliably_measurable',
      headline:
        'This scan could not reliably measure the treatment change.',
    }
  }
  if (
    improved.length &&
    !worsened.length &&
    !mixed.length
  ) {
    return {
      status: 'improved',
      headline:
        'Visible improvement was measured after the treatment.',
    }
  }
  if (
    improved.length >
    worsened.length + mixed.length
  ) {
    return {
      status: 'mostly_improved',
      headline:
        'The overall response was positive, with some areas still settling.',
    }
  }
  if (worsened.length) {
    return {
      status: 'needs_review',
      headline:
        'The scan shows a mixed response, including concerns that need review.',
    }
  }
  return {
    status: 'stable_or_mixed',
    headline:
      'The response was stable or mixed at this measurement point.',
  }
}

export function buildReassessmentDisplayV2({
  reassessmentResult,
  deliveredPrediction = null,
  preSessionPrediction = null,
  executionRecord = null,
  treatmentCompletedAtIso = null,
  scanCapturedAtIso = null,
  explicitHorizonId = null,
  fallbackHorizonId = null,
  imageAssets = null,
} = {}) {
  if (
    !reassessmentResult
      ?.core_feature_outcomes
  ) {
    throw new Error(
      'A Post-Treatment Reassessment V2 result is required.',
    )
  }

  const selectedPrediction =
    predictionSelection({
      deliveredPrediction,
      preSessionPrediction,
    })
  const horizonResolution =
    resolveOutcomeHorizonV2({
      explicitHorizonId,
      treatmentCompletedAtIso:
        treatmentCompletedAtIso ??
        executionRecord?.completed_at_iso ??
        null,
      scanCapturedAtIso,
      fallbackHorizonId,
    })
  const horizonId =
    horizonResolution.horizon_id

  const scoreCards = CORE_FEATURE_IDS.map(
    (featureId) =>
      createScoreCard({
        featureId,
        measuredOutcome:
          reassessmentResult
            .core_feature_outcomes[
            featureId
          ],
        predictionFeature:
          selectedPrediction.prediction
            ?.features?.[featureId],
        horizonId,
        predictionBasis:
          selectedPrediction.basis,
      }),
  ).sort(
    (a, b) =>
      b.priority_score -
        a.priority_score ||
      a.feature_id.localeCompare(
        b.feature_id,
      ),
  )

  const pairQuality =
    reassessmentResult.pairwise_evidence
      ?.comparison_meta?.pair_quality ??
    null
  const zoneMap = buildZoneMap({
    reassessmentResult,
    prediction:
      selectedPrediction.prediction,
    horizonId,
  })
  const headline =
    headlineFromCards(scoreCards)

  const display = {
    schema_version:
      REASSESSMENT_DISPLAY_SCHEMA_VERSION,
    builder_version:
      REASSESSMENT_DISPLAY_BUILDER_VERSION,
    measurement_context: {
      baseline_scan_id:
        reassessmentResult.baseline
          ?.scan_id ?? null,
      post_scan_id:
        reassessmentResult
          .post_treatment?.scan_id ??
        null,
      pair_quality:
        pairQuality,
      pair_quality_issues:
        reassessmentResult
          .pairwise_evidence
          ?.comparison_meta
          ?.pair_quality_issues ?? [],
      horizon_resolution:
        horizonResolution,
      prediction_basis:
        selectedPrediction.basis,
      prediction_fallback_used:
        selectedPrediction.fallback_used,
      actual_measurement_is_authoritative:
        true,
      prediction_is_comparison_only:
        true,
    },
    score_semantics:
      REPORT_SCORE_SEMANTICS_V2,
    top_outcome_banner: headline,
    score_cards: scoreCards,
    featured_score_cards:
      scoreCards.slice(0, 8),
    timeline: buildTimeline({
      scoreCards: scoreCards.slice(0, 8),
      prediction:
        selectedPrediction.prediction,
      horizonResolution,
    }),
    zone_map: zoneMap,
    before_after_image_cards:
      buildImageComparisonCards({
        scoreCards,
        imageAssets,
        pairQuality,
      }),
    treatment_delivery:
      treatmentDeliverySummary({
        deliveredPrediction,
        executionRecord,
      }),
    measurement_notes: unique([
      horizonResolution.caution,
      pairQuality === 'poor'
        ? 'Capture matching was poor. Numeric changes are suppressed where the change is not reliably verifiable.'
        : null,
      selectedPrediction.fallback_used
        ? 'Delivered-treatment prediction was unavailable; comparison uses the original planned-treatment prediction.'
        : null,
    ]),
    display_rules: {
      never_show_prediction_as_achieved_result:
        true,
      never_hide_reliably_measured_worsening:
        true,
      suppress_numeric_change_when_not_reliably_measurable:
        true,
      show_transient_reactivity_separately:
        true,
      client_health_score_is_higher_is_better:
        true,
      concern_burden_score_is_lower_is_better:
        true,
    },
  }

  const validation =
    validateReassessmentDisplayV2(
      display,
    )
  if (!validation.valid) {
    throw new Error(
      `Reassessment display validation failed: ${validation.errors.join(' ')}`,
    )
  }

  return {
    ...display,
    validation,
  }
}
