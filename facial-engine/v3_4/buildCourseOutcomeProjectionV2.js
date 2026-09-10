import {
  buildPreSessionPredictionV2,
  buildDeliveredTreatmentPredictionV2,
} from './predictedOutcomeEngineV2.js'
import {
  OUTCOME_HORIZON_IDS_V2,
} from './predictedOutcomeV2.schema.js'

export const COURSE_OUTCOME_PROJECTION_VERSION =
  'aia_course_outcome_projection_v2.0.0'

function applyExpectedPredictionToSkinState({
  skinState,
  prediction,
  horizonId,
  nextScanId,
}) {
  if (
    !OUTCOME_HORIZON_IDS_V2.includes(horizonId)
  ) {
    throw new Error(
      `Unsupported carry-forward horizon: ${horizonId}`,
    )
  }

  const next = structuredClone(skinState)
  next.scan = {
    ...next.scan,
    scan_id: nextScanId,
    capture_type: 'predicted_future_state',
    paired_baseline_scan_id:
      skinState.scan?.scan_id ?? null,
  }

  for (const [featureId, feature] of Object.entries(
    prediction.features,
  )) {
    const target =
      next.core_features?.[featureId]
    if (!target) continue

    target.global_burden_score_1_to_100 =
      feature.global_prediction.horizons[
        horizonId
      ].predicted_burden_score_1_to_100
        .expected

    for (const [zoneId, zonePrediction] of Object.entries(
      feature.zone_predictions,
    )) {
      if (
        target.zone_scores_1_to_100?.[
          zoneId
        ] === undefined
      ) {
        continue
      }
      target.zone_scores_1_to_100[zoneId] =
        zonePrediction.horizons[
          horizonId
        ].predicted_burden_score_1_to_100
          .expected
    }
  }

  return next
}

export function buildReleasedCourseOutcomeProjectionV2({
  baselineSkinState,
  multiSessionPlan,
  executionRecordsBySession = {},
  carryForwardHorizonId = 'day_28',
} = {}) {
  const block =
    multiSessionPlan?.current_detailed_block
  if (
    !block ||
    block.status !== 'detailed_and_released'
  ) {
    throw new Error(
      'A current detailed and released multi-session block is required.',
    )
  }

  let workingSkinState =
    structuredClone(baselineSkinState)
  const sessionProjections = []

  for (const session of block.detailed_sessions) {
    const executionRecord =
      executionRecordsBySession[
        session.session_number
      ] ?? null
    const prediction = executionRecord
      ? buildDeliveredTreatmentPredictionV2({
          baselineSkinState:
            workingSkinState,
          optimizerResult:
            session.optimiser_plan,
          executionRecord,
        })
      : buildPreSessionPredictionV2({
          baselineSkinState:
            workingSkinState,
          optimizerResult:
            session.optimiser_plan,
        })

    sessionProjections.push({
      session_number:
        session.session_number,
      prediction_basis: executionRecord
        ? 'delivered_treatment'
        : 'planned_treatment',
      baseline_scan_id:
        workingSkinState.scan?.scan_id ??
        null,
      prediction,
      carry_forward_horizon_id:
        carryForwardHorizonId,
    })

    workingSkinState =
      applyExpectedPredictionToSkinState({
        skinState: workingSkinState,
        prediction,
        horizonId:
          carryForwardHorizonId,
        nextScanId:
          `${multiSessionPlan.plan_id}-predicted-after-session-${session.session_number}`,
      })
  }

  return {
    version:
      COURSE_OUTCOME_PROJECTION_VERSION,
    plan_id:
      multiSessionPlan.plan_id,
    block_number:
      block.block_number,
    session_numbers:
      block.session_numbers,
    carry_forward_horizon_id:
      carryForwardHorizonId,
    carry_forward_assumption:
      `The expected ${carryForwardHorizonId} state after one session is used as the starting point for the next released session. This is a planning projection and must be replaced by the actual reassessment scan when available.`,
    session_projections:
      sessionProjections,
    predicted_end_of_released_block_skin_state:
      workingSkinState,
    future_placeholder_blocks_projected:
      false,
    reassessment_gate:
      block.reassessment_gate,
    next_step:
      'Compare the actual reassessment scan with the delivered-treatment predictions before generating the next two detailed sessions.',
  }
}
