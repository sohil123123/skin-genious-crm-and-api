import {
  FACE_ZONE_ATLAS_V2,
} from './faceZoneAtlasV2.js'
import {
  getTreatmentModeContractV2,
} from './treatmentModeContractsV2.js'
import {
  EXECUTION_PROTOCOL_LIBRARY_VERSION,
  FOUNDATION_EXECUTION_PROTOCOLS_V2,
  getExecutionProtocolV2,
} from './executionProtocolLibraryV2.js'
import {
  THERAPIST_SESSION_SCHEMA_VERSION,
  SESSION_COMPILE_STATUS,
  STEP_TYPES,
  createEmptyPatientScriptV2,
  validateCompiledSessionShapeV2,
} from './therapistSessionV2.schema.js'
import {
  createStepUiContractV2,
  SESSION_STEP_UI_CONTRACT_VERSION,
} from './startStepUiContractV2.js'
import {
  buildAdaptiveProtocolRegistryForPlanV3,
} from './adaptiveProtocolSettingsEngineV3.js'

export const THERAPIST_SESSION_COMPILER_VERSION =
  'aia_therapist_session_compiler_v3.4.0'

const clamp = (value, min, max) =>
  Math.max(min, Math.min(max, Number(value)))

const unique = (values) =>
  [...new Set((values ?? []).filter(Boolean))]

const formatValue = (value) => {
  if (
    typeof value === 'object' &&
    value !== null
  ) {
    return JSON.stringify(value)
  }
  return String(value)
}

function stepTypeForAction(action) {
  if (action.plan_role === 'mandatory') {
    return STEP_TYPES.MANDATORY_FINAL
  }
  if (action.plan_role === 'supportive') {
    return STEP_TYPES.SUPPORTIVE
  }
  return STEP_TYPES.CORRECTIVE
}

function normaliseOptimizerResult(input) {
  if (input?.plan?.modality_actions) return input
  if (input?.optimiser_plan?.plan?.modality_actions) {
    return input.optimiser_plan
  }
  throw new Error(
    'A released optimizer result with plan.modality_actions is required.',
  )
}

function resolveProtocolInput(
  modalityId,
  clinicExecutionProtocols,
) {
  const input =
    clinicExecutionProtocols?.[modalityId] ?? null
  if (!input) return null
  if (input.approved !== true) return null
  return input
}

function missingProtocolParameters(
  executionProtocol,
  protocolInput,
) {
  const parameters =
    protocolInput?.parameters ?? {}
  return (
    executionProtocol.required_protocol_parameters ??
    []
  ).filter(
    (field) =>
      parameters[field] === undefined ||
      parameters[field] === null ||
      parameters[field] === '',
  )
}

function protocolResolutionForAction(
  action,
  clinicExecutionProtocols,
) {
  const executionProtocol = getExecutionProtocolV2(
    action.modality_id,
  )
  if (!executionProtocol) {
    return {
      execution_protocol: null,
      protocol_input: null,
      ready: false,
      missing: ['execution_protocol_definition'],
      reason: 'No execution protocol definition exists.',
    }
  }

  if (
    executionProtocol.release_policy ===
      'disabled_modality'
  ) {
    return {
      execution_protocol: executionProtocol,
      protocol_input: null,
      ready: false,
      missing: ['modality_is_disabled'],
      reason:
        'The selected modality is disabled and cannot be compiled.',
    }
  }

  const protocolInput = resolveProtocolInput(
    action.modality_id,
    clinicExecutionProtocols,
  )
  const missing = missingProtocolParameters(
    executionProtocol,
    protocolInput,
  )

  const requiresInput =
    executionProtocol.release_policy !==
    'built_in_general_protocol'
  const ready =
    (!requiresInput || Boolean(protocolInput)) &&
    missing.length === 0

  return {
    execution_protocol: executionProtocol,
    protocol_input: protocolInput,
    ready,
    missing:
      requiresInput && !protocolInput
        ? [
            'approved_clinic_execution_protocol',
            ...executionProtocol.required_protocol_parameters,
          ]
        : missing,
    reason: ready
      ? null
      : 'Adaptive doctor-constraint protocol inputs are incomplete or invalid.',
  }
}

