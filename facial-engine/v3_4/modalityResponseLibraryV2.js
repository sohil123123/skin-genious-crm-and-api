import { CORE_FEATURE_IDS } from './skinStateV2.schema.js'
import { resolveModalityIdV2 } from './clinicalConstraintsV2.js'
import {
  CLINIC_STEP_DURATION_RULES_VERSION,
  getClinicDurationRangeV3_4,
} from './clinicStepDurationRulesV3_4.js'

export const MODALITY_RESPONSE_LIBRARY_VERSION = 'aia_modality_response_library_v3.4.0'

const RAW_LIBRARY = {
  "version": "aia_modality_response_library_v3.4.0",
  "feature_ids": [
    "active_inflammatory_acne",
    "comedonal_congestion",
    "oiliness",
    "erythema_redness",
    "barrier_stress",
    "visual_dehydration",
    "pore_visibility",
    "texture_roughness",
    "visible_pigmentation",
    "underlying_pigment_support",
    "luminosity_loss",
    "fine_line_visibility",
    "visible_laxity",
    "firmness_appearance_loss",
    "peri_orbital_concern",
    "lip_pigmentation"
  ],
  "strength_scale": {
    "0": "no expected corrective benefit / not a treatment target",
    "1": "supportive or minimal",
    "2": "modest",
    "3": "moderate",
    "4": "strong",
    "5": "hero-level fit when eligible and correctly indicated"
  },
  "evidence_tiers": {
    "A": "randomized/comparative human clinical evidence or multiple strong clinical studies",
    "B": "controlled/prospective human clinical evidence",
    "C": "limited clinical evidence, observational evidence, or indirect formulation evidence",
    "D": "clinic protocol/expert engineering prior; requires calibration and conservative claims"
  },
  "global_claim_rules": [
    "Immediate strength describes visible same-day or short-term appearance change, not biological remodeling.",
    "Course strength describes expected benefit after appropriately spaced sessions where relevant.",
    "Strength values are optimizer priors, not guaranteed point reductions.",
    "Exact device settings, passes, peel contact times and endpoints must come from approved protocol files.",
    "A zero does not mean the modality is harmful; it means no reliable corrective benefit should be credited for that feature.",
    "Transient erythema, dryness, edema or residue must be separated from true treatment outcome."
  ],
  "evidence_registry": {
    "HYDRADERMABRASION": {
      "pmid": "19146604",
      "citation": "Hydradermabrasion nonablative facial rejuvenation study"
    },
    "CARBON_ACNE_RCT": {
      "pmid": "22033354",
      "citation": "Carbon-assisted dual-mode 1064-nm Nd:YAG acne randomized study"
    },
    "CARBON_PORES": {
      "pmid": "34255869",
      "citation": "Carbon-assisted Q-switched Nd:YAG for enlarged pores split-face study"
    },
    "CARBON_SKIN_COLOR": {
      "pmid": "38189060",
      "citation": "Carbon peel laser for acne in skin of color prospective study"
    },
    "QSWITCH_PIH_INDIA": {
      "pmid": "26865787",
      "citation": "Q-switched 1064-nm Nd:YAG for post-acne PIH in Indian cases"
    },
    "QSWITCH_532": {
      "pmid": "32592273",
      "citation": "Q-switched 532-nm Nd:YAG for facial ephelides randomized split-face study"
    },
    "PEEL_GLY_SAL_MAN": {
      "pmid": "19076192",
      "citation": "Glycolic versus salicylic-mandelic peel in Indian acne and PIH"
    },
    "PEEL_SAL30": {
      "pmid": "32447767",
      "citation": "Salicylic acid 30% peel in skin of color randomized split-face study"
    },
    "PEEL_2026_RCT": {
      "pmid": "42264092",
      "citation": "Randomized trial of glycolic, salicylic and modified Jessner peels for acne/PIH"
    },
    "MICRONEEDLING_ACNE_SCARS": {
      "pmid": "24919799",
      "citation": "Randomized trial of needling for acne scars"
    },
    "MICRONEEDLING_COMPARE": {
      "pmid": "26845539",
      "citation": "Randomized trial comparing microneedling and fractional erbium laser for acne scars"
    },
    "MNRF_MFU": {
      "pmid": "40980871",
      "citation": "Randomized split-face microfocused ultrasound plus microneedle RF rejuvenation study"
    },
    "MNRF_ACNE": {
      "pmid": "31502662",
      "citation": "Selective sebaceous gland electrothermolysis randomized study"
    },
    "LED_REJUVENATION": {
      "pmid": "17566756",
      "citation": "Randomized placebo-controlled split-face LED rejuvenation study"
    },
    "LED_ACNE_RED": {
      "pmid": "17903156",
      "citation": "Randomized split-face red light acne trial"
    },
    "LED_ACNE_BLUE": {
      "pmid": "27575854",
      "citation": "Multicenter randomized blue-light acne study"
    },
    "MICRODERM_HISTO": {
      "pmid": "11442587",
      "citation": "Clinical and histopathologic microdermabrasion study"
    },
    "MICRODERM_PHOTO": {
      "pmid": "15060359",
      "citation": "Prospective controlled microdermabrasion for photodamage/fine rhytides"
    },
    "MICRODERM_MULTI": {
      "pmid": "37780689",
      "citation": "Diamond-tip dermabrasion plus topical skincare clinical usage study"
    },
    "RF_LAXITY": {
      "pmid": "21572674",
      "citation": "Monopolar RF for facial laxity clinical study"
    },
    "RF_PROTOCOL": {
      "pmid": "25226013",
      "citation": "Monopolar RF protocol for laxity and wrinkles"
    },
    "RF_RCT_2026": {
      "pmid": "41758257",
      "citation": "Multicenter randomized monopolar RF skin-tightening trial"
    },
    "HIFU_PILOT": {
      "pmid": "17372061",
      "citation": "Clinical pilot study of intense ultrasound to facial tissues"
    },
    "HIFU_LAXITY": {
      "pmid": "20115948",
      "citation": "Prospective study of ultrasound tightening of facial and neck skin"
    },
    "ULTRASOUND_DELIVERY": {
      "pmid": "8302758",
      "citation": "Controlled phonophoresis study demonstrating enhanced penetration"
    },
    "OXYBRASION": {
      "pmid": "32573038",
      "citation": "Study of oxybrasion effects on skin parameters"
    },
    "LYMPHATIC_FACE": {
      "pmid": "32680812",
      "citation": "Randomized study of manual lymphatic drainage and facial swelling"
    },
    "QSWITCH_LIP_RCT": {
      "pmid": "31177406",
      "citation": "Randomized controlled comparison of 1064-nm and 532-nm Q-switched Nd:YAG for hyperpigmented lips"
    },
    "QSWITCH_LIP_532": {
      "pmid": "34566363",
      "citation": "Prospective study of 532-nm Q-switched Nd:YAG for lip melanosis"
    }
  },
  "modalities": {
    "hydrafacial_full_protocol": {
      "name": "Hydrafacial / Hydradermabrasion Full Protocol",
      "category": "hydradermabrasion",
      "eligibility_profile_id": "hydrafacial",
      "parent_modality_id": null,
      "aliases": [
        "Hydrafacial",
        "Hydrafacial Machine"
      ],
      "activation_status": "active",
      "correction_role": "corrective_supportive",
      "preferred_zone_groups": [
        "full_skin_face"
      ],
      "avoid_or_protection_zones": [
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "May support acne when congestion and oil are important; not a substitute for acne-directed therapy."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Suction/exfoliation can visibly reduce superficial congestion."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Removes surface oil and supports short-term oil balance."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Benefit depends on gentle settings and barrier-compatible solutions."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 5,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Strong immediate hydration/plumpness appearance when hydrating serums are used."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Improves visible pore appearance through cleansing, hydration and congestion reduction."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Mechanical/solution exfoliation improves surface smoothness."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Surface brightening only; not a deep pigment treatment."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 5,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "High immediate glow/radiance potential."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "Hydration can reduce fine-line visibility without changing deep wrinkles."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary plumpness may improve firmness appearance."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Only with approved ocular probe/serum and protection."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "superficial cleansing",
        "serum delivery",
        "temporary plumpness"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "cleaner-looking pores",
          "smoother texture",
          "higher visible hydration",
          "brighter, fresher appearance"
        ],
        "course_or_delayed": [
          "progressive improvement in surface quality when repeated"
        ]
      },
      "limitations_and_non_claims": [
        "Do not claim deep pigment removal, collagen remodeling or permanent pore-size reduction from one session."
      ],
      "expected_transient_effects": [
        "temporary erythema from suction or exfoliation",
        "temporary tightness if over-exfoliated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "led_red",
          "ultrasound_infusion_face",
          "hydrating_mask",
          "lymphatic_drainage"
        ],
        "conditional_partners": [
          "manual_extraction",
          "gentle_peels"
        ],
        "avoid_same_session": [
          "aggressive_peels",
          "microneedling",
          "microneedling_rf"
        ]
      },
      "sequence_role": [
        "cleanse",
        "exfoliate",
        "extract",
        "infuse",
        "support"
      ],
      "typical_duration_minutes": {
        "min": 25,
        "max": 45
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "HYDRADERMABRASION"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "hydrafacial_cutin_spatula": {
      "name": "Cutin Removal Spatula / Ultrasonic Skin Scrubber",
      "category": "hydrafacial_probe",
      "eligibility_profile_id": "hydrafacial",
      "parent_modality_id": "hydrafacial_full_protocol",
      "aliases": [
        "Cutin Removal Spatula",
        "Exfoliator"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "Loosens superficial debris and keratin."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Removes superficial oil film."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "May improve visible pore cleanliness."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Improves superficial roughness."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Surface polishing can improve reflectance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Benefit is superficial and depends on contact technique; do not equate vibration with deep exfoliation."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 3,
        "max": 8
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "hydrafacial_suction_extraction": {
      "name": "Hydrafacial Suction / Bubble Pen Extraction",
      "category": "hydrafacial_probe",
      "eligibility_profile_id": "high_intensity_extraction",
      "parent_modality_id": "hydrafacial_full_protocol",
      "aliases": [
        "Suction Probe / Bubble Pen"
      ],
      "activation_status": "active",
      "correction_role": "corrective",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "high",
          "expected_onset": "none",
          "clinical_role": "avoid",
          "notes": "Do not suction directly over inflamed lesions."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 5,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Strong immediate response for superficial loosened congestion."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Removes surface oil and follicular contents."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Reduces visible congestion-driven pore prominence."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "May improve irregularity caused by congestion."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Not for cystic/inflamed lesions; excessive suction can cause bruising or erythema."
      ],
      "expected_transient_effects": [
        "localized erythema",
        "temporary suction marks"
      ],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 12
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "HYDRADERMABRASION"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "hydrafacial_ice_probe": {
      "name": "Ice Probe / Cooling Support",
      "category": "hydrafacial_probe",
      "eligibility_profile_id": "hydrafacial",
      "parent_modality_id": "hydrafacial_full_protocol",
      "aliases": [
        "Ice Probe"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "May reduce transient visible redness and improve comfort."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Comfort support only; does not repair barrier by itself."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "May transiently reduce puffiness when used appropriately."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "comfort",
        "temporary cooling",
        "temporary de-puffing"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not claim durable vascular or barrier correction."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 2,
        "max": 6
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "jet_oxygen_infusion": {
      "name": "Jet / Oxygen Infusion",
      "category": "infusion",
      "eligibility_profile_id": "jet_infusion",
      "parent_modality_id": null,
      "aliases": [
        "Oxygen Injection",
        "Hydra Spray"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Benefit is mainly product-mediated."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Can improve surface hydration when delivering humectant serum."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Improves short-term radiance through hydration and product delivery."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration-related softening only."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Only with compatible product and safe distance."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "non-contact serum application",
        "cooling comfort"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not claim meaningful tissue oxygenation unless the specific device and protocol are validated; benefits are primarily delivery/hydration based."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 3,
        "max": 8
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "OXYBRASION"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "ultrasound_infusion_face": {
      "name": "Face Ultrasound Infusion",
      "category": "infusion",
      "eligibility_profile_id": "ultrasound_infusion",
      "parent_modality_id": null,
      "aliases": [
        "Face Ultrasound Infusion Probe"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Can assist delivery of barrier-compatible products."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Product-mediated hydration support."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "supportive",
          "notes": "Only when paired with an approved pigment-active product."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Improved product delivery can enhance glow."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration-mediated visible softening."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "enhanced topical delivery"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Response depends on the delivered formulation; the probe itself should not be credited with the active ingredient's full benefit."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 12
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "ULTRASOUND_DELIVERY"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "ultrasound_infusion_ocular": {
      "name": "Ocular Ultrasound Infusion",
      "category": "infusion",
      "eligibility_profile_id": "ultrasound_infusion",
      "parent_modality_id": "ultrasound_infusion_face",
      "aliases": [
        "Ocular Ultrasound Infusion Probe"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [
        "peri_orbital"
      ],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Product-mediated peri-orbital hydration."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration-mediated line softening."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "May support dryness, fine-line and puffiness appearance depending on product."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Use only approved ocular protocol; avoid mobile eyelid and globe exposure."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 3,
        "max": 8
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [
          "ULTRASOUND_DELIVERY"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "q_switch_1064": {
      "name": "Q-Switch Nd:YAG 1064 nm",
      "category": "laser",
      "eligibility_profile_id": "q_switch_laser",
      "parent_modality_id": null,
      "aliases": [
        "Q-Switch 1064",
        "1064 nm Q-Switch"
      ],
      "activation_status": "active",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "pigment_hotspots",
        "cheeks",
        "forehead"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Possible adjunctive acne benefit but not primary unless a validated acne protocol is used."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Limited secondary benefit in some protocols."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Some laser-toning protocols report pore appearance improvement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Modest rejuvenation effect in repeated protocols."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Useful for selected epidermal/dermal pigment and PIH with appropriate low-fluence protocol."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Can target deeper melanin patterns depending on diagnosis and parameters."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Tone-evening can improve visible clarity."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "tattoo/pigmented lesion treatment only under separate medical protocol"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "selected pigment spots may appear lighter after recovery"
        ],
        "course_or_delayed": [
          "progressive reduction in selected pigmentation patterns",
          "improved tone clarity"
        ]
      },
      "limitations_and_non_claims": [
        "Not a generic full-face brightening tool; melasma can recur or worsen, and exact wavelength/fluence must be diagnosis- and skin-type-specific."
      ],
      "expected_transient_effects": [
        "erythema",
        "temporary darkening or frosting depending on endpoint",
        "risk of PIH/hypopigmentation"
      ],
      "combination_logic": {
        "preferred_partners": [
          "led_red",
          "cooling_support",
          "barrier_recovery"
        ],
        "conditional_partners": [
          "gentle_hydration"
        ],
        "avoid_same_session": [
          "strong_peels",
          "microneedling",
          "microneedling_rf",
          "hifu"
        ]
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "low_to_moderate",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "QSWITCH_PIH_INDIA"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "q_switch_532": {
      "name": "Q-Switch 532 nm",
      "category": "laser",
      "eligibility_profile_id": "q_switch_laser",
      "parent_modality_id": null,
      "aliases": [
        "532 nm Q-Switch"
      ],
      "activation_status": "disabled_not_in_general_facial_engine",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "discrete_superficial_pigment_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips",
        "broad_melasma_pattern"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong for selected superficial epidermal pigmented lesions."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "limited",
          "notes": "Not preferred for deeper diffuse pigment."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Localized pigment clearance may improve tone evenness."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Higher epidermal melanin interaction; strict skin-type, lesion diagnosis and PIH-risk selection required. Not default for diffuse melasma."
      ],
      "expected_transient_effects": [
        "frosting/crusting depending on endpoint",
        "erythema",
        "PIH risk"
      ],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 3,
        "max": 15
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "QSWITCH_532"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "q_switch_755": {
      "name": "Q-Switch 755 nm",
      "category": "laser",
      "eligibility_profile_id": "q_switch_laser",
      "parent_modality_id": null,
      "aliases": [
        "755 nm Q-Switch"
      ],
      "activation_status": "disabled_not_in_general_facial_engine",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "selected_pigment_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Potential benefit for selected pigmented lesions if the actual handpiece/wavelength is verified."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Potential deeper pigment interaction than 532 nm."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "The machine's true 755-nm technology and approved indications must be verified before activation; skin-of-color risk requires conservative use."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 3,
        "max": 15
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "carbon_facial": {
      "name": "Carbon Facial / Carbon-Assisted Q-Switch",
      "category": "laser",
      "eligibility_profile_id": "carbon_facial",
      "parent_modality_id": null,
      "aliases": [
        "Carbon Laser Facial",
        "Hollywood Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "t_zone",
        "cheeks",
        "acne_congestion_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Evidence supports reduction in inflammatory lesions across a course."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Targets follicular debris and non-inflammatory lesions."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Can reduce sebum output in acne protocols."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Can improve enlarged-pore appearance."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Texture benefit is secondary to a genuine acne, oil, congestion or pore indication."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "limited",
          "notes": "Not a primary pigment treatment in the general facial engine."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Minor clarity support only; carbon should not be selected as a generic glow treatment."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Limited rejuvenation benefit; not a primary wrinkle treatment."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "acne lesion reduction",
        "sebaceous activity reduction"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not assume carbon always improves outcomes versus non-carbon laser; benefits depend on pulse modes and protocol."
      ],
      "expected_transient_effects": [
        "erythema",
        "warmth",
        "temporary dryness"
      ],
      "combination_logic": {
        "preferred_partners": [
          "led_blue",
          "led_red",
          "hydrating_mask"
        ],
        "conditional_partners": [
          "manual_extraction",
          "salicylic_spot"
        ],
        "avoid_same_session": [
          "strong_peels",
          "microneedling",
          "microneedling_rf"
        ]
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 15,
        "max": 30
      },
      "downtime_band": "low",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "CARBON_ACNE_RCT",
          "CARBON_PORES",
          "CARBON_SKIN_COLOR"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "radio_frequency": {
      "name": "Non-invasive Radiofrequency / RF Lifting Probe",
      "category": "energy",
      "eligibility_profile_id": "radio_frequency",
      "parent_modality_id": null,
      "aliases": [
        "RF",
        "Lifting Probe (RF)"
      ],
      "activation_status": "active",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "cheeks",
        "jawline",
        "lower_face",
        "selected_periorbital_with_approved_tip"
      ],
      "avoid_or_protection_zones": [
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Some devices may reduce sebum, but not a primary benefit."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Possible delayed pore appearance improvement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Possible delayed skin-quality improvement."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Some studies show improved radiance over weeks."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Supports wrinkle/rhytid appearance improvement."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Primary use is visible tightening/laxity improvement."
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Primary delayed firmness benefit."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Can improve peri-orbital fine wrinkles with approved tip/protocol."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "temporary tightened or plumper appearance may occur"
        ],
        "course_or_delayed": [
          "progressive improvement in visible laxity, firmness and fine lines"
        ]
      },
      "limitations_and_non_claims": [
        "Do not promise immediate collagen remodeling; meaningful benefit is delayed and device-specific."
      ],
      "expected_transient_effects": [
        "warmth",
        "temporary erythema",
        "temporary edema"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_support",
          "led_red"
        ],
        "conditional_partners": [
          "hydrafacial_full_protocol"
        ],
        "avoid_same_session": [
          "hifu",
          "microneedling_rf",
          "strong_peels"
        ]
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 15,
        "max": 35
      },
      "downtime_band": "none_to_low",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "RF_LAXITY",
          "RF_PROTOCOL",
          "RF_RCT_2026"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "hifu": {
      "name": "High-Intensity Focused Ultrasound (HiFU)",
      "category": "energy",
      "eligibility_profile_id": "hifu",
      "parent_modality_id": null,
      "aliases": [
        "HIFU",
        "HiFU"
      ],
      "activation_status": "active",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "cheeks",
        "jawline",
        "chin",
        "lower_face"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "perioral",
        "lips",
        "bony_prominences"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "secondary",
          "notes": "May improve selected lines through tightening."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Primary benefit is lifting/tightening of lax tissue."
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong delayed firmness benefit."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "limited",
          "notes": "Only selected brow/periorbital protocols with appropriate cartridge and anatomy."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "submental/contour lifting where clinically indicated"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Not a glow, hydration, pigment or acne treatment; outcome is delayed and anatomy/device dependent."
      ],
      "expected_transient_effects": [
        "tenderness",
        "edema",
        "temporary numbness or soreness"
      ],
      "combination_logic": {
        "preferred_partners": [
          "gentle_hydration",
          "led_red"
        ],
        "conditional_partners": [],
        "avoid_same_session": [
          "radio_frequency",
          "microneedling_rf",
          "strong_peels",
          "q_switch_1064"
        ]
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 25,
        "max": 60
      },
      "downtime_band": "low",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "HIFU_PILOT",
          "HIFU_LAXITY"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "microneedling": {
      "name": "Microneedling / Dermapen",
      "category": "invasive",
      "eligibility_profile_id": "microneedling",
      "parent_modality_id": null,
      "aliases": [
        "Microneedling",
        "Dermapen"
      ],
      "activation_status": "active",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "cheeks",
        "forehead",
        "scar_and_texture_zones"
      ],
      "avoid_or_protection_zones": [
        "lips",
        "active_inflammatory_lesions"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "high",
          "expected_onset": "none",
          "clinical_role": "avoid",
          "notes": "Do not needle active inflamed acne lesions."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "secondary",
          "notes": "Can improve pore appearance over a course."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong for textural remodeling."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks_to_months",
          "clinical_role": "secondary",
          "notes": "May support selected PIH/melasma protocols but carries PIH risk."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Improved surface quality can increase radiance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Supports fine-line improvement."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "months",
          "clinical_role": "secondary",
          "notes": "Mild tightening appearance possible."
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Supports firmness appearance through remodeling."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Superficial approved-depth treatment can address fine lines and selected pigmentation."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "atrophic acne scar remodeling",
        "transdermal delivery of approved sterile products"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Not for active acne, infection or uncontrolled barrier stress; exact depth and product use must follow approved protocol."
      ],
      "expected_transient_effects": [
        "erythema",
        "pinpoint bleeding",
        "temporary dryness",
        "PIH risk"
      ],
      "combination_logic": {
        "preferred_partners": [
          "led_red",
          "sterile_approved_topical"
        ],
        "conditional_partners": [
          "very_superficial_peel_on_separate_or_doctor_approved_protocol"
        ],
        "avoid_same_session": [
          "strong_peels",
          "q_switch_1064",
          "carbon_facial",
          "hifu"
        ]
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 20,
        "max": 45
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "MICRONEEDLING_ACNE_SCARS",
          "MICRONEEDLING_COMPARE"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "dermaroller": {
      "name": "Dermaroller Microneedling",
      "category": "invasive",
      "eligibility_profile_id": "dermaroller",
      "parent_modality_id": "microneedling",
      "aliases": [
        "Dermaroller"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks_to_months",
          "clinical_role": "secondary",
          "notes": "Less zone precision than a pen device."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Texture remodeling over a course."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Fine-line improvement over a course."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks_to_months",
          "clinical_role": "secondary",
          "notes": "Possible mild firmness improvement."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "atrophic acne scar remodeling"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Lower precision and more variable penetration than Dermapen; do not substitute without protocol approval."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 20,
        "max": 40
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "MICRONEEDLING_ACNE_SCARS"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "microneedling_rf": {
      "name": "Microneedling Radiofrequency (MNRF)",
      "category": "energy_invasive",
      "eligibility_profile_id": "microneedling_rf",
      "parent_modality_id": null,
      "aliases": [
        "MNRF",
        "Microneedling RF"
      ],
      "activation_status": "active",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "cheeks",
        "jawline",
        "scar_texture_zones"
      ],
      "avoid_or_protection_zones": [
        "lips",
        "active_inflammatory_lesions_unless_specific_sebaceous_protocol"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "weeks_to_months",
          "clinical_role": "conditional",
          "notes": "Only if the device/probe supports validated sebaceous-gland targeting; otherwise avoid active lesions."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks_to_months",
          "clinical_role": "conditional",
          "notes": "Possible with sebaceous-gland targeting protocol."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong delayed pore/texture benefit."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong textural remodeling."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Not a primary pigment treatment; PIH risk must be managed."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong fine-line and wrinkle benefit."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Tightening/laxity improvement."
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong firmness benefit."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "conditional",
          "notes": "Only approved superficial peri-orbital protocol."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "atrophic acne scar remodeling",
        "selected sebaceous-gland targeting"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Needle depth, insulation pattern and RF delivery must match the intended indication; do not assume acne benefit from every MNRF cartridge."
      ],
      "expected_transient_effects": [
        "erythema",
        "edema",
        "pinpoint crusting",
        "PIH risk"
      ],
      "combination_logic": {
        "preferred_partners": [
          "led_red",
          "barrier_recovery"
        ],
        "conditional_partners": [],
        "avoid_same_session": [
          "strong_peels",
          "q_switch_1064",
          "carbon_facial",
          "hifu",
          "radio_frequency"
        ]
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 25,
        "max": 50
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "MNRF_MFU",
          "MNRF_ACNE"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "led_blue": {
      "name": "Blue LED Light Therapy",
      "category": "photobiomodulation",
      "eligibility_profile_id": "led_light_therapy",
      "parent_modality_id": null,
      "aliases": [
        "Blue LED"
      ],
      "activation_status": "active",
      "correction_role": "supportive_corrective",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Can reduce inflammatory acne burden over repeated sessions."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Indirect acne support; not a direct sebum-control claim."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Inflammation may improve as acne improves."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "A single brief session is supportive; meaningful acne change generally requires a course. Device wavelength and dose matter."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 25
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "LED_ACNE_BLUE"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "led_red": {
      "name": "Red LED Light Therapy",
      "category": "photobiomodulation",
      "eligibility_profile_id": "led_light_therapy",
      "parent_modality_id": null,
      "aliases": [
        "Red LED"
      ],
      "activation_status": "active",
      "correction_role": "supportive_corrective",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Anti-inflammatory acne support over repeated sessions."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "supportive",
          "notes": "May support inflammatory recovery; not a vascular laser."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "supportive",
          "notes": "Recovery support after compatible procedures."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Rejuvenation support over a course."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "supportive",
          "notes": "May improve overall skin appearance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Photobiomodulation may support rejuvenation."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Supportive, not equivalent to RF/HiFU."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "post-procedure recovery support"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not present as a replacement for stronger corrective modalities when a stronger modality is safe and indicated."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 25
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "LED_REJUVENATION",
          "LED_ACNE_RED"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "led_green": {
      "name": "Green LED Light Therapy",
      "category": "photobiomodulation",
      "eligibility_profile_id": "led_light_therapy",
      "parent_modality_id": null,
      "aliases": [
        "Green LED"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "supportive",
          "notes": "Clinic calming use; robust evidence is limited."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "supportive",
          "notes": "Evidence for pigment benefit is insufficient for strong claims."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "supportive",
          "notes": "May contribute to a calming/radiance protocol."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Keep claims conservative; do not rank as a hero pigment modality without stronger device-specific evidence."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 25
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "microdermabrasion_diamond": {
      "name": "Diamond-Tip Microdermabrasion",
      "category": "mechanical_exfoliation",
      "eligibility_profile_id": "microdermabrasion",
      "parent_modality_id": null,
      "aliases": [
        "Diamond Tip Microdermabrasion"
      ],
      "activation_status": "active",
      "correction_role": "corrective",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "secondary",
          "notes": "Supports superficial unclogging."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Removes superficial oil/keratin."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "With hydrating serum, studies show immediate hydration improvement; abrasion alone may temporarily disrupt barrier."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Improves visible pores over repeated treatments."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong surface-smoothing benefit."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Can improve superficial mottled pigmentation/dyschromia."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong radiance benefit."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Can improve fine rhytid appearance over repeated sessions."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Not for active inflamed acne or fragile barrier; abrasion level determines risk."
      ],
      "expected_transient_effects": [
        "erythema",
        "tightness",
        "temporary sensitivity"
      ],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "low",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "MICRODERM_HISTO",
          "MICRODERM_PHOTO",
          "MICRODERM_MULTI"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "microdermabrasion_crystal": {
      "name": "Crystal Microdermabrasion",
      "category": "mechanical_exfoliation",
      "eligibility_profile_id": "microdermabrasion",
      "parent_modality_id": "microdermabrasion_diamond",
      "aliases": [
        "Crystal Tip Microdermabrasion"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Surface pore appearance improvement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong superficial smoothing."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Superficial dyschromia support."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "immediate_to_weeks",
          "clinical_role": "direct",
          "notes": "Radiance improvement."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Fine rhytid appearance improvement."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Crystal residue and abrasion intensity require careful control; not for reactive barrier."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "low",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "MICRODERM_HISTO",
          "MICRODERM_PHOTO"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "high_frequency_glass_electrode": {
      "name": "High-Frequency Glass Electrode",
      "category": "energy_supportive",
      "eligibility_profile_id": "high_frequency",
      "parent_modality_id": null,
      "aliases": [
        "High Frequency",
        "High-Frequency Machine"
      ],
      "activation_status": "active",
      "correction_role": "supportive_corrective",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Useful as localized post-extraction/acne support in clinic practice."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "supportive",
          "notes": "Drying effect may reduce local surface oil."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "May reduce acne-related inflammation locally, but can irritate reactive skin."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "post-extraction sanitation support"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not equate an esthetic glass-electrode machine with needle sebaceous electrothermolysis trials; strong efficacy claims are not supported."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 2,
        "max": 8
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "manual_extraction": {
      "name": "Manual Comedone Extraction",
      "category": "manual",
      "eligibility_profile_id": "high_intensity_extraction",
      "parent_modality_id": null,
      "aliases": [
        "Manual Comedone Extractor",
        "Loop Extractor",
        "Targeted Extraction"
      ],
      "activation_status": "active",
      "correction_role": "corrective",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "high",
          "expected_onset": "none",
          "clinical_role": "avoid",
          "notes": "Avoid squeezing inflamed lesions unless a doctor-directed procedure is indicated."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 5,
          "course_strength_0_to_5": 1,
          "confidence": "high",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Direct removal of suitable open/closed comedones."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 0,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "Removes follicular contents but does not change baseline sebum production."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 1,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Improves congestion-driven pore appearance."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "secondary",
          "notes": "Improves bumpiness from closed comedones."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Technique-sensitive; over-extraction can cause inflammation, bruising, PIH or scarring."
      ],
      "expected_transient_effects": [
        "localized erythema",
        "tenderness",
        "possible swelling"
      ],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "lymphatic_drainage": {
      "name": "Face Massage + Lymphatic Drainage",
      "category": "manual_supportive",
      "eligibility_profile_id": "face_massage_lymphatic",
      "parent_modality_id": null,
      "aliases": [
        "Lymphatic Drainage",
        "Face Massage"
      ],
      "activation_status": "active",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "neutral",
          "notes": "Not an erythema treatment; aggressive massage may worsen reactive skin."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Product slip and massage can improve temporary plumpness."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Can create temporary refreshed appearance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary contour/plumpness effect only."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 0,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "May temporarily reduce puffiness/fluid-related appearance."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "temporary de-puffing",
        "relaxation",
        "comfort"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not claim collagen remodeling, durable lifting or long-term drainage from one cosmetic session."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 15
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "LYMPHATIC_FACE"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "charcoal_mask": {
      "name": "Charcoal Peel-Off Mask",
      "category": "mask",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [],
      "activation_status": "active_supportive",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Superficial debris removal only."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Surface oil-control and cleansing support."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary cleaner pore appearance."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "May improve freshness."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "less oily, cleaner-looking finish"
        ],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Product-formula dependent; do not claim durable structural change."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "recovery",
        "support"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "calming_mask": {
      "name": "Calming Peel-Off Mask",
      "category": "mask",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [],
      "activation_status": "active_supportive",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary calming appearance."
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Depends on formulation."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Occlusive/hydrating support."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "calmer, more comfortable appearance"
        ],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Product-formula dependent; do not claim durable structural change."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "recovery",
        "support"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "brightening_mask": {
      "name": "Brightening Peel-Off Mask",
      "category": "mask",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [],
      "activation_status": "active_supportive",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary smoothing."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "supportive",
          "notes": "Surface tone support only."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Visible brightness/radiance support."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "brighter, fresher appearance"
        ],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Product-formula dependent; do not claim durable structural change."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "recovery",
        "support"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "hydrating_mask": {
      "name": "Hydrating Peel-Off Mask",
      "category": "mask",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [],
      "activation_status": "active_supportive",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Formulation-dependent comfort and barrier support."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 5,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Strong immediate hydration appearance."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration increases radiance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration softens fine-line visibility."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Can improve dry/puffy appearance if safe for zone."
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "more hydrated, plumper and smoother appearance"
        ],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Product-formula dependent; do not claim durable structural change."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "recovery",
        "support"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "lifting_mask": {
      "name": "Lifting Peel-Off Mask",
      "category": "mask",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [],
      "activation_status": "active_supportive",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary plumpness."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary smoothing."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary tightening feel only."
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Temporary firming appearance."
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "temporary firmer, smoother appearance"
        ],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Product-formula dependent; do not claim durable structural change."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "recovery",
        "support"
      ],
      "typical_duration_minutes": {
        "min": 10,
        "max": 20
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "final_serum_moisturizer_sunscreen": {
      "name": "Final Serum + Moisturizer + Sunscreen",
      "category": "mandatory_finishing_step",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [
        "Final Step"
      ],
      "activation_status": "active",
      "correction_role": "mandatory_supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Moisturizer supports recovery; sunscreen prevents avoidable UV burden."
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "direct",
          "notes": "Hydration and occlusion improve visual dehydration."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "high",
          "expected_onset": "weeks",
          "clinical_role": "preventive",
          "notes": "Sunscreen supports prevention of worsening/recurrence rather than immediate removal."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "preventive",
          "notes": "Photoprotection reduces ongoing UV-driven pigment burden."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 1,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Moisturization improves reflectance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "medium",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration softens fine-line visibility."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "photoprotection",
        "barrier recovery"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "The exact benefit depends on selected products and compliance after the session."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "mandatory_final"
      ],
      "typical_duration_minutes": {
        "min": 4,
        "max": 4
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "party_peel": {
      "name": "Party Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "party_peel",
      "parent_modality_id": null,
      "aliases": [
        "Party Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Mild keratolytic support."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Lactic/arginine formulation may support smoother hydrated appearance."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Surface refinement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 2,
          "confidence": "medium",
          "expected_onset": "immediate_to_days",
          "clinical_role": "direct",
          "notes": "Surface polishing."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Brightening ingredients support superficial tone."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 5,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate_to_days",
          "clinical_role": "direct",
          "notes": "Designed for rapid glow/event readiness."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Hydration/exfoliation-mediated softening."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "instant glow",
          "surface brightness",
          "smoother finish"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "low_to_moderate",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "PEEL_GLY_SAL_MAN"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "whitening_peel": {
      "name": "Whitening Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "whitening_peel",
      "parent_modality_id": null,
      "aliases": [
        "Whitening Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "AHA-driven surface renewal."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Glycolic/lactic plus kojic/arbutin/licorice formulation targets superficial tone."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "limited",
          "notes": "Not a deep pigment treatment."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong brightness/tone-evening role."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Surface renewal may improve fine-line appearance."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "brighter and more even-looking tone"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "PEEL_GLY_SAL_MAN"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "sali_ds_peel": {
      "name": "Sali DS Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "sali_ds_peel",
      "parent_modality_id": null,
      "aliases": [
        "Sali DS Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Acne-directed salicylic formulation."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong keratolytic/comedolytic role."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong oil-control role."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Reduces congestion-driven pore visibility."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Improves acne-associated roughness."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "May improve post-acne marks indirectly over a course."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Clarifying benefit."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "clearer-looking congested skin",
          "reduced oily appearance"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate_to_high",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "PEEL_SAL30",
          "PEEL_2026_RCT"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "salicylic_30_peel": {
      "name": "Salicylic Acid 30% Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "salicylic_30_peel",
      "parent_modality_id": null,
      "aliases": [
        "30% Salicylic Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong acne-directed peel."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong comedolytic benefit."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong oil-regulation benefit."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Improves congestion-driven pore appearance."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Surface clarification."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Can improve post-acne hyperpigmentation over a course."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Improved clarity."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "strong acne and oil-control correction"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "high",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "PEEL_SAL30",
          "PEEL_2026_RCT"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "salicylic_20_peel": {
      "name": "20% Salicylic Acid Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "salicylic_20_peel",
      "parent_modality_id": null,
      "aliases": [
        "Salicylic Acid 20% Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Acne support."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Excellent for blackheads/clogged pores."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Oil-control."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Pore/congestion improvement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Clarifies rough congested texture."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "PIH support."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Cleaner appearance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "blackhead and congestion correction"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "PEEL_SAL30",
          "PEEL_GLY_SAL_MAN"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "pumpkin_peel": {
      "name": "Gel Based Pumpkin Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "pumpkin_peel",
      "parent_modality_id": null,
      "aliases": [
        "Pumpkin Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Low salicylic content offers mild acne support."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Gentle exfoliation."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "supportive",
          "notes": "Mild clarification."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate",
          "clinical_role": "supportive",
          "notes": "Gentler humectant-rich formulation may preserve comfort."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Surface refinement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate_to_days",
          "clinical_role": "direct",
          "notes": "Gentle resurfacing."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Mild brightening."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "immediate_to_days",
          "clinical_role": "direct",
          "notes": "Good glow/tolerability balance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "gentle glow",
          "smoother texture"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "low",
      "evidence": {
        "overall_tier": "C",
        "references": [
          "PEEL_GLY_SAL_MAN"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "mandelic_peel": {
      "name": "Gel Based Mandelic Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": "mandelic_peel",
      "parent_modality_id": null,
      "aliases": [
        "Mandelic Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Gentle acne support."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Gentle follicular clarification."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Moderate oil-control."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Congestion-related pore benefit."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Gentle surface renewal."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Evidence supports pigment and acne benefit, including Indian skin."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Brightening and clarity."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "gentle broad-spectrum clarity and brightening"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "low_to_moderate",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "PEEL_GLY_SAL_MAN"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "fusion_peel_e": {
      "name": "Fusion Peel-E",
      "category": "chemical_peel",
      "eligibility_profile_id": "fusion_peel_e",
      "parent_modality_id": null,
      "aliases": [
        "Fusion Peel E"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Salicylic-containing mixed acid action."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong mixed-acid clarification."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Oil/acne support."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Congestion and resurfacing benefit."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong resurfacing."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 5,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Designed for post-acne pigmentation and mixed resurfacing."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong clarity/radiance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Surface renewal."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "post-acne mark correction",
          "mixed acne-pigment resurfacing"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate_to_high",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "PEEL_GLY_SAL_MAN",
          "PEEL_2026_RCT"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "glyco_35_peel": {
      "name": "Glyco Peel 35",
      "category": "chemical_peel",
      "eligibility_profile_id": "glyco_35_peel",
      "parent_modality_id": null,
      "aliases": [
        "Glycolic 35 Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Not the primary acne peel."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Surface turnover support."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Limited oil benefit."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Surface refinement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Hero texture/rejuvenation peel."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Superficial pigment and dyschromia improvement."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong radiance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Fine-line/photodamage benefit over a course."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "strong surface rejuvenation",
          "texture and brightness improvement"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate_to_high",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "PEEL_GLY_SAL_MAN",
          "PEEL_2026_RCT"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "combination_peel": {
      "name": "Combination Peel (Salicylic + Mandelic)",
      "category": "chemical_peel",
      "eligibility_profile_id": "combination_peel",
      "parent_modality_id": null,
      "aliases": [
        "Combination Peel"
      ],
      "activation_status": "active",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong mixed acne correction."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong comedolytic action."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Strong oil-control."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "high",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Congestion/pore benefit."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Mixed resurfacing."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Strong for acne plus PIH in skin of color."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Improves clarity/radiance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "mixed acne, congestion and post-acne pigmentation correction"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate_to_high",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "PEEL_GLY_SAL_MAN"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "yellow_peel": {
      "name": "Yellow Peel / Formula 1614",
      "category": "chemical_peel",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [
        "Yellow Peel",
        "Formula 1614"
      ],
      "activation_status": "active_doctor_constraint_envelope",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Surface turnover."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 5,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Clinic-reported depigmenting peel; exact formula/protocol confirmation required."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "May be used for persistent pigment patterns depending on formula."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 4,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Tone improvement can increase radiance."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "pigment correction"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Disabled until exact product formula, contact-time and eligibility profile are confirmed.",
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate_to_high",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "cosmelan_protocol": {
      "name": "Cosmelan Depigmentation Protocol",
      "category": "chemical_peel",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [
        "Cosmelan"
      ],
      "activation_status": "active_doctor_constraint_envelope",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 5,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Strong multi-stage depigmentation protocol."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks_to_months",
          "clinical_role": "direct",
          "notes": "Designed for persistent pigment control with maintenance."
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 4,
          "confidence": "medium",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Tone-evening improves clarity."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "progressive pigment reduction"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "This is a protocol, not a one-step peel; home maintenance and medical supervision are integral. Disabled until exact clinic protocol is supplied.",
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "high",
      "evidence": {
        "overall_tier": "C",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "biorepeelcl3": {
      "name": "BioRePeelCl3 / TCA-Based Biphasic Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [
        "BioRePeelCl3",
        "Enzymatic Peel"
      ],
      "activation_status": "active_doctor_constraint_envelope",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Product-specific acne support."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Resurfacing/clarification."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Surface refinement."
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 4,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Resurfacing benefit."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Tone support."
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 4,
          "course_strength_0_to_5": 4,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Radiance/resurfacing."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Rejuvenation support."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "radiance and resurfacing"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Product-specific claims and protocol must be verified from the actual supplied formulation. Disabled until protocol is loaded.",
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "salmon_peel": {
      "name": "Salmon Peel",
      "category": "chemical_peel",
      "eligibility_profile_id": null,
      "parent_modality_id": null,
      "aliases": [
        "Salmon Peel"
      ],
      "activation_status": "active_doctor_constraint_envelope",
      "correction_role": "hero_or_secondary_corrective",
      "preferred_zone_groups": [
        "full_skin_face",
        "selected_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "immediate_to_days",
          "clinical_role": "supportive",
          "notes": "Clinic-reported rejuvenation/hydration intent; formula unknown."
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "secondary",
          "notes": "Possible resurfacing depending on formula."
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 3,
          "course_strength_0_to_5": 3,
          "confidence": "low",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Clinic-reported glow/rejuvenation intent."
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Possible rejuvenation support."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "rejuvenation and glow"
        ],
        "course_or_delayed": [
          "progressive improvement requires appropriately spaced sessions when indicated"
        ]
      },
      "limitations_and_non_claims": [
        "Exact product composition, active concentrations and protocol are required before any clinical ranking. Disabled by default.",
        "Contact time, neutralization, layering and endpoint must come from the approved product protocol, not from the optimizer."
      ],
      "expected_transient_effects": [
        "stinging",
        "erythema",
        "dryness",
        "visible peeling depending on peel and endpoint",
        "PIH risk if overtreated"
      ],
      "combination_logic": {
        "preferred_partners": [
          "hydrating_mask",
          "led_red",
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [
          "manual_extraction_before_peel_when_safe"
        ],
        "avoid_same_session": [
          "other_strong_peels",
          "microneedling",
          "microneedling_rf",
          "q_switch_1064",
          "carbon_facial"
        ]
      },
      "sequence_role": [
        "corrective_exfoliation",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 20
      },
      "downtime_band": "unknown",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "salicylic_spot": {
      "name": "Targeted Salicylic Spot Treatment",
      "category": "targeted_topical",
      "eligibility_profile_id": "sali_ds_peel",
      "parent_modality_id": null,
      "aliases": [
        "Salicylic Spot"
      ],
      "activation_status": "active",
      "correction_role": "secondary_corrective",
      "preferred_zone_groups": [
        "active_acne_hotspots"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital",
        "lips"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days",
          "clinical_role": "direct",
          "notes": "Targeted lesion support."
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 2,
          "course_strength_0_to_5": 3,
          "confidence": "medium",
          "expected_onset": "days_to_weeks",
          "clinical_role": "direct",
          "notes": "Local keratolysis."
        },
        "oiliness": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 2,
          "confidence": "low",
          "expected_onset": "days",
          "clinical_role": "secondary",
          "notes": "Local oil-control."
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "Do not apply indiscriminately over barrier-damaged zones; concentration and contact time must follow approved protocol."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "corrective"
      ],
      "typical_duration_minutes": {
        "min": 2,
        "max": 6
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "B",
        "references": [
          "PEEL_SAL30"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "hydrafacial_teenage_line_probe": {
      "name": "Hydrafacial Teenage Line Probe",
      "category": "hydrafacial_probe",
      "eligibility_profile_id": "hydrafacial",
      "parent_modality_id": "hydrafacial_full_protocol",
      "aliases": [
        "Teenage Line"
      ],
      "activation_status": "active_doctor_constraint_envelope",
      "correction_role": "supportive",
      "preferred_zone_groups": [],
      "avoid_or_protection_zones": [],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        }
      },
      "non_core_benefits": [
        "unknown — requires device manual or photo"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [],
        "course_or_delayed": []
      },
      "limitations_and_non_claims": [
        "The uploaded constraints name this probe but do not define its mechanism or intended use.",
        "It must not be ranked or used until the tech/clinical team supplies the device manual or a clear probe description."
      ],
      "expected_transient_effects": [],
      "combination_logic": {
        "preferred_partners": [],
        "conditional_partners": [],
        "avoid_same_session": []
      },
      "sequence_role": [
        "unknown"
      ],
      "typical_duration_minutes": {
        "min": 0,
        "max": 0
      },
      "downtime_band": "none",
      "evidence": {
        "overall_tier": "D",
        "references": [],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    },
    "q_switch_lip_pigmentation_protocol": {
      "name": "Doctor-Directed Q-Switch Lip Pigmentation Protocol",
      "category": "laser_special_protocol",
      "eligibility_profile_id": "q_switch_laser",
      "parent_modality_id": null,
      "aliases": [
        "Q-Switch Lip Protocol",
        "Lip Pigmentation Laser"
      ],
      "activation_status": "disabled_not_in_general_facial_engine",
      "correction_role": "hero_corrective",
      "preferred_zone_groups": [
        "lips"
      ],
      "avoid_or_protection_zones": [
        "peri_orbital"
      ],
      "feature_response": {
        "active_inflammatory_acne": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "comedonal_congestion": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "oiliness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "erythema_redness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "barrier_stress": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visual_dehydration": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "pore_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "texture_roughness": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "visible_pigmentation": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "underlying_pigment_support": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "luminosity_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "fine_line_visibility": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 1,
          "confidence": "low",
          "expected_onset": "weeks",
          "clinical_role": "secondary",
          "notes": "Any texture benefit is secondary and not the treatment objective."
        },
        "visible_laxity": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "firmness_appearance_loss": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "peri_orbital_concern": {
          "immediate_strength_0_to_5": 0,
          "course_strength_0_to_5": 0,
          "confidence": "low",
          "expected_onset": "none",
          "clinical_role": "none",
          "notes": ""
        },
        "lip_pigmentation": {
          "immediate_strength_0_to_5": 1,
          "course_strength_0_to_5": 5,
          "confidence": "high",
          "expected_onset": "weeks",
          "clinical_role": "direct",
          "notes": "Controlled studies support 1064-nm and 532-nm Q-switched Nd:YAG approaches for selected hyperpigmented lips."
        }
      },
      "non_core_benefits": [
        "lip color evenness"
      ],
      "patient_facing_benefit_claims": {
        "immediate_or_short_term": [
          "temporary darkening or mild swelling may precede improvement"
        ],
        "course_or_delayed": [
          "progressive lip pigment reduction in selected patients"
        ]
      },
      "limitations_and_non_claims": [
        "Disabled until Dr. Aakriti defines diagnosis exclusions, test-spot policy, wavelength selection, fluence, endpoint, herpes history management and aftercare.",
        "The general Q-Switch protection rule for lips must remain active unless this separate doctor-only protocol is explicitly selected."
      ],
      "expected_transient_effects": [
        "erythema",
        "swelling",
        "temporary darkening",
        "crusting depending on endpoint",
        "recurrence risk",
        "herpes reactivation risk"
      ],
      "combination_logic": {
        "preferred_partners": [
          "final_serum_moisturizer_sunscreen"
        ],
        "conditional_partners": [],
        "avoid_same_session": [
          "lip_peels",
          "other_lip_energy"
        ]
      },
      "sequence_role": [
        "doctor_clearance",
        "test_spot_if_required",
        "targeted_laser",
        "recovery"
      ],
      "typical_duration_minutes": {
        "min": 5,
        "max": 15
      },
      "downtime_band": "moderate",
      "evidence": {
        "overall_tier": "A",
        "references": [
          "QSWITCH_LIP_RCT",
          "QSWITCH_LIP_532"
        ],
        "calibration_status": "engineering_prior_requires_clinic_validation"
      },
      "settings_source": "ai_customised_within_doctor_constraints",
      "notes": []
    }
  }
}


function applyClinicStepDurationOverridesV3_4() {
  for (const [modalityId, entry] of Object.entries(
    RAW_LIBRARY.modalities,
  )) {
    const durationRange =
      getClinicDurationRangeV3_4(modalityId)
    if (!durationRange) continue
    entry.typical_duration_minutes = {
      ...durationRange,
    }
    entry.duration_source =
      'clinic_staff_observed_operational_time'
    entry.duration_rules_version =
      CLINIC_STEP_DURATION_RULES_VERSION
  }
}

applyClinicStepDurationOverridesV3_4()

export const MODALITY_RESPONSE_LIBRARY_V2 = Object.freeze(RAW_LIBRARY.modalities)
export const MODALITY_RESPONSE_EVIDENCE_REGISTRY_V2 = Object.freeze(RAW_LIBRARY.evidence_registry)
export const MODALITY_RESPONSE_STRENGTH_SCALE_V2 = Object.freeze(RAW_LIBRARY.strength_scale)
export const MODALITY_RESPONSE_GLOBAL_CLAIM_RULES_V2 = Object.freeze(RAW_LIBRARY.global_claim_rules)

export function resolveResponseModalityIdV2(modality) {
  if (!modality) return null
  if (MODALITY_RESPONSE_LIBRARY_V2[modality]) return modality

  const normalized = String(modality).trim().toLowerCase()
  for (const [id, entry] of Object.entries(MODALITY_RESPONSE_LIBRARY_V2)) {
    if (entry.name.toLowerCase() === normalized) return id
    if ((entry.aliases ?? []).some((alias) => alias.toLowerCase() === normalized)) return id
  }

  const eligibilityId = resolveModalityIdV2(modality)
  if (!eligibilityId) return null

  const direct = Object.entries(MODALITY_RESPONSE_LIBRARY_V2).find(
    ([, entry]) => entry.eligibility_profile_id === eligibilityId,
  )
  return direct?.[0] ?? null
}

export function getModalityResponseV2(modality) {
  const id = resolveResponseModalityIdV2(modality)
  return id ? MODALITY_RESPONSE_LIBRARY_V2[id] : null
}

export function getFeatureResponseV2(modality, featureId) {
  if (!CORE_FEATURE_IDS.includes(featureId)) {
    throw new Error(`Unknown core feature: ${featureId}`)
  }
  const entry = getModalityResponseV2(modality)
  return entry?.feature_response?.[featureId] ?? null
}

export function listCandidateModalitiesForFeatureV2(
  featureId,
  {
    phase = 'course',
    minimumStrength = 1,
    activeOnly = true,
    includeSupportive = true,
  } = {},
) {
  if (!CORE_FEATURE_IDS.includes(featureId)) {
    throw new Error(`Unknown core feature: ${featureId}`)
  }

  const strengthKey =
    phase === 'immediate'
      ? 'immediate_strength_0_to_5'
      : 'course_strength_0_to_5'

  return Object.entries(MODALITY_RESPONSE_LIBRARY_V2)
    .filter(([, entry]) => !activeOnly || entry.activation_status.startsWith('active'))
    .filter(([, entry]) => includeSupportive || !entry.correction_role.includes('supportive'))
    .map(([modality_id, entry]) => ({
      modality_id,
      modality_name: entry.name,
      activation_status: entry.activation_status,
      correction_role: entry.correction_role,
      response: entry.feature_response[featureId],
      strength: entry.feature_response[featureId][strengthKey],
      evidence_tier: entry.evidence.overall_tier,
    }))
    .filter((candidate) => candidate.strength >= minimumStrength)
    .sort((a, b) => b.strength - a.strength || a.modality_name.localeCompare(b.modality_name))
}

export function calculateModalityFeatureUtilityPriorV2({
  modality,
  featureId,
  phase = 'course',
  burdenScore1To100,
  concernPriority0To1 = 1,
  eligibilityMultiplier0To1 = 1,
  zoneMultiplier0To1 = 1,
  evidenceMultiplier = null,
}) {
  const response = getFeatureResponseV2(modality, featureId)
  if (!response) throw new Error(`Missing response for ${modality} / ${featureId}`)

  const strength =
    phase === 'immediate'
      ? response.immediate_strength_0_to_5
      : response.course_strength_0_to_5

  const confidenceMultiplier = {
    high: 1,
    medium: 0.85,
    low: 0.65,
  }[response.confidence] ?? 0.6

  const evidenceTier = getModalityResponseV2(modality).evidence.overall_tier
  const defaultEvidenceMultiplier = {
    A: 1,
    B: 0.9,
    C: 0.75,
    D: 0.55,
  }[evidenceTier] ?? 0.5

  const burden = Math.max(0, Math.min(100, Number(burdenScore1To100))) / 100
  return Number(
    (
      burden *
      Math.max(0, Math.min(1, concernPriority0To1)) *
      (strength / 5) *
      Math.max(0, Math.min(1, eligibilityMultiplier0To1)) *
      Math.max(0, Math.min(1, zoneMultiplier0To1)) *
      confidenceMultiplier *
      (evidenceMultiplier ?? defaultEvidenceMultiplier)
    ).toFixed(4),
  )
}

export function auditModalityResponseLibraryV2() {
  const errors = []
  const warnings = []

  for (const [modalityId, entry] of Object.entries(MODALITY_RESPONSE_LIBRARY_V2)) {
    const responseKeys = Object.keys(entry.feature_response ?? {})
    const missing = CORE_FEATURE_IDS.filter((featureId) => !responseKeys.includes(featureId))
    const extra = responseKeys.filter((featureId) => !CORE_FEATURE_IDS.includes(featureId))
    if (missing.length) errors.push(`${modalityId} missing: ${missing.join(', ')}`)
    if (extra.length) errors.push(`${modalityId} has unknown features: ${extra.join(', ')}`)

    for (const featureId of CORE_FEATURE_IDS) {
      const response = entry.feature_response?.[featureId]
      if (!response) continue
      for (const field of ['immediate_strength_0_to_5', 'course_strength_0_to_5']) {
        const value = response[field]
        if (!Number.isInteger(value) || value < 0 || value > 5) {
          errors.push(`${modalityId}.${featureId}.${field} must be integer 0..5`)
        }
      }
    }

    if (entry.activation_status.startsWith('active') && !entry.evidence?.overall_tier) {
      errors.push(`${modalityId} has no evidence tier`)
    }
    if (!entry.eligibility_profile_id && entry.activation_status === 'active') {
      warnings.push(`${modalityId} has no dedicated eligibility profile`)
    }
  }

  return {
    version: MODALITY_RESPONSE_LIBRARY_VERSION,
    modality_count: Object.keys(MODALITY_RESPONSE_LIBRARY_V2).length,
    feature_count: CORE_FEATURE_IDS.length,
    errors,
    warnings,
    valid: errors.length === 0,
  }
}
