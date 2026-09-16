// Baseline V3.14: proposed visual rubric; not a population-calibrated or clinically validated model.
// One observation namespace, shared by report parameters and treatment evidence.
export const BASELINE_VERSION='aia_visual_baseline_v3.14.0'
export const FORMULA_VERSION='aia_visual_baseline_equations_v3.14.0'
export const MEASUREMENT_VERSION='aia_visual_baseline_measurement_v3.14.0'
export const SCORE_MIN=1 // Preserve existing 1..100 API/report compatibility.
export const SCORE_MAX=100
export const COMPONENT_PEAK_WEIGHTS={visual_acne:.60,texture_open_pores:.35,skin_hydration:.25,barrier_health_sensitivity:.40,peri_orbital_health:.25,superficial_wrinkles:.15,jawline_sagging:.25,skin_firmness_elasticity:.25}
export const REGIONAL_PEAK_WEIGHT=0.20 // Engineering choice; explicit and reviewable.
export const SEVERITY_ANCHORS={0:'absent / normal variation',0.2:'mild: faint but discernible on close inspection',0.45:'moderate: readily visible',0.7:'marked: conspicuous',1:'very marked: pronounced visible feature'}
export const FEATURES={
  "dehydration_lines": {
    "definition": "Fine superficial criss-cross dehydration-type lines; exclude fixed folds and expression creases.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face",
      "eye_left",
      "eye_right"
    ]
  },
  "crepiness": {
    "definition": "Papery or finely crinkled surface appearance, excluding fixed wrinkles.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "flaking": {
    "definition": "Visible adherent scales/flakes corroborated by surface morphology; exclude fibres, hair and product particles.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "dry_patches": {
    "definition": "Visibly dry rough patches; absence alone is insufficient evidence of optimal hydration appearance.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "plumpness_deficit": {
    "definition": "Flat or deflated appearance of the superficial skin surface; exclude constitutional face shape, fat loss, swelling and anatomical hollows.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "diffuse_dullness": {
    "definition": "Reduced broad diffuse luminosity/gray appearance relative to the person\u2019s natural tone. Exclude oily highlights, product glints, exposure artifacts and anatomical shadows.",
    "modes": [
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "reflection_discontinuity": {
    "definition": "Interrupted or patchy broad surface reflection outside concentrated glare. Do not penalize a naturally matte but even finish.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "pigment_contrast": {
    "definition": "Brown patch/freckle contrast against nearby same-region skin, excluding constitutional darkness, moles, hair shadows, red lesions and pore dots. Cross-check white and subsurface views.",
    "modes": [
      "subsurface_polarized",
      "white"
    ],
    "response_class": "appearance",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "roughness": {
    "definition": "Visible coarse or uneven surface relief outside pore openings and scar depressions.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "micrograin": {
    "definition": "Fine surface grain/micro-roughness, excluding discrete pores, scars, hair and anatomic shadows.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "microshadows": {
    "definition": "Fine surface microshadows that follow actual micro-relief, excluding directional/anatomical shadows.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "pore_prominence": {
    "definition": "Conspicuous enlarged-looking pore openings: prominence/size/contrast rather than mere presence of normal pores. Exclude comedone plugs and scars.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "appearance",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "comedones": {
    "definition": "Visible open and closed comedones/plugged openings; exclude normal sebaceous filaments and unblocked pore dots.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "scars_pits": {
    "definition": "Atrophic pits and depressed scars: visible relief/contrast within the affected area. Exclude ordinary pores and flat pigment marks.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "response_class": "structural",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "papules": {
    "definition": "Visible raised inflammatory papules; distinguish from comedones and residual flat marks.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "pustules": {
    "definition": "Visible pustules with a discernible pustular centre. Do not diagnose from UV fluorescence alone.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "deep_inflammatory_lesions": {
    "definition": "Visible large/deep-looking inflammatory swellings; appearance only, not palpated nodule diagnosis.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "response_class": "appearance",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "erythema": {
    "definition": "Diffuse or patchy visible erythema relative to surrounding skin and natural tone; exclude normal blush. Red-lit brightness alone is not erythema.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "vascular_visibility": {
    "definition": "Conspicuous superficial vascular markings corroborated in white/subsurface; red mode context only.",
    "modes": [
      "subsurface_polarized",
      "white"
    ],
    "response_class": "appearance",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "barrier_surface_disruption": {
    "definition": "Visible disrupted/scaly/fissured or irritated-looking surface; exclude pits/scars and mere large pores. No physiological permeability measurement.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "appearance",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "oily_film": {
    "definition": "Concentrated greasy film and shine distribution, especially nose/central forehead. Exclude uniform finishing-product sheen, sweat and isolated glints. No shine does not prove deficient sebum.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "responsive",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ]
  },
  "fixed_lines": {
    "definition": "Persistent-looking creases/folds at rest, distinguished from fine dehydration lines and active expression folds. Depth is a visual estimate.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "structural",
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face",
      "eye_left",
      "eye_right"
    ]
  },
  "under_eye_pigment": {
    "definition": "Under-eye brown contrast against adjacent same-tone skin, excluding shadow and constitutional tone.",
    "modes": [
      "subsurface_polarized",
      "white"
    ],
    "response_class": "appearance",
    "regions": [
      "eye_left",
      "eye_right"
    ]
  },
  "under_eye_vascular": {
    "definition": "Under-eye visible reddish/blue-purple appearance distinguished from brown pigment and shadow.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "response_class": "appearance",
    "regions": [
      "eye_left",
      "eye_right"
    ]
  },
  "under_eye_hollow": {
    "definition": "Visible under-eye hollow contour/shadow with comparable neutral pose; do not infer from image darkness alone.",
    "modes": [
      "white"
    ],
    "response_class": "structural",
    "regions": [
      "eye_left",
      "eye_right"
    ]
  },
  "under_eye_puffiness": {
    "definition": "Visible under-eye swelling/bag prominence distinguished from hollowing and natural shape.",
    "modes": [
      "white"
    ],
    "response_class": "appearance",
    "regions": [
      "eye_left",
      "eye_right"
    ]
  },
  "lip_uneven_pigment": {
    "definition": "Patchy or uneven lip pigmentation relative to the individual\u2019s natural lip tone; pinkness is not a universal ideal.",
    "modes": [
      "white"
    ],
    "response_class": "appearance",
    "regions": [
      "lips"
    ]
  },
  "lip_surface_dullness": {
    "definition": "Visible dry-looking/dull lip surface, excluding constitutional lip colour and product glints.",
    "modes": [
      "white"
    ],
    "response_class": "responsive",
    "regions": [
      "lips"
    ]
  },
  "jowl_prominence": {
    "definition": "Visible lower-face tissue overhang/jowling along mandibular contour, excluding head turn, chin tilt, beard and constitutional jaw shape.",
    "modes": [
      "white"
    ],
    "response_class": "structural",
    "regions": [
      "jaw_left",
      "jaw_right"
    ]
  },
  "prejowl_disruption": {
    "definition": "Visible interruption/notching of lower-face contour associated with laxity, excluding ordinary anatomy or directional shadow.",
    "modes": [
      "white"
    ],
    "response_class": "structural",
    "regions": [
      "jaw_left",
      "jaw_right"
    ]
  },
  "lower_face_drape": {
    "definition": "Visible lax draping below cheek/jaw, distinguished from normal fat distribution, swelling and posture.",
    "modes": [
      "white"
    ],
    "response_class": "structural",
    "regions": [
      "jaw_left",
      "jaw_right"
    ]
  },
  "cheek_laxity": {
    "definition": "Visible lax cheek surface/draping at rest, excluding dehydration crepiness, thin build and anatomical folds.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "response_class": "structural",
    "regions": [
      "cheek_left",
      "cheek_right"
    ]
  },
  "lower_face_laxity": {
    "definition": "Visible lax lower-face surface contour at rest. No collagen content or mechanical recoil can be measured here.",
    "modes": [
      "white"
    ],
    "response_class": "structural",
    "regions": [
      "jaw_left",
      "jaw_right"
    ]
  }
}
FEATURES.uv_pigment_pattern={definition:'Visible patchy fluorescence attenuation pattern in woods_uv; corroborate location against white/subsurface. Exclude product, hair and shadows. This is optical pattern context, not pigment depth or melanin quantity.',modes:['woods_uv','subsurface_polarized','white'],response_class:'context_only',regions:['forehead_left','forehead_center','forehead_right','nose','cheek_left','cheek_right','lower_face']}

export const PARAMETER_WEIGHTS={
  "barrier_health_sensitivity": {
    "barrier_surface_disruption": 0.35,
    "flaking": 0.25,
    "dry_patches": 0.15,
    "erythema": 0.25
  },
  "visual_acne": {
    "comedones": 0.35,
    "papules": 0.3,
    "pustules": 0.2,
    "deep_inflammatory_lesions": 0.15
  },
  "skin_sebum": {
    "oily_film": 1
  },
  "vascularity_redness": {
    "erythema": 0.8,
    "vascular_visibility": 0.2
  },
  "skin_hydration": {
    "dehydration_lines": 0.25,
    "crepiness": 0.25,
    "plumpness_deficit": 0.25,
    "dry_patches": 0.15,
    "flaking": 0.1
  },
  "skin_luminosity_glow": {
    "diffuse_dullness": 0.45,
    "reflection_discontinuity": 0.3,
    "pigment_contrast": 0.25
  },
  "superficial_pigmentation": {
    "pigment_contrast": 1
  },
  "peri_orbital_health": {
    "under_eye_pigment": 0.3,
    "under_eye_vascular": 0.15,
    "under_eye_hollow": 0.25,
    "under_eye_puffiness": 0.2,
    "dehydration_lines": 0.1
  },
  "lip_pigmentation": {
    "lip_uneven_pigment": 0.85,
    "lip_surface_dullness": 0.15
  },
  "texture_open_pores": {
    "pore_prominence": 0.3,
    "comedones": 0.2,
    "roughness": 0.2,
    "scars_pits": 0.3
  },
  "superficial_wrinkles": {
    "fixed_lines": 0.7,
    "dehydration_lines": 0.3
  },
  "jawline_sagging": {
    "jowl_prominence": 0.45,
    "prejowl_disruption": 0.3,
    "lower_face_drape": 0.25
  },
  "skin_firmness_elasticity": {
    "cheek_laxity": 0.55,
    "lower_face_laxity": 0.45
  },
  "textural_radiance": {
    "micrograin": 0.45,
    "reflection_discontinuity": 0.3,
    "microshadows": 0.25
  }
}
export const PARAMETER_REGIONS={"barrier_health_sensitivity": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "visual_acne": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "skin_sebum": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "vascularity_redness": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "skin_hydration": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "skin_luminosity_glow": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "superficial_pigmentation": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "peri_orbital_health": ["eye_left", "eye_right"], "lip_pigmentation": ["lips"], "texture_open_pores": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"], "superficial_wrinkles": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face", "eye_left", "eye_right"], "jawline_sagging": ["jaw_left", "jaw_right"], "skin_firmness_elasticity": ["cheek_left", "cheek_right", "jaw_left", "jaw_right"], "textural_radiance": ["forehead_left", "forehead_center", "forehead_right", "nose", "cheek_left", "cheek_right", "lower_face"]}

export function observationBurden(row){
 if(!row||!Number.isFinite(row.severity)||row.severity<0||row.severity>1||!Number.isFinite(row.extent)||row.extent<0||row.extent>1)throw Error('Invalid visual severity/extent')
 if((row.severity===0)!==(row.extent===0))throw Error('Absent findings require severity=extent=0; present findings require both positive')
 // Preserve local prominence without equating a tiny spot with a whole affected region.
 return row.severity*Math.sqrt(row.extent)
}
export function aggregateFeature(rows,areaWeight){
 if(!rows.length)throw Error('No component observations')
 const burden=rows.map(([g,r])=>[observationBurden(r),areaWeight(g)])
 const areaMean=burden.reduce((s,[b,w])=>s+b*w,0)/burden.reduce((s,[,w])=>s+w,0)
 const peak=Math.max(...burden.map(([b])=>b))
 return {burden:(1-REGIONAL_PEAK_WEIGHT)*areaMean+REGIONAL_PEAK_WEIGHT*peak,area_mean:areaMean,peak}
}
export function scoreParameter(id,observations,areaWeight,onlyRegion=null){
 const weights=PARAMETER_WEIGHTS[id];if(!weights)throw Error(`Unknown score ${id}`)
 const components={}
 for(const[f,weight]of Object.entries(weights)){
  const regions=onlyRegion?[onlyRegion]:PARAMETER_REGIONS[id]
  const expected=regions.filter(g=>FEATURES[f].regions.includes(g))
  // A component that is anatomically irrelevant is excluded only for regional audit scores.
  if(!expected.length&&onlyRegion)continue
  const rows=expected.map(g=>{const r=observations[f]?.[g];if(!r)throw Error(`Missing ${f}.${g}`);return[g,r]})
  components[f]={weight,...aggregateFeature(rows,areaWeight)}
 }
 const totalWeight=Object.values(components).reduce((s,c)=>s+c.weight,0)
 if(!totalWeight)throw Error(`No anatomical score support ${id}`)
 const weightedMean=Object.values(components).reduce((s,c)=>s+c.weight*c.burden,0)/totalWeight
 const peakShare=COMPONENT_PEAK_WEIGHTS[id]??0
 const burden=(1-peakShare)*weightedMean+peakShare*Math.max(...Object.values(components).map(c=>c.burden))
 const health=100-99*burden
 return {health,burden,components,weighted_mean:weightedMean,component_peak_share:peakShare}
}
