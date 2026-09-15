import { encode } from '@toon-format/toon'

// NOTE:
// This rewrite preserves the existing score names, score polarities, score bins,
// and core continuous equations wherever possible.
// The migration is semantic: old 6-mode evidence assumptions are replaced by the
// new 5-mode evidence model consumed through the upstream Feature Packet.
// The Feature Packet is the primary source of truth for scoring inputs.
// Direct image inspection is used mainly for confidence calibration,
// affected-area-image selection, and conservative tie-breaking when needed.

const NEW_MODE_SET = ['red', 'subsurface_polarized', 'surface_polarized', 'white', 'woods_uv']

const GLOBAL_MODE_GUIDE_5_MODE = {
  metadata: {
    device: 'Bitmoji A5 (5-Mode Imaging)',
    lighting_modes_used: NEW_MODE_SET,
    notes:
      'New 5-mode system: red, subsurface_polarized, surface_polarized, white, woods_uv. All scoring must preserve legacy intent and score-distribution philosophy while consuming the new upstream feature packet.',
  },
  mode_roles: {
    red: 'Primary for erythema, vascularity, inflammatory prominence, and redness subtype separation.',
    subsurface_polarized:
      'Primary for lesion conspicuity, deeper pigment contrast, subsurface scatter, deeper shadowing, and puffiness support.',
    surface_polarized:
      'Primary for pore clarity, microtexture, flaking, comedonal detail, micro-lines, contour transitions, and surface irregularity.',
    white:
      'Primary for real-world visible appearance: visible pigment, visible shine, visible lesions, visible glow, and baseline patient-facing clinical impression.',
    woods_uv:
      'Primary for porphyrin-like fluorescence, dry-patch fluorescence, pigment depth/chronicity support, subclinical hotspot support, and photo-damage support.',
  },
}

const skin_type_criteria = {
  skin_type_classification_v4_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      notes:
        'Determines overall skin type based on oiliness, dryness, sensitivity, barrier quality, pore activity, pigment response and sebaceous distribution. No treatment mapping included. Legacy skin-type intent preserved.',
    },

    mode_roles: {
      white: 'Texture, visible dryness, oil distribution, pore visibility, erythema, overall tone.',
      surface_polarized: 'Pore-edge clarity, micro-roughness, flaky texture, congestion structure.',
      subsurface_polarized:
        'Subsurface hydration contrast, deeper unevenness, lesion conspicuity support.',
      woods_uv: 'Barrier stress fluorescence, dry-patch support, porphyrin/chronicity support.',
      red: 'Sensitivity / reactivity / redness support.',
    },

    primary_metrics: {
      sebum_distribution_index: {
        description:
          'Oil pattern across T-zone and cheeks using white + surface_polarized, supported by sebum_oiliness helper bins from the feature packet.',
        range: '0-1',
        classification: {
          dry: '<0.25',
          combination: '0.25-0.55',
          oily: '>0.55',
        },
      },
      hydration_deficit_index: {
        description:
          'Dryness fluorescence and micro-flaking burden using woods_uv + surface_polarized, supported by hydration bins from the feature packet.',
        range: '0-1',
        classification: {
          well_hydrated: '<0.25',
          mildly_dehydrated: '0.25-0.45',
          dehydrated: '>0.45',
        },
      },
      pore_activity_index: {
        description:
          'Pore visibility, density, and congestion from surface_polarized + white, supported by blackhead_congestion_bin where relevant.',
        range: '0-1',
        classification: {
          minimal: '<0.25',
          moderate: '0.25-0.55',
          active: '>0.55',
        },
      },
      barrier_integrity_index: {
        description:
          'Barrier strength inferred from woods_uv dry-fluorescence support + combined barrier feature packet fields.',
        range: '0-1',
        classification: {
          strong: '<0.30',
          compromised: '0.30-0.55',
          weak: '>0.55',
        },
      },
      sensitivity_index: {
        description:
          'Redness/reactivity patterns from red + white, supported by combined_barrier_sensitivity and redness feature packet fields.',
        range: '0-1',
        classification: { low: '<0.30', moderate: '0.30-0.60', high: '>0.60' },
      },
      photo_reactivity_index: {
        description:
          'woods_uv enhancement relative to white-light visible baseline (photo-reactivity / chronicity support).',
        formula: 'woods_uv_support / (white_visible_support + 0.001)',
        range: '0-2',
      },
    },

    composite_skin_type_logic: {
      hydration_vs_oil_matrix: {
        logic: 'Combine sebum_distribution_index and hydration_deficit_index.',
        mapping: {
          dry: 'sebum <0.25 AND dehydration >=0.30',
          oily: 'sebum >0.55 AND dehydration <0.40',
          combination: 'sebum 0.25-0.55 OR mixed patterns across regions',
          balanced: 'sebum <0.40 AND dehydration <0.30 AND minimal sensitivity',
        },
      },
      sensitivity_modifier: {
        rules: [
          "If sensitivity_index>0.60 → append '_sensitive'",
          "If barrier_integrity_index>0.55 → append '_sensitive'",
          "If photo_reactivity_index>1.2 → append '_sun_reactive'",
        ],
      },
    },

    fitzpatrick_classification: {
      inputs: [
        'white-visible pigment response',
        'woods_uv melanocyte/pigment-depth response',
        'melanin density contrast',
        'tanning vs burning likelihood inferred from imaging patterns',
      ],
      logic: {
        FP1: 'Very low melanin signal, high reactivity, minimal brown tone',
        FP2: 'Low melanin, mild tanning markers, strong contrast response',
        FP3: 'Moderate melanin signal, even white/woods_uv patterns',
        FP4: 'High melanin density, lower photo-reactivity spikes',
        FP5: 'Very high melanin, deep absorption, minimal visible erythema',
        FP6: 'Exceptionally dense melanin and minimal visible scatter',
      },
    },

    polarity_metadata: {
      score_semantics: 'label',
      score_polarity: 'label_only',
      ideal_score_direction: 'maintain',
      continuous_index_name: null,
      continuous_index_polarity: 'not_applicable',
      comparison_mode: 'label_mapping',
      target_interpretation_rule:
        'Stable categorical classification; target is to maintain or move only when imaging and feature-packet evidence clearly support a different category.',
    },

    output_format: {
      skin_type: 'Dry / Oily / Combination / Balanced (+ Sensitive modifiers)',
      fitzpatrick_type: 'I-VI',
      backend_details: {
        sebum_distribution_index: '0-1',
        hydration_deficit_index: '0-1',
        pore_activity_index: '0-1',
        barrier_integrity_index: '0-1',
        sensitivity_index: '0-1',
        photo_reactivity_index: '0-2',
      },
    },
  },
}

const combined_barrier_sensitivity = {
  combined_barrier_sensitivity_v6_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Extraction)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'cheeks', 'nose', 'chin'],
      notes:
        'Unified scoring combining barrier integrity + sensitivity reactivity. Vascularity kept separate. Sensitive to treatment-driven changes such as hydration, barrier repair, and inflammation reduction. Core score equation preserved.',
    },

    mode_roles: {
      white: 'Surface dryness, roughness, flaking visibility, visible redness baseline.',
      surface_polarized:
        'Texture disruption, micro-cracks, flaky edges, barrier microtexture homogeneity.',
      subsurface_polarized: 'Hydration scatter support and deeper barrier-uniformity support.',
      woods_uv: 'Chronic dryness, keratin debris fluorescence, subclinical hotspots.',
      red: 'Redness intensity and reactive vascular prominence support.',
    },

    primary_metrics: {
      surface_texture_uniformity: {
        description:
          'Smoothness of skin surface; reduced when barrier is compromised. Primarily driven by feature packet barrier_uniformity + flaking/surface texture evidence.',
        range: '0-1',
        bands: {
          excellent: '>0.85',
          mild_disruption: '0.70-0.85',
          moderate_disruption: '0.55-0.70',
          severe_disruption: '<0.55',
        },
      },
      hydration_signal_index: {
        description:
          'Hydration proxy from combined_barrier_sensitivity.hydration_signal_index in the feature packet; supported by subsurface_polarized + white.',
        range: '0-1',
        bands: {
          hydrated: '>0.65',
          slightly_low: '0.45-0.65',
          low: '0.30-0.45',
          very_low: '<0.30',
        },
      },
      erythema_intensity_index: {
        description:
          'Redness intensity relative to neutral baseline; indicates sensitivity/reactivity.',
        range: '0-1',
        bands: {
          none: '<0.25',
          mild: '0.25-0.45',
          moderate: '0.45-0.65',
          severe: '>0.65',
        },
      },
      vascular_pattern_index: {
        description:
          'Reactive vascular features indicating sensitivity (not chronic generalized redness). Derived from redness + combined_barrier feature packet evidence.',
        range: '0-1',
      },
      barrier_uniformity_index: {
        description: 'Surface + structural barrier stability. Low values = impaired barrier.',
        range: '0-1',
        bands: {
          intact: '>0.80',
          mild_disruption: '0.60-0.80',
          disrupted: '<0.60',
        },
      },
      flaking_texture_index: {
        description: 'High-frequency texture variance from dryness / micro-flaking.',
        range: '0-1',
      },
    },

    backend_indices: {
      region_barrier_map: {
        description: 'Barrier status per region (0-1).',
        format: '{region: float}',
      },
      region_reactivity_map: {
        description: 'Sensitivity/erythema per region (0-1).',
        format: '{region: float}',
      },
      barrier_damage_pattern: {
        description: 'Categorization of dominant barrier issue.',
        rules: [
          {
            if: 'flaking_texture_index > 0.5',
            then: 'Dryness-driven impairment',
          },
          {
            if: 'hydration_signal_index < 0.40',
            then: 'Dehydration-driven impairment',
          },
          {
            if: 'barrier_uniformity_index < 0.55',
            then: 'Structural barrier disruption',
          },
          {
            if: 'erythema_intensity_index > 0.60',
            then: 'Inflammatory sensitivity',
          },
        ],
      },
      sensitivity_pattern: {
        description: 'Determines type of skin reactivity.',
        rules: [
          {
            if: 'vascular_pattern_index > 0.5 && erythema_intensity_index > 0.45',
            then: 'Vascular-reactive',
          },
          {
            if: 'barrier_uniformity_index < 0.55 && flaking_texture_index > 0.30',
            then: 'Barrier-impaired sensitive',
          },
          {
            if: 'erythema_intensity_index < 0.45 && barrier_uniformity_index > 0.60',
            then: 'Low-reactive',
          },
        ],
      },
      improvability_index: {
        description: 'How responsive the barrier + sensitivity are to treatment.',
        formula:
          '(hydration_signal_index * 0.4) + (surface_texture_uniformity * 0.3) + (1 - erythema_intensity_index) * 0.3',
        range: '0-1',
      },
    },

    combined_index_equation: {
      description: 'Continuous barrier-sensitivity burden value (0-1).',
      equation:
        'BSI = 0.30*(1 - surface_texture_uniformity) + 0.25*(1 - hydration_signal_index) + 0.25*erythema_intensity_index + 0.10*vascular_pattern_index + 0.10*flaking_texture_index',
    },

    score_bins: {
      1: {
        range: '<0.20',
        label: 'Strong Barrier / Low Sensitivity',
        anchor: 'Smooth texture, well hydrated, minimal redness or reactivity.',
      },
      2: {
        range: '0.20-0.35',
        label: 'Mildly Compromised',
        anchor: 'Early dryness or mild sensitivity but stable barrier.',
      },
      3: {
        range: '0.35-0.55',
        label: 'Moderately Compromised',
        anchor: 'Visible dryness, uneven texture, mild-to-moderate redness.',
      },
      4: {
        range: '0.55-0.75',
        label: 'Severely Compromised',
        anchor: 'Marked dryness, flaking, barrier disruption, persistent sensitivity.',
      },
      5: {
        range: '>0.75',
        label: 'Highly Sensitive / Barrier Breakdown',
        anchor: 'Severe redness, scaling, burning-prone skin; urgent barrier repair needed.',
      },
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'BSI_continuous',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: 'integer (1-5)',
      BSI_continuous: 'float 0-1',
      backend_details: {
        surface_texture_uniformity: '0-1',
        hydration_signal_index: '0-1',
        erythema_intensity_index: '0-1',
        vascular_pattern_index: '0-1',
        barrier_uniformity_index: '0-1',
        flaking_texture_index: '0-1',
        region_barrier_map: 'dict',
        region_reactivity_map: 'dict',
        barrier_damage_pattern: 'string',
        sensitivity_pattern: 'string',
        improvability_index: '0-1',
      },
    },
  },
}

