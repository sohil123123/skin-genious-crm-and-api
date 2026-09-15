// Exact numerical midpoint references from the supplied extraction prompt.
// Reference anchors only: V3.7 permits supported positions between them.
export const LEGACY_VISUAL_ANCHORS_V37 = {
  "redness.diffuse_redness_bin": {
    "none": 0.05,
    "mild": 0.2,
    "moderate": 0.4,
    "high": 0.65,
    "severe": 0.85
  },
  "redness.vascular_pattern_bin": {
    "none": 0.05,
    "mild": 0.2,
    "moderate": 0.4,
    "high": 0.65,
    "severe": 0.85
  },
  "pores_texture.pore_visibility_bin": {
    "none": 0.1,
    "mild": 0.3,
    "moderate": 0.55,
    "marked": 0.8
  },
  "pores_texture.texture_roughness_bin": {
    "none": 0.1,
    "mild": 0.3,
    "moderate": 0.55,
    "marked": 0.8
  },
  "pores_texture.blackhead_congestion_bin": {
    "none": 0.1,
    "low": 0.3,
    "moderate": 0.55,
    "high": 0.8
  },
  "acne.porphyrin_load_bin": {
    "none": 0.05,
    "low": 0.2,
    "moderate": 0.45,
    "high": 0.7,
    "very_high": 0.9
  },
  "acne.active_lesion_visibility_bin": {
    "none": 0.05,
    "faint": 0.2,
    "mild": 0.4,
    "clear": 0.65,
    "striking": 0.85
  },
  "acne.post_inflammatory_mark_burden_bin": {
    "none": 0.05,
    "low": 0.2,
    "moderate": 0.45,
    "high": 0.7,
    "very_high": 0.9
  },
  "sebum_oiliness.t_zone_oil_bin": {
    "none": 0.05,
    "mild": 0.25,
    "moderate": 0.55,
    "strong": 0.8
  },
  "sebum_oiliness.cheek_oil_bin": {
    "none": 0.05,
    "mild": 0.25,
    "moderate": 0.55,
    "strong": 0.8
  },
  "pigmentation.coverage_band": {
    "very_low": 0.1,
    "low": 0.25,
    "moderate": 0.5,
    "high": 0.75,
    "very_high": 0.9
  },
  "pigmentation.intensity_band": {
    "very_light": 0.1,
    "light": 0.2,
    "mild": 0.32,
    "moderate": 0.48,
    "marked": 0.66,
    "severe": 0.85
  },
  "pigmentation.contrast_band": {
    "very_low": 0.1,
    "low": 0.22,
    "moderate": 0.4,
    "high": 0.62,
    "very_high": 0.85
  },
  "pigmentation.coverage_change_sensitivity_band": {
    "minimal": 0.1,
    "mild": 0.25,
    "moderate": 0.5,
    "high": 0.75
  },
  "pigmentation.underlying_pigment_band": {
    "minimal": 0.1,
    "mild": 0.25,
    "moderate": 0.5,
    "marked": 0.75
  },
  "wrinkles.wrinkle_line_count_bin": {
    "0-10": 0.15,
    "11-30": 0.35,
    "31-60": 0.6,
    "60+": 0.85
  },
  "hydration.surface_reflectance_bin": {
    "very_low": 0.1,
    "low": 0.25,
    "moderate": 0.5,
    "high": 0.75,
    "very_high": 0.9
  },
  "hydration.subsurface_diffusion_bin": {
    "very_low": 0.1,
    "low": 0.25,
    "moderate": 0.5,
    "high": 0.75,
    "very_high": 0.9
  },
  "hydration.microline_density_bin": {
    "none": 0.1,
    "mild": 0.3,
    "moderate": 0.55,
    "marked": 0.8
  },
  "hydration.dry_patch_fluorescence_bin": {
    "none": 0.05,
    "low": 0.25,
    "moderate": 0.55,
    "high": 0.8
  },
  "combined_barrier_sensitivity.erythema_intensity_bin": {
    "none": 0.05,
    "mild": 0.2,
    "moderate": 0.4,
    "high": 0.65,
    "severe": 0.85
  },
  "combined_barrier_sensitivity.erythema_coverage_bin": {
    "none": 0.05,
    "low": 0.25,
    "moderate": 0.55,
    "high": 0.8
  },
  "combined_barrier_sensitivity.flaking_texture_bin": {
    "none": 0.1,
    "mild": 0.3,
    "moderate": 0.55,
    "marked": 0.8
  },
  "combined_barrier_sensitivity.barrier_uniformity_bin": {
    "poor": 0.8,
    "mixed": 0.55,
    "good": 0.3,
    "excellent": 0.15
  },
  "combined_barrier_sensitivity.hydration_signal_bin": {
    "very_low": 0.85,
    "low": 0.65,
    "moderate": 0.45,
    "high": 0.25,
    "very_high": 0.1
  },
  "sebum_oiliness.shine_intensity_bin": {
    "none": 0.05,
    "mild": 0.25,
    "moderate": 0.55,
    "strong": 0.8
  },
  "sebum_oiliness.shine_coverage_bin": {
    "none": 0.05,
    "low": 0.25,
    "moderate": 0.55,
    "high": 0.8
  }
}
