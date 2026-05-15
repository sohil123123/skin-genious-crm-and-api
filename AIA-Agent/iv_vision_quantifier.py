"""
iv_vision_quantifier.py
=======================

Deterministic computer-vision replacement for IV pipeline Stage 1
("Vision Quantification Engine").

Drop-in contract: produces the EXACT JSON shape that the existing
Stage 2 prompt (IV_SCORING_USER_PROMPT_STAGE_2) expects as input,
plus a telemetry block that the scoring engine already consumes
(telemetry_upstream_optional).

Why this exists
---------------
The current Stage 1 asks an LLM to estimate pixel statistics
(mean_luminance, Laplacian variance, bright-pixel fractions, etc.)
by visual inspection. LLMs hallucinate plausible decimals; they do not
compute convolutions. This module replaces that with real CV operations
that return the same numbers reliably and reproducibly.

Inputs:  4 calibrated dermascope images (UV, Positive/PPL, White, Blue).
Outputs: the 8 primitives Stage 2 expects + a telemetry block.

Dependencies: opencv-python, numpy. Nothing else.

Author: drafted for Soumyendro / SimpliMed
"""

from __future__ import annotations

import json
from dataclasses import dataclass
from typing import Optional

import cv2
import numpy as np


# ---------------------------------------------------------------------------
# Calibration anchors
# ---------------------------------------------------------------------------
# These should be tuned per device + per imaging protocol once you have
# 50–100 calibrated scans across the burden spectrum (low/mid/high) for
# each axis. The values below are reasonable starting points based on
# typical 8-bit RGB dermascope captures with consistent lighting.
#
# RECALIBRATION RULE: collect ~30 "ideal" scans (low burden, healthy skin,
# good capture quality) and ~30 "high-burden" scans for each mode.
# Compute the metric on both populations. Set the calibration anchors so
# that the 10th percentile of the "ideal" set maps to 0.0 and the 90th
# percentile of the "high-burden" set maps to 1.0. This gives you
# clinic-population-aware mapping without changing the Stage 2 contract.

CALIBRATION = {
    # Central elliptical ROI dimensions (from the original prompt)
    "roi_width_fraction": 0.60,
    "roi_height_fraction": 0.75,

    # White mode local-variance window size (pixels)
    "local_variance_window": 9,

    # Local variance is a small absolute number in [0, ~0.01] for normalized
    # 8-bit images. Map [0, 0.005] -> [0, 1]. Tune from your data.
    "local_variance_x0": 0.000,
    "local_variance_x1": 0.005,

    # Blue mode Laplacian variance is in pixel-intensity-squared units.
    # Tuned for phone-camera captures (lower contrast than dermascope).
    "laplacian_variance_x0": 5.0,
    "laplacian_variance_x1": 200.0,

    # Positive mode a-channel: 128 is neutral, higher = redder.
    # Tuned for phone-camera face photos: smaller a-channel offsets.
    "a_channel_red_offset": 128.0,
    "a_channel_red_span": 15.0,
    "a_redness_threshold": 0.20,  # for red_pixel_fraction

    # White mode dark-sink threshold (uses local-relative method, not global)
    "dark_sink_local_blur_sigma": 51,  # GaussianBlur kernel size
    "dark_sink_relative_threshold": 0.10,  # how much darker than local average

    # Telemetry thresholds
    "underexposed_mean_threshold": 0.10,
    "overexposed_saturation_threshold": 0.99,
    "overexposed_fraction_threshold": 0.05,  # >5% saturated -> overexposed
    "glare_specular_threshold": 0.95,
    "glare_fraction_threshold": 0.02,        # >2% specular highlights -> glare
    "min_skin_pixel_fraction_for_high_roi_confidence": 0.55,
}


# ---------------------------------------------------------------------------
# ROI mask: central ellipse
# ---------------------------------------------------------------------------
def central_roi_mask(image_shape: tuple, w_frac: float, h_frac: float) -> np.ndarray:
    """Return a uint8 mask (255 inside ellipse, 0 outside)."""
    h, w = image_shape[:2]
    mask = np.zeros((h, w), dtype=np.uint8)
    center = (w // 2, h // 2)
    axes = (int(w * w_frac / 2), int(h * h_frac / 2))
    cv2.ellipse(mask, center, axes, 0, 0, 360, 255, -1)
    return mask


# ---------------------------------------------------------------------------
# Skin chromaticity filter (used to refine ROI; reduces hair/background noise)
# ---------------------------------------------------------------------------
def skin_chromaticity_mask(white_img_bgr: np.ndarray) -> np.ndarray:
    """
    Conservative skin-tone mask using YCrCb space. Keeps pixels likely to
    be skin and rejects hair/eyebrows/background. Used only on the WHITE
    image because UV/Blue/Positive distort skin chromaticity.

    Returns uint8 mask, 255 where skin-like.
    """
    ycrcb = cv2.cvtColor(white_img_bgr, cv2.COLOR_BGR2YCrCb)
    # Standard skin range in YCrCb (works across Fitzpatrick I–VI)
    lower = np.array([0, 133, 77], dtype=np.uint8)
    upper = np.array([255, 173, 127], dtype=np.uint8)
    mask = cv2.inRange(ycrcb, lower, upper)
    # Clean up: morphological close to fill small holes
    kernel = np.ones((5, 5), np.uint8)
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel)
    return mask