const visual_acne_scoring = {
  visual_acne_scoring_v5_1_spatial: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: [
        'forehead',
        'cheek_left',
        'cheek_right',
        'nose',
        'chin',
        'jawline_left',
        'jawline_right',
      ],
      notes:
        'Scale 1-5 where 1 = minimal acne and 5 = severe/nodulocystic. Includes spatial maps & per-lesion coordinates for treatment-level intelligence. Core ASI distribution preserved. New helper bins are consumed from the feature packet.',
    },

    lesion_types: {
      open_comedone: {
        description:
          'Visible dark comedones, strongest in surface_polarized + white; minimal inflammation.',
        weight: 0.18,
      },
      closed_comedone: {
        description: 'Whitish/flesh-colored bumps, strongest in surface_polarized + white.',
        weight: 0.18,
      },
      papule: {
        description: 'Inflamed red bumps; strongest in subsurface_polarized + red + white.',
        weight: 0.26,
      },
      pustule: {
        description:
          'Papule with purulent center; best confirmed in white and subsurface_polarized with inflammatory support from red.',
        weight: 0.24,
      },
      nodule: {
        description:
          'Deep painful lesions with deeper inflammatory prominence; supported by subsurface_polarized + red + woods_uv chronicity context.',
        weight: 0.14,
      },
    },

    region_weights: {
      forehead: 0.15,
      cheek_left: 0.2,
      cheek_right: 0.2,
      nose: 0.1,
      chin: 0.15,
      jawline_left: 0.1,
      jawline_right: 0.1,
    },

    primary_metrics: {
      total_lesion_count: {
        description: 'Total lesions detected across all regions.',
        range: '0-200+',
      },
      inflammatory_ratio: {
        description: 'Inflammatory lesions / total lesions.',
        range: '0-1',
        bands: { low: '<0.25', moderate: '0.25-0.50', high: '>0.50' },
      },
      comedone_density_index: {
        description: 'Closed + open comedones normalized 0-1 across the face.',
        bands: {
          minimal: '<0.15',
          mild: '0.15-0.35',
          moderate: '0.35-0.60',
          dense: '>0.60',
        },
      },
      inflammatory_cluster_index: {
        description: 'Cluster analysis of papules/pustules/nodules using DBSCAN.',
        range: '0-1',
      },
      uv_porhyrin_load: {
        description: 'Porphyrin-like bacterial fluorescence load using woods_uv evidence.',
        range: '0-1',
      },
      chronicity_index: {
        description:
          'Post-acne/chronic burden using woods_uv + white support and post_inflammatory_mark_burden_bin.',
        range: '0-1',
      },
    },

    backend_indices: {
      region_activity_map: {
        description: 'Lesion count and severity per region.',
        format: '{region: 0-1 normalized severity}',
      },
      nodular_flag: {
        description: 'True if any nodules detected.',
        values: ['true', 'false'],
      },
      relapse_risk_index: {
        description: 'Likelihood of relapse (comedone density × chronicity).',
        formula: 'comedone_density_index * chronicity_index',
        range: '0-1',
      },
      improvability_index: {
        description: 'Short-term treatment responsiveness.',
        formula: '(1 - chronicity_index) * inflammatory_ratio',
        range: '0-1',
      },
      per_lesion_coordinate_map: {
        description: 'List of all lesions with type, coordinates, size, depth proxy.',
        format: [
          {
            id: 'string lesion_id',
            type: 'open_comedone | closed_comedone | papule | pustule | nodule',
            region:
              'forehead | cheek_left | cheek_right | nose | chin | jawline_left | jawline_right',
            x: '0-1 normalized coordinate',
            y: '0-1 normalized coordinate',
            size_radius_px: 'float',
            uv_halo_intensity: 'float 0-1 (depth/inflammation proxy)',
            severity_weighted_value: 'float 0-1 using lesion_types.weight',
          },
        ],
      },
      inflammatory_hotspot_map: {
        description: 'Cluster polygons for inflamed zones.',
        format: {
          clusters: [
            {
              cluster_id: 'string',
              lesion_ids: ['L1', 'L2', 'L3'],
              centroid: { x: '0-1', y: '0-1' },
              polygon: [
                [0.12, 0.3],
                [0.15, 0.34],
                [0.18, 0.29],
              ],
              cluster_severity: 'float 0-1',
            },
          ],
        },
      },
      acne_grid_map: {
        description: '6×4 spatial grid aligned with pigmentation grid for precision treatment.',
        components: {
          grid_size: [4, 6],
          grid_values: [
            ['0-1', '0-1', '0-1', '0-1', '0-1', '0-1'],
            ['0-1', '0-1', '0-1', '0-1', '0-1', '0-1'],
            ['0-1', '0-1', '0-1', '0-1', '0-1', '0-1'],
            ['0-1', '0-1', '0-1', '0-1', '0-1', '0-1'],
          ],
          grid_column_map: {
            0: 'left_temporal',
            1: 'left_malar_upper',
            2: 'central_glabella_nose',
            3: 'right_malar_upper',
            4: 'right_temporal',
            5: 'chin_perioral_central',
          },
          grid_row_map: {
            0: 'upper_forehead',
            1: 'mid_forehead_browline',
            2: 'malar_Tzone',
            3: 'perioral_chin_jawline',
          },
        },
        usage_notes: [
          'Cells >0.6 = treatment hotspots.',
          'Combines lesion density + inflammation + porphyrins.',
          'Laser/IPL/peel engines can allocate passes/fluence per cell.',
        ],
      },
      BIBI_index: {
        description:
          'Global Bacterial + Inflammatory Burden Index. Higher values indicate high porphyrin load + high inflammatory lesion ratio.',
        formula: '(0.6 * uv_porhyrin_load) + (0.4 * inflammatory_ratio)',
        range: '0-1',
        clinical_relevance:
          'High BIBI directs AI engine toward antibacterial, anti-inflammatory, keratolytic and bacteriostatic treatments.',
      },
    },

    normalization_logic: {
      lesion_load_normalized: {
        method: '0 at <5 lesions, 1 at >=120 lesions',
        equation: 'clip((total_lesion_count - 5) / (120 - 5), 0, 1)',
      },
      inflammation_normalized: { method: 'Use inflammatory_ratio directly.' },
      clusters_normalized: {
        method: 'Use inflammatory_cluster_index directly.',
      },
    },

    acne_severity_equation: {
      description: 'Core continuous acne severity score (0-1).',
      equation:
        'ASI = 0.40*lesion_load_normalized + 0.30*inflammation_normalized + 0.15*comedone_density_index + 0.15*inflammatory_cluster_index',
    },

    grading_scale: {
      1: {
        range: '<0.20',
        label: 'Minimal Acne',
        anchor: 'Few comedones, almost no inflammation.',
      },
      2: {
        range: '0.20-0.35',
        label: 'Mild Acne',
        anchor: 'Comedonal or occasional papules.',
      },
      3: {
        range: '0.35-0.55',
        label: 'Moderate Acne',
        anchor: 'Papules/pustules, some clusters.',
      },
      4: {
        range: '0.55-0.75',
        label: 'Marked Acne',
        anchor: 'Dense inflammatory lesions.',
      },
      5: {
        range: '>0.75',
        label: 'Severe/Nodulocystic Acne',
        anchor: 'Nodules, widespread inflammation.',
      },
    },

    decision_logic: {
      steps: [
        '1. Read feature_packet.acne as ground truth where non-null / non-borderline.',
        '2. Use subsurface_polarized + white + red as the primary active-lesion evidence family.',
        '3. Use surface_polarized + white as the primary comedone/congestion evidence family.',
        '4. Use woods_uv for porphyrin-like fluorescence and chronicity support.',
        '5. Build region_activity_map and inflammatory hotspots.',
        '6. Construct acne_grid_map from density + inflammation + porphyrin support.',
        '7. Compute ASI (Acne Severity Index).',
        '8. Map ASI to 1-5 severity.',
        '9. Output backend indices and spatial maps.',
      ],
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'ASI_continuous',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: 'integer 1-5',
      ASI_continuous: 'float 0-1',
      lesion_counts: {
        open_comedone: 'integer',
        closed_comedone: 'integer',
        papule: 'integer',
        pustule: 'integer',
        nodule: 'integer',
      },
      lesion_load_normalized: 'float 0-1',
      inflammatory_ratio: 'float 0-1',
      comedone_density_index: 'float 0-1',
      inflammatory_cluster_index: 'float 0-1',
      uv_porhyrin_load: 'float 0-1',
      chronicity_index: 'float 0-1',
      nodular_flag: 'true/false',
      region_activity_map: 'dict',
      relapse_risk_index: 'float 0-1',
      improvability_index: 'float 0-1',
      per_lesion_coordinate_map: 'array',
      inflammatory_hotspot_map: 'object',
      acne_grid_map: 'object',
      confidence: '0-1',
      BIBI_index: 'float 0-1',
    },
  },
}