function selectedZoneLabels(action) {
  return (action.selected_zones ?? []).map(
    (zoneId) =>
      FACE_ZONE_ATLAS_V2.zones[zoneId]?.label ??
      zoneId,
  )
}

function protocolParameterText(
  protocolInput,
) {
  const parameters =
    protocolInput?.parameters ?? {}
  const entries = Object.entries(parameters)
  if (!entries.length) {
    return 'No exact protocol parameters supplied.'
  }
  return entries
    .map(
      ([key, value]) =>
        `${key}: ${formatValue(value)}`,
    )
    .join('; ')
}

function buildTherapistInstruction({
  action,
  executionProtocol,
  protocolInput,
  missing,
}) {
  const template =
    executionProtocol
      .therapist_instruction_template
  const zoneText =
    selectedZoneLabels(action).join(', ') ||
    'selected treatment zones'
  const approvedHowToDo =
    protocolInput?.approved_how_to_do ?? null
  const parameterText =
    protocolParameterText(protocolInput)

  const technique = approvedHowToDo
    ? `${approvedHowToDo} Treat only: ${zoneText}.`
    : `${template.technique} Treat only: ${zoneText}. Approved protocol parameters: ${parameterText}`

  const howToDo = [
    `Technique: ${technique}`,
    `Endpoint: ${template.endpoint}`,
    `Monitor: ${template.safety_signals.join('; ')}`,
    `Stop if: ${template.stop_conditions.join('; ')}`,
    `Transition: ${template.transition_cue}`,
  ].join('\n')

  return {
    technique,
    approved_parameters:
      protocolInput?.parameters ?? {},
    endpoint:
      protocolInput?.endpoint_override ??
      template.endpoint,
    safety_signals: unique([
      ...template.safety_signals,
      ...(protocolInput?.additional_safety_signals ??
        []),
    ]),
    stop_conditions: unique([
      ...template.stop_conditions,
      ...(protocolInput?.additional_stop_conditions ??
        []),
    ]),
    transition_cue:
      protocolInput?.transition_cue_override ??
      template.transition_cue,
    pre_step_confirmations: unique([
      'Correct client and session confirmed.',
      'Selected zones and protected zones confirmed.',
      'Required protocol inputs loaded.',
      ...(protocolInput?.pre_step_confirmations ??
        []),
    ]),
    missing_protocol_inputs: missing,
    how_to_do: howToDo,
  }
}

function actionTargetFeatures(action) {
  return (action.target_features ?? []).map(
    (feature) => ({
      feature_id: feature.feature_id,
      peak_burden_score_1_to_100:
        feature.peak_burden_score_1_to_100,
      zones: feature.zones ?? [],
      clinical_roles:
        feature.clinical_roles ?? [],
    }),
  )
}

function actionExpectedTiming(action) {
  return unique(
    (action.zone_delivery ?? []).flatMap(
      (zone) =>
        (zone.targets ?? []).map(
          (target) => target.expected_onset,
        ),
    ),
  )
}

function actionStage(action) {
  return (
    getExecutionProtocolV2(action.modality_id)
      ?.sequence_stage ?? 50
  )
}

function expandHydrafacialBundle(
  action,
  allActions,
) {
  if (
    action.modality_id !==
    'hydrafacial_full_protocol'
  ) {
    return [action]
  }

  const componentIds = (
    action.included_components ?? []
  ).map((component) => component.modality_id)

  if (!componentIds.length) return [action]

  return componentIds.map((modalityId) => {
    const component =
      action.included_components.find(
        (item) => item.modality_id === modalityId,
      )
    return {
      ...action,
      modality_id: modalityId,
      modality_name:
        component.modality_name ?? modalityId,
      selected_zones:
        component.selected_zones ??
        action.selected_zones,
      source_bundle_modality_id:
        'hydrafacial_full_protocol',
      source_bundle_name:
        action.modality_name,
      included_components: [],
      estimated_duration_minutes: null,
      plan_role:
        action.plan_role,
    }
  })
}

function expandActions(optimizerResult) {
  const actions =
    optimizerResult.plan.modality_actions
  return actions.flatMap((action) =>
    expandHydrafacialBundle(action, actions),
  )
}

