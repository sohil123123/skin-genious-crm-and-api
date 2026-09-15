import { CORE_FEATURE_IDS } from './skinStateV2.schema.js'
import {
  optimizeZonalTreatmentV2,
  buildFeaturePriorityMapV2,
} from './zonalTreatmentOptimizerV2.js'
import {
  TREATMENT_MODE_CONTRACTS_VERSION,
  getTreatmentModeContractV2,
  validateTreatmentModePlanV2,
} from './treatmentModeContractsV2.js'

export const MULTI_SESSION_PLAN_ORCHESTRATOR_VERSION =
  'aia_multi_session_plan_orchestrator_v2.8.0'

const unique = (values) => [...new Set((values ?? []).filter(Boolean))]

function globalFeatureScore(skinState, featureId) {
  const value =
    skinState?.core_features?.[featureId]
      ?.global_burden_score_1_to_100
  return Number.isFinite(Number(value)) ? Number(value) : null
}

function dominantZones(skinState, featureId) {
  const explicit =
    skinState?.core_features?.[featureId]?.dominant_zones
  if (Array.isArray(explicit) && explicit.length) return explicit

  return Object.entries(
    skinState?.core_features?.[featureId]
      ?.zone_scores_1_to_100 ?? {},
  )
    .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
    .slice(0, 4)
    .map(([zoneId]) => zoneId)
}

function actionIds(sessionPlan) {
  return (
    sessionPlan?.plan?.modality_actions?.map(
      (action) => action.modality_id,
    ) ?? []
  )
}

function heroIds(sessionPlan) {
  return (
    sessionPlan?.plan?.hero_actions?.map(
      (action) => action.modality_id,
    ) ?? []
  )
}

function createDetailedSession({
  sessionNumber,
  skinState,
  patientHistory,
  primaryConcerns,
  secondaryConcerns,
  featurePriorities,
  sessionConstraints,
  regionalTemperaturesC,
  zoneContext,
  capabilities,
  candidateModalityIds,
  clinicProtocolIds,
  previousSessionPlan,
}) {
  const optimizerPlan = optimizeZonalTreatmentV2({
    skinState,
    patientHistory,
    primaryConcerns,
    secondaryConcerns,
    featurePriorities,
    treatmentMode: 'multiple',
    sessionConstraints,
    regionalTemperaturesC,
    zoneContext,
    capabilities,
    candidateModalityIds,
    clinicProtocolIds,
    courseContext: {
      session_number: sessionNumber,
      previous_session_action_ids:
        actionIds(previousSessionPlan),
      previous_session_hero_ids:
        heroIds(previousSessionPlan),
    },
  })

  const businessContractValidation =
    validateTreatmentModePlanV2(optimizerPlan)
  if (!businessContractValidation.valid) {
    throw new Error(
      `Session ${sessionNumber} violates the Multiple-session business contract: ${businessContractValidation.errors.join(' ')}`,
    )
  }

  return {
    session_number: sessionNumber,
    detail_status: 'released_for_session_compiler',
    measurement_basis:
      sessionNumber % 2 === 1
        ? 'latest_reassessment_skin_state'
        : 'same_block_skin_state_with_course_sequence_adjustment',
    pre_session_safety_recheck_required: true,
    business_contract_validation:
      businessContractValidation,
    minimum_duration_minutes: 55,
    mandatory_lymphatic_drainage_present:
      optimizerPlan.plan
        .mandatory_lymphatic_drainage_present,
    optimiser_plan: optimizerPlan,
  }
}

function buildStrategyTracks({
  skinState,
  featurePriorityMap,
  detailedSessions,
  targetBurdenThreshold,
}) {
  const actionsByFeature = {}

  for (const session of detailedSessions) {
    for (const action of session.optimiser_plan.plan
      .modality_actions) {
      for (const target of action.target_features ?? []) {
        const current = actionsByFeature[target.feature_id] ?? {
          modality_ids: [],
          session_numbers: [],
          zones: [],
        }
        current.modality_ids.push(action.modality_id)
        current.session_numbers.push(session.session_number)
        current.zones.push(...(target.zones ?? []))
        actionsByFeature[target.feature_id] = current
      }
    }
  }

  return CORE_FEATURE_IDS
    .map((featureId) => ({
      feature_id: featureId,
      baseline_burden_score_1_to_100:
        globalFeatureScore(skinState, featureId),
      priority:
        featurePriorityMap.priorities[featureId] ?? 0,
      priority_sources:
        featurePriorityMap.sources[featureId] ?? [],
      dominant_zones: dominantZones(skinState, featureId),
      course_target:
        `Reduce burden meaningfully toward or below ${targetBurdenThreshold}, subject to clinical realism and reassessment.`,
      current_block_modality_ids: unique(
        actionsByFeature[featureId]?.modality_ids,
      ),
      current_block_session_numbers: unique(
        actionsByFeature[featureId]?.session_numbers,
      ),
    }))
    .filter(
      (track) =>
        track.baseline_burden_score_1_to_100 !== null &&
        (
          track.baseline_burden_score_1_to_100 >= 25 ||
          track.priority >= 0.9
        ),
    )
    .sort(
      (a, b) =>
        b.priority * b.baseline_burden_score_1_to_100 -
          a.priority * a.baseline_burden_score_1_to_100 ||
        a.feature_id.localeCompare(b.feature_id),
    )
}