const sebum_content_scoring = {
  sebum_content_scoring_v6_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'nose', 'cheeks_left', 'cheeks_right', 'chin'],
      notes:
        'Score reflects clinically visible oiliness, subclinical sebaceous activity, bacterial porphyrins, and shine dynamics. Designed to be treatment-responsive. Core SSI equation and score bins preserved.',
    },

    mode_roles: {
      white: 'Visible shine, highlight streaks, oily T-zone areas.',
      surface_polarized: 'Follicular shine structure, pore/oil film mapping, congestion detail.',
      subsurface_polarized:
        'Deeper sebaceous activity support and oily-shadow differentiation when needed.',
      woods_uv: 'Porphyrin-like fluorescence and follicular congestion support.',
      red: 'Inflammatory/oily redness support.',
    },

    primary_metrics: {
      shine_reflectance_index: {
        description: 'Specular reflection (white + surface_polarized support).',
        formula: 'max_specular_intensity / mean_skin_intensity',
        range: '0-1',
      },
      sebum_fluorescence_index: {
        description:
          'Follicular / sebaceous activity support using woods_uv + surface_polarized evidence family.',
        formula: 'fluorescent_or_follicular_activity_ratio',
        range: '0-1',
      },
      porphyrin_load_index: {
        description: 'Porphyrin-like fluorescence from woods_uv evidence.',
        formula: 'porphyrin_spot_count_normalized',
        range: '0-1',
      },
      sebaceous_congestion_index: {
        description: 'Follicular congestion using surface_polarized + woods_uv support.',
        formula: 'cluster_density_normalized',
        range: '0-1',
      },
    },

    backend_indices: {
      regional_sebum_map: {
        description: 'Per-region visible + subclinical sebum burden.',
        format: {
          forehead: '0-1',
          nose: '0-1',
          cheeks_left: '0-1',
          cheeks_right: '0-1',
          chin: '0-1',
        },
      },
      sebum_hotspot_grid: {
        description: '6×4 grid of pixel-level shine/sebum hotspots.',
        components: {
          grid_size: [4, 6],
          grid_values: 'array[4][6] with each cell 0-1',
        },
      },
      sebum_quantity_index_global: {
        description: 'Overall quantity of sebum.',
        formula: '0.45*shine_reflectance + 0.35*sebum_fluorescence + 0.20*porphyrin_load',
      },
      sebum_depth_component_index: {
        description: 'Superficial shine vs deeper follicular activity.',
        formula: 'sebaceous_congestion_index * 0.6 + porphyrin_load_index * 0.4',
        range: '0-1',
      },
      sebum_variability_index: {
        description: 'Unevenness of distribution.',
        formula: 'std(region_sebum_values)/mean(region_sebum_values)',
        range: '0-1',
      },
      improvability_index: {
        description: 'Responsiveness to treatment.',
        formula: '(1 - sebum_depth_component_index) * (1 - sebum_variability_index)',
        range: '0-1',
      },
      bacterial_inflammatory_burden_index: {
        description:
          'Combined measure of bacterial activity + inflammation derived from porphyrin support and follicular inflammatory congestion.',
        components: {
          bacterial_component: {
            formula: 'porphyrin_load_index',
            weight: 0.55,
            description: 'Primary indicator of bacterial-load support.',
          },
          subclinical_inflammation_component: {
            formula: '(sebum_fluorescence_index * 0.6) + (sebaceous_congestion_index * 0.4)',
            weight: 0.45,
            description:
              'Contribution from follicular blockage + inflammatory fluorescence/congestion patterns.',
          },
        },
        final_equation:
          'BIBI = (0.55 * porphyrin_load_index) + (0.45 * ((sebum_fluorescence_index * 0.6) + (sebaceous_congestion_index * 0.4)))',
        output_range: '0-1',
      },
    },

    normalization_logic: {
      shine_norm: 'Use shine_reflectance_index directly',
      fluorescence_norm: 'Use sebum_fluorescence_index directly',
      porphyrin_norm: 'Use porphyrin_load_index directly',
      congestion_norm: 'Use sebaceous_congestion_index directly',
    },

    sebum_burden_equation: {
      description: 'Global continuous sebum severity metric.',
      equation:
        'SSI = 0.40*shine_norm + 0.25*fluorescence_norm + 0.20*porphyrin_norm + 0.15*congestion_norm',
    },

    score_bins: {
      1: { range: '<0.20', label: 'Very Low Sebum / Dry' },
      2: { range: '0.20-0.38', label: 'Low-Normal Sebum' },
      3: { range: '0.38-0.58', label: 'Moderate Sebum' },
      4: { range: '0.58-0.78', label: 'High Sebum / Oily' },
      5: { range: '>0.78', label: 'Very Oily / Seborrheic' },
    },

    decision_logic: {
      steps: [
        '1. Read feature_packet.sebum_oiliness as ground truth where non-null / non-borderline.',
        '2. Quantify visible shine from white, with support from surface_polarized.',
        '3. Quantify follicular activity / congestion from surface_polarized + woods_uv.',
        '4. Count porphyrin-like support from woods_uv.',
        '5. Compute regional_sebum_map and sebum_hotspot_grid.',
        '6. Compute SSI.',
        '7. Map SSI to 1-5 severity.',
        '8. Compute backend indices including BIBI.',
      ],
    },

    polarity_metadata: {
      score_semantics: 'state_spectrum',
      score_polarity: 'distance_to_target',
      ideal_score_direction: 'move_toward_target',
      continuous_index_name: 'SSI_continuous',
      continuous_index_polarity: 'depends_on_target',
      comparison_mode: 'target_distance',
      target_interpretation_rule:
        'Middle-to-ideal range is best; target is movement toward the clinically appropriate balance zone rather than uniformly lower or higher values.',
    },

    output_format: {
      final_score: 'integer 1-5',
      SSI_continuous: 'float 0-1',
      shine_reflectance_index: '0-1',
      sebum_fluorescence_index: '0-1',
      porphyrin_load_index: '0-1',
      sebaceous_congestion_index: '0-1',
      backend_details: {
        regional_sebum_map: 'dict',
        sebum_hotspot_grid: '4×6 matrix',
        sebum_quantity_index_global: '0-1',
        sebum_depth_component_index: '0-1',
        sebum_variability_index: '0-1',
        improvability_index: '0-1',
        bacterial_inflammatory_burden_index: '0-1',
      },
    },
  },
}

const vascularity_redness_scoring = {
  vascularity_redness_scoring_v6: {
    metadata: {
      device: 'Bitmoji A5 Analyzer',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'cheeks', 'nose', 'chin'],
      version: '6.0',
    },

    core_parameters: {
      clinical_erythema_visibility: {
        description: 'How clearly redness is seen in white-light mode with naked-eye clarity.',
        lighting: 'white',
        role: 'Primary determinant of patient-perceived redness.',
      },
      vascular_pattern_prominence: {
        description:
          'Visibility of linear/telangiectatic vessel-like structure in red-supported vascular view.',
        lighting: ['red', 'subsurface_polarized'],
        role: 'Indicates structural vascular changes that worsen redness.',
      },
      diffuse_background_redness: {
        description: 'Uniform blotchy redness, seen best in red mode with white support.',
        lighting: ['red', 'white'],
        role: 'Represents inflammation that treatments can reduce.',
      },
      subclinical_inflammation_hotspots: {
        description: 'red/woods_uv detection of deeper inflammation clusters.',
        lighting: ['red', 'woods_uv'],
        role: 'Predicts future worsening; improves with anti-inflammatory treatments.',
      },
      sebaceous_inflammation_component: {
        description: 'Inflammatory activity linked to acne/oily zones.',
        lighting: ['red', 'woods_uv', 'surface_polarized'],
        role: 'Detects acne-associated or T-zone inflammation contributing to redness.',
      },
    },

    score_definitions: {
      1: {
        label: 'Minimal Redness',
        clinical_features: [
          'Almost no visible redness in white light',
          'No meaningful vascular structure prominence',
          'No meaningful diffuse erythema',
          'Minimal subclinical hotspots',
        ],
        patient_perception: 'Skin appears even-toned with no visible redness.',
        treatment_responsiveness: 'Small but noticeable improvements possible.',
      },
      2: {
        label: 'Mild Redness / Reactive',
        clinical_features: [
          'Faint cheek or nose redness visible only on close view',
          'Very fine vascular patterns may appear',
          'Slight background erythema',
          'Scattered microinflammatory dots',
        ],
        patient_perception: 'Occasional redness, often called sensitive skin.',
        treatment_responsiveness: 'Improves well with facials, LED, calming agents.',
      },
      3: {
        label: 'Moderate Redness',
        clinical_features: [
          'Easily visible redness in cheeks/nose in white light',
          'Clear but limited vascular structures',
          'Noticeable diffuse erythema',
          'Multiple subclinical hotspots',
        ],
        patient_perception: 'Redness is a visible cosmetic concern.',
        treatment_responsiveness: 'Strongly responsive to clinical facials, yellow LED, peels.',
      },
      4: {
        label: 'High Redness / Vascular Prominence',
        clinical_features: [
          'Obvious redness from conversational distance',
          'Dense or branching vessel-like structures',
          'Widespread erythema',
          'Strong inflammatory clusters',
        ],
        patient_perception: 'Skin appears constantly red; makeup needed to cover.',
        treatment_responsiveness:
          'Requires stronger interventions like vascular lasers or multiple sessions.',
      },
      5: {
        label: 'Severe Redness / Rosacea-like',
        clinical_features: [
          'Intense diffuse redness covering large areas',
          'Prominent vascularity',
          'Strong diffuse erythema',
          'Multiple active inflammation hotspots',
          'High acne-linked inflammatory burden if present',
        ],
        patient_perception: 'Heavy facial redness impacting confidence.',
        treatment_responsiveness: 'Significant improvement possible but requires structured plan.',
      },
    },

    backend_output: {
      clinical_erythema_score: '1-5',
      vascular_pattern_score: '1-5',
      diffuse_redness_score: '1-5',
      subclinical_hotspot_score: '1-5',
      sebaceous_inflammation_score: '1-5',
      global_vascularity_redness_score:
        'Final score (1-5 based on clinical hierarchy, not averaging)',
      BIBI_index: {
        description: 'Bacterial + Inflammatory Burden Index for redness pathways.',
        components: {
          porphyrin_component: 'Derived from woods_uv porphyrin support (0-1)',
          deep_inflammation_component: 'Derived from red/woods_uv hotspot density (0-1)',
          sebaceous_inflammation_component:
            'Red + woods_uv + surface_polarized acne-linked inflammatory support (0-1)',
        },
        formula:
          'BIBI = 0.45*porphyrin_component + 0.35*deep_inflammation_component + 0.20*sebaceous_inflammation_component',
        range: '0-1',
      },
    },

    decision_logic: {
      rules: [
        'White-light erythema sets the baseline patient-visible severity.',
        'Red-supported vascular structure can raise the score by +1 if significant.',
        'Diffuse red background erythema can raise the score by +1 if widespread.',
        'red/woods_uv hotspots refine whether redness is inflammatory or vascular.',
        'Final score reflects the highest clinically meaningful severity, not a mathematical mean.',
      ],
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'BIBI_index',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },
  },
}

