# Skin State Scoring Hardening V3.3

## Accepted production principles

1. Absolute image scoring is history-blind.
2. Treatment context does not change the absolute score.
3. No dynamic questions are used for the general facial engine.
4. No OpenCV or external QC layer is required.
5. Image mode identity is supplied by the backend.
6. Low visibility reduces confidence, not the visible-severity estimate.
7. One shared morphology map prevents cross-feature double counting.
8. The final 1–100 score is calculated by deterministic JavaScript.
9. Validated assessments are immutable and reused downstream.
10. Post-treatment reactivity is interpreted in the outcome layer, not discounted by the absolute scorer.

## Five-mode order

- `red`
- `subsurface_polarized`
- `surface_polarized`
- `white`
- `woods_uv`

## Specialist modules

- Surface: congestion, oiliness, dehydration, pores, texture, luminosity.
- Inflammation: active acne, erythema/redness, barrier stress.
- Pigment: visible/background pigmentation, underlying pigment support, lip pigment.
- Structure: peri-orbital concern, fine lines, laxity, firmness.

All modules consume the same morphology/exclusion map.

## Continuous primitives

For outcome-sensitive features, each component may include:

```json
{
  "severity_grade_0_to_5": 3,
  "measurement_primitives": {
    "coverage_0_to_100": 45,
    "contrast_0_to_100": 55,
    "cross_mode_corroboration_0_to_100": 70,
    "regional_salience_0_to_100": 50
  }
}
```

The anchored grade remains the dominant input. Continuous primitives provide sensitivity within the grade without transferring final-score control to the vision model.

## Morphology exclusions

Examples:

- post-inflammatory marks do not count as active inflammatory lesions;
- raised lesions, scars, shadows and beard/stubble do not define diffuse background pigment;
- glare does not define oiliness or luminosity;
- dehydration micro-lines are distinguished from persistent structural lines.

## Low-visibility fallback

```text
assessable
→ full aggregation weight

partially assessable
→ best estimate retained
→ reduced aggregation weight and reliability

not assessable
→ zone excluded
```

The engine does not convert uncertainty into a healthier score.