function desiredActionDuration(
  action,
  protocol,
  protocolInput = null,
  sourceBundleComponentCount = 1,
) {
  const min =
    protocol.duration_range_minutes.min
  const max =
    protocol.duration_range_minutes.max
  const validatedProtocolDuration = Number(
    protocolInput?.parameters?.duration_minutes,
  )
  if (Number.isFinite(validatedProtocolDuration)) {
    return clamp(
      Math.round(validatedProtocolDuration),
      min,
      max,
    )
  }

  const estimated = Number(
    action.estimated_duration_minutes,
  )

  if (Number.isFinite(estimated)) {
    return clamp(
      Math.round(estimated),
      min,
      max,
    )
  }

  if (action.source_bundle_modality_id) {
    return Math.round((min + max) / 2)
  }

  return min
}

function allocateDurations({
  actionDescriptors,
  treatmentMode,
}) {
  const contract =
    getTreatmentModeContractV2(treatmentMode)
  const foundation =
    FOUNDATION_EXECUTION_PROTOCOLS_V2
      .cleanse_and_prepare

  const descriptors = [
    {
      kind: 'foundation',
      id: 'cleanse_and_prepare',
      min: foundation.duration_range_minutes.min,
      max: foundation.duration_range_minutes.max,
      desired: foundation.duration_range_minutes.min,
      priority: 2,
    },
    ...actionDescriptors.map((descriptor) => ({
      kind: 'action',
      id: descriptor.compilation_id,
      min:
        descriptor.execution_protocol
          .duration_range_minutes.min,
      max:
        descriptor.execution_protocol
          .duration_range_minutes.max,
      desired: desiredActionDuration(
        descriptor.action,
        descriptor.execution_protocol,
        descriptor.protocol_input,
      ),
      priority:
        descriptor.action.plan_role === 'hero'
          ? 5
          : descriptor.action.plan_role ===
              'secondary'
            ? 4
            : descriptor.action.plan_role ===
                'supportive'
              ? 3
              : 1,
    })),
  ]

  const allocations = Object.fromEntries(
    descriptors.map((item) => [
      item.id,
      clamp(item.desired, item.min, item.max),
    ]),
  )

  const total = () =>
    Object.values(allocations).reduce(
      (sum, value) => sum + value,
      0,
    )

  const hardMax =
    contract.duration.hard_maximum_minutes
  while (total() > hardMax) {
    const reducible = descriptors
      .filter(
        (item) =>
          allocations[item.id] > item.min,
      )
      .sort(
        (a, b) =>
          a.priority - b.priority ||
          b.max - a.max ||
          a.id.localeCompare(b.id),
      )[0]
    if (!reducible) break
    allocations[reducible.id] -= 1
  }

  const hardMin =
    contract.duration.hard_minimum_minutes
  while (total() < hardMin) {
    const expandable = descriptors
      .filter(
        (item) =>
          allocations[item.id] < item.max,
      )
      .sort(
        (a, b) =>
          b.priority - a.priority ||
          b.max - a.max ||
          a.id.localeCompare(b.id),
      )[0]
    if (!expandable) break
    allocations[expandable.id] += 1
  }

  return {
    allocations,
    calculated_total_minutes: total(),
    within_maximum: total() <= hardMax,
    meets_minimum: total() >= hardMin,
    minimum_minutes: hardMin,
    maximum_minutes: hardMax,
  }
}

function createFoundationStep(
  durationMinutes,
) {
  const protocol =
    FOUNDATION_EXECUTION_PROTOCOLS_V2
      .cleanse_and_prepare
  return {
    compilation_id: 'foundation_cleanse_and_prepare',
    modality_id: null,
    source_action_modality_id: null,
    source_bundle_modality_id: null,
    plan_role: 'foundation',
    step_type: STEP_TYPES.FOUNDATION,
    title: 'Cleanse and Prepare the Skin',
    duration_minutes: durationMinutes,
    target_zones:
      FACE_ZONE_ATLAS_V2.groups.full_face,
    target_features: [],
    expected_timing: ['immediate'],
    ingredients_equipments:
      protocol.ingredients_equipments,
    protocol_reference: {
      execution_protocol_id:
        protocol.protocol_id,
      clinic_protocol_id: null,
      approved: true,
      missing_inputs: [],
    },
    therapist_instruction: {
      ...protocol
        .therapist_instruction_template,
      pre_step_confirmations: [
        'Client identity and treatment mode confirmed.',
        'History, allergies and recent-treatment answers rechecked.',
        'Consent and treatment-zone agreement confirmed.',
      ],
      approved_parameters: {},
      missing_protocol_inputs: [],
      how_to_do: [
        `Technique: ${protocol.therapist_instruction_template.technique}`,
        `Endpoint: ${protocol.therapist_instruction_template.endpoint}`,
        `Monitor: ${protocol.therapist_instruction_template.safety_signals.join('; ')}`,
        `Stop if: ${protocol.therapist_instruction_template.stop_conditions.join('; ')}`,
        `Transition: ${protocol.therapist_instruction_template.transition_cue}`,
      ].join('\n'),
    },
    patient_script_context:
      protocol.patient_script_context,
    limitations_and_non_claims: [],
    patient_script:
      createEmptyPatientScriptV2(),
    script_status: 'pending_generation',
  }
}