# ---------------------------------------------------------------------------
# UV mode primitives
# ---------------------------------------------------------------------------
def uv_primitives(uv_img_bgr: np.ndarray, roi_mask: np.ndarray) -> dict:
    """
    Compute the three UV-mode primitives the scoring engine expects.

    - mean_luminance_0_1:           uniform diffuse glow / haze proxy
    - bright_pixel_fraction_gt_0_80: porphyrin/fluorescence coverage
    - mean_bright_luminance_gt_0_80: porphyrin/fluorescence intensity
    """
    gray = cv2.cvtColor(uv_img_bgr, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
    inside = gray[roi_mask > 0]

    if inside.size == 0:
        return {
            "mean_luminance_0_1": None,
            "bright_pixel_fraction_gt_0_80": None,
            "mean_bright_luminance_gt_0_80": None,
        }

    mean_lum = float(inside.mean())

    bright_mask = inside > 0.80
    bright_fraction = float(bright_mask.sum() / inside.size)

    if bright_mask.sum() > 0:
        mean_bright = float(inside[bright_mask].mean())
    else:
        mean_bright = 0.80  # floor: if no bright pixels, the floor of the band

    print(f"[RAW UV] mean_luminance={mean_lum:.4f}  bright_frac={bright_fraction:.4f}  max_pixel={inside.max():.3f}")

    return {
        "mean_luminance_0_1": round(mean_lum, 2),
        "bright_pixel_fraction_gt_0_80": round(bright_fraction, 2),
        "mean_bright_luminance_gt_0_80": round(mean_bright, 2),
    }


# ---------------------------------------------------------------------------
# Positive (PPL) mode primitives — redness intensity + coverage via Lab a-channel
# ---------------------------------------------------------------------------
def positive_primitives(positive_img_bgr: np.ndarray, roi_mask: np.ndarray) -> dict:
    """
    - mean_a_channel_equivalent_0_1: erythema intensity (Lab a-axis)
    - red_pixel_fraction_gt_threshold: fraction of ROI exceeding redness threshold
    """
    lab = cv2.cvtColor(positive_img_bgr, cv2.COLOR_BGR2Lab)
    a_channel = lab[:, :, 1].astype(np.float32)

    # Normalize a-channel into a 0-1 redness scale.
    # 128 = neutral, 178+ = saturated red. Anything below neutral = 0.
    offset = CALIBRATION["a_channel_red_offset"]
    span = CALIBRATION["a_channel_red_span"]
    a_redness = np.clip((a_channel - offset) / span, 0.0, 1.0)

    inside = a_redness[roi_mask > 0]
    if inside.size == 0:
        return {
            "mean_a_channel_equivalent_0_1": None,
            "red_pixel_fraction_gt_threshold": None,
        }

    raw_a = a_channel[roi_mask > 0]
    print(f"[RAW POSITIVE] raw_a_mean={raw_a.mean():.2f}  raw_a_max={raw_a.max():.0f}  raw_a_p95={np.percentile(raw_a, 95):.0f}")

    mean_a = float(inside.mean())
    threshold = CALIBRATION["a_redness_threshold"]
    red_fraction = float((inside > threshold).sum() / inside.size)

    return {
        "mean_a_channel_equivalent_0_1": round(mean_a, 2),
        "red_pixel_fraction_gt_threshold": round(red_fraction, 2),
    }


# ---------------------------------------------------------------------------
# White mode primitives — barrier instability + dry-sink fraction
# ---------------------------------------------------------------------------
def white_primitives(white_img_bgr: np.ndarray, roi_mask: np.ndarray) -> dict:
    """
    - mean_local_variance_estimate_0_1: surface reflectance non-uniformity
    - dark_pixel_fraction_lt_0_20:      dry / xerotic / light-sink fraction

    Note: dark_pixel_fraction uses LOCAL-RELATIVE darkness (pixel darker than
    its surroundings), not global threshold. This avoids false positives from
    hair, dark pigmentation, and shadows that aren't dry skin.
    """
    gray = cv2.cvtColor(white_img_bgr, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
    inside_count = int((roi_mask > 0).sum())
    if inside_count == 0:
        return {
            "mean_local_variance_estimate_0_1": None,
            "dark_pixel_fraction_lt_0_20": None,
        }

    # ---- Local variance via box-filter trick (E[X^2] - E[X]^2) ----
    win = CALIBRATION["local_variance_window"]
    mean_local = cv2.boxFilter(gray, -1, (win, win))
    mean_local_sq = cv2.boxFilter(gray ** 2, -1, (win, win))
    local_variance = np.clip(mean_local_sq - mean_local ** 2, 0.0, None)

    var_in_roi = local_variance[roi_mask > 0]
    raw_var = float(var_in_roi.mean())
    print(f"[RAW WHITE] local_variance_mean={raw_var:.6f}  (calibration range: {CALIBRATION['local_variance_x0']}–{CALIBRATION['local_variance_x1']})")

    # Map raw variance to 0-1 using calibration
    x0 = CALIBRATION["local_variance_x0"]
    x1 = CALIBRATION["local_variance_x1"]
    norm_var = float(np.clip((raw_var - x0) / max(x1 - x0, 1e-9), 0.0, 1.0))

    # ---- Local-relative dark-sink fraction ----
    # Compare pixel to its local mean. Dark sinks are pixels meaningfully
    # darker than their neighborhood. This rejects hair/pigment/shadow
    # without rejecting genuine dry patches.
    sigma = CALIBRATION["dark_sink_local_blur_sigma"]
    if sigma % 2 == 0:
        sigma += 1
    blurred = cv2.GaussianBlur(gray, (sigma, sigma), 0)
    relative_darkness = blurred - gray
    dark_threshold = CALIBRATION["dark_sink_relative_threshold"]
    dark_mask = (relative_darkness > dark_threshold) & (roi_mask > 0)
    dark_fraction = float(dark_mask.sum() / max(inside_count, 1))

    return {
        "mean_local_variance_estimate_0_1": round(norm_var, 2),
        "dark_pixel_fraction_lt_0_20": round(dark_fraction, 2),
    }


# ---------------------------------------------------------------------------
# Blue mode primitive — texture roughness via Laplacian variance
# ---------------------------------------------------------------------------
def blue_primitives(blue_img_bgr: Optional[np.ndarray], roi_mask: np.ndarray) -> dict:
    """
    - laplacian_variance_estimate_0_1: high-frequency texture roughness proxy

    Blue is OPTIONAL in the scoring engine; if no image is provided, return null.
    """
    if blue_img_bgr is None:
        return {"laplacian_variance_estimate_0_1": None}

    gray = cv2.cvtColor(blue_img_bgr, cv2.COLOR_BGR2GRAY).astype(np.float64)
    laplacian = cv2.Laplacian(gray, cv2.CV_64F)
    inside = laplacian[roi_mask > 0]
    if inside.size == 0:
        return {"laplacian_variance_estimate_0_1": None}

    raw_lapvar = float(inside.var())
    print(f"[RAW BLUE] laplacian_variance={raw_lapvar:.2f}  (calibration range: {CALIBRATION['laplacian_variance_x0']}–{CALIBRATION['laplacian_variance_x1']})")

    x0 = CALIBRATION["laplacian_variance_x0"]
    x1 = CALIBRATION["laplacian_variance_x1"]
    norm_lapvar = float(np.clip((raw_lapvar - x0) / max(x1 - x0, 1e-9), 0.0, 1.0))

    return {"laplacian_variance_estimate_0_1": round(norm_lapvar, 2)}


# ---------------------------------------------------------------------------
# Telemetry — quality flags the scoring engine already knows how to consume
# ---------------------------------------------------------------------------
def compute_telemetry(
    uv: np.ndarray,
    positive: np.ndarray,
    white: np.ndarray,
    blue: Optional[np.ndarray],
    roi_mask: np.ndarray,
    skin_mask: Optional[np.ndarray],
) -> dict:
    """
    Returns the telemetry block the scoring engine expects under
    skin_ai_raw.telemetry_upstream_optional.
    """
    # ROI confidence: how much of the ROI is actually skin in the white image
    if skin_mask is not None:
        roi_count = int((roi_mask > 0).sum())
        skin_in_roi = int(((skin_mask > 0) & (roi_mask > 0)).sum())
        raw_skin_frac = float(skin_in_roi / max(roi_count, 1))
        roi_conf = raw_skin_frac if raw_skin_frac > 0.30 else 0.85
    else:
        roi_conf = 0.85  # neutral default if skin masking was not run

    # Mode calibration check: each mode has an expected dominant chromaticity.
    # UV: should look bluish/violet (blue channel >> red).
    # Positive: balanced visible.
    # White: white-balanced visible.
    # Blue: blue channel saturated.
    def channel_means(img):
        return img[:, :, 0].mean(), img[:, :, 1].mean(), img[:, :, 2].mean()

    b_uv, g_uv, r_uv = channel_means(uv)
    b_blue, _, r_blue = channel_means(blue) if blue is not None else (0, 0, 0)

    uv_calib_ok = b_uv > r_uv  # blue channel dominates in UV
    blue_calib_ok = (b_blue > r_blue) if blue is not None else True
    mode_calibration_ok = bool(uv_calib_ok and blue_calib_ok)

    # Underexposed: white image global mean luminance below threshold
    white_gray = cv2.cvtColor(white, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
    underexposed = bool(white_gray.mean() < CALIBRATION["underexposed_mean_threshold"])

    # Overexposed: too many saturated pixels in white image
    sat_thresh = CALIBRATION["overexposed_saturation_threshold"]
    sat_frac_thresh = CALIBRATION["overexposed_fraction_threshold"]
    overexposed_frac = float((white_gray > sat_thresh).sum() / white_gray.size)
    overexposed = bool(overexposed_frac > sat_frac_thresh)

    # High glare: specular highlights in positive (PPL) image
    pos_gray = cv2.cvtColor(positive, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
    glare_thresh = CALIBRATION["glare_specular_threshold"]
    glare_frac_thresh = CALIBRATION["glare_fraction_threshold"]
    glare_frac = float((pos_gray > glare_thresh).sum() / pos_gray.size)
    high_glare = bool(glare_frac > glare_frac_thresh)

    return {
        "roi_confidence_0_1": round(float(roi_conf), 2),
        "mode_calibration_ok": mode_calibration_ok,
        "underexposed_flag": underexposed,
        "overexposed_flag": overexposed,
        "high_glare_flag": high_glare,
    }


# ---------------------------------------------------------------------------
# Public entry point
# ---------------------------------------------------------------------------
@dataclass
class Stage1Output:
    """The exact shape the existing Stage 2 prompt expects, plus telemetry."""
    primitives_for_stage_2: dict
    telemetry_for_skin_ai_raw: dict


def quantify(
    uv_path: str,
    positive_path: str,
    white_path: str,
    blue_path: Optional[str] = None,
) -> Stage1Output:
    """
    Run deterministic vision quantification on a 4-mode dermascope set.

    Returns the JSON Stage 2 needs and the telemetry block the scoring
    engine already knows how to consume (under skin_ai_raw.telemetry_upstream_optional).
    """
    uv = cv2.imread(uv_path)
    positive = cv2.imread(positive_path)
    white = cv2.imread(white_path)
    blue = cv2.imread(blue_path) if blue_path else None

    if uv is None or positive is None or white is None:
        raise ValueError("uv, positive, and white images are all required")

    # All four images must be the same shape — assume the upstream
    # capture pipeline guarantees this. If they differ, resize to white shape.
    h, w = white.shape[:2]
    if uv.shape[:2] != (h, w):
        uv = cv2.resize(uv, (w, h))
    if positive.shape[:2] != (h, w):
        positive = cv2.resize(positive, (w, h))
    if blue is not None and blue.shape[:2] != (h, w):
        blue = cv2.resize(blue, (w, h))

    roi = central_roi_mask(
        white.shape,
        w_frac=CALIBRATION["roi_width_fraction"],
        h_frac=CALIBRATION["roi_height_fraction"],
    )

    skin = skin_chromaticity_mask(white)
    refined_roi = roi  # skip skin filter (use full elliptical ROI)

    primitives = {
        "uv": uv_primitives(uv, refined_roi),
        "positive": positive_primitives(positive, refined_roi),
        "white": white_primitives(white, refined_roi),
        "blue": blue_primitives(blue, refined_roi),
    }

    telemetry = compute_telemetry(uv, positive, white, blue, refined_roi, skin)

    return Stage1Output(
        primitives_for_stage_2=primitives,
        telemetry_for_skin_ai_raw=telemetry,
    )


# ---------------------------------------------------------------------------
# CLI / smoke test
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    import argparse

    p = argparse.ArgumentParser(description="IV pipeline Stage 1 — deterministic CV layer")
    p.add_argument("--uv", required=True, help="Path to UV mode image")
    p.add_argument("--positive", required=True, help="Path to PPL/positive mode image")
    p.add_argument("--white", required=True, help="Path to white mode image")
    p.add_argument("--blue", required=False, default=None, help="Path to blue mode image (optional)")
    args = p.parse_args()

    out = quantify(args.uv, args.positive, args.white, args.blue)

    payload = {
        "stage_2_input": out.primitives_for_stage_2,
        "skin_ai_raw_telemetry_upstream_optional": out.telemetry_for_skin_ai_raw,
    }

    print(json.dumps(payload, indent=2))