const skin_hydration_scoring = {
  skin_hydration_scoring_v6_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'cheeks_left', 'cheeks_right', 'nose', 'chin'],
      notes:
        'Score reflects surface hydration, micro-line density, diffusion quality, dullness, and dryness patterns. Fully treatment-responsive and patient-perception aligned. Core HSI equation preserved.',
    },

    mode_roles: {
      white: 'Surface brightness, dullness, plumpness, fine-line visibility.',
      surface_polarized: 'Micro-lines, micro-cracks, surface irregularity.',
      subsurface_polarized: 'Deeper hydration scatter and plumpness support.',
      woods_uv: 'Dry keratin patches, scaling, uneven hydration zones, dry fluorescence.',
      red: 'Inflammation-linked hydration confounding support.',
    },

    primary_metrics: {
      surface_reflectance_index: {
        description:
          'How well hydrated skin reflects light. Hydrated skin shows smooth, even reflectance.',
        range: '0-1',
        bands: {
          very_low: '<0.30',
          low: '0.30-0.45',
          moderate: '0.45-0.60',
          good: '0.60-0.75',
          excellent: '>0.75',
        },
      },
      microline_density_index: {
        description:
          'Fine-line density from surface_polarized/white evidence. Dehydration exaggerates micro-lines.',
        range: '0-1',
        bands: {
          minimal: '<0.15',
          mild: '0.15-0.30',
          moderate: '0.30-0.45',
          marked: '0.45-0.60',
          severe: '>0.60',
        },
      },
      subsurface_diffusion_index: {
        description:
          'Light diffusion proxy for plump hydrated dermis using subsurface_polarized + white support.',
        range: '0-1',
        bands: {
          poor: '<0.40',
          fair: '0.40-0.55',
          moderate: '0.55-0.70',
          good: '0.70-0.80',
          high: '>0.80',
        },
      },
      dry_patch_fluorescence_index: {
        description: 'woods_uv detection of dry keratin, scaling, micropatch dehydration.',
        range: '0-1',
        bands: {
          none: '<0.10',
          few: '0.10-0.25',
          scattered: '0.25-0.40',
          multiple: '0.40-0.60',
          dense: '>0.60',
        },
      },
      sebum_balance_ratio: {
        description:
          'Differentiates true dehydration (low oil support) vs oil-dehydration mix using feature_packet hydration + sebum fields.',
        formula: 'sebum_presence / optimal_sebum_reference',
        range: '0-1',
        bands: {
          very_low: '<0.25',
          low: '0.25-0.40',
          balanced: '0.40-0.65',
          slightly_high: '0.65-0.80',
          high: '>0.80',
        },
      },
    },

    backend_indices: {
      regional_hydration_map: {
        description: 'Per-region hydration status for treatment personalization.',
        format: {
          region: {
            surface_reflectance_index: '0-1',
            microline_density_index: '0-1',
            dry_patch_fluorescence_index: '0-1',
            regional_hydration_score: '0-1 (combined regional score)',
          },
        },
      },
      hydration_deficit_type: {
        description: 'Characterizes dehydration type.',
        values: [
          'surface_dehydration',
          'deep_dermal_dehydration',
          'sebum_deficiency_dehydration',
          'mixed_dehydration',
          'well_hydrated',
        ],
      },
      barrier_compromise_index: {
        description:
          'Barrier dysfunction from dehydration using combined_barrier + hydration feature packet evidence.',
        range: '0-1',
      },
      hydration_recovery_potential: {
        description: 'How much hydration can improve after a single session.',
        formula: '1 - (microline_density_index * dry_patch_fluorescence_index)',
        range: '0-1',
      },
    },

    normalization_logic: {
      surface_reflectance_normalized: 'use surface_reflectance_index directly',
      microline_penalty: 'equal to microline_density_index',
      diffusion_normalized: 'use subsurface_diffusion_index directly',
      dry_patch_penalty: 'use dry_patch_fluorescence_index',
      sebum_balance_normalized: 'mapped toward ideal range (0.40-0.65)',
    },

    hydration_burden_equation: {
      description: 'Core hydration score 0-1',
      equation:
        'HSI = 0.40*surface_reflectance_index + 0.25*subsurface_diffusion_index + 0.15*(1 - microline_density_index) + 0.10*sebum_balance_ratio + 0.10*(1 - dry_patch_fluorescence_index)',
    },

    score_bins: {
      1: {
        range: '<0.30',
        label: 'Severely Dehydrated',
        anchor: 'Dull, flaky, tight appearance; marked micro-lines.',
      },
      2: {
        range: '0.30-0.45',
        label: 'Moderately Dehydrated',
        anchor: 'Uneven reflectance, scattered dry patches, visible fine lines.',
      },
      3: {
        range: '0.45-0.60',
        label: 'Mild Dehydration',
        anchor: 'Healthy but lacks plumpness; minor dullness.',
      },
      4: {
        range: '0.60-0.75',
        label: 'Well Hydrated',
        anchor: 'Smooth surface, good glow, soft micro-lines.',
      },
      5: {
        range: '>0.75',
        label: 'Optimally Hydrated',
        anchor: 'Plump, luminous, radiant appearance with high diffusion.',
      },
    },

    decision_logic: {
      steps: [
        '1. Read feature_packet.hydration as ground truth where non-null / non-borderline.',
        '2. Use white + surface_polarized for surface reflectance/microline support.',
        '3. Use subsurface_polarized for deeper diffusion/plumpness support.',
        '4. Use woods_uv for dry fluorescence support.',
        '5. Compute regional hydration metrics and full-face averages.',
        '6. Normalize all metrics (0-1).',
        '7. Calculate HSI using hydration_burden_equation.',
        '8. Map HSI to 1-5 hydration score.',
        '9. Generate backend indices (hydration type, barrier compromise, recovery potential).',
      ],
    },

    polarity_metadata: {
      score_semantics: 'health',
      score_polarity: 'higher_is_better',
      ideal_score_direction: 'increase',
      continuous_index_name: 'HSI_continuous',
      continuous_index_polarity: 'higher_is_better',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Higher score is better; target is progressive upward movement toward 5 while preserving clinical realism and avoiding overstating short-term gains.',
    },

    output_format: {
      final_score: 'integer 1-5',
      HSI_continuous: 'float 0-1',
      surface_reflectance_index: '0-1',
      microline_density_index: '0-1',
      subsurface_diffusion_index: '0-1',
      dry_patch_fluorescence_index: '0-1',
      sebum_balance_ratio: '0-1',
      regional_hydration_map: 'dict',
      hydration_deficit_type: 'string',
      barrier_compromise_index: '0-1',
      hydration_recovery_potential: '0-1',
      confidence: '0-1',
    },
  },
}

const skin_luminosity_index = {
  skin_luminosity_index_v1_0: {
    metadata: {
      device: 'Bitmoji A5 (5-mode)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'malar_left', 'malar_right', 'nose', 'chin'],
      notes:
        'Measures surface glow, subsurface translucency, brightness, uniformity, dryness/shadow contrast. Fully treatment-responsive. Core GLI equation preserved.',
    },

    mode_roles: {
      white: 'Primary for visible glow, reflectivity, visible luminosity.',
      surface_polarized: 'Edge contrast, hotspot detection, specular / textural clarity support.',
      subsurface_polarized: 'Subsurface diffusion and translucency support.',
      red: 'Redness contamination / vascular dullness support.',
      woods_uv: 'Keratin/dryness fluorescence indicating reduced luminosity.',
    },

    primary_metrics: {
      surface_reflectance_uniformity: {
        description: 'Evenness of specular reflection under visible-light conditions.',
        formula: '1 - (stddev_reflectance / mean_reflectance)',
        range: '0-1',
        bands: {
          dull: '<0.55',
          uneven: '0.55-0.70',
          healthy: '0.70-0.82',
          radiant: '>0.82',
        },
      },

      color_luminance_index: {
        description: 'Brightness based on visible luminance normalized to reference white.',
        formula: 'mean_luminance / luminance_reference',
        range: '0-1',
        bands: {
          dull: '<0.45',
          soft: '0.45-0.60',
          bright: '0.60-0.75',
          radiant: '>0.75',
        },
      },

      subsurface_diffusion_index: {
        description: 'Light scatter depth measured using subsurface_polarized support.',
        formula: 'diffuse_spread / total_intensity',
        range: '0-1',
        bands: {
          low: '<0.45',
          moderate: '0.45-0.65',
          high: '0.65-0.80',
          very_high: '>0.80',
        },
      },

      shadow_softness_index: {
        description: 'Softness of contour transitions; lower harsh shadows = higher glow.',
        formula: '1 - (edge_contrast / mean_reflectance)',
        range: '0-1',
        bands: {
          harsh: '<0.35',
          moderate: '0.35-0.55',
          soft: '0.55-0.75',
          silky: '>0.75',
        },
      },

      sebum_gloss_index: {
        description:
          'Healthy gloss vs patchy oiliness using white + surface_polarized + feature_packet sebum bins.',
        formula: 'even_sebum_distribution_score',
        range: '0-1',
        bands: {
          dry: '<0.25',
          balanced: '0.25-0.55',
          glossy: '>0.55',
        },
      },

      dryness_dullness_index: {
        description: 'Dryness-induced dullness from woods_uv dry signal + surface microtexture.',
        formula: 'dry_signal / (total_reflectance + 1)',
        range: '0-1',
        bands: {
          none: '<0.25',
          mild: '0.25-0.45',
          moderate: '0.45-0.65',
          marked: '>0.65',
        },
      },
    },

    backend_indices: {
      regional_glow_map: {
        description: 'Glow score per region for zonal treatments.',
        format: '{region: float_0-1}',
      },

      luminosity_grid_map: {
        description: '4×6 grid for micro-zone glow targeting.',
        components: {
          grid_size: [4, 6],
          grid_values: '2D array with 0-1 normalized luminosity per cell',
        },
      },

      glow_limiting_factors: {
        description: 'Identifies what is reducing glow the most.',
        fields: {
          dullness_due_to_dryness: '0-1',
          dullness_due_to_shadows: '0-1',
          dullness_due_to_low_L: '0-1',
          dullness_due_to_texture: '0-1',
        },
      },

      treatment_responsiveness_index: {
        description: 'Predicts whether glow will improve quickly.',
        formula:
          '0.5*(1 - dryness_dullness_index) + 0.3*sebum_gloss_index + 0.2*(subsurface_diffusion_index)',
        range: '0-1',
      },
    },

    index_equation: {
      description: 'Weighted luminosity equation (0-1)',
      equation:
        'GLI = 0.30*surface_reflectance_uniformity + 0.25*color_luminance_index + 0.20*subsurface_diffusion_index + 0.15*shadow_softness_index + 0.05*sebum_gloss_index + 0.05*(1 - dryness_dullness_index)',
    },

    score_bins: {
      1: {
        range: '<0.25',
        label: 'Very Dull',
        anchor: 'Low brightness, marked dryness, uneven reflection.',
      },
      2: {
        range: '0.25-0.40',
        label: 'Mild Glow',
        anchor: 'Some brightness, but dullness/patchiness persists.',
      },
      3: {
        range: '0.40-0.60',
        label: 'Healthy Glow',
        anchor: 'Good brightness and uniformity with mild shadow softness.',
      },
      4: {
        range: '0.60-0.78',
        label: 'Radiant',
        anchor: 'Bright, even surface glow and soft facial contours.',
      },
      5: {
        range: '>0.78',
        label: 'Luminous / High Radiance',
        anchor: 'Strong surface + subsurface glow, minimal dullness.',
      },
    },

    polarity_metadata: {
      score_semantics: 'health',
      score_polarity: 'higher_is_better',
      ideal_score_direction: 'increase',
      continuous_index_name: 'GLI_continuous',
      continuous_index_polarity: 'higher_is_better',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Higher score is better; target is progressive upward movement toward 5 while preserving clinical realism and avoiding overstating short-term gains.',
    },

    output_format: {
      final_score: 'integer 1-5',
      GLI_continuous: 'float 0-1',
      backend_details: {
        surface_reflectance_uniformity: '0-1',
        color_luminance_index: '0-1',
        subsurface_diffusion_index: '0-1',
        shadow_softness_index: '0-1',
        sebum_gloss_index: '0-1',
        dryness_dullness_index: '0-1',
        regional_glow_map: 'dict region → 0-1',
        luminosity_grid_map: '4×6 matrix 0-1',
        glow_limiting_factors: 'dict',
        treatment_responsiveness_index: '0-1',
      },
    },
  },
}

