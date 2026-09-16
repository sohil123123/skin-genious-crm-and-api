// Schema compatibility only. This is NOT the forthcoming comparative image-scoring algorithm.
import {MEASUREMENT_VERSION} from './regionalMeasurementV310.js'
import {impactToHealth} from './visibleImpactRubricV315.js'
export function regionalMeasurementChanges(before,after){
 if(before?.version!==MEASUREMENT_VERSION||after?.version!==MEASUREMENT_VERSION)throw Error('Matching V3.15 measurement versions required')
 const diff=(b,a)=>b===null||a===null?null:a-b
 return {comparison_method:'absolute_impact_differences_only_not_registered_pairwise_measurement',score_adjustment_from_pairwise:0,
  parameters:Object.fromEntries(Object.keys(before.parameters).map(id=>{const b=before.parameters[id],a=after.parameters[id];return[id,{before_impact:b.impact_level,after_impact:a.impact_level,after_minus_before_impact:diff(b.impact_level,a.impact_level),after_minus_before_health:b.impact_level===null||a.impact_level===null?null:impactToHealth(a.impact_level)-impactToHealth(b.impact_level),before_state:b.state,after_state:a.state}]})),
  features:Object.fromEntries(Object.keys(before.features).map(id=>{const b=before.features[id],a=after.features[id];return[id,{before_impact:b.impact_level,after_impact:a.impact_level,after_minus_before_impact:diff(b.impact_level,a.impact_level),regions_before:b.regions,regions_after:a.regions,before_state:b.state,after_state:a.state}]}))}
}
