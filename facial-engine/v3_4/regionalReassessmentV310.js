import {CONTRACT,MEASUREMENT_VERSION} from './regionalMeasurementV310.js'
// Signed regional evidence stays internal; no bonus, floor or weighted-best-region selection.
export function regionalMeasurementChanges(before,after){
 if(before?.version!==MEASUREMENT_VERSION||after?.version!==MEASUREMENT_VERSION)throw Error('Regional measurement versions must match')
 return Object.fromEntries(Object.entries(CONTRACT).map(([id,c])=>[id,Object.fromEntries(c.regions.map(g=>{
  const br=before.parameters[id].regions[g],ar=after.parameters[id].regions[g],b=br.metrics,a=ar.metrics
  const delta=Object.fromEntries(Object.keys(b).map(n=>[n,a[n]-b[n]]))
  const visibilityChanged=Math.abs(before.regions[g].visible_fraction-after.regions[g].visible_fraction)>.1
  const issues=[]
  if(visibilityChanged)issues.push('visible_region_changed')
  if(id==='superficial_pigmentation'){
   // Review flags, not diagnostic cutoffs. They do not change the score.
   if(Math.abs(a.white_patch_contrast-a.subsurface_patch_contrast)>.15||Math.abs(b.white_patch_contrast-b.subsurface_patch_contrast)>.15)issues.push('mode_contrast_disagreement')
   if(delta.white_patch_contrast*delta.subsurface_patch_contrast<0 && Math.abs(delta.white_patch_contrast-delta.subsurface_patch_contrast)>.08)issues.push('opposite_mode_change')
   if(Math.abs(delta.coverage_area_percent)>=5)issues.push('coverage_change_requires_patch_identity_check')
  }
  return[g,{after_minus_before:delta,score_adjustment_from_pairwise:0,visibility_changed:visibilityChanged,pigment_landmark_before:br.landmark_reference??null,pigment_landmark_after:ar.landmark_reference??null,review_flags:issues,review_status:issues.length?'review_requested_no_automatic_score_override':'no_rule_triggered',review_thresholds_are_engineering_only:true}]
 }))]))
}