const superficial_pigmentation_scoring = {
  superficial_pigmentation_scoring_v6_1: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Extraction)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'malar_left', 'malar_right', 'nose', 'chin'],
      notes:
        'Score reflects perceived superficial pigment burden in real-life lighting and is designed to be treatment-relevant, sensitive to change, and spatially precise for localized treatments. Core PPL equation and score bins preserved.',
    },

    mode_roles: {
      white:
        'Visible pigmentation and unevenness as perceived by patient; primary driver of clinical score.',
      woods_uv: 'Highlights pigment depth/chronicity support and spot clustering support.',
      subsurface_polarized: 'Supports contrast and underlying/deeper pigment contrast.',
      surface_polarized: 'Supports border sharpening, visible uniformity and segmentation support.',
      red: 'Helps separate pigment burden from redness-driven false darkening when needed.',
    },

    primary_metrics: {
      coverage_area_percent: {
        description:
          'Percentage of analyzed facial area with pigment intensity above threshold in white + woods_uv support combined.',
        bands: {
          very_low: '<5%',
          low: '5-15%',
          moderate: '15-35%',
          high: '35-60%',
          very_high: '>60%',
        },
      },

      mean_intensity_index: {
        description:
          'Average melanin-related visible intensity in pigmented pixels, normalized 0-1.',
        bands: {
          very_light: '<0.20',
          light: '0.20-0.32',
          mild: '0.32-0.48',
          moderate: '0.48-0.62',
          marked: '0.62-0.78',
          severe: '>0.78',
        },
      },

      contrast_to_surrounding_skin_index: {
        description:
          'How strongly pigmented regions stand out against adjacent non-pigmented skin in visible appearance.',
        bands: {
          very_low: '<0.15',
          low: '0.15-0.28',
          moderate: '0.28-0.45',
          high: '0.45-0.68',
          very_high: '>0.68',
        },
      },

      uniformity_index: {
        description:
          'How even the pigmentation is across the face. 1 = perfectly even, 0 = highly mottled.',
        bands: {
          even: '>=0.80',
          mottled: '0.60-0.80',
          uneven: '<0.60',
        },
      },

      border_definition_score: {
        description:
          'Sharpness of lesion edges derived from surface_polarized/subsurface_polarized support; 0-1 (0 = indistinct, 1 = sharply demarcated).',
      },
    },

    backend_indices: {
      depth_index_uv_to_woods: {
        description:
          'Legacy-preserved depth proxy name. In the 5-mode system this is computed as a closest-compatible pigment-depth proxy using woods_uv support versus visible intensity.',
        formula: 'depth_support / (visible_support + 0.001)',
        range: '0-2',
      },

      melanin_chroma_separation_index: {
        description:
          'Legacy-preserved chroma-separation proxy name. In the 5-mode system this estimates pigment-versus-redness separation using pigment-support vs redness-support evidence.',
        formula:
          '(pigment_support - redness_support) / (pigment_support + redness_support + 0.001)',
        range: '-1 to +1',
      },

      region_variation_index: {
        description: 'Variation of pigment load across forehead, cheeks, nose, chin.',
        formula: 'stddev(region_pigment_loads) / mean(region_pigment_loads)',
        range: '0-1',
      },

      asymmetry_index: {
        description: 'Left-right malar pigment asymmetry.',
        formula: 'abs(malar_left - malar_right) / ((malar_left + malar_right)/2 + 0.001)',
        range: '0-1',
      },

      uv_enhancement_ratio: {
        description:
          'Legacy-preserved chronicity/photo-damage proxy name. In the 5-mode system this is computed from woods_uv support relative to visible burden.',
        formula: 'woods_uv_support / (white_support + 0.001)',
        range: '0-2',
      },

      superficial_fraction_index: {
        description:
          'Proportion of total pigment signal likely superficial/epidermal and therefore more treatment-responsive.',
        formula: '1 - normalized_depth_indicator_ratio',
        range: '0-1',
      },

      improvability_index: {
        description:
          'Heuristic measure of how much of the pigment burden is realistically improvable in the short-to-medium term.',
        formula: 'superficial_fraction_index * (1 - chronicity_component)',
        components: {
          chronicity_component: 'clipped(uv_enhancement_ratio / 2, 0, 1)',
        },
        range: '0-1',
      },

      regional_burden_map: {
        description: 'Per-region superficial pigment burden for treatment planning.',
        format: {
          forehead: {
            coverage_area_percent: 'float 0-100',
            mean_intensity_index: 'float 0-1',
            woods_cluster_density: 'float 0-1',
            regional_PPL: 'float 0-1',
          },
          malar_left: {
            coverage_area_percent: 'float 0-100',
            mean_intensity_index: 'float 0-1',
            woods_cluster_density: 'float 0-1',
            regional_PPL: 'float 0-1',
          },
          malar_right: {
            coverage_area_percent: 'float 0-100',
            mean_intensity_index: 'float 0-1',
            woods_cluster_density: 'float 0-1',
            regional_PPL: 'float 0-1',
          },
          nose: {
            coverage_area_percent: 'float 0-100',
            mean_intensity_index: 'float 0-1',
            woods_cluster_density: 'float 0-1',
            regional_PPL: 'float 0-1',
          },
          chin: {
            coverage_area_percent: 'float 0-100',
            mean_intensity_index: 'float 0-1',
            woods_cluster_density: 'float 0-1',
            regional_PPL: 'float 0-1',
          },
        },
      },

      pigment_grid_map: {
        description:
          'Hybrid anatomical 6×4 grid of superficial pigment intensity for precise spot treatments.',
        components: {
          grid_size: '[rows, columns] → [4, 6]',
          grid_values:
            '2D array [4][6] with each cell as float 0-1 representing normalized superficial pigment intensity.',
        },
      },
    },

    scoring_logic: {
      description:
        'Determines a perceived pigment load score (1-5) without population-based Gaussian normalization, while being sensitive to mottling and regional variation.',

      normalization: {
        coverage_area_normalized: {
          method: 'Piecewise linear mapping: 0 at 0-5%, 1 at >=70%.',
          equation: 'coverage_norm = clip((coverage_area_percent - 5) / (70 - 5), 0, 1)',
        },

        mean_intensity_normalized: {
          method: 'Direct 0-1 normalization using defined bands.',
        },

        contrast_to_surrounding_skin_normalized: {
          method: 'Use contrast_to_surrounding_skin_index directly (0-1).',
        },

        woods_cluster_normalized: {
          method: 'Use woods_cluster_density directly (0-1).',
        },

        uniformity_penalty: {
          description: 'Higher penalty for mottled/uneven tone.',
          equation: 'uniformity_penalty = 1 - uniformity_index',
        },
      },

      perceived_pigment_load_equation: {
        description:
          'Core continuous load metric (0-1). Visible intensity and contrast are allowed to drive score change even when pigment distribution remains broadly similar after treatment.',
        equation:
          'PPL = 0.25 * coverage_area_normalized + 0.40 * mean_intensity_normalized + 0.15 * contrast_to_surrounding_skin_normalized + 0.15 * woods_cluster_normalized + 0.05 * ((uniformity_penalty + region_variation_index) / 2)',
      },

      score_bins: {
        1: {
          range: '<0.18',
          anchor: 'Essentially clear or only a few faint spots.',
        },
        2: {
          range: '0.18-0.36',
          anchor: 'Mild pigmentation; some spots or dullness in certain areas.',
        },
        3: {
          range: '0.36-0.58',
          anchor: 'Moderate pigmentation; uneven tone is clearly visible in daily life.',
        },
        4: {
          range: '0.58-0.78',
          anchor: 'Marked pigmentation; multiple obvious patches or dense clusters.',
        },
        5: {
          range: '>0.78',
          anchor: 'Severe, widespread pigmentation with dense signal across most regions.',
        },
      },

      reassessment_response_logic: {
        description:
          'Allows subtle but real post-treatment pigment lightening to register even when patch geography is largely unchanged.',

        inputs_required: [
          'before.mean_intensity_normalized',
          'after.mean_intensity_normalized',
          'before.contrast_to_surrounding_skin_normalized',
          'after.contrast_to_surrounding_skin_normalized',
          'before.coverage_area_normalized',
          'after.coverage_area_normalized',
          'before.woods_cluster_normalized',
          'after.woods_cluster_normalized',
        ],

        delta_definitions: {
          delta_intensity: 'before.mean_intensity_normalized - after.mean_intensity_normalized',
          delta_contrast:
            'before.contrast_to_surrounding_skin_normalized - after.contrast_to_surrounding_skin_normalized',
          delta_coverage: 'before.coverage_area_normalized - after.coverage_area_normalized',
          delta_woods_cluster: 'before.woods_cluster_normalized - after.woods_cluster_normalized',
        },

        pigment_response_index: {
          description:
            'Composite reassessment improvement metric emphasizing visible lightening over mere redistribution.',
          equation:
            'PRI = 0.50*delta_intensity + 0.25*delta_contrast + 0.15*delta_woods_cluster + 0.10*delta_coverage',
        },
      },

      steps: [
        '1. Read feature_packet.pigmentation as ground truth where non-null / non-borderline.',
        '2. Segment visible pigment burden primarily from white, with support from subsurface_polarized and woods_uv.',
        '3. Compute global coverage, intensity, contrast, uniformity and woods_uv cluster support.',
        '4. Compute region variation and pigment_grid_map.',
        '5. Normalize metrics to 0-1 and compute PPL.',
        '6. Assign final_score 1-5 based on score_bins.',
        '7. On reassessment, compute PRI using intensity, contrast, woods cluster, and coverage deltas.',
        '8. Independently compute backend indices (depth, superficial fraction, improvability, regional burden, pigment grid).',
      ],
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'PPL_continuous',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: 'integer 1-5 representing perceived superficial pigment load (PPL category).',
      backend_details: {
        PPL_continuous: 'float 0-1',
        coverage_area_percent: 'float 0-100',
        coverage_area_normalized: 'float 0-1',
        mean_intensity_index: 'float 0-1',
        mean_intensity_normalized: 'float 0-1',
        contrast_to_surrounding_skin_index: 'float 0-1',
        contrast_to_surrounding_skin_normalized: 'float 0-1',
        uniformity_index: 'float 0-1',
        woods_cluster_density: 'float 0-1',
        border_definition_score: 'float 0-1',
        depth_indicator_ratio: 'float 0-1',
        depth_index_uv_to_woods: 'float',
        melanin_chroma_separation_index: 'float -1 to +1',
        region_variation_index: 'float 0-1',
        asymmetry_index: 'float 0-1',
        uv_enhancement_ratio: 'float 0-2',
        superficial_fraction_index: 'float 0-1',
        improvability_index: 'float 0-1',
        pigment_response_index: 'float -1 to +1',
        regional_burden_map: 'dict',
        pigment_grid_map: 'object',
      },
    },
  },
}

