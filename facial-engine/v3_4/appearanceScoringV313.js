// V3.13 — client-facing "present state" appearance layer.
//
// The regional measurement layer and the burden formulas that feed the treatment engine,
// constraints and safety gates are UNCHANGED. This module adds, on top of the same
// measurements plus six new appearance primitives, a second set of scores that describe
// what the client sees in the mirror. Definitions were reviewed with Dr. Aakriti Mehra
// (15 Sep 2026); her three amendments are marked [AM].
//
// Engineering weights: fixed before evaluation, not fitted to any patient's gains.
// Structural components (pits/scars, fixed wrinkles, laxity) are part of the present-state
// score but are never allowed to move in a same-day comparison.

export const APPEARANCE_VERSION_V313 = 'aia_appearance_scores_v3.13.0'

// New measurement primitives (all 0-1 anchored visual estimates, bounds default to [0,1]).
export const APPEARANCE_PRIMITIVES_V313 = Object.freeze({
  skin_hydration: ['plumpness_index', 'dewy_finish_index'],
  skin_luminosity_glow: ['diffuse_brightness_index', 'tone_evenness_index'],
  texture_open_pores: ['blackhead_count_index', 'scar_pit_index'],
})

// Client-language definitions. These are the words the client would use.
export const APPEARANCE_DEFINITIONS_V313 = Object.freeze({
  skin_hydration: 'Plump, supple, fresh surface. No tight, crepey or papery look. Fine dehydration lines softened. A healthy dewy finish.',
  skin_luminosity_glow: 'Diffuse brightness and evenness of light across the face; skin looks lit, not flat or grey. Less greyness and less visible pigment also read as more glow. [AM]',
  textural_radiance: 'Smooth, continuous surface reflection. No fine grain or micro-roughness.',
  texture_open_pores: 'Visible pores, blackheads and surface roughness; the client sees blackheads as pores. Also fewer pits and scars. [AM]',
  barrier_health_sensitivity: 'Calm, even, comfortable-looking skin. No flaking, tightness, blotchiness or reactive redness.',
  superficial_pigmentation: 'Uneven tone, tan, dark patches, freckling. Patch darkness relative to the skin around it.',
  visual_acne: 'Active spots, visible papules and pustules, whiteheads and blackheads, and marks. [AM]',
  skin_sebum: 'Greasy shine and film, mainly T-zone, against a balanced finish.',
  vascularity_redness: 'Visible redness and flushing, diffuse or patchy, beyond a healthy even tone.',
  peri_orbital_health: 'Under-eye darkness, hollowing, puffiness and fine lines.',
  superficial_wrinkles: 'Fine lines and creases. Dehydration microlines soften with hydration; fixed lines do not change in one session.',
  lip_pigmentation: 'Lip darkness and dullness against a healthy pink tone.',
  jawline_sagging: 'Lower-face contour definition. Assessed over a course, not in one session.',
  skin_firmness_elasticity: 'Firm, lifted look of the cheeks and jaw. Assessed over a course, not in one session.',
})

// Anchor ladders for the absolute baseline score (what 100 / 80 / 60 / 40 / 20 look like).
export const APPEARANCE_ANCHORS_V313 = Object.freeze({
  skin_hydration: '100 plump, dewy, no lines from dryness | 80 fresh with faint microlines | 60 slightly tight or flat, visible microlines | 40 papery patches, tight look | 20 marked dryness, crepey surface',
  skin_luminosity_glow: '100 evenly lit, bright, even tone | 80 bright with minor unevenness | 60 some flatness or grey cast, mild pigment unevenness | 40 dull, uneven tone | 20 grey, lifeless, heavily uneven',
  textural_radiance: '100 glass-smooth reflection | 80 mostly smooth | 60 fine grain visible | 40 rough, broken reflection | 20 coarse surface',
  texture_open_pores: '100 pores not visible, no blackheads, no pits | 80 few small pores or blackheads | 60 visible pores on nose and cheeks, some blackheads or shallow pits | 40 enlarged pores, many blackheads or scars | 20 coarse, congested, pitted',
  barrier_health_sensitivity: '100 calm and even | 80 mild blotchiness | 60 patchy redness or flaking | 40 reactive, flaky, uneven | 20 raw, inflamed',
  superficial_pigmentation: '100 even tone | 80 faint tan or freckling | 60 visible patches on forehead or cheeks | 40 marked patches | 20 extensive dark patches',
  visual_acne: '100 clear | 80 a few comedones | 60 several comedones, a few papules | 40 many lesions incl. pustules | 20 widespread active acne',
  skin_sebum: '100 balanced, no greasy film | 80 slight T-zone shine | 60 clear T-zone shine | 40 greasy film beyond T-zone | 20 heavy greasy shine everywhere',
})