function createFutureBlockPlaceholders(
  currentBlockNumber,
  maximumSessions,
  blockSize,
) {
  const placeholders = []
  const currentBlockEnd = currentBlockNumber * blockSize

  for (
    let start = currentBlockEnd + 1;
    start <= maximumSessions;
    start += blockSize
  ) {
    const end = Math.min(start + blockSize - 1, maximumSessions)
    placeholders.push({
      block_number: Math.ceil(start / blockSize),
      session_numbers: Array.from(
        { length: end - start + 1 },
        (_, index) => start + index,
      ),
      status: 'awaiting_prior_block_reassessment',
      detailed_steps_generated: false,
      generation_rule:
        'Generate only after the preceding two-session reassessment.',
    })
  }

  return placeholders
}

function createReassessmentGate(blockNumber, sessionNumbers) {
  const afterSession = Math.max(...sessionNumbers)
  return {
    gate_id: `reassessment_after_session_${afterSession}`,
    block_number: blockNumber,
    after_session: afterSession,
    required: true,
    status: 'pending',
    required_inputs: [
      'new_five_mode_scan',
      'same_method_skin_state_v2',
      'pairwise_outcome_evidence_v2',
      'actual_treatment_execution_records',
      'client_tolerance_and_feedback',
    ],
    next_action:
      'Generate the next two detailed sessions only after this gate is completed.',
  }
}

function explicitStopReason(reassessmentDecision = {}) {
  if (reassessmentDecision.doctor_stop) return 'doctor_stopped_course'
  if (reassessmentDecision.client_stop) return 'client_stopped_course'
  if (reassessmentDecision.goals_met) return 'goals_met'
  return null
}

function automaticGoalScreen({
  skinState,
  featurePriorityMap,
  targetBurdenThreshold,
}) {
  const priorityFeatures = CORE_FEATURE_IDS.filter(
    (featureId) =>
      (featurePriorityMap.priorities[featureId] ?? 0) >= 0.9,
  )
  if (!priorityFeatures.length) {
    return {
      all_priority_targets_met: false,
      feature_results: [],
    }
  }

  const featureResults = priorityFeatures.map((featureId) => {
    const score = globalFeatureScore(skinState, featureId)
    return {
      feature_id: featureId,
      score_1_to_100: score,
      target_threshold: targetBurdenThreshold,
      target_met:
        score !== null && score <= targetBurdenThreshold,
    }
  })

  return {
    all_priority_targets_met: featureResults.every(
      (item) => item.target_met,
    ),
    feature_results: featureResults,
  }
}

function createBlock({
  blockNumber,
  startSessionNumber,
  skinState,
  commonInputs,
  previousSessionPlan,
}) {
  const firstSession = createDetailedSession({
    sessionNumber: startSessionNumber,
    skinState,
    previousSessionPlan,
    ...commonInputs,
  })
  const secondSessionNumber = startSessionNumber + 1
  const secondSession =
    secondSessionNumber <=
    commonInputs.maximumSessions
      ? createDetailedSession({
          sessionNumber: secondSessionNumber,
          skinState,
          previousSessionPlan:
            firstSession.optimiser_plan,
          ...commonInputs,
        })
      : null

  const sessions = [
    firstSession,
    secondSession,
  ].filter(Boolean)

  return {
    block_number: blockNumber,
    session_numbers: sessions.map(
      (session) => session.session_number,
    ),
    status: 'detailed_and_released',
    detailed_sessions: sessions,
    reassessment_gate: createReassessmentGate(
      blockNumber,
      sessions.map((session) => session.session_number),
    ),
    important_note:
      'Every released plan session is independently validated for a minimum duration of 55 minutes and mandatory lymphatic drainage. Session 2 is detailed using the latest available Skin State and course-sequencing logic. A pre-session safety recheck remains mandatory. Sessions after this block are not detailed before reassessment.',
  }
}