const peri_orbital_skin_health_scoring = {
  peri_orbital_skin_health_scoring_v1_0: {
    metadata: {
      device: 'Bitmoji A5 Analyzer',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['infraorbital', 'lateral canthus', 'upper cheek junction'],
      version: '1.0',
    },

    scoring_parameters: {
      pigmentation_severity: {
        description: 'Brown/gray hyperpigmentation visible in white + woods_uv support.',
        grading_basis: 'Area, density, and uniformity of pigmentation.',
      },

      vascular_visibility: {
        description:
          'Purple/red vascular tint enhanced under red and subsurface_polarized support.',
        grading_basis: 'Prominence of vascular networks and hue intensity.',
      },

      structural_shadows_hollowness: {
        description: 'Depth of tear trough / infraorbital hollow creating shadow contrast.',
        grading_basis: 'Shadow length, boundary sharpness under white and surface_polarized.',
      },

      puffiness_edema: {
        description: 'Infraorbital swelling due to fluid or fat prolapse.',
        grading_basis: 'Forward projection / puffiness support under subsurface_polarized + white.',
      },

      texture_fine_lines: {
        description: 'Micro-lines and creases amplified in surface_polarized and white.',
        grading_basis: 'Line density and depth.',
      },
    },

    parameter_weights: {
      pigmentation_severity: 0.3,
      vascular_visibility: 0.2,
      structural_shadows_hollowness: 0.3,
      puffiness_edema: 0.1,
      texture_fine_lines: 0.1,
    },

    severity_scale: {
      1: {
        label: 'Excellent Peri-orbital Health',
        clinical_features: [
          'No obvious pigmentation',
          'Minimal vascular tint',
          'No hollowness or puffiness',
          'Fine lines barely visible',
        ],
      },

      2: {
        label: 'Mild Concerns',
        clinical_features: [
          'Mild discoloration',
          'Faint vascular hue',
          'Slight trough demarcation',
          'Occasional fine lines',
          'No significant puffiness',
        ],
      },

      3: {
        label: 'Moderate Concerns',
        clinical_features: [
          'Visible pigmentation',
          'Notable vascular tint',
          'Moderate tear trough shadowing',
          'Fine lines present at rest',
          'Mild puffiness',
        ],
      },

      4: {
        label: 'Significant Concerns',
        clinical_features: [
          'Marked pigmentation',
          'Prominent vascular visibility',
          'Deep structural hollowness',
          'Multiple fine lines',
          'Moderate puffiness',
        ],
      },

      5: {
        label: 'Severe Peri-orbital Aging / Darkness',
        clinical_features: [
          'Dense pigmentation with sharp borders',
          'Strong vascular pooling',
          'Severe hollowness with long shadows',
          'Prominent lines/wrinkling',
          'Pronounced puffiness or fat prolapse',
        ],
      },
    },

    backend_sub_indices: {
      pigment_index: {
        source_modes: ['white', 'woods_uv'],
        output: '0-100',
        description: 'Brown/gray melanin load and distribution.',
      },

      vascular_index: {
        source_modes: ['red', 'subsurface_polarized'],
        output: '0-100',
        description: 'Vascular prominence and density.',
      },

      shadow_hollow_index: {
        source_modes: ['white', 'surface_polarized'],
        output: '0-100',
        description: 'Shadow intensity, length, and edge contrast.',
      },

      puffiness_index: {
        source_modes: ['subsurface_polarized', 'white'],
        output: '0-100',
        description: 'Infraorbital bulging severity.',
      },

      texture_line_index: {
        source_modes: ['surface_polarized', 'white'],
        output: '0-100',
        description: 'Fine line count and micro-crease density.',
      },
    },

    decision_logic: {
      steps: [
        '1. Compute pigment_index from feature_packet.peri_orbital under_eye_pigment_index and pigmentation support.',
        '2. Compute vascular_index from feature_packet.peri_orbital vascular_congestion_index.',
        '3. Compute shadow_hollow_index from hollow-shadow support.',
        '4. Compute puffiness_index from puffiness support.',
        '5. Compute texture_line_index from fine-line texture support.',
        '6. Combine all into weighted global_periorbital_score.',
        '7. Map global_periorbital_score → discrete 1-5 severity level.',
      ],

      output_format: {
        final_score: 'integer (1-5)',
        backend_details: {
          pigment_index: '0-100',
          vascular_index: '0-100',
          shadow_hollow_index: '0-100',
          puffiness_index: '0-100',
          texture_line_index: '0-100',
        },
      },
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: null,
      continuous_index_polarity: 'not_applicable',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },
  },
}

const lip_pigmentation_scoring = {
  lip_pigmentation_scoring_v4_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['upper_lip', 'lower_lip', 'vermilion_border'],
      version: '4.0',
      notes:
        'Built to detect melanin-based and vascular-based lip darkening even when cosmetic masking is present. Core intent preserved.',
    },

    mode_roles: {
      white: 'Surface color tone, visible darkness, dryness exaggeration.',
      surface_polarized: 'Enhances contour and matte-vs-gloss evaluation.',
      subsurface_polarized: 'Separates intrinsic vs deeper/subsurface contribution.',
      red: 'Vascular-congestion support.',
      woods_uv: 'Intrinsic melanin / deeper pigment support.',
    },

    primary_metrics: {
      intrinsic_melanin_index: {
        description:
          'white + woods_uv + subsurface support showing true lip melanin less confounded by cosmetic masking.',
        range: '0-1',
        bands: {
          minimal: '<0.15',
          mild: '0.15-0.30',
          moderate: '0.30-0.50',
          marked: '0.50-0.70',
          severe: '>0.70',
        },
      },

      surface_darkness_index: {
        description: 'Visible tone drop after cosmetic-mask sanity filtering.',
        range: '0-1',
        bands: {
          none: '<0.10',
          faint: '0.10-0.25',
          visible: '0.25-0.45',
          obvious: '0.45-0.65',
          intense: '>0.65',
        },
      },

      lipstick_mask_confidence: {
        description: 'Classifier that measures whether visible color is cosmetic.',
        values: ['true', 'false'],
        confidence: '0-1',
      },

      vascular_congestion_index: {
        description: 'Red/vascular under-tone caused by vascular congestion.',
        range: '0-1',
        bands: {
          none: '<0.10',
          mild: '0.10-0.25',
          moderate: '0.25-0.45',
          marked: '0.45-0.65',
          severe: '>0.65',
        },
      },
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: null,
      continuous_index_polarity: 'not_applicable',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },
  },
}

const texture_pores_scoring = {
  texture_open_pores_scoring_v1_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'cheeks', 'nose', 'chin'],
      notes:
        'Texture and open pores score aligned to feature_packet.pores_texture and pores_texture_plus.',
    },

    core_metrics: {
      pore_density_index: {
        description: 'Derived from feature_packet.pores_texture_plus.pore_density_index.',
        range: '0-1',
      },

      pore_diameter_index: {
        description: 'Derived from feature_packet.pores_texture_plus.pore_diameter_index.',
        range: '0-1',
      },

      pore_clarity_index: {
        description: 'Derived from feature_packet.pores_texture_plus.pore_clarity_index.',
        range: '0-1',
      },

      texture_roughness_index: {
        description: 'Derived from feature_packet.pores_texture.texture_roughness_bin support.',
        range: '0-1',
      },

      blackhead_congestion_index: {
        description: 'Derived from feature_packet.pores_texture.blackhead_congestion_bin support.',
        range: '0-1',
      },
    },

    equation: {
      description: 'Continuous pore-texture burden.',
      formula:
        'PTI = 0.30*pore_density_index + 0.25*pore_diameter_index + 0.20*(1-pore_clarity_index) + 0.15*texture_roughness_index + 0.10*blackhead_congestion_index',
    },

    score_bins: {
      1: {
        range: '<0.18',
        label: 'Minimal Texture/Pores',
      },
      2: {
        range: '0.18-0.34',
        label: 'Mild Texture/Pores',
      },
      3: {
        range: '0.34-0.56',
        label: 'Moderate Texture/Pores',
      },
      4: {
        range: '0.56-0.76',
        label: 'Marked Texture/Pores',
      },
      5: {
        range: '>0.76',
        label: 'Severe Texture/Pores',
      },
    },

    decision_logic: {
      steps: [
        '1. Read feature_packet.pores_texture and pores_texture_plus as ground truth.',
        '2. Compute PTI.',
        '3. Map PTI to 1-5.',
        '4. Use white/surface_polarized only for confidence and affected-area-image support.',
      ],
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'PTI_continuous',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: '1-5',
      PTI_continuous: '0-1',
    },
  },
}

const superficial_wrinkles_scoring = {
  superficial_wrinkles_scoring_v1_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'cheeks', 'perioral', 'chin'],
      notes:
        'Wrinkle burden consumes feature_packet.wrinkles and wrinkles_plus. Legacy severity bins preserved.',
    },

    core_metrics: {
      wrinkle_line_count: {
        description: 'Derived from feature_packet.wrinkles.wrinkle_line_count_bin.',
      },

      wrinkle_depth_index: {
        description: 'Derived from feature_packet.wrinkles.wrinkle_depth_index.',
      },

      microline_density_index: {
        description: 'Derived from feature_packet.wrinkles_plus.wrinkle_microline_density_index.',
      },

      regional_uniformity_index: {
        description: 'Derived from feature_packet.wrinkles_plus.regional_uniformity_index.',
      },

      chronicity_uv_index: {
        description: 'Derived from feature_packet.wrinkles.chronicity_uv_index.',
      },

      structural_vs_dehydration_index: {
        description: 'Derived from feature_packet.wrinkles structural_vs_dehydration support.',
      },
    },

    equation: {
      description: 'Wrinkle burden index.',
      formula:
        'WBI = 0.30*wrinkle_depth_index + 0.20*microline_density_index + 0.15*chronicity_uv_index + 0.15*(1-regional_uniformity_index) + 0.20*structural_vs_dehydration_index',
    },

    score_bins: {
      1: {
        range: '<0.18',
        label: 'Minimal Wrinkles',
      },
      2: {
        range: '0.18-0.34',
        label: 'Mild Wrinkles',
      },
      3: {
        range: '0.34-0.54',
        label: 'Moderate Wrinkles',
      },
      4: {
        range: '0.54-0.75',
        label: 'Marked Wrinkles',
      },
      5: {
        range: '>0.75',
        label: 'Severe Wrinkles',
      },
    },

    decision_logic: {
      steps: [
        '1. Read feature_packet.wrinkles and wrinkles_plus as ground truth.',
        '2. Compute WBI.',
        '3. Map WBI to 1-5 score bins.',
        '4. Use surface_polarized/white/woods_uv only for confidence support and affected-area-image selection.',
      ],
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'WBI_continuous',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: 'integer 1-5',
      backend_details: {
        WBI_continuous: 'float 0-1',
        wrinkle_line_count: 'int',
        wrinkle_depth_index: 'float',
        microline_density_index: 'float',
        regional_uniformity_index: 'float',
        chronicity_uv_index: 'float',
        structural_vs_dehydration_index: 'float',
        improvability_index: 'float',
        regional_wrinkle_map: 'object',
        wrinkle_grid_map: 'object',
      },
    },
  },
}

const jawline_sagging_scoring = {
  jawline_sagging_scoring_v6_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['left_jawline', 'right_jawline', 'submental'],
      notes:
        'Measures lower-face contour integrity, soft-tissue descent, pre-jowl sulcus depth, and submental heaviness. Core intent preserved; now consumes feature_packet.jawline_sagging.',
    },

    mode_roles: {
      white: 'Visible contour shape, sag visibility, jowl prominence.',
      surface_polarized:
        'Shadow-edge and contour transition support for sag depth and angle deflection.',
      subsurface_polarized: 'Deeper contour shadow / fullness support when helpful.',
      woods_uv: 'Chronicity-related dermal thinning / collagen-poor support.',
      red: 'Inflammatory confounding support only.',
    },

    primary_metrics: {
      mandibular_line_deflection_angle: {
        description:
          'Deviation (in degrees) of the lower jawline from an ideal straight mandibular contour.',
        range: '0-12 degrees',
        bands: {
          excellent: '<2',
          mild: '2-4',
          moderate: '4-7',
          marked: '7-10',
          severe: '>10',
        },
      },

      pre_jowl_sulcus_depth_index: {
        description: 'Depth of depression anterior to jowl prominence.',
        range: '0-1',
      },

      jowl_bulge_prominence_index: {
        description: 'Prominence of jowl bulge.',
        range: '0-1',
      },

      submental_fullness_index: {
        description: 'Submental heaviness / fullness.',
        range: '0-1',
      },

      dermal_collagen_thinning_index: {
        description: 'Chronicity-related dermal thinning support.',
        range: '0-1',
      },

      left_right_asymmetry_index: {
        description: 'Asymmetry across left and right jawline.',
        range: '0-1',
      },
    },

    backend_indices: {
      regional_sagging_map: 'object',
      jawline_grid_map: 'object',
      contour_continuity_break_index: '0-1',
      sagging_chronicity_index: '0-1',
      fat_vs_laxity_component_split: 'object',
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: null,
      continuous_index_polarity: 'not_applicable',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: '1-5',
      backend_details: {
        mandibular_line_deflection_angle: 'float',
        pre_jowl_sulcus_depth_index: '0-1',
        jowl_bulge_prominence_index: '0-1',
        submental_fullness_index: '0-1',
        dermal_collagen_thinning_index: '0-1',
        left_right_asymmetry_index: '0-1',
        contour_continuity_break_index: '0-1',
        sagging_chronicity_index: '0-1',
        fat_vs_laxity_component_split: 'object',
        regional_sagging_map: 'object',
        jawline_grid_map: 'object',
      },
    },
  },
}

