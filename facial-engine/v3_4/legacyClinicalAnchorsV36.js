export const LEGACY_CLINICAL_ANCHORS_V36 = {
  "barrier_health_sensitivity": {
    "source_variable": "combined_barrier_sensitivity",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "BSI_continuous",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": {
      "1": {
        "range": "<0.20",
        "label": "Strong Barrier / Low Sensitivity",
        "anchor": "Smooth texture, well hydrated, minimal redness or reactivity."
      },
      "2": {
        "range": "0.20-0.35",
        "label": "Mildly Compromised",
        "anchor": "Early dryness or mild sensitivity but stable barrier."
      },
      "3": {
        "range": "0.35-0.55",
        "label": "Moderately Compromised",
        "anchor": "Visible dryness, uneven texture, mild-to-moderate redness."
      },
      "4": {
        "range": "0.55-0.75",
        "label": "Severely Compromised",
        "anchor": "Marked dryness, flaking, barrier disruption, persistent sensitivity."
      },
      "5": {
        "range": ">0.75",
        "label": "Highly Sensitive / Barrier Breakdown",
        "anchor": "Severe redness, scaling, burning-prone skin; urgent barrier repair needed."
      }
    },
    "clinical_definitions": null,
    "metric_bands": {
      "surface_texture_uniformity": {
        "description": "Smoothness of skin surface; reduced when barrier is compromised. Primarily driven by feature packet barrier_uniformity + flaking/surface texture evidence.",
        "range": "0-1",
        "bands": {
          "excellent": ">0.85",
          "mild_disruption": "0.70-0.85",
          "moderate_disruption": "0.55-0.70",
          "severe_disruption": "<0.55"
        }
      },
      "hydration_signal_index": {
        "description": "Hydration proxy from combined_barrier_sensitivity.hydration_signal_index in the feature packet; supported by subsurface_polarized + white.",
        "range": "0-1",
        "bands": {
          "hydrated": ">0.65",
          "slightly_low": "0.45-0.65",
          "low": "0.30-0.45",
          "very_low": "<0.30"
        }
      },
      "erythema_intensity_index": {
        "description": "Redness intensity relative to neutral baseline; indicates sensitivity/reactivity.",
        "range": "0-1",
        "bands": {
          "none": "<0.25",
          "mild": "0.25-0.45",
          "moderate": "0.45-0.65",
          "severe": ">0.65"
        }
      },
      "barrier_uniformity_index": {
        "description": "Surface + structural barrier stability. Low values = impaired barrier.",
        "range": "0-1",
        "bands": {
          "intact": ">0.80",
          "mild_disruption": "0.60-0.80",
          "disrupted": "<0.60"
        }
      }
    }
  },
  "visual_acne": {
    "source_variable": "visual_acne_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "ASI_continuous",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": {
      "1": {
        "range": "<0.20",
        "label": "Minimal Acne",
        "anchor": "Few comedones, almost no inflammation."
      },
      "2": {
        "range": "0.20-0.35",
        "label": "Mild Acne",
        "anchor": "Comedonal or occasional papules."
      },
      "3": {
        "range": "0.35-0.55",
        "label": "Moderate Acne",
        "anchor": "Papules/pustules, some clusters."
      },
      "4": {
        "range": "0.55-0.75",
        "label": "Marked Acne",
        "anchor": "Dense inflammatory lesions."
      },
      "5": {
        "range": ">0.75",
        "label": "Severe/Nodulocystic Acne",
        "anchor": "Nodules, widespread inflammation."
      }
    },
    "clinical_definitions": null,
    "metric_bands": {
      "inflammatory_ratio": {
        "description": "Inflammatory lesions / total lesions.",
        "range": "0-1",
        "bands": {
          "low": "<0.25",
          "moderate": "0.25-0.50",
          "high": ">0.50"
        }
      },
      "comedone_density_index": {
        "description": "Closed + open comedones normalized 0-1 across the face.",
        "bands": {
          "minimal": "<0.15",
          "mild": "0.15-0.35",
          "moderate": "0.35-0.60",
          "dense": ">0.60"
        }
      }
    }
  },
  "skin_sebum": {
    "source_variable": "sebum_content_scoring",
    "polarity_metadata": {
      "score_semantics": "state_spectrum",
      "score_polarity": "distance_to_target",
      "ideal_score_direction": "move_toward_target",
      "continuous_index_name": "SSI_continuous",
      "continuous_index_polarity": "depends_on_target",
      "comparison_mode": "target_distance",
      "target_interpretation_rule": "Middle-to-ideal range is best; target is movement toward the clinically appropriate balance zone rather than uniformly lower or higher values."
    },
    "score_bins": {
      "1": {
        "range": "<0.20",
        "label": "Very Low Sebum / Dry"
      },
      "2": {
        "range": "0.20-0.38",
        "label": "Low-Normal Sebum"
      },
      "3": {
        "range": "0.38-0.58",
        "label": "Moderate Sebum"
      },
      "4": {
        "range": "0.58-0.78",
        "label": "High Sebum / Oily"
      },
      "5": {
        "range": ">0.78",
        "label": "Very Oily / Seborrheic"
      }
    },
    "clinical_definitions": null,
    "metric_bands": {}
  },
  "vascularity_redness": {
    "source_variable": "vascularity_redness_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "BIBI_index",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": null,
    "clinical_definitions": {
      "1": {
        "label": "Minimal Redness",
        "clinical_features": [
          "Almost no visible redness in white light",
          "No meaningful vascular structure prominence",
          "No meaningful diffuse erythema",
          "Minimal subclinical hotspots"
        ],
        "patient_perception": "Skin appears even-toned with no visible redness.",
        "treatment_responsiveness": "Small but noticeable improvements possible."
      },
      "2": {
        "label": "Mild Redness / Reactive",
        "clinical_features": [
          "Faint cheek or nose redness visible only on close view",
          "Very fine vascular patterns may appear",
          "Slight background erythema",
          "Scattered microinflammatory dots"
        ],
        "patient_perception": "Occasional redness, often called sensitive skin.",
        "treatment_responsiveness": "Improves well with facials, LED, calming agents."
      },
      "3": {
        "label": "Moderate Redness",
        "clinical_features": [
          "Easily visible redness in cheeks/nose in white light",
          "Clear but limited vascular structures",
          "Noticeable diffuse erythema",
          "Multiple subclinical hotspots"
        ],
        "patient_perception": "Redness is a visible cosmetic concern.",
        "treatment_responsiveness": "Strongly responsive to clinical facials, yellow LED, peels."
      },
      "4": {
        "label": "High Redness / Vascular Prominence",
        "clinical_features": [
          "Obvious redness from conversational distance",
          "Dense or branching vessel-like structures",
          "Widespread erythema",
          "Strong inflammatory clusters"
        ],
        "patient_perception": "Skin appears constantly red; makeup needed to cover.",
        "treatment_responsiveness": "Requires stronger interventions like vascular lasers or multiple sessions."
      },
      "5": {
        "label": "Severe Redness / Rosacea-like",
        "clinical_features": [
          "Intense diffuse redness covering large areas",
          "Prominent vascularity",
          "Strong diffuse erythema",
          "Multiple active inflammation hotspots",
          "High acne-linked inflammatory burden if present"
        ],
        "patient_perception": "Heavy facial redness impacting confidence.",
        "treatment_responsiveness": "Significant improvement possible but requires structured plan."
      }
    },
    "metric_bands": {}
  },
  "skin_hydration": {
    "source_variable": "skin_hydration_scoring",
    "polarity_metadata": {
      "score_semantics": "health",
      "score_polarity": "higher_is_better",
      "ideal_score_direction": "increase",
      "continuous_index_name": "HSI_continuous",
      "continuous_index_polarity": "higher_is_better",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Higher score is better; target is progressive upward movement toward 5 while preserving clinical realism and avoiding overstating short-term gains."
    },
    "score_bins": {
      "1": {
        "range": "<0.30",
        "label": "Severely Dehydrated",
        "anchor": "Dull, flaky, tight appearance; marked micro-lines."
      },
      "2": {
        "range": "0.30-0.45",
        "label": "Moderately Dehydrated",
        "anchor": "Uneven reflectance, scattered dry patches, visible fine lines."
      },
      "3": {
        "range": "0.45-0.60",
        "label": "Mild Dehydration",
        "anchor": "Healthy but lacks plumpness; minor dullness."
      },
      "4": {
        "range": "0.60-0.75",
        "label": "Well Hydrated",
        "anchor": "Smooth surface, good glow, soft micro-lines."
      },
      "5": {
        "range": ">0.75",
        "label": "Optimally Hydrated",
        "anchor": "Plump, luminous, radiant appearance with high diffusion."
      }
    },
    "clinical_definitions": null,
    "metric_bands": {
      "surface_reflectance_index": {
        "description": "How well hydrated skin reflects light. Hydrated skin shows smooth, even reflectance.",
        "range": "0-1",
        "bands": {
          "very_low": "<0.30",
          "low": "0.30-0.45",
          "moderate": "0.45-0.60",
          "good": "0.60-0.75",
          "excellent": ">0.75"
        }
      },
      "microline_density_index": {
        "description": "Fine-line density from surface_polarized/white evidence. Dehydration exaggerates micro-lines.",
        "range": "0-1",
        "bands": {
          "minimal": "<0.15",
          "mild": "0.15-0.30",
          "moderate": "0.30-0.45",
          "marked": "0.45-0.60",
          "severe": ">0.60"
        }
      },
      "subsurface_diffusion_index": {
        "description": "Light diffusion proxy for plump hydrated dermis using subsurface_polarized + white support.",
        "range": "0-1",
        "bands": {
          "poor": "<0.40",
          "fair": "0.40-0.55",
          "moderate": "0.55-0.70",
          "good": "0.70-0.80",
          "high": ">0.80"
        }
      },
      "dry_patch_fluorescence_index": {
        "description": "woods_uv detection of dry keratin, scaling, micropatch dehydration.",
        "range": "0-1",
        "bands": {
          "none": "<0.10",
          "few": "0.10-0.25",
          "scattered": "0.25-0.40",
          "multiple": "0.40-0.60",
          "dense": ">0.60"
        }
      },
      "sebum_balance_ratio": {
        "description": "Differentiates true dehydration (low oil support) vs oil-dehydration mix using feature_packet hydration + sebum fields.",
        "formula": "sebum_presence / optimal_sebum_reference",
        "range": "0-1",
        "bands": {
          "very_low": "<0.25",
          "low": "0.25-0.40",
          "balanced": "0.40-0.65",
          "slightly_high": "0.65-0.80",
          "high": ">0.80"
        }
      }
    }
  },
  "skin_luminosity_glow": {
    "source_variable": "skin_luminosity_index",
    "polarity_metadata": {
      "score_semantics": "health",
      "score_polarity": "higher_is_better",
      "ideal_score_direction": "increase",
      "continuous_index_name": "GLI_continuous",
      "continuous_index_polarity": "higher_is_better",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Higher score is better; target is progressive upward movement toward 5 while preserving clinical realism and avoiding overstating short-term gains."
    },
    "score_bins": {
      "1": {
        "range": "<0.25",
        "label": "Very Dull",
        "anchor": "Low brightness, marked dryness, uneven reflection."
      },
      "2": {
        "range": "0.25-0.40",
        "label": "Mild Glow",
        "anchor": "Some brightness, but dullness/patchiness persists."
      },
      "3": {
        "range": "0.40-0.60",
        "label": "Healthy Glow",
        "anchor": "Good brightness and uniformity with mild shadow softness."
      },
      "4": {
        "range": "0.60-0.78",
        "label": "Radiant",
        "anchor": "Bright, even surface glow and soft facial contours."
      },
      "5": {
        "range": ">0.78",
        "label": "Luminous / High Radiance",
        "anchor": "Strong surface + subsurface glow, minimal dullness."
      }
    },
    "clinical_definitions": null,
    "metric_bands": {
      "surface_reflectance_uniformity": {
        "description": "Evenness of specular reflection under visible-light conditions.",
        "formula": "1 - (stddev_reflectance / mean_reflectance)",
        "range": "0-1",
        "bands": {
          "dull": "<0.55",
          "uneven": "0.55-0.70",
          "healthy": "0.70-0.82",
          "radiant": ">0.82"
        }
      },
      "color_luminance_index": {
        "description": "Brightness based on visible luminance normalized to reference white.",
        "formula": "mean_luminance / luminance_reference",
        "range": "0-1",
        "bands": {
          "dull": "<0.45",
          "soft": "0.45-0.60",
          "bright": "0.60-0.75",
          "radiant": ">0.75"
        }
      },
      "subsurface_diffusion_index": {
        "description": "Light scatter depth measured using subsurface_polarized support.",
        "formula": "diffuse_spread / total_intensity",
        "range": "0-1",
        "bands": {
          "low": "<0.45",
          "moderate": "0.45-0.65",
          "high": "0.65-0.80",
          "very_high": ">0.80"
        }
      },
      "shadow_softness_index": {
        "description": "Softness of contour transitions; lower harsh shadows = higher glow.",
        "formula": "1 - (edge_contrast / mean_reflectance)",
        "range": "0-1",
        "bands": {
          "harsh": "<0.35",
          "moderate": "0.35-0.55",
          "soft": "0.55-0.75",
          "silky": ">0.75"
        }
      },
      "sebum_gloss_index": {
        "description": "Healthy gloss vs patchy oiliness using white + surface_polarized + feature_packet sebum bins.",
        "formula": "even_sebum_distribution_score",
        "range": "0-1",
        "bands": {
          "dry": "<0.25",
          "balanced": "0.25-0.55",
          "glossy": ">0.55"
        }
      },
      "dryness_dullness_index": {
        "description": "Dryness-induced dullness from woods_uv dry signal + surface microtexture.",
        "formula": "dry_signal / (total_reflectance + 1)",
        "range": "0-1",
        "bands": {
          "none": "<0.25",
          "mild": "0.25-0.45",
          "moderate": "0.45-0.65",
          "marked": ">0.65"
        }
      }
    }
  },
  "superficial_pigmentation": {
    "source_variable": "superficial_pigmentation_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "PPL_continuous",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": {
      "1": {
        "range": "<0.18",
        "anchor": "Essentially clear or only a few faint spots."
      },
      "2": {
        "range": "0.18-0.36",
        "anchor": "Mild pigmentation; some spots or dullness in certain areas."
      },
      "3": {
        "range": "0.36-0.58",
        "anchor": "Moderate pigmentation; uneven tone is clearly visible in daily life."
      },
      "4": {
        "range": "0.58-0.78",
        "anchor": "Marked pigmentation; multiple obvious patches or dense clusters."
      },
      "5": {
        "range": ">0.78",
        "anchor": "Severe, widespread pigmentation with dense signal across most regions."
      }
    },
    "clinical_definitions": null,
    "metric_bands": {
      "coverage_area_percent": {
        "description": "Percentage of analyzed facial area with pigment intensity above threshold in white + woods_uv support combined.",
        "bands": {
          "very_low": "<5%",
          "low": "5-15%",
          "moderate": "15-35%",
          "high": "35-60%",
          "very_high": ">60%"
        }
      },
      "mean_intensity_index": {
        "description": "Average melanin-related visible intensity in pigmented pixels, normalized 0-1.",
        "bands": {
          "very_light": "<0.20",
          "light": "0.20-0.32",
          "mild": "0.32-0.48",
          "moderate": "0.48-0.62",
          "marked": "0.62-0.78",
          "severe": ">0.78"
        }
      },
      "contrast_to_surrounding_skin_index": {
        "description": "How strongly pigmented regions stand out against adjacent non-pigmented skin in visible appearance.",
        "bands": {
          "very_low": "<0.15",
          "low": "0.15-0.28",
          "moderate": "0.28-0.45",
          "high": "0.45-0.68",
          "very_high": ">0.68"
        }
      },
      "uniformity_index": {
        "description": "How even the pigmentation is across the face. 1 = perfectly even, 0 = highly mottled.",
        "bands": {
          "even": ">=0.80",
          "mottled": "0.60-0.80",
          "uneven": "<0.60"
        }
      }
    }
  },
  "peri_orbital_health": {
    "source_variable": "peri_orbital_skin_health_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": null,
      "continuous_index_polarity": "not_applicable",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": null,
    "clinical_definitions": {
      "1": {
        "label": "Excellent Peri-orbital Health",
        "clinical_features": [
          "No obvious pigmentation",
          "Minimal vascular tint",
          "No hollowness or puffiness",
          "Fine lines barely visible"
        ]
      },
      "2": {
        "label": "Mild Concerns",
        "clinical_features": [
          "Mild discoloration",
          "Faint vascular hue",
          "Slight trough demarcation",
          "Occasional fine lines",
          "No significant puffiness"
        ]
      },
      "3": {
        "label": "Moderate Concerns",
        "clinical_features": [
          "Visible pigmentation",
          "Notable vascular tint",
          "Moderate tear trough shadowing",
          "Fine lines present at rest",
          "Mild puffiness"
        ]
      },
      "4": {
        "label": "Significant Concerns",
        "clinical_features": [
          "Marked pigmentation",
          "Prominent vascular visibility",
          "Deep structural hollowness",
          "Multiple fine lines",
          "Moderate puffiness"
        ]
      },
      "5": {
        "label": "Severe Peri-orbital Aging / Darkness",
        "clinical_features": [
          "Dense pigmentation with sharp borders",
          "Strong vascular pooling",
          "Severe hollowness with long shadows",
          "Prominent lines/wrinkling",
          "Pronounced puffiness or fat prolapse"
        ]
      }
    },
    "metric_bands": {}
  },
  "lip_pigmentation": {
    "source_variable": "lip_pigmentation_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": null,
      "continuous_index_polarity": "not_applicable",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": null,
    "clinical_definitions": null,
    "metric_bands": {
      "intrinsic_melanin_index": {
        "description": "white + woods_uv + subsurface support showing true lip melanin less confounded by cosmetic masking.",
        "range": "0-1",
        "bands": {
          "minimal": "<0.15",
          "mild": "0.15-0.30",
          "moderate": "0.30-0.50",
          "marked": "0.50-0.70",
          "severe": ">0.70"
        }
      },
      "surface_darkness_index": {
        "description": "Visible tone drop after cosmetic-mask sanity filtering.",
        "range": "0-1",
        "bands": {
          "none": "<0.10",
          "faint": "0.10-0.25",
          "visible": "0.25-0.45",
          "obvious": "0.45-0.65",
          "intense": ">0.65"
        }
      },
      "vascular_congestion_index": {
        "description": "Red/vascular under-tone caused by vascular congestion.",
        "range": "0-1",
        "bands": {
          "none": "<0.10",
          "mild": "0.10-0.25",
          "moderate": "0.25-0.45",
          "marked": "0.45-0.65",
          "severe": ">0.65"
        }
      }
    }
  },
  "texture_open_pores": {
    "source_variable": "texture_pores_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "PTI_continuous",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": {
      "1": {
        "range": "<0.18",
        "label": "Minimal Texture/Pores"
      },
      "2": {
        "range": "0.18-0.34",
        "label": "Mild Texture/Pores"
      },
      "3": {
        "range": "0.34-0.56",
        "label": "Moderate Texture/Pores"
      },
      "4": {
        "range": "0.56-0.76",
        "label": "Marked Texture/Pores"
      },
      "5": {
        "range": ">0.76",
        "label": "Severe Texture/Pores"
      }
    },
    "clinical_definitions": null,
    "metric_bands": {}
  },
  "superficial_wrinkles": {
    "source_variable": "superficial_wrinkles_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "WBI_continuous",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": {
      "1": {
        "range": "<0.18",
        "label": "Minimal Wrinkles"
      },
      "2": {
        "range": "0.18-0.34",
        "label": "Mild Wrinkles"
      },
      "3": {
        "range": "0.34-0.54",
        "label": "Moderate Wrinkles"
      },
      "4": {
        "range": "0.54-0.75",
        "label": "Marked Wrinkles"
      },
      "5": {
        "range": ">0.75",
        "label": "Severe Wrinkles"
      }
    },
    "clinical_definitions": null,
    "metric_bands": {}
  },
  "jawline_sagging": {
    "source_variable": "jawline_sagging_scoring",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": null,
      "continuous_index_polarity": "not_applicable",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": null,
    "clinical_definitions": null,
    "metric_bands": {
      "mandibular_line_deflection_angle": {
        "description": "Deviation (in degrees) of the lower jawline from an ideal straight mandibular contour.",
        "range": "0-12 degrees",
        "bands": {
          "excellent": "<2",
          "mild": "2-4",
          "moderate": "4-7",
          "marked": "7-10",
          "severe": ">10"
        }
      }
    }
  },
  "skin_firmness_elasticity": {
    "source_variable": "skin_firmness_elasticity_index",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "continuous_firmness_index",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": null,
    "clinical_definitions": null,
    "metric_bands": {
      "micro_laxity_pattern_index": {
        "description": "Subtle sag/crepe patterns detected via surface_polarized microtexture mapping and feature packet firmness fields.",
        "range": "0-1",
        "bands": {
          "tight": "<0.20",
          "mild_laxity": "0.20-0.35",
          "moderate": "0.35-0.55",
          "marked": "0.55-0.75",
          "severe": ">0.75"
        }
      },
      "collagen_reflectance_uniformity": {
        "description": "Uniformity of collagen-linked reflectance under white and woods_uv support.",
        "range": "0-1",
        "bands": {
          "excellent": ">0.80",
          "good": "0.65-0.80",
          "fair": "0.50-0.65",
          "poor": "<0.50"
        }
      },
      "elastic_recoil_proxy_index": {
        "description": "Edge-sharpness + contour-response ratio from surface_polarized/white-supported feature packet fields.",
        "range": "0-1",
        "bands": {
          "strong": ">0.75",
          "mild_drop": "0.55-0.75",
          "moderate_drop": "0.35-0.55",
          "weak": "<0.35"
        }
      }
    }
  },
  "textural_radiance": {
    "source_variable": "textural_radiance_index",
    "polarity_metadata": {
      "score_semantics": "severity",
      "score_polarity": "higher_is_worse",
      "ideal_score_direction": "decrease",
      "continuous_index_name": "continuous_TRI",
      "continuous_index_polarity": "higher_is_worse",
      "comparison_mode": "direct_numeric",
      "target_interpretation_rule": "Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement."
    },
    "score_bins": null,
    "clinical_definitions": null,
    "metric_bands": {
      "micro_clarity_index": {
        "description": "How clean/clear the skin surface appears (absence of haze, film, residue).",
        "source_modes": [
          "white",
          "surface_polarized"
        ],
        "range": "0-1",
        "bands": {
          "crisp": ">0.80",
          "good": "0.65-0.80",
          "fair": "0.45-0.65",
          "hazy": "<0.45"
        }
      },
      "surface_smooth_scatter_index": {
        "description": "Light scatter uniformity due to smoothness (inverse of micro-roughness).",
        "source_modes": [
          "surface_polarized",
          "white"
        ],
        "range": "0-1",
        "bands": {
          "excellent": ">0.80",
          "good": "0.65-0.80",
          "moderate": "0.45-0.65",
          "coarse": "<0.45"
        }
      },
      "keratin_shadow_index": {
        "description": "Subclinical keratin/oil film detected in woods_uv affecting radiance.",
        "range": "0-1",
        "bands": {
          "minimal": "<0.20",
          "mild": "0.20-0.40",
          "moderate": "0.40-0.60",
          "marked": ">0.60"
        }
      }
    }
  }
}