export function createMultiSessionPlanV2({
  skinState,
  patientHistory = {},
  primaryConcerns = [],
  secondaryConcerns = [],
  featurePriorities = {},
  sessionConstraints = {},
  regionalTemperaturesC = {},
  zoneContext = {},
  capabilities = {},
  candidateModalityIds = null,
  clinicProtocolIds = [],
  maximumSessions = 8,
  targetBurdenThreshold = 25,
} = {}) {
  if (!skinState?.core_features) {
    throw new Error(
      'Skin State V2 with core_features is required.',
    )
  }

  const contract = getTreatmentModeContractV2('multiple')
  const cappedMaximumSessions = Math.max(
    5,
    Math.min(
      contract.course.maximum_sessions,
      Number(maximumSessions) || 8,
    ),
  )
  const featurePriorityMap = buildFeaturePriorityMapV2({
    primaryConcerns,
    secondaryConcerns,
    featurePriorities,
  })

  const commonInputs = {
    patientHistory,
    primaryConcerns,
    secondaryConcerns,
    featurePriorities,
    sessionConstraints,
    regionalTemperaturesC,
    zoneContext,
    capabilities,
    candidateModalityIds,
    clinicProtocolIds,
    maximumSessions: cappedMaximumSessions,
  }

  const currentBlock = createBlock({
    blockNumber: 1,
    startSessionNumber: 1,
    skinState,
    commonInputs,
    previousSessionPlan: null,
  })

  const strategyTracks = buildStrategyTracks({
    skinState,
    featurePriorityMap,
    detailedSessions:
      currentBlock.detailed_sessions,
    targetBurdenThreshold,
  })

  return {
    multi_session_orchestrator_version:
      MULTI_SESSION_PLAN_ORCHESTRATOR_VERSION,
    treatment_mode_contract_version:
      TREATMENT_MODE_CONTRACTS_VERSION,
    plan_id:
      `aia-course-${skinState.scan?.scan_id ?? 'unknown'}-v2`,
    plan_type: 'multiple',
    status: 'active',
    maximum_sessions: cappedMaximumSessions,
    detailed_block_size:
      contract.course.detailed_block_size,
    current_block_number: 1,
    completed_sessions: [],
    overall_strategy: {
      objective:
        'Maximise the best achievable outcome across a dynamic course while adapting after every two sessions.',
      strategy_tracks: strategyTracks,
      target_burden_threshold:
        targetBurdenThreshold,
      exact_future_settings_precommitted: false,
    },
    current_detailed_block: currentBlock,
    future_blocks: createFutureBlockPlaceholders(
      1,
      cappedMaximumSessions,
      contract.course.detailed_block_size,
    ),
    reassessment_history: [],
    outcome_stop_policy: {
      stop_early_when_goals_met: true,
      explicit_doctor_or_client_stop_supported: true,
      automatic_goal_screen_is_a_gate_not_a_diagnosis: true,
    },
    session_delivery_contract: {
      voice_guidance_required_for_every_released_step: true,
      next_button_uses_only_released_detailed_sessions: true,
      future_placeholder_sessions_have_no_steps_or_audio: true,
      no_live_permission_or_approval_prompt: true,
    },
    source_inputs: {
      patientHistory,
      primaryConcerns,
      secondaryConcerns,
      featurePriorities,
      sessionConstraints,
      regionalTemperaturesC,
      zoneContext,
      capabilities,
      candidateModalityIds,
      clinicProtocolIds,
    },
  }
}

