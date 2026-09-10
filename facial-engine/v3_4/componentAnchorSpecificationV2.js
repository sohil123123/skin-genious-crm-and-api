export const COMPONENT_ANCHOR_SPECIFICATION_VERSION = 'aia_component_anchors_v2.0.0'

export const COMPONENT_ANCHOR_SPECIFICATION_V2 = {
  "version": "aia_component_anchors_v2.0.0",
  "grade_semantics": {
    "0": "Absent: no clinically meaningful burden in the assessed zone.",
    "1": "Minimal: trace or isolated finding, usually visible only on close enhanced inspection.",
    "2": "Mild: clearly present but limited in intensity, extent, or frequency.",
    "3": "Moderate: obvious and clinically meaningful within the zone.",
    "4": "Marked: prominent, broad, dense, or visually dominant within the zone.",
    "5": "Severe: extreme, confluent, very dense, or dominant enough to define the zone."
  },
  "global_rules": [
    "Grade every component independently within each anatomical zone.",
    "Use the feature's designated lead modes first and corroboration modes second.",
    "Do not convert enhanced-mode brightness or contrast directly into pathology without recognising a coherent clinical pattern.",
    "Use the exact same anchors for baseline and post-treatment scans.",
    "Do not alter a grade because a treatment was expected to work.",
    "If a zone is partially assessable, grade conservatively and record the artifact.",
    "If a zone is not assessable, return grade 0 only as a structural placeholder and mark assessment_status as not_assessable; the mapper must exclude that zone from clinical aggregation.",
    "For identical image hashes, return the cached evidence packet rather than rescoring."
  ],
  "features": {
    "active_inflammatory_acne": {
      "feature_definition": "Visible active inflammatory acne burden, excluding post-inflammatory marks and isolated porphyrin fluorescence.",
      "lead_modes": [
        "white",
        "subsurface_polarized"
      ],
      "support_modes": [
        "red",
        "surface_polarized"
      ],
      "support_only_modes": [
        "woods_uv"
      ],
      "components": {
        "inflammatory_lesion_prominence": {
          "definition": "How visually prominent individual papules, pustules, or clearly inflamed lesions are in the zone.",
          "anchors": {
            "0": "No visible inflammatory lesion.",
            "1": "One trace or equivocal inflammatory spot; barely distinguishable.",
            "2": "A few small, clearly visible inflammatory lesions with low prominence.",
            "3": "Several obvious papules/pustules or one notably prominent lesion.",
            "4": "Multiple prominent inflamed lesions that visually dominate part of the zone.",
            "5": "Dense, very prominent, confluent, nodular, or severe inflammatory lesions dominating the zone."
          },
          "exclusions": [
            "Flat pigment marks without active inflammation",
            "Porphyrin fluorescence alone",
            "Normal follicular dots"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "inflammatory_lesion_density": {
          "definition": "Regional concentration of active inflammatory lesions, judged as an ordinal density rather than an invented exact count.",
          "anchors": {
            "0": "No active inflammatory lesions.",
            "1": "Isolated single lesion or equivalent trace burden.",
            "2": "Few scattered lesions with large unaffected areas.",
            "3": "Several lesions distributed through a meaningful part of the zone.",
            "4": "Many lesions with limited clear skin between them.",
            "5": "Very dense or near-confluent inflammatory lesion burden."
          },
          "exclusions": [
            "Post-acne marks",
            "Non-inflamed comedones"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "inflammatory_clustering": {
          "definition": "Degree to which active lesions form clinically meaningful local clusters.",
          "anchors": {
            "0": "No clustering.",
            "1": "One weak pair or doubtful micro-cluster.",
            "2": "Small limited cluster with otherwise scattered/clear zone.",
            "3": "One clear cluster or multiple small clusters.",
            "4": "Large or multiple dense clusters occupying much of the zone.",
            "5": "Confluent or widespread clustered inflammation."
          },
          "exclusions": [
            "Random isolated lesions",
            "UV-only hotspots without visible lesions"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "deep_lesion_support": {
          "definition": "Subsurface evidence suggesting deeper inflammatory lesions, not merely surface redness.",
          "anchors": {
            "0": "No deep-lesion support.",
            "1": "Equivocal or trace subsurface prominence.",
            "2": "One or few mildly deeper-appearing lesions.",
            "3": "Clear deeper inflammatory support in part of the zone.",
            "4": "Multiple marked deep lesions or strong subsurface burden.",
            "5": "Severe nodular/deep inflammatory pattern."
          },
          "exclusions": [
            "Diffuse red illumination",
            "Shadow",
            "Normal contour"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "comedonal_congestion": {
      "feature_definition": "Open/closed comedones and follicular plugging, distinct from normal pores.",
      "lead_modes": [
        "surface_polarized",
        "white"
      ],
      "support_modes": [
        "woods_uv"
      ],
      "support_only_modes": [
        "red"
      ],
      "components": {
        "open_comedone_prominence": {
          "definition": "Visibility and burden of dark open comedonal plugs.",
          "anchors": {
            "0": "None.",
            "1": "One or a few barely visible dark plugs.",
            "2": "Limited small open comedones in a focal area.",
            "3": "Clear moderate open-comedone burden in the zone.",
            "4": "Numerous prominent open comedones across much of the zone.",
            "5": "Very dense, widespread, visually dominant open comedones."
          },
          "exclusions": [
            "Normal follicular openings",
            "Hair follicles",
            "Pigment specks"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "closed_comedone_prominence": {
          "definition": "Visibility and burden of flesh-coloured/whitish closed comedonal bumps.",
          "anchors": {
            "0": "None.",
            "1": "One or a few equivocal bumps.",
            "2": "Limited mild closed comedones.",
            "3": "Several clearly visible closed comedones.",
            "4": "Numerous prominent closed comedones across much of the zone.",
            "5": "Very dense or near-confluent closed-comedone burden."
          },
          "exclusions": [
            "Milia unless clinically intended",
            "Normal texture",
            "Dehydration microrelief"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "follicular_congestion": {
          "definition": "Overall visual pattern of follicular plugging or congestion.",
          "anchors": {
            "0": "No congestion pattern.",
            "1": "Trace follicular plugging visible only on close inspection.",
            "2": "Mild focal congestion.",
            "3": "Moderate, clearly recognisable congestion.",
            "4": "Marked widespread follicular plugging.",
            "5": "Severe dense/confluent congestion dominating the zone."
          },
          "exclusions": [
            "Pores without plugs",
            "UV fluorescence alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "distribution_extent": {
          "definition": "Fraction of the zone meaningfully affected by congestion.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "oiliness": {
      "feature_definition": "Visible oil-film burden and follicular activity, excluding specular glare.",
      "lead_modes": [
        "white",
        "surface_polarized"
      ],
      "support_modes": [
        "woods_uv"
      ],
      "components": {
        "visible_shine_intensity": {
          "definition": "Strength of clinically plausible oil shine in the zone.",
          "anchors": {
            "0": "No visible oil shine.",
            "1": "Trace sheen only.",
            "2": "Mild clear sheen.",
            "3": "Moderate obvious shine.",
            "4": "Marked strong shine that dominates the zone.",
            "5": "Severe wet/greasy appearance."
          },
          "exclusions": [
            "Flash/specular glare",
            "Moisturiser or treatment residue",
            "Sweat"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "shine_coverage": {
          "definition": "Fraction of the zone showing clinically plausible oil shine.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [
            "Single glare hotspot"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "follicular_oil_activity": {
          "definition": "Follicular/sebaceous activity supported by surface and UV patterns.",
          "anchors": {
            "0": "No meaningful support.",
            "1": "Trace follicular activity.",
            "2": "Mild focal activity.",
            "3": "Moderate clear activity.",
            "4": "Marked widespread activity.",
            "5": "Severe dense follicular activity."
          },
          "exclusions": [
            "Porphyrin fluorescence alone is support, not visible oil"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "oil_film_uniformity_loss": {
          "definition": "Patchiness or imbalance of oil distribution across the zone.",
          "anchors": {
            "0": "Even/normal oil distribution.",
            "1": "Minimal patchiness.",
            "2": "Mild unevenness.",
            "3": "Moderate patchy oil film.",
            "4": "Markedly uneven oily and non-oily patches.",
            "5": "Severely irregular oil-film distribution."
          },
          "exclusions": [
            "Lighting gradient"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "erythema_redness": {
      "feature_definition": "Clinically coherent diffuse erythema, vascular prominence, and inflammatory redness—not red-mode brightness itself.",
      "lead_modes": [
        "red"
      ],
      "support_modes": [
        "white",
        "subsurface_polarized"
      ],
      "support_only_modes": [
        "woods_uv"
      ],
      "components": {
        "diffuse_erythema_intensity": {
          "definition": "Intensity of coherent diffuse erythema in the zone.",
          "anchors": {
            "0": "No meaningful erythema.",
            "1": "Trace blush/reactivity.",
            "2": "Mild but clear erythema.",
            "3": "Moderate obvious erythema.",
            "4": "Marked strong erythema dominating the zone.",
            "5": "Severe intense/confluent erythema."
          },
          "exclusions": [
            "Uniform red illumination",
            "Warm colour cast",
            "Makeup"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "vascular_pattern_prominence": {
          "definition": "Prominence of recognisable linear/branching vessel-like patterns.",
          "anchors": {
            "0": "No vessel pattern.",
            "1": "Trace fine vessel-like detail.",
            "2": "Mild limited vascular pattern.",
            "3": "Moderate clear vascular structures.",
            "4": "Marked dense/branching vascular pattern.",
            "5": "Severe widespread prominent vascular network."
          },
          "exclusions": [
            "Texture edges",
            "Hair",
            "Compression marks"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "affected_coverage": {
          "definition": "Fraction of the zone with coherent erythema/vascular involvement.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [
            "Global red-mode background"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "inflammatory_hotspots": {
          "definition": "Focal inflammatory red hotspots distinct from diffuse background.",
          "anchors": {
            "0": "None.",
            "1": "One equivocal hotspot.",
            "2": "Few mild hotspots.",
            "3": "Several clear hotspots.",
            "4": "Numerous marked hotspots.",
            "5": "Dense/confluent inflammatory hotspots."
          },
          "exclusions": [
            "Porphyrin signal without inflammatory morphology"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "barrier_stress": {
      "feature_definition": "Visible barrier compromise expressed as flaking, scaling, surface disruption, patchy dryness, or reactive support.",
      "lead_modes": [
        "surface_polarized",
        "woods_uv"
      ],
      "support_modes": [
        "white",
        "subsurface_polarized",
        "red"
      ],
      "components": {
        "flaking_or_scaling": {
          "definition": "Visible flaking or scaling in the zone.",
          "anchors": {
            "0": "None.",
            "1": "Trace isolated flakes.",
            "2": "Mild focal flaking.",
            "3": "Moderate clear flaking in several areas.",
            "4": "Marked widespread scaling/flaking.",
            "5": "Severe dense or confluent scaling."
          },
          "exclusions": [
            "Makeup residue",
            "Lint",
            "Image noise"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "surface_disruption": {
          "definition": "Loss of smooth surface continuity consistent with barrier disturbance.",
          "anchors": {
            "0": "Smooth/intact appearance.",
            "1": "Trace irregularity.",
            "2": "Mild limited disruption.",
            "3": "Moderate obvious surface disruption.",
            "4": "Marked broad disruption.",
            "5": "Severe cracked/very disrupted surface appearance."
          },
          "exclusions": [
            "Normal pores",
            "Acne lesions counted separately"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "patchy_hydration_signal": {
          "definition": "Patchy dry/low-hydration appearance across the zone.",
          "anchors": {
            "0": "Uniform hydration appearance.",
            "1": "Trace patchiness.",
            "2": "Mild patchy dryness.",
            "3": "Moderate clear patchiness.",
            "4": "Marked broad heterogeneous dryness.",
            "5": "Severe widespread, strongly patchy dehydration pattern."
          },
          "exclusions": [
            "Lighting/exposure mismatch"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "reactive_erythema_support": {
          "definition": "Redness/reactivity that supports barrier stress rather than primary vascular disease.",
          "anchors": {
            "0": "None.",
            "1": "Trace reactive support.",
            "2": "Mild focal reactive redness.",
            "3": "Moderate clear reactive pattern.",
            "4": "Marked widespread reactive redness.",
            "5": "Severe intense reactive inflammation."
          },
          "exclusions": [
            "Stable vascular pattern without barrier signs"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "visual_dehydration": {
      "feature_definition": "Visible dehydration appearance: reduced plumpness, dehydration micro-lines, dull reflectance, and dry-patch signal.",
      "lead_modes": [
        "surface_polarized",
        "subsurface_polarized",
        "white"
      ],
      "support_modes": [
        "woods_uv"
      ],
      "components": {
        "plumpness_deficit": {
          "definition": "Visible loss of plump, hydrated surface appearance.",
          "anchors": {
            "0": "No deficit; skin appears well plumped.",
            "1": "Trace loss of plumpness.",
            "2": "Mild flattening/dry appearance.",
            "3": "Moderate obvious plumpness deficit.",
            "4": "Marked deflated/dehydrated appearance.",
            "5": "Severe widespread loss of plumpness."
          },
          "exclusions": [
            "Structural hollowing alone",
            "Laxity alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "dehydration_micro_lines": {
          "definition": "Fine superficial lines consistent with dehydration rather than fixed structural wrinkles.",
          "anchors": {
            "0": "None.",
            "1": "Trace micro-lines only under enhancement.",
            "2": "Mild limited micro-lines.",
            "3": "Moderate clear micro-line pattern.",
            "4": "Marked dense micro-lines.",
            "5": "Severe widespread/confluent dehydration line pattern."
          },
          "exclusions": [
            "Fixed deep wrinkles"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "reflectance_dullness": {
          "definition": "Dull or uneven reflectance consistent with low hydration appearance.",
          "anchors": {
            "0": "Bright/even reflectance.",
            "1": "Trace dullness.",
            "2": "Mild dullness.",
            "3": "Moderate obvious dull/flat appearance.",
            "4": "Marked low-lustre appearance.",
            "5": "Severe widespread matte/very dull appearance."
          },
          "exclusions": [
            "Pigmentation alone",
            "Exposure error"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "dry_patch_signal": {
          "definition": "Visible or UV-supported dry-patch pattern.",
          "anchors": {
            "0": "None.",
            "1": "Trace isolated patch.",
            "2": "Mild focal patches.",
            "3": "Moderate multiple/clear patches.",
            "4": "Marked broad dry-patch burden.",
            "5": "Severe widespread/confluent dry patches."
          },
          "exclusions": [
            "Product residue",
            "Makeup"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "pore_visibility": {
      "feature_definition": "Visible prominence and distribution of pores, independent of congestion where possible.",
      "lead_modes": [
        "surface_polarized"
      ],
      "support_modes": [
        "white"
      ],
      "support_only_modes": [
        "woods_uv"
      ],
      "components": {
        "pore_prominence": {
          "definition": "How visually prominent individual pores appear.",
          "anchors": {
            "0": "Pores not meaningfully distinguishable beyond normal follicular texture.",
            "1": "A few faint pores visible only on close enhanced inspection.",
            "2": "Clearly visible pores in a limited area.",
            "3": "Moderately prominent pores, obvious within the zone.",
            "4": "Marked pores that strongly shape surface appearance.",
            "5": "Very prominent/widespread pores dominating the zone."
          },
          "exclusions": [
            "Comedones counted separately",
            "Enhancement noise"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "pore_distribution_extent": {
          "definition": "Fraction of the zone with meaningfully visible pores.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "pore_edge_clarity_loss": {
          "definition": "Irregular, enlarged, or blurred pore-edge appearance.",
          "anchors": {
            "0": "Crisp/normal follicular edges.",
            "1": "Trace irregularity.",
            "2": "Mild edge enlargement/irregularity.",
            "3": "Moderate clear edge distortion.",
            "4": "Marked widespread edge distortion.",
            "5": "Severe very irregular/enlarged pore-edge pattern."
          },
          "exclusions": [
            "Motion blur",
            "Focus loss"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "congestion_support": {
          "definition": "Contribution of visible follicular congestion to pore appearance.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild focal support.",
            "3": "Moderate support.",
            "4": "Marked widespread support.",
            "5": "Severe dense congestion contribution."
          },
          "exclusions": [
            "Pores without plugs"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "texture_roughness": {
      "feature_definition": "Surface roughness and microrelief irregularity, excluding normal pores and enhancement noise.",
      "lead_modes": [
        "surface_polarized"
      ],
      "support_modes": [
        "white",
        "woods_uv"
      ],
      "components": {
        "surface_roughness": {
          "definition": "Visible coarse or rough surface appearance.",
          "anchors": {
            "0": "Smooth.",
            "1": "Trace roughness.",
            "2": "Mild focal roughness.",
            "3": "Moderate obvious roughness.",
            "4": "Marked broad roughness.",
            "5": "Severe very coarse/widespread roughness."
          },
          "exclusions": [
            "Normal pores",
            "Acne lesions alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "microrelief_irregularity": {
          "definition": "Irregularity of fine surface relief and transitions.",
          "anchors": {
            "0": "Uniform microrelief.",
            "1": "Trace irregularity.",
            "2": "Mild limited irregularity.",
            "3": "Moderate clear irregularity.",
            "4": "Marked broad irregularity.",
            "5": "Severe chaotic/confluent microrelief."
          },
          "exclusions": [
            "Image sharpening noise"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "keratin_or_flaking_support": {
          "definition": "Keratin build-up or flaking contribution to texture.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild focal support.",
            "3": "Moderate clear support.",
            "4": "Marked widespread support.",
            "5": "Severe dominant keratin/flaking burden."
          },
          "exclusions": [
            "Residue"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "texture_uniformity_loss": {
          "definition": "Extent to which the zone lacks consistent smooth texture.",
          "anchors": {
            "0": "Uniform.",
            "1": "Trace non-uniformity.",
            "2": "Mild limited non-uniformity.",
            "3": "Moderate obvious non-uniformity.",
            "4": "Marked broad non-uniformity.",
            "5": "Severe widespread irregularity."
          },
          "exclusions": [
            "Lighting gradient"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "visible_pigmentation": {
      "feature_definition": "Patient-visible pigmentation burden from intensity, contrast, coverage, and tone unevenness.",
      "lead_modes": [
        "white",
        "subsurface_polarized"
      ],
      "support_modes": [
        "surface_polarized"
      ],
      "support_only_modes": [
        "woods_uv"
      ],
      "components": {
        "pigment_intensity": {
          "definition": "Darkness/intensity of visible pigment relative to surrounding skin.",
          "anchors": {
            "0": "No meaningful pigment.",
            "1": "Very faint/trace.",
            "2": "Mild clear darkening.",
            "3": "Moderate obvious darkening.",
            "4": "Marked dark pigment.",
            "5": "Severe very dark/dominant pigment."
          },
          "exclusions": [
            "Beard/stubble",
            "Makeup",
            "Shadow"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "contrast_to_surrounding_skin": {
          "definition": "Contrast between pigment and immediately adjacent skin.",
          "anchors": {
            "0": "No contrast.",
            "1": "Trace contrast.",
            "2": "Mild contrast.",
            "3": "Moderate obvious contrast.",
            "4": "Marked high contrast.",
            "5": "Severe stark contrast."
          },
          "exclusions": [
            "Lighting gradient"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "pigment_coverage": {
          "definition": "Fraction of the zone meaningfully affected by visible pigment.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [
            "Freckles/marks should still be counted by aggregate affected area, not bounding box"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "tone_unevenness": {
          "definition": "Mottling or uneven tonal distribution attributable to pigment.",
          "anchors": {
            "0": "Even tone.",
            "1": "Trace unevenness.",
            "2": "Mild mottling.",
            "3": "Moderate obvious unevenness.",
            "4": "Marked broad mottling.",
            "5": "Severe highly uneven/confluent tone."
          },
          "exclusions": [
            "Redness-only unevenness"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "underlying_pigment_support": {
      "feature_definition": "Enhanced-mode support for underlying/deeper/chronic pigment burden; not a definitive histological depth diagnosis.",
      "lead_modes": [
        "woods_uv",
        "subsurface_polarized"
      ],
      "support_modes": [
        "white"
      ],
      "components": {
        "underlying_signal_intensity": {
          "definition": "Strength of enhanced-mode pigment signal relative to nearby skin.",
          "anchors": {
            "0": "No meaningful signal.",
            "1": "Trace signal.",
            "2": "Mild signal.",
            "3": "Moderate clear signal.",
            "4": "Marked strong signal.",
            "5": "Severe very strong/dominant signal."
          },
          "exclusions": [
            "UV illumination gradient",
            "Hair/stubble"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "underlying_signal_coverage": {
          "definition": "Fraction of the zone with meaningful enhanced-mode pigment support.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "depth_or_chronicity_support": {
          "definition": "Pattern consistency supporting deeper or chronic pigment rather than a transient superficial tone variation.",
          "anchors": {
            "0": "No support.",
            "1": "Equivocal trace support.",
            "2": "Mild limited support.",
            "3": "Moderate coherent support.",
            "4": "Marked strong, broad support.",
            "5": "Severe widespread/dominant chronic/deeper-support pattern."
          },
          "exclusions": [
            "This is support only; do not claim histological depth"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "luminosity_loss": {
      "feature_definition": "Loss of visible glow, clarity, and even reflectance.",
      "lead_modes": [
        "white"
      ],
      "support_modes": [
        "surface_polarized",
        "subsurface_polarized"
      ],
      "components": {
        "brightness_loss": {
          "definition": "Reduction in overall visible brightness relative to a healthy even appearance.",
          "anchors": {
            "0": "No brightness loss.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate obvious loss.",
            "4": "Marked low brightness.",
            "5": "Severe very dull/dim appearance."
          },
          "exclusions": [
            "Skin tone itself",
            "Exposure settings"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "reflectance_uniformity_loss": {
          "definition": "Uneven distribution of reflected light/glow.",
          "anchors": {
            "0": "Even reflectance.",
            "1": "Trace unevenness.",
            "2": "Mild unevenness.",
            "3": "Moderate patchy reflectance.",
            "4": "Marked broad irregular reflectance.",
            "5": "Severe very uneven reflectance."
          },
          "exclusions": [
            "Specular glare"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "surface_dullness": {
          "definition": "Flat, low-lustre surface appearance.",
          "anchors": {
            "0": "No dullness.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate obvious dullness.",
            "4": "Marked dullness.",
            "5": "Severe widespread flat/matte appearance."
          },
          "exclusions": [
            "Pigmentation alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "tone_clarity_loss": {
          "definition": "Loss of clear, fresh appearance from mixed tone/texture effects.",
          "anchors": {
            "0": "Clear tone.",
            "1": "Trace loss.",
            "2": "Mild loss.",
            "3": "Moderate loss.",
            "4": "Marked muddiness/low clarity.",
            "5": "Severe highly unclear/uneven appearance."
          },
          "exclusions": [
            "Do not double-count one isolated spot"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "fine_line_visibility": {
      "feature_definition": "Visible superficial fine-line burden, separated from dehydration-only micro-lines where possible.",
      "lead_modes": [
        "surface_polarized",
        "white"
      ],
      "support_modes": [
        "woods_uv"
      ],
      "components": {
        "line_prominence": {
          "definition": "Visibility/depth appearance of fine lines.",
          "anchors": {
            "0": "None.",
            "1": "Trace lines visible only under enhancement.",
            "2": "Mild shallow lines.",
            "3": "Moderate clearly visible lines.",
            "4": "Marked prominent lines.",
            "5": "Severe deep/dominant line pattern."
          },
          "exclusions": [
            "Dehydration-only micro-lines should remain conservative"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "line_density": {
          "definition": "Regional concentration of visible lines.",
          "anchors": {
            "0": "None.",
            "1": "One/few trace lines.",
            "2": "Few scattered lines.",
            "3": "Several lines across a meaningful area.",
            "4": "Dense line pattern.",
            "5": "Very dense/confluent line network."
          },
          "exclusions": [],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "cross_mode_persistence": {
          "definition": "Persistence of line pattern across surface-polarized and white/support views.",
          "anchors": {
            "0": "No persistent pattern.",
            "1": "Single-mode trace only.",
            "2": "Mild limited persistence.",
            "3": "Moderate clear persistence.",
            "4": "Marked strong persistence.",
            "5": "Severe persistent pattern across modes and area."
          },
          "exclusions": [
            "Image sharpening artifacts"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "distribution_extent": {
          "definition": "Fraction of the zone meaningfully affected by lines.",
          "anchors": {
            "0": "0%.",
            "1": "Trace, under about 10%.",
            "2": "Limited, about 10–25%.",
            "3": "Moderate, about 25–50%.",
            "4": "Broad, about 50–75%.",
            "5": "Very broad, over about 75%."
          },
          "exclusions": [],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "visible_laxity": {
      "feature_definition": "Visible lower-face contour laxity—not measured collagen or biomechanics.",
      "lead_modes": [
        "white",
        "surface_polarized"
      ],
      "support_modes": [
        "subsurface_polarized"
      ],
      "components": {
        "jawline_definition_loss": {
          "definition": "Loss of crisp mandibular/jawline definition.",
          "anchors": {
            "0": "Crisp definition.",
            "1": "Trace softening.",
            "2": "Mild definition loss.",
            "3": "Moderate obvious softening.",
            "4": "Marked poor definition.",
            "5": "Severe major contour loss."
          },
          "exclusions": [
            "Submental fullness alone",
            "Shadow"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "pre_jowl_or_contour_shadow": {
          "definition": "Visible pre-jowl sulcus or contour shadow pattern consistent with laxity.",
          "anchors": {
            "0": "None.",
            "1": "Trace/equivocal.",
            "2": "Mild focal shadow/sulcus.",
            "3": "Moderate clear pattern.",
            "4": "Marked deep/broad pattern.",
            "5": "Severe dominant contour distortion."
          },
          "exclusions": [
            "Lighting shadow",
            "Beard shadow"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "visible_tissue_descent": {
          "definition": "Visible downward displacement/softening of lower-face tissue.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate obvious descent.",
            "4": "Marked descent.",
            "5": "Severe pronounced descent."
          },
          "exclusions": [
            "Pose-induced asymmetry"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "asymmetry": {
          "definition": "Clinically meaningful side-to-side laxity difference.",
          "anchors": {
            "0": "Symmetric.",
            "1": "Trace difference.",
            "2": "Mild difference.",
            "3": "Moderate obvious asymmetry.",
            "4": "Marked asymmetry.",
            "5": "Severe major asymmetry."
          },
          "exclusions": [
            "Head rotation",
            "Unequal lighting"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "firmness_appearance_loss": {
      "feature_definition": "Visible loss of firm/plump appearance, without claiming elastic recoil or collagen measurement.",
      "lead_modes": [
        "white",
        "surface_polarized"
      ],
      "support_modes": [
        "subsurface_polarized"
      ],
      "components": {
        "micro_laxity_appearance": {
          "definition": "Subtle visible looseness or crepey softness.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate obvious.",
            "4": "Marked.",
            "5": "Severe widespread."
          },
          "exclusions": [
            "Dehydration lines alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "contour_softness": {
          "definition": "Loss of crisp surface contour in the zone.",
          "anchors": {
            "0": "Crisp.",
            "1": "Trace softness.",
            "2": "Mild softness.",
            "3": "Moderate obvious softness.",
            "4": "Marked soft contour.",
            "5": "Severe very poorly defined contour."
          },
          "exclusions": [
            "Diffuse lighting"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "plumpness_loss": {
          "definition": "Visible loss of fullness/plump surface appearance.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe."
          },
          "exclusions": [
            "Structural hollowing alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "regional_uniformity_loss": {
          "definition": "Unevenness in visible firmness/plumpness across the zone.",
          "anchors": {
            "0": "Uniform.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe."
          },
          "exclusions": [
            "Lighting gradient"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "peri_orbital_concern": {
      "feature_definition": "Composite peri-orbital appearance separated into pigment, vascular, hollow-shadow, puffiness, and fine-line components.",
      "lead_modes": [
        "white",
        "surface_polarized",
        "subsurface_polarized"
      ],
      "support_modes": [
        "red",
        "woods_uv"
      ],
      "components": {
        "pigment_darkness": {
          "definition": "Melanin/pigment-related darkness around the eye.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe dominant pigment darkness."
          },
          "exclusions": [
            "Hollow shadow",
            "Mascara/makeup"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "vascular_darkness": {
          "definition": "Blue/red/purple vascular contribution to darkness.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe dominant vascular darkness."
          },
          "exclusions": [
            "Pigment-only darkness"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "hollow_shadow": {
          "definition": "Structural shadow from tear trough/hollowing.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate obvious.",
            "4": "Marked deep shadow.",
            "5": "Severe dominant hollow shadow."
          },
          "exclusions": [
            "Unequal lighting",
            "Head tilt"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "puffiness": {
          "definition": "Visible peri-orbital oedematous/puffy appearance.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe pronounced puffiness."
          },
          "exclusions": [
            "Normal eyelid anatomy"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "fine_lines": {
          "definition": "Visible peri-orbital fine-line burden.",
          "anchors": {
            "0": "None.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe dense lines."
          },
          "exclusions": [
            "Closed eyes alone do not invalidate assessment"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    },
    "lip_pigmentation": {
      "feature_definition": "Visible and enhanced-mode lip pigment burden, accounting for possible vascular or cosmetic contribution.",
      "lead_modes": [
        "white",
        "woods_uv"
      ],
      "support_modes": [
        "red"
      ],
      "components": {
        "visible_lip_darkness": {
          "definition": "Visible lip darkness relative to expected natural lip tone.",
          "anchors": {
            "0": "No meaningful darkness.",
            "1": "Trace.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe very dark/dominant appearance."
          },
          "exclusions": [
            "Lipstick/tint",
            "Shadow"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "melanin_support": {
          "definition": "Enhanced-mode support for melanin-dominant lip darkness.",
          "anchors": {
            "0": "None.",
            "1": "Trace/equivocal.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe strong support."
          },
          "exclusions": [
            "Vascular darkness alone"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        },
        "pigment_unevenness": {
          "definition": "Uneven distribution of lip pigment.",
          "anchors": {
            "0": "Even.",
            "1": "Trace unevenness.",
            "2": "Mild.",
            "3": "Moderate.",
            "4": "Marked.",
            "5": "Severe patchy/confluent unevenness."
          },
          "exclusions": [
            "Lip product distribution"
          ],
          "tie_break_rule": "When borderline between adjacent grades, choose the lower grade unless the lead mode and at least one corroboration mode clearly support the higher grade."
        }
      }
    }
  }
}

export function getFeatureAnchorSpecification(featureId) {
  const feature = COMPONENT_ANCHOR_SPECIFICATION_V2.features[featureId]
  if (!feature) throw new Error(`Unknown feature anchor specification: ${featureId}`)
  return feature
}

export function renderAnchorSpecificationForFeatures(featureIds) {
  const selected = Object.fromEntries(
    featureIds.map((featureId) => [
      featureId,
      getFeatureAnchorSpecification(featureId),
    ]),
  )

  return JSON.stringify(
    {
      version: COMPONENT_ANCHOR_SPECIFICATION_V2.version,
      grade_semantics: COMPONENT_ANCHOR_SPECIFICATION_V2.grade_semantics,
      global_rules: COMPONENT_ANCHOR_SPECIFICATION_V2.global_rules,
      features: selected,
    },
    null,
    2,
  )
}