function createActionStep({
  descriptor,
  durationMinutes,
}) {
  const {
    action,
    execution_protocol: protocol,
    protocol_input: protocolInput,
    missing,
  } = descriptor

  return {
    compilation_id:
      descriptor.compilation_id,
    modality_id: action.modality_id,
    source_action_modality_id:
      action.source_bundle_modality_id ??
      action.modality_id,
    source_bundle_modality_id:
      action.source_bundle_modality_id ?? null,
    plan_role: action.plan_role,
    step_type: stepTypeForAction(action),
    title: action.modality_name,
    duration_minutes: durationMinutes,
    target_zones:
      action.selected_zones ?? [],
    protected_or_avoided_zones: unique(
      (action.zone_delivery ?? []).flatMap(
        (zone) =>
          zone.safety
            ?.required_protection ?? [],
      ),
    ),
    target_features:
      actionTargetFeatures(action),
    expected_timing:
      actionExpectedTiming(action),
    ingredients_equipments:
      protocol.ingredients_equipments,
    protocol_reference: {
      execution_protocol_id:
        protocol.protocol_id,
      clinic_protocol_id:
        protocolInput?.protocol_id ??
        protocolInput?.parameters
          ?.clinic_protocol_id ??
        null,
      approved: descriptor.ready,
      missing_inputs: missing,
    },
    therapist_instruction:
      buildTherapistInstruction({
        action,
        executionProtocol: protocol,
        protocolInput,
        missing,
      }),
    patient_script_context:
      protocol.patient_script_context,
    limitations_and_non_claims:
      action.limitations_and_non_claims ??
      [],
    expected_transient_effects:
      action.expected_transient_effects ??
      [],
    patient_script:
      createEmptyPatientScriptV2(),
    script_status: descriptor.ready
      ? 'pending_generation'
      : 'blocked_until_protocol_complete',
  }
}


function parameterSubset(parameters, fields) {
  if (!fields?.length) return parameters ?? {}
  return Object.fromEntries(
    fields
      .filter(
        (field) =>
          parameters?.[field] !== undefined,
      )
      .map((field) => [field, parameters[field]]),
  )
}

function executionSubstepInstruction({
  parentStep,
  substep,
}) {
  const original =
    parentStep.therapist_instruction
  const parameters = parameterSubset(
    original.approved_parameters,
    substep.parameter_fields,
  )
  const technique = `${substep.technique} Treat only: ${
    parentStep.target_zones
      .map(
        (zoneId) =>
          FACE_ZONE_ATLAS_V2.zones[zoneId]
            ?.label ?? zoneId,
      )
      .join(', ') || 'selected treatment zones'
  }.`

  return {
    ...original,
    technique,
    approved_parameters: parameters,
    endpoint:
      substep.endpoint ?? original.endpoint,
    transition_cue:
      substep.transition_cue ??
      original.transition_cue,
    how_to_do: [
      `Technique: ${technique}`,
      `Endpoint: ${
        substep.endpoint ?? original.endpoint
      }`,
      `Monitor: ${original.safety_signals.join(
        '; ',
      )}`,
      `Stop if: ${original.stop_conditions.join(
        '; ',
      )}`,
      `Transition: ${
        substep.transition_cue ??
        original.transition_cue
      }`,
    ].join('\n'),
  }
}