// Prompt text appended to the measurement prompt (baseline and post scans).
export function appearanceInstructionsV313() {
  return `V3.13 APPEARANCE PRIMITIVES (client-facing present state; backend indices unchanged).
plumpness_index: how plump and supple the surface looks (0 flat/papery, .5 moderate, 1 fully plump). dewy_finish_index: healthy dewy sheen across cheeks and forehead (0 none, 1 clear dewy finish); a uniform sheen from serum, moisturiser or sunscreen COUNTS as dewy finish, do not subtract it. diffuse_brightness_index: broad, soft brightness of the skin on a matte-to-dewy surface relative to this person's own tone (0 grey/flat, 1 clearly lit); greasy T-zone shine is excluded and its absence is NOT dullness. tone_evenness_index: evenness of colour across the face incl. pigment and redness unevenness (0 very uneven, 1 even). blackhead_count_index: visible blackhead/comedone density on nose, chin and forehead (0 none, .5 moderate, 1 dense). scar_pit_index: visible pits and atrophic acne scars (0 none, 1 marked); structural, never treatment-day responsive.
DEFINITIONS (score these as what the client sees): ${Object.entries(APPEARANCE_DEFINITIONS_V313).map(([k, v]) => `${k}: ${v}`).join(' ')}
ANCHORS: ${Object.entries(APPEARANCE_ANCHORS_V313).map(([k, v]) => `${k}: ${v}`).join(' | ')}
Same visible state must receive the same absolute estimate whether this is a baseline or a post-treatment scan.`
}

const clamp = (x) => Math.max(0, Math.min(1, x))
const q = (x) => (Number.isFinite(x) ? clamp(x) : 0.5)

// Appearance quality (0-1, higher is better) from parameter-level weighted means `m` of the
// measured primitives, plus `shared` values pulled from other parameters.
export function appearanceQualityV313(id, m, shared = {}) {
  switch (id) {
    case 'skin_hydration':
      return 0.35 * q(m.plumpness_index) + 0.25 * (1 - q(m.microline_density_index)) + 0.20 * q(m.dewy_finish_index) + 0.20 * (1 - q(m.visible_dry_patch_index))
    case 'skin_luminosity_glow':
      return 0.35 * q(m.diffuse_brightness_index) + 0.25 * q(m.surface_reflectance_uniformity) + 0.20 * q(m.tone_evenness_index) + 0.20 * (1 - q(m.dryness_dullness_index))
    case 'textural_radiance':
      return 0.40 * (1 - q(shared.texture_roughness_index)) + 0.35 * q(m.surface_smooth_scatter_index) + 0.25 * (1 - q(m.keratin_shadow_index))
    case 'texture_open_pores':
      return 0.30 * (1 - q(m.pore_density_index)) + 0.25 * (1 - q(m.blackhead_count_index)) + 0.20 * (1 - q(m.texture_roughness_index)) + 0.25 * (1 - q(m.scar_pit_index))
    case 'barrier_health_sensitivity':
      return 0.30 * q(m.surface_texture_uniformity) + 0.25 * (1 - q(m.erythema_intensity_index)) + 0.20 * (1 - q(m.flaking_texture_index)) + 0.15 * (1 - q(m.vascular_pattern_index)) + 0.10 * q(m.hydration_signal_index)
    case 'superficial_pigmentation':
      return 0.40 * (1 - q(m.contrast_to_surrounding_skin_index)) + 0.30 * (1 - q(m.mean_intensity_index)) + 0.30 * (1 - q((m.coverage_area_percent ?? 50) / 100))
    case 'visual_acne':
      return 0.35 * (1 - q(m.lesion_load_normalized)) + 0.30 * (1 - q(m.inflammation_normalized)) + 0.35 * (1 - q(m.comedone_density_index))
    case 'skin_sebum': {
      // Balance target on the T-zone shine: 0.10-0.35 is balanced; over-shine penalised fully,
      // extreme dryness (no shine at all) penalised at half weight.
      const s = q(shared.tzone_shine_norm ?? m.shine_norm)
      return 1 - clamp(Math.max(0, s - 0.35) / 0.65 + 0.5 * Math.max(0, 0.10 - s) / 0.10)
    }
    case 'vascularity_redness': {
      const g = Number.isFinite(m.legacy_grade_continuous) ? m.legacy_grade_continuous : 3
      return 1 - clamp((g - 1) / 4)
    }
    case 'peri_orbital_health':
      return 1 - q(((m.pigment_index ?? 50) + (m.vascular_index ?? 50) + (m.shadow_hollow_index ?? 50) + (m.puffiness_index ?? 50) + (m.texture_line_index ?? 50)) / 500)
    case 'superficial_wrinkles':
      return 0.5 * (1 - q(m.microline_density_index)) + 0.5 * (1 - q(m.wrinkle_depth_index))
    case 'lip_pigmentation':
      return 0.5 * (1 - q(m.intrinsic_melanin_index)) + 0.5 * (1 - q(m.surface_darkness_index))
    default:
      return null // jawline_sagging, skin_firmness_elasticity: pass the engine health score through
  }
}

