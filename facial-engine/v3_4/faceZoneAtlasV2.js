/**
 * AI Aesthetics — Canonical facial zone atlas V2.
 *
 * The atlas is deliberately anatomical rather than a generic pixel grid.
 * Geometry/landmark tooling may align these zones, but no CV-derived clinical
 * severity is accepted here.
 */

export const FACE_ZONE_ATLAS_VERSION = 'aia_face_zone_atlas_v2.0.0'

export const FACE_ZONE_IDS = [
  'forehead_left',
  'forehead_center',
  'forehead_right',
  'glabella',
  'temple_left',
  'temple_right',
  'nose',
  'malar_medial_left',
  'malar_medial_right',
  'cheek_lateral_left',
  'cheek_lateral_right',
  'peri_orbital_left',
  'peri_orbital_right',
  'perioral',
  'chin',
  'jawline_left',
  'jawline_right',
  'lips',
]

export const FACE_ZONE_ATLAS_V2 = {
  version: FACE_ZONE_ATLAS_VERSION,
  capture_view: 'frontal',
  coordinate_convention:
    'Patient anatomical left/right. Normalized image coordinates may be added by the alignment layer, but are not part of clinical scoring.',

  zones: {
    forehead_left: {
      label: 'Left forehead',
      groups: ['forehead', 'upper_face'],
      mirror_zone: 'forehead_right',
      default_area_weight: 0.055,
    },
    forehead_center: {
      label: 'Central forehead',
      groups: ['forehead', 'upper_face', 't_zone'],
      mirror_zone: null,
      default_area_weight: 0.06,
    },
    forehead_right: {
      label: 'Right forehead',
      groups: ['forehead', 'upper_face'],
      mirror_zone: 'forehead_left',
      default_area_weight: 0.055,
    },
    glabella: {
      label: 'Glabella',
      groups: ['upper_face', 't_zone'],
      mirror_zone: null,
      default_area_weight: 0.035,
    },
    temple_left: {
      label: 'Left temple',
      groups: ['upper_face', 'lateral_face'],
      mirror_zone: 'temple_right',
      default_area_weight: 0.035,
    },
    temple_right: {
      label: 'Right temple',
      groups: ['upper_face', 'lateral_face'],
      mirror_zone: 'temple_left',
      default_area_weight: 0.035,
    },
    nose: {
      label: 'Nose',
      groups: ['central_face', 't_zone'],
      mirror_zone: null,
      default_area_weight: 0.07,
    },
    malar_medial_left: {
      label: 'Left inner cheek',
      groups: ['cheek', 'malar', 'central_face'],
      mirror_zone: 'malar_medial_right',
      default_area_weight: 0.085,
    },
    malar_medial_right: {
      label: 'Right inner cheek',
      groups: ['cheek', 'malar', 'central_face'],
      mirror_zone: 'malar_medial_left',
      default_area_weight: 0.085,
    },
    cheek_lateral_left: {
      label: 'Left outer cheek',
      groups: ['cheek', 'malar', 'lateral_face'],
      mirror_zone: 'cheek_lateral_right',
      default_area_weight: 0.09,
    },
    cheek_lateral_right: {
      label: 'Right outer cheek',
      groups: ['cheek', 'malar', 'lateral_face'],
      mirror_zone: 'cheek_lateral_left',
      default_area_weight: 0.09,
    },
    peri_orbital_left: {
      label: 'Left peri-orbital area',
      groups: ['peri_orbital', 'upper_face'],
      mirror_zone: 'peri_orbital_right',
      default_area_weight: 0.035,
      protection_zone: true,
    },
    peri_orbital_right: {
      label: 'Right peri-orbital area',
      groups: ['peri_orbital', 'upper_face'],
      mirror_zone: 'peri_orbital_left',
      default_area_weight: 0.035,
      protection_zone: true,
    },
    perioral: {
      label: 'Perioral area',
      groups: ['lower_face', 'central_face'],
      mirror_zone: null,
      default_area_weight: 0.05,
      protection_zone: true,
    },
    chin: {
      label: 'Chin',
      groups: ['lower_face', 'central_face', 't_zone'],
      mirror_zone: null,
      default_area_weight: 0.055,
    },
    jawline_left: {
      label: 'Left jawline',
      groups: ['lower_face', 'jawline', 'lateral_face'],
      mirror_zone: 'jawline_right',
      default_area_weight: 0.055,
    },
    jawline_right: {
      label: 'Right jawline',
      groups: ['lower_face', 'jawline', 'lateral_face'],
      mirror_zone: 'jawline_left',
      default_area_weight: 0.055,
    },
    lips: {
      label: 'Lips',
      groups: ['lips', 'lower_face', 'central_face'],
      mirror_zone: null,
      default_area_weight: 0.02,
      protection_zone: true,
    },
  },

  groups: {
    forehead: ['forehead_left', 'forehead_center', 'forehead_right'],
    t_zone: ['forehead_center', 'glabella', 'nose', 'chin'],
    cheeks: [
      'malar_medial_left',
      'malar_medial_right',
      'cheek_lateral_left',
      'cheek_lateral_right',
    ],
    peri_orbital: ['peri_orbital_left', 'peri_orbital_right'],
    jawline: ['jawline_left', 'jawline_right'],
    lower_face: ['perioral', 'chin', 'jawline_left', 'jawline_right', 'lips'],
    full_skin_face: FACE_ZONE_IDS.filter((zoneId) => zoneId !== 'lips'),
  },
}

export function assertValidZoneId(zoneId) {
  if (!FACE_ZONE_ATLAS_V2.zones[zoneId]) {
    throw new Error(`Unknown facial zone: ${zoneId}`)
  }
}

export function getZoneIdsForGroups(groups) {
  const ids = new Set()
  for (const group of groups) {
    const groupZones = FACE_ZONE_ATLAS_V2.groups[group]
    if (!groupZones) throw new Error(`Unknown facial zone group: ${group}`)
    groupZones.forEach((zoneId) => ids.add(zoneId))
  }
  return [...ids]
}
