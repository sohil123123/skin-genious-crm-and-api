import {applyAppearanceDefinitions} from './appearanceContractV317.js'
// V3.15: anchored visible-impact judgments, not a pixel-area model or instrument measurement.
export const MEASUREMENT_VERSION='aia_visual_impact_measurement_v3.17.0'
export const FORMULA_VERSION='aia_visual_impact_equations_v3.17.0'
export const GROUPS={
  "forehead_left": [
    "forehead_left",
    "temple_left"
  ],
  "forehead_center": [
    "forehead_center",
    "glabella"
  ],
  "forehead_right": [
    "forehead_right",
    "temple_right"
  ],
  "nose": [
    "nose"
  ],
  "cheek_left": [
    "malar_medial_left",
    "cheek_lateral_left"
  ],
  "cheek_right": [
    "malar_medial_right",
    "cheek_lateral_right"
  ],
  "lower_face": [
    "perioral",
    "chin"
  ],
  "eye_left": [
    "peri_orbital_left"
  ],
  "eye_right": [
    "peri_orbital_right"
  ],
  "jaw_left": [
    "jawline_left"
  ],
  "jaw_right": [
    "jawline_right"
  ],
  "lips": [
    "lips"
  ]
}
export const DEVICE_PROFILE={
  "id": "aia_five_optical_modes_spec_v1",
  "white": "broadband white",
  "surface_polarized": "parallel polarized visible capture",
  "subsurface_polarized": "cross polarized visible capture",
  "red": "630–660 nm illumination; pattern context only",
  "woods_uv": "365–385 nm excitation; visible fluorescence",
  "exact_center_wavelengths_verified": false
}
export const FEATURES={
  "dehydration_lines": {
    "definition": "Fine superficial criss-cross dehydration-type lines; exclude fixed folds and expression creases.",
    "modes": [
      "surface_polarized",
      "white"
    ],
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
    ],
    "response_class": "responsive"
  },
  "crepiness": {
    "definition": "Papery or finely crinkled surface appearance, excluding fixed wrinkles.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "flaking": {
    "definition": "Visible adherent scales/flakes corroborated by surface morphology; exclude fibres, hair and product particles.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "dry_patches": {
    "definition": "Visibly dry rough patches; absence alone is insufficient evidence of optimal hydration appearance.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "plumpness_deficit": {
    "definition": "Flat or deflated appearance of the superficial skin surface; exclude constitutional face shape, fat loss, swelling and anatomical hollows.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "diffuse_dullness": {
    "definition": "Reduced broad diffuse luminosity/gray appearance relative to the person’s natural tone. Exclude oily highlights, product glints, exposure artifacts and anatomical shadows.",
    "modes": [
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "reflection_discontinuity": {
    "definition": "Interrupted or patchy broad surface reflection outside concentrated glare. Do not penalize a naturally matte but even finish.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "pigment_contrast": {
    "definition": "Brown patch/freckle contrast against nearby same-region skin, excluding constitutional darkness, moles, hair shadows, red lesions and pore dots. Cross-check white and subsurface views.",
    "modes": [
      "subsurface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "appearance"
  },
  "roughness": {
    "definition": "Visible coarse or uneven surface relief outside pore openings and scar depressions.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "micrograin": {
    "definition": "Fine surface grain/micro-roughness, excluding discrete pores, scars, hair and anatomic shadows.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "microshadows": {
    "definition": "Fine surface microshadows that follow actual micro-relief, excluding directional/anatomical shadows.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "pore_prominence": {
    "definition": "Conspicuous enlarged-looking pore openings: prominence/size/contrast rather than mere presence of normal pores. Exclude comedone plugs and scars.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "appearance"
  },
  "comedones": {
    "definition": "Visible open and closed comedones/plugged openings; exclude normal sebaceous filaments and unblocked pore dots.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "scars_pits": {
    "definition": "Atrophic pits and depressed scars: visible relief/contrast within the affected area. Exclude ordinary pores and flat pigment marks.",
    "modes": [
      "surface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "structural"
  },
  "papules": {
    "definition": "Visible raised inflammatory papules; distinguish from comedones and residual flat marks.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "pustules": {
    "definition": "Visible pustules with a discernible pustular centre. Do not diagnose from UV fluorescence alone.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "deep_inflammatory_lesions": {
    "definition": "Visible large/deep-looking inflammatory swellings; appearance only, not palpated nodule diagnosis.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "appearance"
  },
  "erythema": {
    "definition": "Diffuse or patchy visible erythema relative to surrounding skin and natural tone; exclude normal blush. Red-lit brightness alone is not erythema.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "vascular_visibility": {
    "definition": "Conspicuous superficial vascular markings corroborated in white/subsurface; red mode context only.",
    "modes": [
      "subsurface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "appearance"
  },
  "barrier_surface_disruption": {
    "definition": "Visible disrupted/scaly/fissured or irritated-looking surface; exclude pits/scars and mere large pores. No physiological permeability measurement.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "appearance"
  },
  "oily_film": {
    "definition": "Concentrated greasy film and shine distribution, especially nose/central forehead. Exclude uniform finishing-product sheen, sweat and isolated glints. No shine does not prove deficient sebum.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "responsive"
  },
  "fixed_lines": {
    "definition": "Persistent-looking creases/folds at rest, distinguished from fine dehydration lines and active expression folds. Depth is a visual estimate.",
    "modes": [
      "white",
      "surface_polarized"
    ],
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
    ],
    "response_class": "structural"
  },
  "under_eye_pigment": {
    "definition": "Under-eye brown contrast against adjacent same-tone skin, excluding shadow and constitutional tone.",
    "modes": [
      "subsurface_polarized",
      "white"
    ],
    "regions": [
      "eye_left",
      "eye_right"
    ],
    "response_class": "appearance"
  },
  "under_eye_vascular": {
    "definition": "Under-eye visible reddish/blue-purple appearance distinguished from brown pigment and shadow.",
    "modes": [
      "white",
      "subsurface_polarized"
    ],
    "regions": [
      "eye_left",
      "eye_right"
    ],
    "response_class": "appearance"
  },
  "under_eye_hollow": {
    "definition": "Visible under-eye hollow contour/shadow with comparable neutral pose; do not infer from image darkness alone.",
    "modes": [
      "white"
    ],
    "regions": [
      "eye_left",
      "eye_right"
    ],
    "response_class": "structural"
  },
  "under_eye_puffiness": {
    "definition": "Visible under-eye swelling/bag prominence distinguished from hollowing and natural shape.",
    "modes": [
      "white"
    ],
    "regions": [
      "eye_left",
      "eye_right"
    ],
    "response_class": "appearance"
  },
  "lip_uneven_pigment": {
    "definition": "Patchy or uneven lip pigmentation relative to the individual’s natural lip tone; pinkness is not a universal ideal.",
    "modes": [
      "white"
    ],
    "regions": [
      "lips"
    ],
    "response_class": "appearance"
  },
  "lip_surface_dullness": {
    "definition": "Visible dry-looking/dull lip surface, excluding constitutional lip colour and product glints.",
    "modes": [
      "white"
    ],
    "regions": [
      "lips"
    ],
    "response_class": "responsive"
  },
  "jowl_prominence": {
    "definition": "Visible lower-face tissue overhang/jowling along mandibular contour, excluding head turn, chin tilt, beard and constitutional jaw shape.",
    "modes": [
      "white"
    ],
    "regions": [
      "jaw_left",
      "jaw_right"
    ],
    "response_class": "structural"
  },
  "prejowl_disruption": {
    "definition": "Visible interruption/notching of lower-face contour associated with laxity, excluding ordinary anatomy or directional shadow.",
    "modes": [
      "white"
    ],
    "regions": [
      "jaw_left",
      "jaw_right"
    ],
    "response_class": "structural"
  },
  "lower_face_drape": {
    "definition": "Visible lax draping below cheek/jaw, distinguished from normal fat distribution, swelling and posture.",
    "modes": [
      "white"
    ],
    "regions": [
      "jaw_left",
      "jaw_right"
    ],
    "response_class": "structural"
  },
  "cheek_laxity": {
    "definition": "Visible lax cheek surface/draping at rest, excluding dehydration crepiness, thin build and anatomical folds.",
    "modes": [
      "white",
      "surface_polarized"
    ],
    "regions": [
      "cheek_left",
      "cheek_right"
    ],
    "response_class": "structural"
  },
  "lower_face_laxity": {
    "definition": "Visible lax lower-face surface contour at rest. No collagen content or mechanical recoil can be measured here.",
    "modes": [
      "white"
    ],
    "regions": [
      "jaw_left",
      "jaw_right"
    ],
    "response_class": "structural"
  },
  "uv_pigment_pattern": {
    "definition": "Visible patchy fluorescence attenuation pattern in woods_uv; corroborate location against white/subsurface. Exclude product, hair and shadows. This is optical pattern context, not pigment depth or melanin quantity.",
    "modes": [
      "woods_uv",
      "subsurface_polarized",
      "white"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "response_class": "context_only"
  }
}
export const PARAMETERS={
  "barrier_health_sensitivity": {
    "definition": "Visible calmness and surface integrity; redness, scaling, disrupted surface. Not physiological barrier permeability.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Mild visible reactivity or small dry areas on otherwise comfortable-looking skin.",
      "moderate": "Clearly apparent irritated/dry-looking surface that meaningfully affects appearance.",
      "marked": "Conspicuous surface disruption, flaking or reactive-looking redness.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "barrier_surface_disruption",
      "flaking",
      "dry_patches",
      "erythema"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "white",
      "surface_polarized",
      "subsurface_polarized"
    ]
  },
  "visual_acne": {
    "definition": "Visible comedones, papules, pustules and inflammatory swellings. Normal sebaceous filaments, flat pigment marks and scars remain distinct.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Occasional discernible active lesions or limited congestion.",
      "moderate": "Readily visible congestion and/or active lesions that noticeably affect appearance; widespread inflammation is not required.",
      "marked": "Conspicuous active lesions or congestion dominating relevant regions.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "comedones",
      "papules",
      "pustules",
      "deep_inflammatory_lesions"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "white",
      "surface_polarized",
      "subsurface_polarized"
    ]
  },
  "skin_sebum": {
    "definition": "Balance of visible surface finish; judge excess greasy film and, separately, visibly stripped/dry finish. No shine alone does not mean low sebum.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Slightly oily or visibly dry finish, otherwise balanced.",
      "moderate": "Noticeably greasy T-zone or convincing visibly stripped finish.",
      "marked": "Heavy greasy film or marked visibly stripped finish that dominates appearance.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "oily_film",
      "dry_patches",
      "crepiness"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "white",
      "surface_polarized"
    ]
  },
  "vascularity_redness": {
    "definition": "Visible red/vascular contrast in white and subsurface polarized. Red-lit intensity is not a redness measurement.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Mild visible pinkness/reactivity or superficial vascular markings.",
      "moderate": "Readily visible regional redness or vascular prominence.",
      "marked": "Conspicuous redness/vascular appearance across important visible regions.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "erythema",
      "vascular_visibility"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "white",
      "subsurface_polarized"
    ]
  },
  "skin_hydration": {
    "definition": "Hydrated appearance: surface plumpness, suppleness-looking smoothness, crepiness and dehydration microlines. Flaking is only one possible sign. Shine is not water content.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Reasonably hydrated appearance with subtle flatness, fine dehydration lines or slight crepiness; absence of flakes is not ideal hydration.",
      "moderate": "Clearly diminished fresh/plump appearance, visible microlines or crepiness, with or without flakes.",
      "marked": "Conspicuous papery/crepey or dry-looking surface with pronounced dehydration-type lines.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "dehydration_lines",
      "crepiness",
      "plumpness_deficit",
      "dry_patches",
      "flaking"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "surface_polarized",
      "white"
    ]
  },
  "skin_luminosity_glow": {
    "definition": "Overall diffuse luminosity, clarity, evenness and absence of a gray/dull cast relative to natural skin tone. Pigment unevenness contributes. Oily highlights do not count as glow.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Generally luminous appearance with modest dullness or unevenness.",
      "moderate": "Noticeably limited diffuse glow or uneven clarity; not required to look severely dry or diseased.",
      "marked": "Dull/uneven appearance strongly affects the face despite any bright greasy highlights.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "diffuse_dullness",
      "reflection_discontinuity",
      "pigment_contrast"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "white",
      "surface_polarized",
      "subsurface_polarized"
    ]
  },
  "superficial_pigmentation": {
    "definition": "Visible impact of patches, macules or mottled tone against neighboring skin. Both conspicuous local patches and distribution matter; never absolute skin darkness.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Faint pigment unevenness with modest effect on overall tone.",
      "moderate": "Readily visible patches/mottling noticeably interrupt even tone; may be localized to the forehead or cheeks.",
      "marked": "Prominent patches or mottled pigment strongly affect visible evenness; need not cover the whole face.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "pigment_contrast"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "subsurface_polarized",
      "white"
    ]
  },
  "peri_orbital_health": {
    "definition": "Visible under-eye pigment/vascular contrast, hollow appearance, puffiness and fine lines, distinguished by component.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Minor darkness, contour or line concerns.",
      "moderate": "Readily noticeable under-eye darkness, contour changes or puffiness.",
      "marked": "Conspicuous under-eye concerns dominate the peri-orbital appearance.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "under_eye_pigment",
      "under_eye_vascular",
      "under_eye_hollow",
      "under_eye_puffiness",
      "dehydration_lines"
    ],
    "regions": [
      "eye_left",
      "eye_right"
    ],
    "modes": [
      "subsurface_polarized",
      "white",
      "surface_polarized"
    ]
  },
  "lip_pigmentation": {
    "definition": "Visible uneven pigment and dull/dry surface relative to natural lip tone; naturally darker lips are not unhealthy and pink is not a universal target.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Minor uneven tone or dry-looking lip surface.",
      "moderate": "Readily apparent uneven pigmentation or dull surface.",
      "marked": "Conspicuous uneven dark patches or pronounced dull surface.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "lip_uneven_pigment",
      "lip_surface_dullness"
    ],
    "regions": [
      "lips"
    ],
    "modes": [
      "white"
    ]
  },
  "texture_open_pores": {
    "definition": "Visible surface quality: pore prominence, congestion, roughness and pits/scars. No scars does not cancel prominent pores; normal pores are not defects.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Mild visible openings/roughness with an otherwise smooth impression.",
      "moderate": "Readily noticeable pores, congestion, roughness or pits in key regions; concern can be important without whole-face coverage.",
      "marked": "Conspicuous pores, roughness or scars substantially disrupt surface appearance.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "pore_prominence",
      "comedones",
      "roughness",
      "scars_pits"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "surface_polarized",
      "white"
    ]
  },
  "superficial_wrinkles": {
    "definition": "Fine lines and fixed resting lines, distinguished from dehydration lines and active expression.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Faint/subtle resting lines or limited superficial lines.",
      "moderate": "Clearly visible resting/fine lines with meaningful appearance impact.",
      "marked": "Conspicuous or deeper-looking lines strongly affect the surface impression.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "fixed_lines",
      "dehydration_lines"
    ],
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
    ],
    "modes": [
      "white",
      "surface_polarized"
    ]
  },
  "jawline_sagging": {
    "definition": "Visible lower-face contour/laxity, excluding constitutional jaw shape, posture and beard edges. Estimate only from identifiable skin/contour evidence.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Small visible contour laxity.",
      "moderate": "Readily apparent jowl/pre-jowl contour disruption.",
      "marked": "Conspicuous tissue overhang or lax contour.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "jowl_prominence",
      "prejowl_disruption",
      "lower_face_drape"
    ],
    "regions": [
      "jaw_left",
      "jaw_right"
    ],
    "modes": [
      "white"
    ]
  },
  "skin_firmness_elasticity": {
    "definition": "Visible firmness/laxity of cheeks and lower face; not measured collagen, water content or mechanical recoil.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Minor visible laxity/draping.",
      "moderate": "Readily visible laxity of cheek/lower-face surface.",
      "marked": "Conspicuous draping/laxity in visible areas.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "cheek_laxity",
      "lower_face_laxity"
    ],
    "regions": [
      "cheek_left",
      "cheek_right",
      "jaw_left",
      "jaw_right"
    ],
    "modes": [
      "white",
      "surface_polarized"
    ]
  },
  "textural_radiance": {
    "definition": "Fine surface smoothness and continuity of diffuse reflection, excluding oily glints, discrete pore holes and major scars.",
    "anchors": {
      "excellent": "Excellent realistic appearance for THIS parameter, supported by positive visible evidence; normal anatomy and natural tone allowed.",
      "mild": "Slight grain or disrupted fine reflection.",
      "moderate": "Readily apparent grain/microtexture or discontinuous reflection that limits the smooth luminous appearance.",
      "marked": "Conspicuous grain/rough fine reflection strongly limits surface radiance.",
      "severe": "Severe visible impact of the defined parameter; more pronounced than the marked anchor.",
      "extreme": "Extreme visible impact of the defined parameter."
    },
    "features": [
      "micrograin",
      "reflection_discontinuity",
      "microshadows"
    ],
    "regions": [
      "forehead_left",
      "forehead_center",
      "forehead_right",
      "nose",
      "cheek_left",
      "cheek_right",
      "lower_face"
    ],
    "modes": [
      "surface_polarized",
      "white"
    ]
  }
}
applyAppearanceDefinitions(FEATURES,PARAMETERS)

// Fixed scale. Vision estimates continuous anchor position; code maps and rounds it.
// This mapping expresses clinical appearance semantics, not a fitted population percentile.
export const IMPACT_KNOTS=Object.freeze([[0,100],[1,80],[2,60],[3,40],[4,20],[5,1]])
export function impactToHealth(level){
 if(typeof level!=='number'||!Number.isFinite(level)||level<0||level>5)throw Error('Impact must be supported and between 0 and 5')
 if(level===5)return 1
 const i=Math.floor(level),[x0,y0]=IMPACT_KNOTS[i],[x1,y1]=IMPACT_KNOTS[i+1]
 return y0+(level-x0)*(y1-y0)/(x1-x0)
}
export function healthToBurden(health){return (100-health)/99}