// Parameters compared pairwise-first in a same-day reassessment, with the core features whose
// pairwise verdicts drive them. Blackhead clearance (comedonal_congestion) now reaches pores.
export const APPEARANCE_PAIRWISE_SOURCES_V313 = Object.freeze({
  skin_hydration: ['visual_dehydration'],
  skin_luminosity_glow: ['luminosity_loss', 'visible_pigmentation'],
  textural_radiance: ['texture_roughness'],
  texture_open_pores: ['pore_visibility', 'comedonal_congestion', 'texture_roughness'],
  barrier_health_sensitivity: ['barrier_stress'],
  superficial_pigmentation: ['visible_pigmentation'],
  visual_acne: ['active_inflammatory_acne', 'comedonal_congestion'],
  skin_sebum: ['oiliness'],
  vascularity_redness: ['erythema_redness'],
  peri_orbital_health: ['peri_orbital_concern'],
  superficial_wrinkles: ['fine_line_visibility'],
  lip_pigmentation: ['lip_pigmentation'],
})

// Points per visible-change band (slight / clear / strong) on the 1-100 client scale.
export const PAIRWISE_POINTS_TABLE_V313 = Object.freeze({
  skin_hydration: [4, 8, 12], skin_luminosity_glow: [4, 8, 12], textural_radiance: [4, 8, 12],
  skin_sebum: [4, 8, 12], texture_open_pores: [4, 8, 12],
  superficial_pigmentation: [3, 6, 9], visual_acne: [3, 6, 9],
  barrier_health_sensitivity: [3, 5, 8], vascularity_redness: [3, 5, 8], peri_orbital_health: [3, 5, 8],
  superficial_wrinkles: [3, 5, 8], lip_pigmentation: [3, 5, 8],
})
// A pairwise band may exceed the measured appearance delta by at most this many points.
export const PAIRWISE_NUMERIC_MARGIN_V313 = 6

// Per-feature definitions handed to the pairwise comparison model (it previously got bare IDs).
export const PAIRWISE_FEATURE_DEFINITIONS_V313 = Object.freeze({
  active_inflammatory_acne: 'Visible papules, pustules and inflamed spots. A single fresh spot at an extraction site is treatment-day reactivity: note it, do not call the feature worsened on that alone.',
  comedonal_congestion: 'Blackheads and whiteheads on nose, chin and forehead. Fewer or emptier comedones after extraction = improved.',
  oiliness: 'Greasy shine and film on nose and central forehead only. A matte T-zone = improved. Uniform sheen from finishing serum/SPF is NOT oil.',
  erythema_redness: 'Diffuse or patchy redness on cheeks, nose and forehead in white and subsurface-polarised views. A localised fresh flush is transient; note it.',
  barrier_stress: 'Calm, even, comfortable look: absence of flaking, tightness, blotchiness and reactive redness.',
  visual_dehydration: 'Hydration appearance: plumpness, softened fine dehydration lines, fresh dewy finish. Product sheen counts for it. Judge plumpness and microlines, not water content.',
  pore_visibility: 'How visible and open the pores look on nose and cheeks. Tighter, cleaner-looking pores = improved. Pits and scars are structural: ignore them for same-day change.',
  texture_roughness: 'Fine surface grain and micro-roughness in the surface-polarised view. Smoother, more continuous reflection = improved.',
  visible_pigmentation: 'Darkness of the identified patches, tan and freckling RELATIVE to the surrounding skin of the same region, in subsurface-polarised and white. Compare the same patch by landmark. Lighting shift between scans is not lightening.',
  underlying_pigment_support: 'Deeper pigment seen under UV; slow to change. Same-day movement is unlikely.',
  luminosity_loss: 'Glow: diffuse brightness and evenness of light on a matte-to-dewy surface, relative to this person\'s own tone. Loss of greasy shine is NOT loss of glow. Less greyness and less visible pigment = improved.',
  fine_line_visibility: 'Fine lines. Only dehydration microlines can soften same-day; fixed lines do not change in one session.',
  visible_laxity: 'Lower-face contour. Structural; not assessable same-day.',
  firmness_appearance_loss: 'Firmness of cheeks and jaw. Structural; not assessable same-day.',
  peri_orbital_concern: 'Under-eye darkness, hollowing, puffiness and lines.',
  lip_pigmentation: 'Lip darkness and dullness. Only meaningful if lips were treated.',
})