function expandExecutionSubsteps({
  parentStep,
  descriptor,
}) {
  const substeps =
    descriptor.execution_protocol
      .execution_substeps
  if (!substeps?.length) return [parentStep]

  const totalSubstepMinutes = substeps.reduce(
    (sum, substep) =>
      sum + Number(substep.duration_minutes),
    0,
  )
  if (
    totalSubstepMinutes !==
    Number(parentStep.duration_minutes)
  ) {
    throw new Error(
      `${parentStep.modality_id} execution substeps total ${totalSubstepMinutes} minutes, but the allocated action duration is ${parentStep.duration_minutes} minutes.`,
    )
  }

  return substeps.map((substep, index) => ({
    ...parentStep,
    compilation_id: `${
      parentStep.compilation_id
    }__${substep.substep_id}`,
    parent_compilation_id:
      parentStep.compilation_id,
    execution_substep_id:
      substep.substep_id,
    execution_substep_number: index + 1,
    execution_substep_count:
      substeps.length,
    title: substep.title,
    duration_minutes:
      substep.duration_minutes,
    ingredients_equipments:
      substep.ingredients_equipments ??
      parentStep.ingredients_equipments,
    therapist_instruction:
      executionSubstepInstruction({
        parentStep,
        substep,
      }),
    patient_script_context: {
      ...parentStep.patient_script_context,
      plain_language_action: substep.title,
    },
  }))
}

function buildPreparationChecklist({
  actionDescriptors,
  treatmentMode,
}) {
  const categories = new Set(
    actionDescriptors.map(
      (item) => item.action.category,
    ),
  )
  const checklist = [
    'Confirm client identity, treatment mode, session number and selected concerns.',
    'Recheck pregnancy/breastfeeding status, allergies, recent actives, recent laser/energy treatment, sun exposure and event/travel context.',
    'Confirm consent, selected treatment zones and all protected/avoid zones.',
    'Take or confirm standardized pre-treatment photographs when required.',
    'Secure hair, remove jewellery from the treatment area and provide appropriate draping.',
    'Prepare all selected machines, probes, products, consumables and disposables before starting.',
    'Load only the stored approved protocol parameters; do not estimate missing settings.',
    'Confirm emergency stop access and ask the client to report heat, burning, sharp pain or unusual discomfort immediately.',
    'Confirm that mandatory face massage and lymphatic drainage is present in the compiled session and allocated 5–15 minutes.',
  ]

  if (
    [...categories].some((category) =>
      [
        'laser',
        'laser_special_protocol',
        'photobiomodulation',
      ].includes(category),
    )
  ) {
    checklist.push(
      'Confirm correct eye protection for the client, therapist and any person in the room.',
    )
  }
  if (
    [...categories].some((category) =>
      [
        'invasive',
        'energy_invasive',
      ].includes(category),
    )
  ) {
    checklist.push(
      'Prepare a sterile field, verify sealed cartridges and document batch/lot details where required.',
    )
  }
  if (categories.has('chemical_peel')) {
    checklist.push(
      'Prepare the exact peel timer, protection product, neutralizer or removal supplies specified in the stored protocol.',
    )
  }
  if (treatmentMode === 'express') {
    checklist.push(
      'Confirm the compiled session fits the 40-minute Express contract before the first step begins.',
    )
  }

  return checklist.slice(0, 12)
}

function createSessionTitle(
  optimizerResult,
  sessionNumber,
) {
  const heroes =
    optimizerResult.plan.hero_actions?.length
      ? optimizerResult.plan.hero_actions
      : optimizerResult.plan.hero_action
        ? [optimizerResult.plan.hero_action]
        : []
  const heroNames = heroes
    .map((action) => action.modality_name)
    .slice(0, 2)
    .join(' + ')
  return heroNames
    ? `Session ${sessionNumber}: ${heroNames}`
    : `Session ${sessionNumber}: Personalized Facial`
}

function selectedActionAudit(
  optimizerResult,
  steps,
) {
  const selectedActionIds = unique(
    optimizerResult.plan.modality_actions.map(
      (action) => action.modality_id,
    ),
  )
  const represented = unique(
    steps.flatMap((step) => [
      step.source_action_modality_id,
      step.modality_id,
    ]),
  )
  const missing = selectedActionIds.filter(
    (modalityId) =>
      !represented.includes(modalityId),
  )
  return {
    selected_action_ids:
      selectedActionIds,
    represented_action_ids:
      represented,
    missing_selected_actions:
      missing,
    all_selected_actions_preserved:
      missing.length === 0,
  }
}

