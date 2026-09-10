# AI Aesthetics Skin State V3.3 — Complete Revised Facial Engine

## Scope

This package is the general five-mode AI facial-treatment engine.

It is intentionally distinct from the Pigmentation Decode engine. The facial engine performs broad skin-state assessment, personalised facial planning, session compilation, predicted outcomes and reassessment without a dynamic diagnostic-question layer.

## Final workflow

```text
Five fixed-mode facial images
→ shared morphology and exclusion map
→ four specialist vision-evidence modules
→ deterministic Skin State scoring
→ original 15-parameter Skin Analysis Report
→ treatment-mode selection
→ personalised treatment optimisation
→ optional plan report for Multiple
→ adaptive protocol settings within Dr. Aakriti constraints
→ therapist session compiler
→ execution record
→ independent post-treatment scoring
→ Treatment Outcome Report
```

## Image-scoring architecture

### Vision inputs

The image scorer receives only:

- the five authoritative image modes;
- scan ID and content hash;
- the canonical 18-zone atlas;
- the shared morphology/exclusion map.

It does not receive:

- patient history;
- treatment history;
- baseline/post-treatment labels;
- dynamic questions;
- treatment selection;
- predicted outcome;
- a preferred treatment.

History enters only after scoring, when the eligibility and treatment-selection engines run.

### No OpenCV/QC dependency

The fixed scanner and focal-length setup are treated as the capture source of truth. No OpenCV clinical scoring or separate external scan-QA layer is required.

Low-visibility zones are handled as partially assessable:

- the model provides its best visible estimate;
- the score is not automatically lowered;
- reliability and aggregation weight are reduced;
- not-assessable zones are excluded.

### Shared morphology map

Before specialist scoring, the engine separates:

- active inflammatory lesions;
- comedonal findings;
- background pigment;
- flat focal pigment;
- raised pigmented lesions;
- scars or friction marks;
- structural shadows;
- glare;
- beard/stubble;
- dehydration micro-lines;
- persistent structural lines.

This prevents the same visible object from being counted incorrectly across acne, redness, pigmentation, oiliness, texture and fine lines.

### Score sensitivity

The backend retains anchored 0–5 evidence grades and adds controlled continuous primitives for outcome-sensitive features:

- coverage;
- contrast/intensity;
- cross-mode corroboration;
- regional salience.

The final 1–100 scores remain deterministic. The vision model does not directly invent the final score.

## Client/report score hierarchy

```text
Primary client-facing assessment
= original 15 parameters

Internal clinical and zonal support
= 16 Core Skin State features
```

The 16 internal features are not presented as an additional 16-score report.

## Treatment intelligence

The optimizer remains deterministic and modality-neutral.

It chooses actions from:

- measured feature burden;
- facial zones;
- primary and secondary concerns;
- treatment objective;
- expected immediate/course response;
- safety and eligibility;
- time budget;
- compatibility;
- marginal utility.

No modality receives a positive preference multiplier merely because it is commercially familiar.

Carbon Facial has a direct-utility gate. It is not selected for generic dullness alone. It remains available when meaningful oiliness, congestion, pore burden or an appropriate acne indication directly supports it.

## Adaptive machine settings

The clinic approves the constraint envelope. The AI selects the strongest valid client- and zone-specific settings inside it.

There is no live approval popup.

### Q-Switch facial constraints

- wavelength: 1064 nm only;
- energy: 100–2000 mJ;
- frequency: 1–10 Hz;
- fixed spot area: 1 cm²;
- passes: 1–2.

Q-Switch 532 nm, 755 nm and the separate lip-pigmentation protocol are outside the general facial engine.

### Simple facial steps

Hydrafacial probes, exfoliation devices, LED, masks, infusion, extraction, lymphatic drainage and final skincare do not require machine-preset validation merely to compile.

The AI may personalise:

- step duration;
- selected product or solution;
- zones;
- sequence;
- technique within the execution-library instructions.

### Chemical-peel neutralisation

Dr. Aakriti-approved rule:

- glycolic- or lactic-acid peels use an alkaline neutraliser;
- all other facial peels use normal saline (NS).

The AI may personalise contact time, layers, zone use and endpoint while the fixed neutralisation rule remains enforced.

## Treatment modes

### Single

- best possible appropriate result today;
- 55–70 minutes;
- multiple compatible corrective actions may be selected;
- lymphatic drainage and final skincare are mandatory.

### Express

- exactly one hero corrective;
- 30–40 minutes;
- no secondary corrective;
- supportive and mandatory finishing steps retained.

### Multiple

- up to eight sessions;
- each session 55–70 minutes;
- only the next two sessions are detailed and released;
- reassessment after each two-session block;
- future sessions remain summary-level until reassessment.

## Formal reports

### Report 1 — AI Skin Analysis Report

Generated immediately after scoring and before treatment selection.

### Report 2 — Personalised Treatment Plan Report

Generated only when the client chooses a Multiple plan.

It summarises every expected session and the recommended total session count. Package pricing is handled entirely by the clinic.

### Report 3 — Treatment Outcome Report

Generated after treatment, execution recording and post-treatment scoring.

The Expected Outcome Preview remains an optional screen, not a formal report.

## Production boundary

The backend clinical-intelligence and report-data architecture is complete.

Remaining product work:

- production frontend;
- branded report/PDF rendering;
- API/database wiring;
- real image-model integration;
- voice plugin integration;
- shadow validation and calibration with clinic cases.
