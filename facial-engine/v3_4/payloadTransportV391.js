// Transport-only projection. Full evidence stays in the existing server artifact.
// Never call vision, rescore, round, change calibration or substitute defaults here.
export const TRANSPORT_VERSION_V391 = 'aia_compact_transport_v3.9.1'
const omit = (value, keys) => Object.fromEntries(Object.entries(value ?? {}).filter(([key]) => !keys.includes(key)))
export function compactDiagnosisV391(diagnosis) {
  if (!diagnosis?.diagnosis_report) throw new Error('Completed diagnosis missing; do not pass an engine envelope into the legacy scoring prompt.')
  return {
    ...omit(diagnosis, ['v3_4_report', 'v3_4_skin_state']),
    diagnosis_report: Object.fromEntries(Object.entries(diagnosis.diagnosis_report).map(([key,card]) => [key,omit(card,['calibration_audit','data_quality'])])),
    treatable_concerns_summary: diagnosis.treatable_concerns_summary ? {
      ...diagnosis.treatable_concerns_summary,
      parameters_with_abnormal_scores: (diagnosis.treatable_concerns_summary.parameters_with_abnormal_scores ?? []).map(c => omit(c,['treatment_data_quality'])),
    } : undefined,
  }
}
export function assessmentReferenceV391(result, assessmentId, post = false) {
  const packet = post ? result.post_run?.evidence_packet ?? result.post_feature_packet : result.feature_packet
  const state = post ? result.post_run?.skin_state : result.skin_state
  if (!packet?.scan?.scan_id || !state?.scoring_execution?.calibration_version) throw new Error('Stored assessment is incomplete; cannot return a server reference.')
  return {
    schema_version: 'aia_server_assessment_reference_v3.9.1',
    assessment_id: assessmentId,
    scan_id: packet.scan.scan_id,
    capture_type: packet.scan.capture_type,
    image_set_hash: packet.scan.image_set_hash,
    model_version: packet.model_execution?.model_version,
    prompt_version: packet.model_execution?.prompt_version,
    calibration_version: state.scoring_execution.calibration_version,
    evidence_storage: 'server',
    contains_completed_scores: true,
  }
}
export function compactResultV391(result, assessmentId) {
  if (result.command === 'assessment') return {
    engine_version: result.engine_version,
    transport_version: TRANSPORT_VERSION_V391,
    command: result.command,
    latency_profile: result.latency_profile,
    feature_packet: assessmentReferenceV391(result, assessmentId),
    diagnosis: compactDiagnosisV391(result.diagnosis),
    assessment_contract: result.assessment_contract,
  }
  if (result.command === 'reassessment') return {
    engine_version: result.engine_version,
    transport_version: TRANSPORT_VERSION_V391,
    command: result.command,
    post_feature_packet: assessmentReferenceV391(result, assessmentId, true),
    post_diagnosis: omit(result.post_diagnosis, ['v3_4_reassessment']),
    outcome_report_status: result.outcome_report_status,
  }
  throw new Error('Unsupported transport projection command.')
}
// Keep all clinical packet/state fields. Only remove literal duplicate containers
// in the planning context; no new scoring or clinical selection logic.
export function compactPlanningContextV391(context) {
  const state = context.skin_state
  const packet = context.feature_packet
  const packetCopy = {...packet}
  if (JSON.stringify(packet?.legacy_measurements) === JSON.stringify(state?.legacy_measurements)) delete packetCopy.legacy_measurements
  const diagnosis = context.diagnosis?.diagnosis_report
    ? compactDiagnosisV391(context.diagnosis)
    : omit(context.diagnosis, ['v3_4_reassessment'])
  return {...context, feature_packet: packetCopy, diagnosis,
    transport_version: TRANSPORT_VERSION_V391,
    reference_notes: 'Legacy measurements are in skin_state. Full component/zone evidence is in feature_packet. Do not rescore; use the supplied completed scores.',
  }
}