function auditMandatorySessionActionsV2_8({
  optimizerResult,
  compiledSteps,
  treatmentModeContract,
}) {
  const mandatoryDefinitions =
    treatmentModeContract.mandatory_session_actions ?? {}
  const requiredIds = Object.values(
    mandatoryDefinitions,
  ).map((definition) => definition.modality_id)

  const optimizerMandatoryIds = new Set(
    (optimizerResult.plan.mandatory_actions ?? []).map(
      (action) => action.modality_id,
    ),
  )
  const compiledModalityIds = new Set(
    compiledSteps.map((step) => step.modality_id),
  )

  const missingFromOptimizer = requiredIds.filter(
    (modalityId) =>
      !optimizerMandatoryIds.has(modalityId),
  )
  const missingFromCompiledSteps = requiredIds.filter(
    (modalityId) =>
      !compiledModalityIds.has(modalityId),
  )

  const lymphaticStep = compiledSteps.find(
    (step) =>
      step.modality_id === 'lymphatic_drainage',
  )
  const lymphaticDefinition =
    mandatoryDefinitions.lymphatic_drainage
  const lymphaticDurationValid =
    Boolean(lymphaticStep) &&
    Number(lymphaticStep.duration_minutes) >=
      Number(
        lymphaticDefinition?.minimum_duration_minutes ??
          5,
      ) &&
    Number(lymphaticStep.duration_minutes) <=
      Number(
        lymphaticDefinition?.maximum_duration_minutes ??
          15,
      )

  return {
    required_modality_ids: requiredIds,
    missing_from_optimizer:
      missingFromOptimizer,
    missing_from_compiled_steps:
      missingFromCompiledSteps,
    lymphatic_duration_minutes:
      lymphaticStep?.duration_minutes ?? 0,
    lymphatic_duration_valid:
      lymphaticDurationValid,
    valid:
      missingFromOptimizer.length === 0 &&
      missingFromCompiledSteps.length === 0 &&
      lymphaticDurationValid,
  }
}