const skin_firmness_elasticity_index = {
  skin_firmness_elasticity_index_v1_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['cheeks', 'jawline', 'peri-oral', 'lower face'],
      version: '1.0',
    },

    core_metrics: {
      micro_laxity_pattern_index: {
        description:
          'Subtle sag/crepe patterns detected via surface_polarized microtexture mapping and feature packet firmness fields.',
        range: '0-1',
        bands: {
          tight: '<0.20',
          mild_laxity: '0.20-0.35',
          moderate: '0.35-0.55',
          marked: '0.55-0.75',
          severe: '>0.75',
        },
      },

      collagen_reflectance_uniformity: {
        description: 'Uniformity of collagen-linked reflectance under white and woods_uv support.',
        range: '0-1',
        bands: {
          excellent: '>0.80',
          good: '0.65-0.80',
          fair: '0.50-0.65',
          poor: '<0.50',
        },
      },

      elastic_recoil_proxy_index: {
        description:
          'Edge-sharpness + contour-response ratio from surface_polarized/white-supported feature packet fields.',
        range: '0-1',
        bands: {
          strong: '>0.75',
          mild_drop: '0.55-0.75',
          moderate_drop: '0.35-0.55',
          weak: '<0.35',
        },
      },
    },

    backend_indices: {
      regional_firmness_map: {
        description: 'Firmness score per region (0-1).',
        format: '{cheeks:0-1, jawline:0-1, peri_oral:0-1}',
      },

      collagen_loss_pattern_type: {
        description: 'Qualitative classification to guide treatment engine.',
        values: [
          'early_diffuse',
          'lower_face_predominant',
          'cheek_predominant',
          'global_mild',
          'global_severe',
        ],
      },

      improvability_index: {
        description: 'Likelihood of short-term improvement with non-invasive tightening.',
        range: '0-1',
      },
    },

    equation: {
      description: 'Continuous firmness-loss index.',
      formula:
        'FI = (0.40*micro_laxity_pattern_index) + 0.35*(1-collagen_reflectance_uniformity) + 0.25*(1-elastic_recoil_proxy_index)',
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'continuous_firmness_index',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: '1-5',
      continuous_firmness_index: '0-1',
      backend_details: {
        micro_laxity_pattern_index: '0-1',
        collagen_reflectance_uniformity: '0-1',
        elastic_recoil_proxy_index: '0-1',
        regional_firmness_map: 'dict',
        collagen_loss_pattern_type: 'string',
        improvability_index: '0-1',
      },
    },
  },
}

const textural_radiance_index = {
  textural_radiance_index_v1_0: {
    metadata: {
      device: 'Bitmoji A5 (5-Mode Imaging)',
      lighting_modes_used: NEW_MODE_SET,
      regions_analyzed: ['forehead', 'cheeks', 'nose', 'chin'],
      version: '1.0',
    },

    core_metrics: {
      micro_clarity_index: {
        description: 'How clean/clear the skin surface appears (absence of haze, film, residue).',
        source_modes: ['white', 'surface_polarized'],
        range: '0-1',
        bands: {
          crisp: '>0.80',
          good: '0.65-0.80',
          fair: '0.45-0.65',
          hazy: '<0.45',
        },
      },

      surface_smooth_scatter_index: {
        description: 'Light scatter uniformity due to smoothness (inverse of micro-roughness).',
        source_modes: ['surface_polarized', 'white'],
        range: '0-1',
        bands: {
          excellent: '>0.80',
          good: '0.65-0.80',
          moderate: '0.45-0.65',
          coarse: '<0.45',
        },
      },

      keratin_shadow_index: {
        description: 'Subclinical keratin/oil film detected in woods_uv affecting radiance.',
        range: '0-1',
        bands: {
          minimal: '<0.20',
          mild: '0.20-0.40',
          moderate: '0.40-0.60',
          marked: '>0.60',
        },
      },
    },

    backend_indices: {
      radiance_loss_pattern: {
        description: 'Guides treatment type selection.',
        values: [
          'surface_smoothness_deficit',
          'clarity_haze_deficit',
          'keratin_congestion_deficit',
          'mixed',
        ],
      },

      regional_radiance_map: {
        description: '0-1 radiance values per region.',
        format: '{forehead:0-1, cheeks:0-1, nose:0-1, chin:0-1}',
      },

      improvability_index: {
        description: 'Short-term radiance improvement potential.',
        range: '0-1',
      },
    },

    equation: {
      description: 'Continuous TRI.',
      formula:
        'TRI = 0.40*(1-micro_clarity_index) + 0.35*(1-surface_smooth_scatter_index) + 0.25*(keratin_shadow_index)',
    },

    polarity_metadata: {
      score_semantics: 'severity',
      score_polarity: 'higher_is_worse',
      ideal_score_direction: 'decrease',
      continuous_index_name: 'continuous_TRI',
      continuous_index_polarity: 'higher_is_worse',
      comparison_mode: 'direct_numeric',
      target_interpretation_rule:
        'Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.',
    },

    output_format: {
      final_score: '1-5',
      continuous_TRI: '0-1',
      backend_details: {
        micro_clarity_index: '0-1',
        surface_smooth_scatter_index: '0-1',
        keratin_shadow_index: '0-1',
        regional_radiance_map: 'dict',
        radiance_loss_pattern: 'string',
        improvability_index: '0-1',
      },
    },
  },
}

const affected_area_image_selector = {
  affected_area_image_selector: {
    selection_logic: {
      rules: [
        {
          parameter: 'skin_type',
          preferred_lighting_mode: 'white',
          fallback_mode: 'surface_polarized',
        },
        {
          parameter: 'barrier_health_sensitivity',
          preferred_lighting_mode: 'surface_polarized',
          fallback_mode: 'red',
        },
        {
          parameter: 'visual_acne_grading',
          preferred_lighting_mode: 'subsurface_polarized',
          fallback_mode: 'white',
        },
        {
          parameter: 'skin_sebum_content',
          preferred_lighting_mode: 'surface_polarized',
          fallback_mode: 'white',
        },
        {
          parameter: 'vascularity_redness_profiling',
          preferred_lighting_mode: 'red',
          fallback_mode: 'white',
        },
        {
          parameter: 'skin_hydration_score',
          preferred_lighting_mode: 'white',
          fallback_mode: 'surface_polarized',
        },
        {
          parameter: 'skin_luminosity_glow_index',
          preferred_lighting_mode: 'white',
          fallback_mode: 'surface_polarized',
        },
        {
          parameter: 'superficial_pigmentation_score',
          preferred_lighting_mode: 'woods_uv',
          fallback_mode: 'white',
        },
        {
          parameter: 'periorbital_health',
          preferred_lighting_mode: 'subsurface_polarized',
          fallback_mode: 'white',
        },
        {
          parameter: 'lip_pigmentation',
          preferred_lighting_mode: 'woods_uv',
          fallback_mode: 'white',
        },
        {
          parameter: 'texture_open_pores_scoring',
          preferred_lighting_mode: 'surface_polarized',
          fallback_mode: 'white',
        },
        {
          parameter: 'superficial_wrinkles_scoring',
          preferred_lighting_mode: 'surface_polarized',
          fallback_mode: 'white',
        },
        {
          parameter: 'jawline_sagging_score',
          preferred_lighting_mode: 'white',
          fallback_mode: 'surface_polarized',
        },
        {
          parameter: 'skin_firmness_elasticity_index',
          preferred_lighting_mode: 'white',
          fallback_mode: 'surface_polarized',
        },
        {
          parameter: 'textural_radiance_index',
          preferred_lighting_mode: 'surface_polarized',
          fallback_mode: 'white',
        },
      ],
    },

    output_format: {
      affected_area_image: '',
      use_overlay: false,
    },
  },
}

