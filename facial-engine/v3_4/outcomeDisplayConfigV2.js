export const OUTCOME_DISPLAY_CONFIG_VERSION =
  'aia_outcome_display_config_v2.0.0'

export const FEATURE_DISPLAY_CONFIG_V2 = Object.freeze({
  active_inflammatory_acne: {
    label: 'Active Breakouts',
    short_label: 'Breakouts',
    category: 'clarity',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'subsurface_polarized',
      'red',
    ],
  },
  comedonal_congestion: {
    label: 'Congestion & Blackheads',
    short_label: 'Congestion',
    category: 'clarity',
    primary_mode: 'surface_polarized',
    comparison_modes: [
      'surface_polarized',
      'white',
      'woods_uv',
    ],
  },
  oiliness: {
    label: 'Oiliness',
    short_label: 'Oil',
    category: 'balance',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'surface_polarized',
      'woods_uv',
    ],
  },
  erythema_redness: {
    label: 'Redness',
    short_label: 'Redness',
    category: 'calmness',
    primary_mode: 'red',
    comparison_modes: [
      'red',
      'white',
      'subsurface_polarized',
    ],
  },
  barrier_stress: {
    label: 'Barrier Stress',
    short_label: 'Barrier',
    category: 'calmness',
    primary_mode: 'surface_polarized',
    comparison_modes: [
      'surface_polarized',
      'red',
      'white',
    ],
  },
  visual_dehydration: {
    label: 'Visible Dehydration',
    short_label: 'Hydration',
    category: 'hydration',
    primary_mode: 'surface_polarized',
    comparison_modes: [
      'surface_polarized',
      'subsurface_polarized',
      'white',
    ],
  },
  pore_visibility: {
    label: 'Visible Pores',
    short_label: 'Pores',
    category: 'texture',
    primary_mode: 'surface_polarized',
    comparison_modes: [
      'surface_polarized',
      'white',
    ],
  },
  texture_roughness: {
    label: 'Texture & Smoothness',
    short_label: 'Texture',
    category: 'texture',
    primary_mode: 'surface_polarized',
    comparison_modes: [
      'surface_polarized',
      'white',
    ],
  },
  visible_pigmentation: {
    label: 'Visible Pigmentation',
    short_label: 'Pigmentation',
    category: 'tone',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'subsurface_polarized',
      'woods_uv',
    ],
  },
  underlying_pigment_support: {
    label: 'Underlying Pigment Pattern',
    short_label: 'Deeper Pigment',
    category: 'tone',
    primary_mode: 'subsurface_polarized',
    comparison_modes: [
      'subsurface_polarized',
      'woods_uv',
      'white',
    ],
  },
  luminosity_loss: {
    label: 'Glow & Luminosity',
    short_label: 'Glow',
    category: 'radiance',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'surface_polarized',
      'subsurface_polarized',
    ],
  },
  fine_line_visibility: {
    label: 'Visible Fine Lines',
    short_label: 'Fine Lines',
    category: 'ageing',
    primary_mode: 'surface_polarized',
    comparison_modes: [
      'surface_polarized',
      'white',
    ],
  },
  visible_laxity: {
    label: 'Visible Laxity',
    short_label: 'Laxity',
    category: 'ageing',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'surface_polarized',
    ],
  },
  firmness_appearance_loss: {
    label: 'Firmness Appearance',
    short_label: 'Firmness',
    category: 'ageing',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'surface_polarized',
    ],
  },
  peri_orbital_concern: {
    label: 'Under-Eye Appearance',
    short_label: 'Under-Eye',
    category: 'eyes',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'surface_polarized',
      'subsurface_polarized',
    ],
  },
  lip_pigmentation: {
    label: 'Lip Pigmentation',
    short_label: 'Lips',
    category: 'tone',
    primary_mode: 'white',
    comparison_modes: [
      'white',
      'woods_uv',
      'red',
    ],
  },
})

export const REPORT_PARAMETER_DISPLAY_CONFIG_V2 =
  Object.freeze({
    barrier_health_sensitivity: {
      label: 'Barrier Health',
      category: 'calmness',
    },
    visual_acne: {
      label: 'Acne & Congestion',
      category: 'clarity',
    },
    skin_sebum: {
      label: 'Oil Balance',
      category: 'balance',
    },
    vascularity_redness: {
      label: 'Redness',
      category: 'calmness',
    },
    skin_hydration: {
      label: 'Hydration',
      category: 'hydration',
    },
    skin_luminosity_glow: {
      label: 'Glow',
      category: 'radiance',
    },
    superficial_pigmentation: {
      label: 'Pigmentation',
      category: 'tone',
    },
    peri_orbital_health: {
      label: 'Under-Eye',
      category: 'eyes',
    },
    lip_pigmentation: {
      label: 'Lip Tone',
      category: 'tone',
    },
    texture_open_pores: {
      label: 'Texture & Pores',
      category: 'texture',
    },
    superficial_wrinkles: {
      label: 'Fine Lines',
      category: 'ageing',
    },
    jawline_sagging: {
      label: 'Jawline Definition',
      category: 'ageing',
    },
    skin_firmness_elasticity: {
      label: 'Firmness',
      category: 'ageing',
    },
    textural_radiance: {
      label: 'Smooth Radiance',
      category: 'radiance',
    },
  })

export const REASSESSMENT_HORIZON_DISPLAY_V2 =
  Object.freeze({
    immediate_post: {
      label: 'Immediately After',
      short_label: 'Immediate',
      order: 1,
    },
    hours_48: {
      label: 'Around 48 Hours',
      short_label: '48 Hours',
      order: 2,
    },
    day_7: {
      label: 'Around 1 Week',
      short_label: '1 Week',
      order: 3,
    },
    day_28: {
      label: 'Around 3–4 Weeks',
      short_label: '3–4 Weeks',
      order: 4,
    },
  })

export const OUTCOME_STATUS_DISPLAY_V2 =
  Object.freeze({
    above_predicted_range: {
      label: 'Above Expected Range',
      tone: 'positive',
    },
    within_predicted_range_above_expected: {
      label: 'Within Range — Better Than Expected',
      tone: 'positive',
    },
    within_predicted_range: {
      label: 'Within Expected Range',
      tone: 'positive',
    },
    within_predicted_range_below_expected: {
      label: 'Within Range — Below Expected Point',
      tone: 'neutral',
    },
    below_predicted_range: {
      label: 'Below Expected Range',
      tone: 'attention',
    },
    temporarily_obscured_by_expected_reactivity: {
      label: 'Temporarily Obscured by Treatment Reactivity',
      tone: 'neutral',
    },
    not_reliably_measurable: {
      label: 'Not Reliably Measurable',
      tone: 'neutral',
    },
    prediction_not_available: {
      label: 'No Comparable Prediction',
      tone: 'neutral',
    },
  })

export const REPORT_SECTION_ORDER_V2 = Object.freeze([
  'outcome_summary',
  'treatment_delivered',
  'score_change_cards',
  'prediction_timeline',
  'zone_outcomes',
  'before_after_images',
  'course_progress',
  'next_session_handoff',
  'important_notes',
])