export function compileTherapistSessionV2({
  optimizerResult: rawOptimizerResult,
  sessionNumber = 1,
  planId = null,
  clinicExecutionProtocols = {},
  aiProtocolProposalsByModality = {},
  adaptiveProtocolSelectionEnabled = true,
  skinState = null,
  patientHistory = {},
  regionalTemperaturesC = {},
  patientId = null,
} = {}) {
  const optimizerResult =
    normaliseOptimizerResult(rawOptimizerResult)
  const treatmentMode =
    optimizerResult.plan.treatment_mode ??
    optimizerResult.input_summary
      ?.treatment_mode ??
    'single'
  const contract =
    getTreatmentModeContractV2(treatmentMode)

  const adaptiveProtocolRegistry =
    adaptiveProtocolSelectionEnabled
      ? buildAdaptiveProtocolRegistryForPlanV3({
          optimizerResult,
          aiProposalsByModality:
            aiProtocolProposalsByModality,
          skinState,
          patientHistory,
          regionalTemperaturesC,
        })
      : {}

  const resolvedExecutionProtocols = {
    ...adaptiveProtocolRegistry,
    ...clinicExecutionProtocols,
  }

  const expandedActions =
    expandActions(optimizerResult)
  const actionDescriptors =
    expandedActions
      .map((action, index) => {
        const resolution =
          protocolResolutionForAction(
            action,
            resolvedExecutionProtocols,
          )
        if (!resolution.execution_protocol) {
          return null
        }
        return {
          action,
          compilation_id: `action_${String(
            index + 1,
          ).padStart(2, '0')}_${action.modality_id}`,
          ...resolution,
        }
      })
      .filter(Boolean)
      .sort(
        (a, b) =>
          a.execution_protocol.sequence_stage -
            b.execution_protocol
              .sequence_stage ||
          a.compilation_id.localeCompare(
            b.compilation_id,
          ),
      )

  const durationAllocation =
    allocateDurations({
      actionDescriptors,
      treatmentMode,
    })
  const foundationStep =
    createFoundationStep(
      durationAllocation.allocations
        .cleanse_and_prepare,
    )

  const actionSteps =
    actionDescriptors.flatMap((descriptor) => {
      const parentStep = createActionStep({
        descriptor,
        durationMinutes:
          durationAllocation.allocations[
            descriptor.compilation_id
          ],
      })
      return expandExecutionSubsteps({
        parentStep,
        descriptor,
      })
    })

  const orderedSteps = [
    foundationStep,
    ...actionSteps,
  ].map((step, index) => ({
    ...step,
    step_id: `session_${sessionNumber}_step_${String(
      index + 1,
    ).padStart(2, '0')}`,
    step_number: index + 1,
  }))

  const sessionId = `${planId ?? optimizerResult.input_summary?.scan_id ?? 'aia'}-session-${sessionNumber}`
  const stepsWithUi = orderedSteps.map(
    (step) => ({
      ...step,
      ui_contract:
        createStepUiContractV2({
          stepId: step.step_id,
          stepNumber: step.step_number,
          durationMinutes:
            step.duration_minutes,
        }),
    }),
  )

  const missingProtocolInputs =
    actionDescriptors
      .filter((descriptor) => !descriptor.ready)
      .map((descriptor) => ({
        modality_id:
          descriptor.action.modality_id,
        modality_name:
          descriptor.action.modality_name,
        missing_inputs:
          descriptor.missing,
        reason: descriptor.reason,
      }))

  const actionAudit =
    selectedActionAudit(
      optimizerResult,
      stepsWithUi,
    )
  const mandatoryActionAudit =
    auditMandatorySessionActionsV2_8({
      optimizerResult,
      compiledSteps: stepsWithUi,
      treatmentModeContract: contract,
    })
  const durationReady =
    durationAllocation.within_maximum &&
    durationAllocation.meets_minimum
  const releaseReady =
    missingProtocolInputs.length === 0 &&
    actionAudit.all_selected_actions_preserved &&
    mandatoryActionAudit.valid &&
    durationReady

  const compileStatus = releaseReady
    ? SESSION_COMPILE_STATUS.RELEASE_READY
    : !mandatoryActionAudit.valid
      ? SESSION_COMPILE_STATUS
          .BLOCKED_INVALID_OPTIMIZER_INPUT
      : SESSION_COMPILE_STATUS
          .DRAFT_MISSING_PROTOCOL_INPUTS

  const calculatedDuration =
    stepsWithUi.reduce(
      (sum, step) =>
        sum + step.duration_minutes,
      0,
    )

  const session = {
    schema_version:
      THERAPIST_SESSION_SCHEMA_VERSION,
    compiler_version:
      THERAPIST_SESSION_COMPILER_VERSION,
    execution_protocol_library_version:
      EXECUTION_PROTOCOL_LIBRARY_VERSION,
    ui_contract_version:
      SESSION_STEP_UI_CONTRACT_VERSION,
    adaptive_protocol_selection: {
      enabled:
        adaptiveProtocolSelectionEnabled,
      source:
        'doctor_approved_constraint_envelope',
      live_approval_prompt_required:
        false,
      selected_protocols:
        Object.fromEntries(
          Object.entries(
            resolvedExecutionProtocols,
          ).map(([modalityId, protocol]) => [
            modalityId,
            {
              approved:
                protocol.approved === true,
              approval_basis:
                protocol.approval_basis ??
                null,
              parameter_source:
                protocol.parameter_source ??
                null,
              protocol_id:
                protocol.protocol_id ??
                null,
              adaptive_settings_audit:
                protocol.adaptive_settings_audit ??
                null,
            },
          ]),
        ),
    },
    session_id: sessionId,
    plan_id: planId,
    patient_id: patientId,
    treatment_mode: treatmentMode,
    treatment_mode_contract: {
      minimum_minutes:
        contract.duration
          .hard_minimum_minutes,
      target_minutes:
        contract.duration.target_minutes,
      maximum_minutes:
        contract.duration
          .hard_maximum_minutes,
    },
    session_number: sessionNumber,
    title: createSessionTitle(
      optimizerResult,
      sessionNumber,
    ),
    compile_status: compileStatus,
    release_ready: releaseReady,
    script_generation_status:
      releaseReady
        ? 'ready_for_patient_script_prompt'
        : 'blocked_until_protocol_complete',
    preparations_checklist_for_therapist:
      buildPreparationChecklist({
        actionDescriptors,
        treatmentMode,
      }),
    concerns_addressed: unique(
      optimizerResult.plan.modality_actions.flatMap(
        (action) =>
          (action.target_features ?? []).map(
            (feature) =>
              feature.feature_id,
          ),
      ),
    ),
    steps: stepsWithUi,
    step_duration_total:
      calculatedDuration,
    timing_validation: {
      calculated_from_steps:
        calculatedDuration,
      treatment_mode_minimum:
        contract.duration
          .hard_minimum_minutes,
      treatment_mode_maximum:
        contract.duration
          .hard_maximum_minutes,
      within_maximum:
        durationAllocation.within_maximum,
      meets_minimum:
        durationAllocation.meets_minimum,
      valid:
        durationAllocation.within_maximum &&
        durationAllocation.meets_minimum,
    },
    protocol_validation: {
      missing_protocol_inputs:
        missingProtocolInputs,
      no_settings_invented:
        true,
      exact_settings_source:
        'approved_clinic_execution_protocols_only',
    },
    selected_action_audit:
      actionAudit,
    mandatory_action_audit:
      mandatoryActionAudit,
    workflow_contract: {
      patient_script_is_only_llm_generated_field:
        true,
      therapist_instructions_are_deterministic:
        true,
      compiler_generates_audio: false,
      voice_plugin_owner: 'technology_team',
      patient_script_trigger_button:
        'start_step_button',
      next_step_triggers_script: false,
      all_selected_optimizer_actions_locked:
        true,
      mandatory_lymphatic_drainage_required:
        true,
      mandatory_lymphatic_drainage_trigger:
        'compiled_as_normal_start_step',
      minimum_session_duration:
        contract.duration.hard_minimum_minutes,
      compiler_may_add_unselected_filler_steps:
        false,
      future_multi_session_placeholders_not_compilable:
        true,
    },
  }

  const shape =
    validateCompiledSessionShapeV2(session)
  if (!shape.valid) {
    return {
      ...session,
      compile_status:
        SESSION_COMPILE_STATUS
          .BLOCKED_INVALID_OPTIMIZER_INPUT,
      release_ready: false,
      shape_validation: shape,
    }
  }

  return {
    ...session,
    shape_validation: shape,
  }
}