export function advanceMultiSessionPlanAfterReassessmentV2({
  existingPlan,
  reassessedSkinState,
  reassessmentDecision = {},
  executionRecords = [],
  pairwiseOutcomeEvidence = null,
  patientHistory = null,
  regionalTemperaturesC = null,
  zoneContext = null,
  capabilities = null,
} = {}) {
  if (existingPlan?.plan_type !== 'multiple') {
    throw new Error(
      'An existing multiple-session plan is required.',
    )
  }
  if (!reassessedSkinState?.core_features) {
    throw new Error(
      'Reassessed Skin State V2 is required.',
    )
  }

  const currentBlock =
    existingPlan.current_detailed_block
  const completedSessionNumbers =
    currentBlock.session_numbers
  const completedSessions = unique([
    ...(existingPlan.completed_sessions ?? []),
    ...completedSessionNumbers,
  ]).sort((a, b) => a - b)

  const source = existingPlan.source_inputs ?? {}
  const primaryConcerns = source.primaryConcerns ?? []
  const secondaryConcerns =
    source.secondaryConcerns ?? []
  const featurePriorities =
    source.featurePriorities ?? {}
  const featurePriorityMap = buildFeaturePriorityMapV2({
    primaryConcerns,
    secondaryConcerns,
    featurePriorities,
  })

  const stopReason = explicitStopReason(
    reassessmentDecision,
  )
  const goalScreen = automaticGoalScreen({
    skinState: reassessedSkinState,
    featurePriorityMap,
    targetBurdenThreshold:
      existingPlan.overall_strategy
        .target_burden_threshold,
  })
  const maximumReached =
    completedSessions.length >=
    existingPlan.maximum_sessions
  const shouldStop =
    Boolean(stopReason) ||
    maximumReached ||
    (
      goalScreen.all_priority_targets_met &&
      reassessmentDecision.continue_despite_targets_met !==
        true
    )

  const historyEntry = {
    block_number: currentBlock.block_number,
    after_session: Math.max(
      ...currentBlock.session_numbers,
    ),
    reassessment_scan_id:
      reassessedSkinState.scan?.scan_id ?? null,
    pairwise_outcome_evidence_attached:
      Boolean(pairwiseOutcomeEvidence),
    execution_record_count:
      executionRecords.length,
    decision: reassessmentDecision,
    automatic_goal_screen: goalScreen,
    completed_at_iso:
      reassessmentDecision.completed_at_iso ?? null,
  }

  if (shouldStop) {
    return {
      ...existingPlan,
      status: 'completed',
      completion_reason:
        stopReason ??
        (maximumReached
          ? 'maximum_sessions_reached'
          : 'priority_targets_met'),
      completed_sessions: completedSessions,
      current_detailed_block: {
        ...currentBlock,
        status: 'completed_and_reassessed',
        reassessment_gate: {
          ...currentBlock.reassessment_gate,
          status: 'completed',
        },
      },
      future_blocks: (
        existingPlan.future_blocks ?? []
      ).map((block) => ({
        ...block,
        status: 'cancelled_course_completed',
      })),
      reassessment_history: [
        ...(existingPlan.reassessment_history ?? []),
        historyEntry,
      ],
      latest_skin_state_scan_id:
        reassessedSkinState.scan?.scan_id ?? null,
    }
  }

  const nextStartSession =
    Math.max(...completedSessions) + 1
  if (
    nextStartSession >
    existingPlan.maximum_sessions
  ) {
    return {
      ...existingPlan,
      status: 'completed',
      completion_reason:
        'maximum_sessions_reached',
      completed_sessions: completedSessions,
      reassessment_history: [
        ...(existingPlan.reassessment_history ?? []),
        historyEntry,
      ],
    }
  }

  const nextBlockNumber =
    currentBlock.block_number + 1
  const previousSessionPlan =
    currentBlock.detailed_sessions.at(-1)
      ?.optimiser_plan ?? null

  const commonInputs = {
    patientHistory:
      patientHistory ?? source.patientHistory ?? {},
    primaryConcerns,
    secondaryConcerns,
    featurePriorities,
    sessionConstraints:
      source.sessionConstraints ?? {},
    regionalTemperaturesC:
      regionalTemperaturesC ??
      source.regionalTemperaturesC ??
      {},
    zoneContext:
      zoneContext ?? source.zoneContext ?? {},
    capabilities:
      capabilities ?? source.capabilities ?? {},
    candidateModalityIds:
      source.candidateModalityIds ?? null,
    clinicProtocolIds:
      source.clinicProtocolIds ?? [],
    maximumSessions:
      existingPlan.maximum_sessions,
  }

  const nextBlock = createBlock({
    blockNumber: nextBlockNumber,
    startSessionNumber: nextStartSession,
    skinState: reassessedSkinState,
    commonInputs,
    previousSessionPlan,
  })

  return {
    ...existingPlan,
    status: 'active',
    current_block_number: nextBlockNumber,
    completed_sessions: completedSessions,
    current_detailed_block: nextBlock,
    future_blocks: createFutureBlockPlaceholders(
      nextBlockNumber,
      existingPlan.maximum_sessions,
      existingPlan.detailed_block_size,
    ),
    reassessment_history: [
      ...(existingPlan.reassessment_history ?? []),
      historyEntry,
    ],
    overall_strategy: {
      ...existingPlan.overall_strategy,
      strategy_tracks: buildStrategyTracks({
        skinState: reassessedSkinState,
        featurePriorityMap,
        detailedSessions:
          nextBlock.detailed_sessions,
        targetBurdenThreshold:
          existingPlan.overall_strategy
            .target_burden_threshold,
      }),
      latest_reassessment_scan_id:
        reassessedSkinState.scan?.scan_id ?? null,
    },
  }
}