const diagnosis_json_structure = {
  diagnosis_report: {
    skin_type: {
      parameter_name: 'Skin Type Classification',
      description:
        'Classifies your skin into oily, dry, combination, or normal based on sebum distribution, shine patterns, pore visibility, and hydration cues across the 5 imaging modes.',
      client_description:
        '<A simple, jargon-free explanation of your skin type and what it means for your daily care.>',
      score_or_label: '<Skin Type>',
      score_explanation: '<Why this skin type was chosen>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<BSI_continuous | ASI_continuous | SSI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Stable categorical classification; target is to maintain or move only when imaging and feature-packet evidence clearly support a different category.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    barrier_health_sensitivity: {
      parameter_name: 'Barrier Health + Sensitivity (Combined Score)',
      description:
        'Evaluates redness, flaking, barrier uniformity, hydration stress, and reactivity using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of how calm, resilient and well-protected your skin barrier appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<BSI_continuous | ASI_continuous | SSI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    visual_acne_grading: {
      parameter_name: 'Visual Acne Grading',
      description:
        'Assesses active acne burden, comedones, inflammatory lesions and clustering using the new 5-mode system plus the feature packet.',
      client_description: '<A simple explanation of how active your acne appears today.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<Which acne features drove the score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<ASI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    skin_sebum_content: {
      parameter_name: 'Skin Sebum Index',
      description:
        'Assesses visible oiliness, shine pattern, follicular congestion, porphyrin-like support, and sebum variability using the 5-mode system plus the feature packet.',
      client_description: '<A simple explanation of how oily or balanced your skin appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the oil/sebum score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<SSI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Middle-to-ideal range is best; target is movement toward the clinically appropriate balance zone rather than uniformly lower or higher values.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    vascularity_redness_profiling: {
      parameter_name: 'Vascularity / Redness Score',
      description:
        'Assesses visible redness, vascular prominence, diffuse erythema and inflammatory hotspots using red-first evidence plus the feature packet.',
      client_description: '<A simple explanation of how red or reactive your skin appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the redness score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<BIBI_index | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    skin_hydration_score: {
      parameter_name: 'Skin Hydration Score',
      description:
        'Measures plumpness, reflectance, micro-lines, dryness fluorescence and hydration support using the 5-mode system plus the feature packet.',
      client_description: '<A simple explanation of how hydrated or dehydrated your skin appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the hydration score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<HSI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Higher score is better; target is progressive upward movement toward 5 while preserving clinical realism and avoiding overstating short-term gains.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    skin_luminosity_glow_index: {
      parameter_name: 'Skin Luminosity / Glow Index',
      description:
        'Assesses brightness, glow, reflectance uniformity and dullness using the 5-mode system plus the feature packet.',
      client_description: '<A simple explanation of how radiant or dull your skin appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the glow score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<GLI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Higher score is better; target is progressive upward movement toward 5 while preserving clinical realism and avoiding overstating short-term gains.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    superficial_pigmentation_score: {
      parameter_name: 'Superficial Pigmentation Score',
      description:
        'Assesses visible pigment burden, intensity, contrast, distribution and depth support using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of how visible your pigmentation or uneven tone appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the pigmentation score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<PPL_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    periorbital_health: {
      parameter_name: 'Peri-Orbital Health Score',
      description:
        'Assesses under-eye pigment, vascularity, hollowness, puffiness and fine-line burden using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of what the skin around your eyes looks like today.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the peri-orbital score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    lip_pigmentation: {
      parameter_name: 'Lip Pigmentation Score',
      description:
        'Assesses visible lip darkening, intrinsic melanin support and vascular congestion using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of whether your lips appear naturally even or pigmented.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the lip pigmentation score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    texture_open_pores_scoring: {
      parameter_name: 'Texture & Open Pores Score',
      description:
        'Assesses pore size, distribution, blackhead/congestion burden, and surface irregularity using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of how smooth your skin surface is and how visible your pores appear.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<Texture and pore pattern characteristics>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<PTI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    superficial_wrinkles_scoring: {
      parameter_name: 'Superficial Wrinkles Score',
      description:
        'Measures fine lines, etched lines, and early wrinkle patterns using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of any fine lines or surface wrinkles detected on your skin.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<Depth, density, and visibility factors>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<WBI_continuous | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    jawline_sagging_score: {
      parameter_name: 'Jawline Sagging Score',
      description:
        'Assesses jawline definition, tissue descent, and contour smoothness using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of the firmness and definition of your jawline area.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<Which structural findings determined the score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    skin_firmness_elasticity_index: {
      parameter_name: 'Skin Firmness & Elasticity Index',
      description:
        'Assesses subtle laxity, collagen reflectance support, and firmness behavior using the 5-mode system plus the feature packet.',
      client_description: '<A simple explanation of how firm and elastic your skin appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the firmness score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<continuous_firmness_index | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },

    textural_radiance_index: {
      parameter_name: 'Textural Radiance Index',
      description:
        'Assesses surface clarity, smooth scatter, and keratin-related radiance loss using the 5-mode system plus the feature packet.',
      client_description:
        '<A simple explanation of how clean, refined and polished your skin texture appears.>',
      score_or_label: '<Score 1-5>',
      score_explanation: '<What drove the textural radiance score>',
      affected_area_image: '<1-5>',
      possible_causes: ['<Cause 1>', '<Cause 2>'],
      score_semantics: '<severity | health | label | state_spectrum>',
      score_polarity: '<higher_is_worse | higher_is_better | label_only | depends_on_target>',
      ideal_score_direction: '<increase | decrease | maintain | move_toward_target>',
      continuous_index_name: '<continuous_TRI | null>',
      continuous_index_polarity:
        '<higher_is_worse | higher_is_better | not_applicable | depends_on_target>',
      comparison_mode: '<direct_numeric | label_mapping | target_distance>',
      target_interpretation_rule:
        '<Lower score is better; target is progressive downward movement toward 1 while preserving clinical realism and avoiding overcalling improvement.>',
      normalized_burden_0_to_1: '<0-1>',
      data_quality: {
        is_estimated: false,
        estimated_fields: [],
        estimation_basis: 'feature_packet_correlates|direct_image_inference|mixed',
        confidence_0_1: 1.0,
      },
    },
  },
  script:
    'A patient-centric script summarizing the overall findings. YOU MUST use markdown bolding (**text**) to highlight key parts relevant for improvements and recommended actions for the patient.',
}

export const SYSTEM_PROMPT_DIAGNOSIS = `
You are the diagnostic scoring engine for the AI Aesthetics 15-parameter facial assessment system.
Return STRICT JSON only.

GLOBAL PRINCIPLE:
- The upstream Feature Packet is the primary source of truth whenever a field is present and usable.
- The 5 images are still available and should be used for:
  1) confidence calibration,
  2) affected_area_image selection,
  3) conservative tie-breaking / sanity checking,
  4) explanation grounding.
- Do NOT re-invent upstream measurements if the Feature Packet already provides them.
- Preserve the original score intent, polarity, and score-distribution philosophy.
- Use the new helper bins only to improve accuracy, not to change severity standards.

5-MODE SYSTEM:
${encode(GLOBAL_MODE_GUIDE_5_MODE)}

Additional global rules:
- If a required field is missing or borderline in the feature packet, estimate conservatively from the 5 images.
- Any parameter that required estimation MUST populate its data_quality object accurately.
- confidence_0_1 must be <= 0.55 for any parameter that required estimation.
- Every parameter must declare:
  - score_semantics
  - score_polarity
  - ideal_score_direction
  - continuous_index_name
  - continuous_index_polarity
  - comparison_mode
  - target_interpretation_rule
  - data_quality

### 1. Skin Type Criteria:
${encode(skin_type_criteria)}
---
### 2. Barrier Health and Sensitivity:
${encode(combined_barrier_sensitivity)}
---
### 3. Visual Acne Grading:
${encode(visual_acne_scoring)}
---
### 4. Skin Sebum Index:
${encode(sebum_content_scoring)}
---
### 5. Vascularity / Redness Scoring:
${encode(vascularity_redness_scoring)}
---
### 6. Skin Hydration Score:
${encode(skin_hydration_scoring)}
---
### 7. Skin Luminosity Index:
${encode(skin_luminosity_index)}
---
### 8. Superficial Pigmentation Scoring:
${encode(superficial_pigmentation_scoring)}
---
### 9. Peri-Orbital Health Score:
${encode(peri_orbital_skin_health_scoring)}
---
### 10. Lip Pigmentation Score:
${encode(lip_pigmentation_scoring)}
---
### 11. Texture and Open Pores Scoring:
${encode(texture_pores_scoring)}
---
### 12. Superficial Wrinkles Scoring:
${encode(superficial_wrinkles_scoring)}
---
### 13. Jawline Sagging Score:
${encode(jawline_sagging_scoring)}
---
### 14. Skin Firmness and Elasticity Index:
${encode(skin_firmness_elasticity_index)}
---
### 15. Textural Radiance Index:
${encode(textural_radiance_index)}
---
### 16. Affected Area Image Selection:
${encode(affected_area_image_selector)}

TASK INSTRUCTIONS:
1. Analyze the 5 provided facial scan images across the lighting modes:
   - Red
   - Subsurface Polarized
   - Surface Polarized
   - White
   - Woods/UV
2. Read the upstream Feature Packet first and use it as the primary structured evidence source.
3. For each of the 15 diagnostic parameters, determine the score or label using the corresponding framework defined above.
4. For the affected_area_image field:
   - Do NOT choose lighting modes manually.
   - Use the image-mapping rules defined in Section 16.
   - Return only the image number (1-5) whose lighting corresponds to the parameter’s preferred_lighting_mode.
   - If the preferred mode is unavailable, use the fallback_mode.
5. For every parameter, populate data_quality:
   - is_estimated = true if any required part of the parameter had to be inferred beyond directly usable feature-packet evidence
   - estimated_fields = list the exact fields that were estimated
   - estimation_basis = one of:
     - feature_packet_correlates
     - direct_image_inference
     - mixed
   - confidence_0_1 = numeric confidence for that parameter
6. Return the diagnosis result strictly in valid JSON with the following base structure:
${JSON.stringify(diagnosis_json_structure)}

RULES:
- score_explanation is PATIENT-FACING. Write it in plain, everyday language that a non-expert can understand, in 1–3 short sentences explaining why this score/label was given. Keep the reasoning here (not in other fields and not outside the JSON).
- score_explanation MUST NOT expose any internal data. Never include feature-packet field names, bin names, variable names, or key=value / code-style tokens. For example, never write "(t_zone_oil_bin=mild, cheek_oil_bin=mild)" or "shine_coverage_bin=low". Translate every internal measurement into natural descriptors instead — e.g. "mild oiliness in the T-zone", "low visible shine", "skin shows little dryness". No parentheses containing raw data fields, no snake_case, no equals signs reporting values.
- client_description must be simple, patient-facing and non-technical.
- In the script field, provide an empathetic summary of the results, and use markdown bolding (**text**) to highlight areas relevant for improvement or corrective action.
- Do NOT output anything outside JSON.
- If multiple features appear, select the dominant grading pattern.
- Follow the parameter order exactly as defined:
  1. Skin Type Classification
  2. Barrier Health + Sensitivity
  3. Visual Acne Grading
  4. Skin Sebum Index
  5. Vascularity / Redness
  6. Skin Hydration
  7. Skin Luminosity / Glow
  8. Superficial Pigmentation
  9. Peri-Orbital Health
  10. Lip Pigmentation
  11. Texture + Open Pores
  12. Superficial Wrinkles
  13. Jawline Sagging
  14. Skin Firmness & Elasticity
  15. Textural Radiance
- Use exact key names from the JSON structure above.

### 17. Treatable Concerns Summary (Auto-generated from Diagnosis)
After generating the full diagnosis_report, append a second top-level JSON object named "treatable_concerns_summary".

Purpose:
- Identify parameters whose scores indicate non-ideal, abnormal, or clinically improvable conditions.
- Estimate a realistic single-session achievable target for each such parameter.
- Flag the most clinically meaningful concerns as primary concerns for treatment planning.

Generation rules:
1. Include only parameters that are meaningfully treatable or improvable in-clinic.
2. Exclude purely stable label outputs unless they directly affect treatment planning.
3. Use backend burden maps, improvability_index, response indices, confidence, and severity context when deciding inclusion.
4. target_score must represent a realistic single-session achievable outcome, not an ideal long-term goal.
5. For parameters with:
   - comparison_mode = direct_numeric:
     infer improvement direction using score_polarity and ideal_score_direction.
   - comparison_mode = target_distance:
     set target_score as a more clinically balanced value with smaller distance to the ideal zone, not merely higher or lower.
   - comparison_mode = label_mapping:
     only include if there is a clinically meaningful treatment implication.
6. is_primary_concern should be true only for the most clinically meaningful, visible, or outcome-relevant concerns for the current session.
7. reason_for_selection must be brief and grounded in diagnosis backend data, visible burden, improvability, and patient-facing relevance.
8. Preserve exact parameter naming consistency with treatment-planning inputs.

Expected appended JSON structure:
"treatable_concerns_summary": {
  "description": "Parameters showing measurable deviations and their expected improvement after a single treatment session.",
  "parameters_with_abnormal_scores": [
    {
      "parameter": "<Parameter Name>",
      "current_score": "<Score or Label>",
      "target_single_session_score": "<Realistically Achievable Single-Session Score or Label>",
      "is_primary_concern": false,
      "reason_for_selection": "<Short explanation based on diagnosis backend data>",
      "short_description": "<client facing language description with 1-2 line>",
      "score_semantics": "<label>",
      "score_polarity": "<label>",
      "ideal_score_direction": "<label>",
      "comparison_mode": "<label>"
    }
  ]
}
`
export const D_REPORT_USER_PROMPT = `
You are given 5 facial scan images of the same person captured under different light modes:
1. Red
2. Subsurface Polarized
3. Surface Polarized
4. White
5. Woods/UV

Analyze these images together with the upstream Feature Packet to determine all 15 diagnostic parameters:

1. Skin Type
2. Barrier Health
3. Visual Acne Grading
4. Skin Sebum Content
5. Vascularity / Redness Profiling
6. Skin Hydration
7. Skin Luminosity / Glow Index
8. Superficial Pigmentation Score
9. Peri-Orbital Health
10. Lip Pigmentation
11. Texture + Open Pores Grading
12. Superficial Wrinkles
13. Jawline Sagging
14. Skin Firmness and Elasticity Index
15. Textural Radiance Index

Return the output strictly in the full JSON format described in the system prompt, including:
- diagnosis_report
- treatable_concerns

Do not include any extra explanations, text, or formatting outside the JSON.
`