export function compileReleasedMultiSessionBlockV2({
  multiSessionPlan,
  clinicExecutionProtocolsBySession = {},
  patientId = null,
} = {}) {
  const block =
    multiSessionPlan?.current_detailed_block
  if (
    !block ||
    block.status !== 'detailed_and_released'
  ) {
    throw new Error(
      'Only the current detailed and released two-session block can be compiled.',
    )
  }

  const compiledSessions =
    block.detailed_sessions.map((session) =>
      compileTherapistSessionV2({
        optimizerResult:
          session.optimiser_plan,
        sessionNumber:
          session.session_number,
        planId: multiSessionPlan.plan_id,
        clinicExecutionProtocols:
          clinicExecutionProtocolsBySession[
            session.session_number
          ] ?? {},
        patientId,
      }),
    )

  return {
    plan_id: multiSessionPlan.plan_id,
    plan_type: 'multiple',
    block_number: block.block_number,
    session_numbers:
      block.session_numbers,
    compiled_sessions:
      compiledSessions,
    future_blocks_compiled: false,
    future_block_policy:
      'Future sessions remain placeholders until reassessment releases the next block.',
    all_released_sessions_ready:
      compiledSessions.every(
        (session) => session.release_ready,
      ),
  }
}

export function createClinicExecutionProtocolTemplateV2(
  optimizerResult,
) {
  const normalized =
    normaliseOptimizerResult(optimizerResult)
  const actions = expandActions(normalized)
  return Object.fromEntries(
    actions.map((action) => {
      const protocol = getExecutionProtocolV2(
        action.modality_id,
      )
      return [
        action.modality_id,
        {
          approved: false,
          protocol_id: null,
          parameters: Object.fromEntries(
            (
              protocol
                ?.required_protocol_parameters ??
              []
            ).map((field) => [field, null]),
          ),
          approved_how_to_do: null,
          pre_step_confirmations: [],
          additional_safety_signals: [],
          additional_stop_conditions: [],
        },
      ]
    }),
  )
}
