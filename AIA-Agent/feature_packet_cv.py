#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
feature_packet_cv.py
====================
Generates the Bitmoji A5 feature packet (same JSON schema you currently get from
OpenAI) using OpenCV instead.

It produces the IDENTICAL structure:
  feature_packet_version, scan_meta, regions, proxies{...}, missing_data

Two layers:
  1) MEASUREMENT  (pixels -> categorical bins): real OpenCV. Thresholds in CONFIG
     are sensible defaults you must CALIBRATE against your own ground truth.
  2) DERIVED      (bins -> *_index / *_ratio and the jawline / firmness /
     wrinkles_plus / pores_texture_plus families): implemented EXACTLY from the
     midpoint tables + formulas in your aiPrompts.js, so these match your current
     output deterministically.

Determinism: same input pixels -> same output, every run. No randomness.

Dependencies:
    pip install opencv-python numpy

Usage:
    # Option A: a folder whose filenames contain the mode keyword
    python feature_packet_cv.py --dir ./scan_folder --out packet.json

    # Option B: explicit paths
    python feature_packet_cv.py \
        --white white.jpg --red red.jpg \
        --surface_polarized sp.jpg --subsurface_polarized ssp.jpg \
        --woods_uv uv.jpg --out packet.json

    # Option C: 5 positional paths in canonical order
    #   [red, subsurface_polarized, surface_polarized, white, woods_uv]
    python feature_packet_cv.py r.jpg ssp.jpg sp.jpg w.jpg uv.jpg
"""

import os
import sys
import json
import math
import argparse
import urllib.request
import tempfile
import cv2
import numpy as np

MODES = ["red", "subsurface_polarized", "surface_polarized", "white", "woods_uv"]
REGIONS = ["forehead", "nose", "left_cheek", "right_cheek", "chin", "perioral"]

# ----------------------------------------------------------------------------
# CONFIG  ---  CALIBRATE THESE THRESHOLDS AGAINST YOUR GROUND TRUTH
# Each entry is an ordered list of (upper_edge, label). The first edge the value
# is <= wins; the last label is the catch-all.
# ----------------------------------------------------------------------------
CONFIG = {
    # ---- redness (Lab a* based) ----
    "diffuse_redness": [(6, "none"), (12, "mild"), (20, "moderate"), (30, "high"), (1e9, "severe")],
    "erythema_intensity": [(6, "none"), (12, "mild"), (20, "moderate"), (30, "high"), (1e9, "severe")],
    "erythema_coverage": [(0.05, "none"), (0.20, "low"), (0.45, "moderate"), (1e9, "high")],
    "vascular_pattern": [(0.04, "none"), (0.09, "mild"), (0.16, "moderate"), (0.25, "high"), (1e9, "severe")],
    "subclinical_hotspots": [(0.02, "none"), (0.06, "low"), (0.12, "moderate"), (1e9, "high")],

    # ---- sebum / shine (HSV specular based) ----
    "shine_intensity": [(0.05, "none"), (0.15, "mild"), (0.30, "moderate"), (1e9, "strong")],
    "shine_coverage": [(0.05, "none"), (0.15, "low"), (0.30, "moderate"), (1e9, "high")],
    "t_zone_oil": [(0.05, "none"), (0.15, "mild"), (0.30, "moderate"), (1e9, "strong")],
    "cheek_oil": [(0.05, "none"), (0.15, "mild"), (0.30, "moderate"), (1e9, "strong")],

    # ---- hydration ----
    "surface_reflectance": [(0.10, "very_low"), (0.25, "low"), (0.45, "moderate"), (0.65, "high"), (1e9, "very_high")],
    "subsurface_diffusion": [(0.10, "very_low"), (0.25, "low"), (0.45, "moderate"), (0.65, "high"), (1e9, "very_high")],
    "microline_density": [(0.05, "none"), (0.12, "mild"), (0.22, "moderate"), (1e9, "marked")],
    "dry_patch_fluorescence": [(0.03, "none"), (0.10, "low"), (0.25, "moderate"), (1e9, "high")],

    # ---- barrier ----
    "flaking_texture": [(0.05, "none"), (0.12, "mild"), (0.22, "moderate"), (1e9, "marked")],
    # barrier_uniformity: lower texture-variance => better. value = normalized texture variance.
    "barrier_uniformity": [(0.10, "excellent"), (0.20, "good"), (0.35, "mixed"), (1e9, "poor")],
    # hydration_signal: higher subsurface luminance spread => more hydrated
    "hydration_signal": [(0.20, "very_low"), (0.35, "low"), (0.55, "moderate"), (0.75, "high"), (1e9, "very_high")],

    # ---- acne (Tier 3 - approximate, flagged borderline) ----
    "inflammatory_lesion_count": [(0.5, "0"), (5.5, "1-5"), (20.5, "6-20"), (50.5, "21-50"), (1e9, "50+")],
    "comedone_count": [(0.5, "0"), (10.5, "1-10"), (30.5, "11-30"), (80.5, "31-80"), (1e9, "80+")],
    "porphyrin_load": [(0.005, "none"), (0.02, "low"), (0.05, "moderate"), (0.10, "high"), (1e9, "very_high")],
    "inflammatory_ratio": [(0.05, "none"), (0.20, "low"), (0.50, "medium"), (1e9, "high")],
    "active_lesion_visibility": [(0.05, "none"), (0.12, "faint"), (0.22, "mild"), (0.35, "clear"), (1e9, "striking")],
    "post_inflammatory_mark_burden": [(0.02, "none"), (0.06, "low"), (0.14, "moderate"), (0.25, "high"), (1e9, "very_high")],

    # ---- pigmentation ----
    "coverage_band": [(0.05, "very_low"), (0.15, "low"), (0.30, "moderate"), (0.50, "high"), (1e9, "very_high")],
    "intensity_band": [(6, "very_light"), (12, "light"), (20, "mild"), (30, "moderate"), (42, "marked"), (1e9, "severe")],
    "contrast_band": [(8, "very_low"), (16, "low"), (26, "moderate"), (38, "high"), (1e9, "very_high")],
    "uniformity_band": [(12, "even"), (24, "mottled"), (1e9, "uneven")],
    "depth_band": [(0.9, "superficial"), (1.15, "mixed"), (1e9, "deep")],
    "underlying_pigment_band": [(0.05, "minimal"), (0.15, "mild"), (0.30, "moderate"), (1e9, "marked")],

    # ---- pores / texture ----
    "pore_visibility": [(0.0008, "none"), (0.003, "mild"), (0.008, "moderate"), (1e9, "marked")],
    "texture_roughness": [(0.04, "none"), (0.10, "mild"), (0.18, "moderate"), (1e9, "marked")],
    "blackhead_congestion": [(0.0005, "none"), (0.002, "low"), (0.005, "moderate"), (1e9, "high")],

    # ---- wrinkles ----
    "wrinkle_line_count": [(10.5, "0-10"), (30.5, "11-30"), (60.5, "31-60"), (1e9, "60+")],
}

# ----------------------------------------------------------------------------
# MIDPOINT TABLES  ---  copied verbatim from aiPrompts.js (do not change)
# ----------------------------------------------------------------------------
MID = {
    "diffuse_redness_bin": {"none": 0.05, "mild": 0.20, "moderate": 0.40, "high": 0.65, "severe": 0.85},
    "vascular_pattern_bin": {"none": 0.05, "mild": 0.20, "moderate": 0.40, "high": 0.65, "severe": 0.85},
    "pore_visibility_bin": {"none": 0.10, "mild": 0.30, "moderate": 0.55, "marked": 0.80},
    "texture_roughness_bin": {"none": 0.10, "mild": 0.30, "moderate": 0.55, "marked": 0.80},
    "blackhead_congestion_bin": {"none": 0.10, "low": 0.30, "moderate": 0.55, "high": 0.80},
    "porphyrin_load_bin": {"none": 0.05, "low": 0.20, "moderate": 0.45, "high": 0.70, "very_high": 0.90},
    "active_lesion_visibility_bin": {"none": 0.05, "faint": 0.20, "mild": 0.40, "clear": 0.65, "striking": 0.85},
    "post_inflammatory_mark_burden_bin": {"none": 0.05, "low": 0.20, "moderate": 0.45, "high": 0.70, "very_high": 0.90},
    "t_zone_oil_bin": {"none": 0.05, "mild": 0.25, "moderate": 0.55, "strong": 0.80},
    "cheek_oil_bin": {"none": 0.05, "mild": 0.25, "moderate": 0.55, "strong": 0.80},
    "coverage_band": {"very_low": 0.10, "low": 0.25, "moderate": 0.50, "high": 0.75, "very_high": 0.90},
    "intensity_band": {"very_light": 0.10, "light": 0.20, "mild": 0.32, "moderate": 0.48, "marked": 0.66, "severe": 0.85},
    "contrast_band": {"very_low": 0.10, "low": 0.22, "moderate": 0.40, "high": 0.62, "very_high": 0.85},
    "underlying_pigment_band": {"minimal": 0.10, "mild": 0.25, "moderate": 0.50, "marked": 0.75},
    "wrinkle_line_count_bin": {"0-10": 0.15, "11-30": 0.35, "31-60": 0.60, "60+": 0.85},
    "surface_reflectance_bin": {"very_low": 0.10, "low": 0.25, "moderate": 0.50, "high": 0.75, "very_high": 0.90},
    "subsurface_diffusion_bin": {"very_low": 0.10, "low": 0.25, "moderate": 0.50, "high": 0.75, "very_high": 0.90},
    "microline_density_bin": {"none": 0.10, "mild": 0.30, "moderate": 0.55, "marked": 0.80},
    "dry_patch_fluorescence_bin": {"none": 0.05, "low": 0.25, "moderate": 0.55, "high": 0.80},
    "erythema_intensity_bin": {"none": 0.05, "mild": 0.20, "moderate": 0.40, "high": 0.65, "severe": 0.85},
    "erythema_coverage_bin": {"none": 0.05, "low": 0.25, "moderate": 0.55, "high": 0.80},
    "flaking_texture_bin": {"none": 0.10, "mild": 0.30, "moderate": 0.55, "marked": 0.80},
    "barrier_uniformity_bin": {"poor": 0.80, "mixed": 0.55, "good": 0.30, "excellent": 0.15},
    "hydration_signal_bin": {"very_low": 0.85, "low": 0.65, "moderate": 0.45, "high": 0.25, "very_high": 0.10},
    "shine_intensity_bin": {"none": 0.05, "mild": 0.25, "moderate": 0.55, "strong": 0.80},
    "shine_coverage_bin": {"none": 0.05, "low": 0.25, "moderate": 0.55, "high": 0.80},
    # inline midpoints used by fallback formulas:
    "subclinical_hotspots_bin": {"none": 0.05, "low": 0.25, "moderate": 0.55, "high": 0.80},
    "uniformity_band": {"even": 0.15, "mottled": 0.50, "uneven": 0.80},
}

# ----------------------------------------------------------------------------
# DETERMINISTIC HELPERS (verbatim semantics from aiPrompts.js)
# ----------------------------------------------------------------------------
def clip01(x):
    return min(1.00, max(0.00, x))

def round005(x):
    return round(round(x / 0.05) * 0.05, 2)

def mid(field, value):
    return MID[field][value]

def bin_of(value, spec):
    for upper, label in spec:
        if value <= upper:
            return label
    return spec[-1][1]

# ----------------------------------------------------------------------------
# IMAGE / ROI UTILITIES
# ----------------------------------------------------------------------------
def get_cascade_path(filename):
    if hasattr(cv2, "data"):
        p = os.path.join(cv2.data.haarcascades, filename)
        if os.path.exists(p):
            return p
    
    # Check common fallback paths
    base_dir = os.path.dirname(cv2.__file__)
    possible_paths = [
        os.path.join(base_dir, "data", filename),
        os.path.join("/usr/share/opencv4/haarcascades", filename),
        os.path.join("/usr/share/opencv/haarcascades", filename),
        os.path.join("/usr/local/share/opencv4/haarcascades", filename),
        os.path.join("/usr/local/share/opencv/haarcascades", filename),
    ]
    if hasattr(sys, "prefix"):
        possible_paths.extend([
            os.path.join(sys.prefix, "share", "opencv4", "haarcascades", filename),
            os.path.join(sys.prefix, "share", "opencv", "haarcascades", filename)
        ])
        
    for p in possible_paths:
        if os.path.exists(p):
            return p
            
    # If not found anywhere, download it to the system temporary directory
    # since we might not have write permissions to the script's directory.
    local_path = os.path.join(tempfile.gettempdir(), filename)
    if not os.path.exists(local_path):
        url = "https://raw.githubusercontent.com/opencv/opencv/master/data/haarcascades/" + filename
        print(f"Downloading {filename} to {local_path}...", file=sys.stderr)
        try:
            urllib.request.urlretrieve(url, local_path)
        except Exception as e:
            print(f"Failed to download {filename}: {e}", file=sys.stderr)
            
    return local_path

def detect_face_box(white_bgr):
    """Detect a face box on the white image; reuse the box on all modes
    (assumes the device captures registered/aligned frames)."""
    gray = cv2.cvtColor(white_bgr, cv2.COLOR_BGR2GRAY)
    cascade_path = get_cascade_path("haarcascade_frontalface_default.xml")
    cascade = cv2.CascadeClassifier(cascade_path)
    faces = cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
    if len(faces) > 0:
        x, y, w, h = max(faces, key=lambda f: f[2] * f[3])
        return (int(x), int(y), int(w), int(h)), True
    # fallback: assume the face roughly fills a centered 80% box
    H, W = gray.shape[:2]
    bw, bh = int(W * 0.8), int(H * 0.9)
    return (int((W - bw) / 2), int((H - bh) / 2), bw, bh), False

def roi_rects(face_box):
    """Proportional ROIs inside the face box. Crude but adequate for frontal,
    controlled captures. Swap in MediaPipe Face Mesh landmarks for precision."""
    x, y, w, h = face_box
    def R(fx0, fy0, fx1, fy1):
        return (int(x + fx0 * w), int(y + fy0 * h), int((fx1 - fx0) * w), int((fy1 - fy0) * h))
    return {
        "forehead":   R(0.20, 0.05, 0.80, 0.25),
        "nose":       R(0.40, 0.40, 0.60, 0.66),
        "left_cheek": R(0.12, 0.45, 0.35, 0.72),
        "right_cheek":R(0.65, 0.45, 0.88, 0.72),
        "chin":       R(0.35, 0.82, 0.65, 0.98),
        "perioral":   R(0.32, 0.68, 0.68, 0.82),
        "t_zone":     R(0.20, 0.05, 0.80, 0.66),
        "infra_left": R(0.18, 0.33, 0.40, 0.45),
        "infra_right":R(0.60, 0.33, 0.82, 0.45),
        "lips":       R(0.38, 0.70, 0.62, 0.80),
        "full":       (x, y, w, h),
    }

def crop(img, rect):
    x, y, w, h = rect
    H, W = img.shape[:2]
    x0, y0 = max(0, x), max(0, y)
    x1, y1 = min(W, x + w), min(H, y + h)
    if x1 <= x0 or y1 <= y0:
        return img[0:1, 0:1]
    return img[y0:y1, x0:x1]

def a_star(img_bgr):
    """Lab a* channel as float, centered at 0 (higher = redder)."""
    lab = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2Lab)
    return lab[:, :, 1].astype(np.float32) - 128.0

def specular_mask(img_bgr):
    """High value + low saturation = specular highlight (oil/shine)."""
    hsv = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2HSV)
    s = hsv[:, :, 1].astype(np.float32) / 255.0
    v = hsv[:, :, 2].astype(np.float32) / 255.0
    return ((v > 0.82) & (s < 0.35)).astype(np.uint8)

def highpass_energy(gray):
    """Normalized high-frequency energy (texture/roughness proxy, 0-1ish)."""
    g = gray.astype(np.float32) / 255.0
    blur = cv2.GaussianBlur(g, (0, 0), 3)
    hp = g - blur
    return float(np.std(hp) * 4.0)  # scale into a usable range

def pigment_map(gray):
    """Local-darkness map: how much darker each pixel is than its neighborhood.
    Positive = pigmented/darker spot. Returned in 0-255-ish L units."""
    g = gray.astype(np.float32)
    local = cv2.GaussianBlur(g, (0, 0), 25)
    return np.clip(local - g, 0, None)

# ----------------------------------------------------------------------------
# MEASUREMENT LAYER (pixels -> bins). Each returns dict + sets borderline flags.
# Failures fall back conservatively and log FALLBACK_USED (no nulls).
# ----------------------------------------------------------------------------
def measure(images, rois, log):
    out = {}
    W = images["white"]
    RED = images.get("red", W)
    SP = images.get("surface_polarized", W)
    SSP = images.get("subsurface_polarized", W)
    UV = images.get("woods_uv", W)

    skin_regions = ["forehead", "nose", "left_cheek", "right_cheek", "chin", "perioral"]

    def region_pixels(img, names):
        vals = []
        for n in names:
            vals.append(crop(img, rois[n]))
        return vals

    # ---------- REDNESS / ERYTHEMA ----------
    try:
        a = a_star(RED)
        roi_means = [float(np.mean(crop(a, rois[n]))) for n in skin_regions]
        a_mean = float(np.mean(roi_means))
        a_full = crop(a, rois["full"])
        coverage = float(np.mean(a_full > (a_mean + 8)))
        # vascular: linear high-freq structure in a*
        ah = a_full - cv2.GaussianBlur(a_full, (0, 0), 2)
        vasc = float(np.mean(np.abs(ah) > 6))
        # hotspots
        hot = float(np.mean(a_full > (a_mean + 18)))
        out["diffuse_redness_bin"] = bin_of(a_mean, CONFIG["diffuse_redness"])
        out["erythema_intensity_bin"] = bin_of(a_mean, CONFIG["erythema_intensity"])
        out["erythema_coverage_bin"] = bin_of(coverage, CONFIG["erythema_coverage"])
        out["vascular_pattern_bin"] = bin_of(vasc, CONFIG["vascular_pattern"])
        out["subclinical_hotspots_bin"] = bin_of(hot, CONFIG["subclinical_hotspots"])
        out["_red_borderline"] = True  # vascular pattern is approximate
    except Exception as e:
        log.append("FALLBACK_USED:redness")
        out.update(dict(diffuse_redness_bin="mild", erythema_intensity_bin="mild",
                        erythema_coverage_bin="low", vascular_pattern_bin="mild",
                        subclinical_hotspots_bin="low", _red_borderline=True))

    # diffuse vs vascular dominance
    try:
        diffuse_e = MID["diffuse_redness_bin"][out["diffuse_redness_bin"]]
        vasc_e = MID["vascular_pattern_bin"][out["vascular_pattern_bin"]]
        if max(diffuse_e, vasc_e) < 0.20:
            out["diffuse_vs_vascular_dominance_bin"] = "minimal"
        elif abs(diffuse_e - vasc_e) < 0.12:
            out["diffuse_vs_vascular_dominance_bin"] = "mixed"
        elif diffuse_e > vasc_e:
            out["diffuse_vs_vascular_dominance_bin"] = "diffuse_dominant"
        else:
            out["diffuse_vs_vascular_dominance_bin"] = "vascular_dominant"
    except Exception:
        out["diffuse_vs_vascular_dominance_bin"] = "minimal"

    # ---------- SEBUM / SHINE ----------
    try:
        smask = specular_mask(W)
        tz = crop(smask, rois["t_zone"]); ch_l = crop(smask, rois["left_cheek"]); ch_r = crop(smask, rois["right_cheek"])
        full_s = crop(smask, rois["full"])
        v = cv2.cvtColor(W, cv2.COLOR_BGR2HSV)[:, :, 2].astype(np.float32) / 255.0
        intensity = float(np.mean(np.sort(crop(v, rois["full"]).ravel())[-max(1, crop(v, rois['full']).size // 50):]))
        coverage = float(np.mean(full_s))
        out["shine_intensity_bin"] = bin_of(max(0.0, intensity - 0.7) / 0.3, CONFIG["shine_intensity"])
        out["shine_coverage_bin"] = bin_of(coverage, CONFIG["shine_coverage"])
        out["t_zone_oil_bin"] = bin_of(float(np.mean(tz)), CONFIG["t_zone_oil"])
        out["cheek_oil_bin"] = bin_of(float((np.mean(ch_l) + np.mean(ch_r)) / 2.0), CONFIG["cheek_oil"])
    except Exception:
        log.append("FALLBACK_USED:sebum")
        out.update(dict(shine_intensity_bin="mild", shine_coverage_bin="low",
                        t_zone_oil_bin="mild", cheek_oil_bin="mild"))

    # surface_shine vs follicular congestion
    try:
        shine_e = MID["shine_intensity_bin"][out["shine_intensity_bin"]]
        # follicular congestion proxy = pore-band energy in surface_polarized
        spg = cv2.cvtColor(SP, cv2.COLOR_BGR2GRAY)
        foll = highpass_energy(crop(spg, rois["t_zone"]))
        if max(shine_e, foll) < 0.15:
            out["surface_shine_vs_follicular_congestion_bin"] = "minimal"
        elif abs(shine_e - foll) < 0.10:
            out["surface_shine_vs_follicular_congestion_bin"] = "mixed"
        elif shine_e >= foll:
            out["surface_shine_vs_follicular_congestion_bin"] = "surface_shine_dominant"
        else:
            out["surface_shine_vs_follicular_congestion_bin"] = "follicular_congestion_dominant"
    except Exception:
        out["surface_shine_vs_follicular_congestion_bin"] = "minimal"

    # ---------- HYDRATION ----------
    try:
        vW = cv2.cvtColor(W, cv2.COLOR_BGR2HSV)[:, :, 2].astype(np.float32) / 255.0
        surf_refl = float(np.mean(crop(vW, rois["full"])))
        ssp_g = cv2.cvtColor(SSP, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        subsurf = float(np.mean(crop(ssp_g, rois["full"])))
        spg = cv2.cvtColor(SP, cv2.COLOR_BGR2GRAY)
        microline = highpass_energy(crop(spg, rois["full"]))
        uvg = cv2.cvtColor(UV, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        # dry patch fluorescence: dull, low-texture bright-ish patches in UV
        dry = float(np.mean((crop(uvg, rois["full"]) > 0.5)))
        out["surface_reflectance_bin"] = bin_of(surf_refl, CONFIG["surface_reflectance"])
        out["subsurface_diffusion_bin"] = bin_of(subsurf, CONFIG["subsurface_diffusion"])
        out["microline_density_bin"] = bin_of(microline, CONFIG["microline_density"])
        out["dry_patch_fluorescence_bin"] = bin_of(dry, CONFIG["dry_patch_fluorescence"])
        out["_hydration_regional"] = {n: round005(clip01(float(np.mean(crop(vW, rois[n]))))) for n in skin_regions}
    except Exception:
        log.append("FALLBACK_USED:hydration")
        out.update(dict(surface_reflectance_bin="moderate", subsurface_diffusion_bin="moderate",
                        microline_density_bin="mild", dry_patch_fluorescence_bin="low",
                        _hydration_regional={n: 0.5 for n in skin_regions}))

    # ---------- BARRIER ----------
    try:
        spg = cv2.cvtColor(SP, cv2.COLOR_BGR2GRAY)
        # flaking: small bright scale via white top-hat
        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5))
        tophat = cv2.morphologyEx(crop(spg, rois["full"]), cv2.MORPH_TOPHAT, kernel)
        flaking = float(np.mean(tophat > 18))
        # barrier uniformity: normalized texture variance (lower = more uniform)
        tex_var = highpass_energy(crop(spg, rois["full"]))
        # hydration signal: subsurface luminance spread
        ssp_g = cv2.cvtColor(SSP, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        hyd_sig = float(np.mean(crop(ssp_g, rois["full"])))
        out["flaking_texture_bin"] = bin_of(flaking, CONFIG["flaking_texture"])
        out["barrier_uniformity_bin"] = bin_of(tex_var, CONFIG["barrier_uniformity"])
        out["hydration_signal_bin"] = bin_of(hyd_sig, CONFIG["hydration_signal"])
    except Exception:
        log.append("FALLBACK_USED:barrier")
        out.update(dict(flaking_texture_bin="mild", barrier_uniformity_bin="mixed",
                        hydration_signal_bin="moderate"))

    # ---------- PORES / TEXTURE ----------
    try:
        spg = cv2.cvtColor(SP, cv2.COLOR_BGR2GRAY)
        roi = crop(spg, rois["t_zone"])
        # pore density via DoG dark-blob fraction
        dog = cv2.GaussianBlur(roi.astype(np.float32), (0, 0), 1) - cv2.GaussianBlur(roi.astype(np.float32), (0, 0), 3)
        pore_frac = float(np.mean(dog < -3)) / max(1.0, (roi.shape[0] * roi.shape[1]) / (roi.shape[0] * roi.shape[1]))
        pore_density = float(np.mean(dog < -3))
        roughness = highpass_energy(crop(spg, rois["full"]))
        # blackheads: very dark tiny plugs in t-zone
        blackhead = float(np.mean(crop(spg, rois["nose"]) < 40))
        out["pore_visibility_bin"] = bin_of(pore_density, CONFIG["pore_visibility"])
        out["texture_roughness_bin"] = bin_of(roughness, CONFIG["texture_roughness"])
        out["blackhead_congestion_bin"] = bin_of(blackhead, CONFIG["blackhead_congestion"])
        # dominant regions by roughness
        rr = sorted(skin_regions, key=lambda n: highpass_energy(cv2.cvtColor(crop(SP, rois[n]), cv2.COLOR_BGR2GRAY)), reverse=True)
        out["_pores_dominant"] = rr[:3]
    except Exception:
        log.append("FALLBACK_USED:pores_texture")
        out.update(dict(pore_visibility_bin="mild", texture_roughness_bin="mild",
                        blackhead_congestion_bin="low", _pores_dominant=["nose", "left_cheek", "right_cheek"]))

    # ---------- PIGMENTATION ----------
    try:
        gW = cv2.cvtColor(W, cv2.COLOR_BGR2GRAY)
        pm = pigment_map(crop(gW, rois["full"]))
        pig_thr = 14.0
        mask = pm > pig_thr
        coverage = float(np.mean(mask))
        intensity = float(np.mean(pm[mask])) if np.any(mask) else 0.0
        contrast = float(np.percentile(pm, 98) - np.percentile(pm, 50))
        uniformity = float(np.std(crop(gW, rois["full"]).astype(np.float32)))
        # depth: pigment accentuation under UV vs visible
        gUV = cv2.cvtColor(UV, cv2.COLOR_BGR2GRAY)
        pm_uv = pigment_map(crop(gUV, rois["full"]))
        uv_cov = float(np.mean(pm_uv > pig_thr))
        depth_ratio = (uv_cov + 1e-3) / (coverage + 1e-3)
        underlying = max(0.0, uv_cov - coverage)
        out["coverage_band"] = bin_of(coverage, CONFIG["coverage_band"])
        out["intensity_band"] = bin_of(intensity, CONFIG["intensity_band"])
        out["contrast_band"] = bin_of(contrast, CONFIG["contrast_band"])
        out["uniformity_band"] = bin_of(uniformity, CONFIG["uniformity_band"])
        out["depth_band"] = bin_of(depth_ratio, CONFIG["depth_band"])
        out["underlying_pigment_band"] = bin_of(underlying, CONFIG["underlying_pigment_band"])
        # per-region maps
        loads, inten = {}, {}
        for n in skin_regions:
            pmn = pigment_map(crop(gW, rois[n]))
            loads[n] = round005(clip01(float(np.mean(pmn > pig_thr)) * 3.0))
            inten[n] = round005(clip01(float(np.mean(pmn)) / 60.0))
        out["_pig_loads"] = loads
        out["_pig_inten"] = inten
        out["_pig_borderline"] = True  # depth/underlying are approximate
    except Exception:
        log.append("FALLBACK_USED:pigmentation")
        out.update(dict(coverage_band="low", intensity_band="mild", contrast_band="low",
                        uniformity_band="even", depth_band="superficial", underlying_pigment_band="minimal",
                        _pig_loads={n: 0.2 for n in skin_regions}, _pig_inten={n: 0.2 for n in skin_regions},
                        _pig_borderline=True))

    # ---------- WRINKLES ----------
    try:
        spg = cv2.cvtColor(SP, cv2.COLOR_BGR2GRAY)
        roi = crop(spg, rois["full"])
        edges = cv2.Canny(roi, 30, 90)
        lines = cv2.HoughLinesP(edges, 1, np.pi / 180, threshold=30, minLineLength=max(8, roi.shape[1] // 25), maxLineGap=4)
        line_count = 0 if lines is None else len(lines)
        depth = highpass_energy(roi)  # ridge contrast proxy
        gUV = cv2.cvtColor(UV, cv2.COLOR_BGR2GRAY)
        chronicity = highpass_energy(crop(gUV, rois["full"]))
        out["wrinkle_line_count_bin"] = bin_of(line_count, CONFIG["wrinkle_line_count"])
        out["_wrinkle_depth_index"] = round005(clip01(depth))
        out["_chronicity_uv_index"] = round005(clip01(chronicity))
        # structural vs dehydration
        microline_e = MID["microline_density_bin"][out["microline_density_bin"]]
        if microline_e > 0.5 and depth < 0.3:
            out["structural_vs_dehydration_bin"] = "mostly_dehydration"
        elif depth > 0.5:
            out["structural_vs_dehydration_bin"] = "mostly_structural"
        else:
            out["structural_vs_dehydration_bin"] = "mixed"
        wr = sorted(skin_regions, key=lambda n: highpass_energy(cv2.cvtColor(crop(SP, rois[n]), cv2.COLOR_BGR2GRAY)), reverse=True)
        out["_wrinkle_dominant"] = wr[:2]
    except Exception:
        log.append("FALLBACK_USED:wrinkles")
        out.update(dict(wrinkle_line_count_bin="0-10", _wrinkle_depth_index=0.2, _chronicity_uv_index=0.2,
                        structural_vs_dehydration_bin="mixed", _wrinkle_dominant=["forehead", "perioral"]))

    # ---------- ACNE (Tier 3 - approximate) ----------
    try:
        a = a_star(RED)
        # inflammatory: localized red blobs
        ah = crop(a, rois["full"])
        infl_mask = (ah > (float(np.mean(ah)) + 18)).astype(np.uint8)
        infl_mask = cv2.morphologyEx(infl_mask, cv2.MORPH_OPEN, np.ones((3, 3), np.uint8))
        n_infl, _, stats_i, _ = cv2.connectedComponentsWithStats(infl_mask)
        infl_count = int(max(0, sum(1 for s in stats_i[1:] if s[cv2.CC_STAT_AREA] > 6)))
        # comedones: small dark/light follicular blobs in surface_polarized t-zone
        spg = cv2.cvtColor(SP, cv2.COLOR_BGR2GRAY)
        roi = crop(spg, rois["t_zone"])
        dog = cv2.GaussianBlur(roi.astype(np.float32), (0, 0), 1) - cv2.GaussianBlur(roi.astype(np.float32), (0, 0), 4)
        com_mask = (np.abs(dog) > 6).astype(np.uint8)
        n_com, _, stats_c, _ = cv2.connectedComponentsWithStats(com_mask)
        com_count = int(max(0, sum(1 for s in stats_c[1:] if 2 < s[cv2.CC_STAT_AREA] < 60)))
        ratio = infl_count / max(1.0, (infl_count + com_count))
        # porphyrins in UV: orange-red fluorescent spots
        hsv_uv = cv2.cvtColor(UV, cv2.COLOR_BGR2HSV)
        roi_uv = crop(hsv_uv, rois["full"])
        h, s, v = roi_uv[:, :, 0], roi_uv[:, :, 1], roi_uv[:, :, 2]
        porph = (((h < 25) | (h > 165)) & (s > 80) & (v > 80)).astype(np.uint8)
        porph_frac = float(np.mean(porph))
        # active lesion visibility: contrast of inflamed blobs
        vis = float(np.mean(ah[infl_mask > 0])) / 60.0 if infl_count > 0 else 0.0
        # PIH: brownish flat marks (darker, low texture) in white
        gW = cv2.cvtColor(W, cv2.COLOR_BGR2GRAY)
        pm = pigment_map(crop(gW, rois["full"]))
        pih = float(np.mean((pm > 10) & (pm < 25)))
        out["inflammatory_lesion_count_bin"] = bin_of(infl_count, CONFIG["inflammatory_lesion_count"])
        out["comedone_count_bin"] = bin_of(com_count, CONFIG["comedone_count"])
        out["porphyrin_load_bin"] = bin_of(porph_frac, CONFIG["porphyrin_load"])
        out["inflammatory_ratio_bin"] = bin_of(ratio, CONFIG["inflammatory_ratio"])
        out["active_lesion_visibility_bin"] = bin_of(clip01(vis), CONFIG["active_lesion_visibility"])
        out["post_inflammatory_mark_burden_bin"] = bin_of(pih, CONFIG["post_inflammatory_mark_burden"])
        # dominant regions by local inflamed density
        ad = sorted(skin_regions, key=lambda n: float(np.mean(crop(a, rois[n]) > (float(np.mean(a)) + 12))), reverse=True)
        out["_acne_dominant"] = ad[:2]
    except Exception:
        log.append("FALLBACK_USED:acne")
        out.update(dict(inflammatory_lesion_count_bin="0", comedone_count_bin="1-10",
                        porphyrin_load_bin="low", inflammatory_ratio_bin="low",
                        active_lesion_visibility_bin="faint", post_inflammatory_mark_burden_bin="low",
                        _acne_dominant=["chin", "nose"]))

    # ---------- LIPS ----------
    try:
        lip = rois["lips"]
        gUV = cv2.cvtColor(UV, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        woods_int = float(np.mean(crop(gUV, lip)))
        uv_abs = clip01(1.0 - woods_int)
        gW = cv2.cvtColor(W, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        melanin = clip01(1.0 - float(np.mean(crop(gW, lip))))
        a = a_star(RED)
        vascular = clip01(float(np.mean(crop(a, lip))) / 40.0)
        out["_lip"] = dict(woods=round005(clip01(woods_int)), uv_abs=round005(uv_abs),
                           melanin=round005(melanin), vascular=round005(vascular))
        if melanin > vascular + 0.15:
            cls = "melanin_dominant"
        elif vascular > melanin + 0.15:
            cls = "vascular_dominant"
        else:
            cls = "mixed_type"
        out["_lip_class"] = cls  # NOTE: 'cosmetic_mask' (lipstick) not reliably separable in CV
    except Exception:
        log.append("FALLBACK_USED:lips")
        out["_lip"] = dict(woods=0.3, uv_abs=0.3, melanin=0.3, vascular=0.3)
        out["_lip_class"] = "mixed_type"

    # ---------- PERI-ORBITAL (measure what we can; rest via fallback formulas) ----------
    try:
        gW = cv2.cvtColor(W, cv2.COLOR_BGR2GRAY)
        pm_l = pigment_map(crop(gW, rois["infra_left"]))
        pm_r = pigment_map(crop(gW, rois["infra_right"]))
        under_l = clip01(float(np.mean(pm_l)) / 40.0)
        under_r = clip01(float(np.mean(pm_r)) / 40.0)
        a = a_star(RED)
        vasc = clip01((float(np.mean(crop(a, rois["infra_left"]))) + float(np.mean(crop(a, rois["infra_right"])))) / 2.0 / 40.0)
        out["_peri_under"] = round005((under_l + under_r) / 2.0)
        out["_peri_vasc"] = round005(vasc)
        out["_peri_side"] = "left" if under_l > under_r + 0.07 else ("right" if under_r > under_l + 0.07 else "symmetric")
        # eyes_closed via eye cascade
        eye_cascade_path = get_cascade_path("haarcascade_eye.xml")
        eye_cascade = cv2.CascadeClassifier(eye_cascade_path)
        eyes = eye_cascade.detectMultiScale(gW, 1.1, 6)
        out["_eyes_closed"] = bool(len(eyes) == 0)
    except Exception:
        log.append("FALLBACK_USED:peri_orbital")
        out.update({"_peri_under": 0.3, "_peri_vasc": 0.2, "_peri_side": "symmetric", "_eyes_closed": True})

    return out


# ----------------------------------------------------------------------------
# DERIVED LAYER (bins -> indices + derived families). Verbatim from aiPrompts.js.
# ----------------------------------------------------------------------------
def build_proxies(m):
    p = {}

    # ----- hydration -----
    sr = round005(mid("surface_reflectance_bin", m["surface_reflectance_bin"]))
    sd = round005(mid("subsurface_diffusion_bin", m["subsurface_diffusion_bin"]))
    ml = round005(mid("microline_density_bin", m["microline_density_bin"]))
    dpf = round005(mid("dry_patch_fluorescence_bin", m["dry_patch_fluorescence_bin"]))
    tz = mid("t_zone_oil_bin", m["t_zone_oil_bin"])
    ck = mid("cheek_oil_bin", m["cheek_oil_bin"])
    sebum_balance = round005(clip01(1.00 - abs((0.65 * tz + 0.35 * ck) - 0.50) / 0.50))
    p["hydration"] = {
        "surface_reflectance_bin": m["surface_reflectance_bin"],
        "subsurface_diffusion_bin": m["subsurface_diffusion_bin"],
        "microline_density_bin": m["microline_density_bin"],
        "dry_patch_fluorescence_bin": m["dry_patch_fluorescence_bin"],
        "surface_reflectance_index": sr,
        "subsurface_diffusion_index": sd,
        "microline_density_index": ml,
        "dry_patch_fluorescence_index": dpf,
        "sebum_balance_ratio": sebum_balance,
        "regional_map": m["_hydration_regional"],
        "borderline": False,
    }

    # ----- combined_barrier_sensitivity -----
    ei = round005(mid("erythema_intensity_bin", m["erythema_intensity_bin"]))
    ec = round005(mid("erythema_coverage_bin", m["erythema_coverage_bin"]))
    ft = round005(mid("flaking_texture_bin", m["flaking_texture_bin"]))
    bu = round005(1.00 - mid("barrier_uniformity_bin", m["barrier_uniformity_bin"]))
    hs = round005(1.00 - mid("hydration_signal_bin", m["hydration_signal_bin"]))
    p["combined_barrier_sensitivity"] = {
        "erythema_intensity_bin": m["erythema_intensity_bin"],
        "erythema_coverage_bin": m["erythema_coverage_bin"],
        "flaking_texture_bin": m["flaking_texture_bin"],
        "barrier_uniformity_bin": m["barrier_uniformity_bin"],
        "hydration_signal_bin": m["hydration_signal_bin"],
        "erythema_intensity_index": ei,
        "erythema_coverage_ratio": ec,
        "flaking_texture_index": ft,
        "barrier_uniformity_index": bu,
        "hydration_signal_index": hs,
        "borderline": False,
    }

    # ----- acne -----
    p["acne"] = {
        "inflammatory_lesion_count_bin": m["inflammatory_lesion_count_bin"],
        "comedone_count_bin": m["comedone_count_bin"],
        "porphyrin_load_bin": m["porphyrin_load_bin"],
        "inflammatory_ratio_bin": m["inflammatory_ratio_bin"],
        "active_lesion_visibility_bin": m["active_lesion_visibility_bin"],
        "post_inflammatory_mark_burden_bin": m["post_inflammatory_mark_burden_bin"],
        "dominant_regions": m["_acne_dominant"],
        "borderline": True,  # lesion counting/typing is approximate in classical CV
    }

    # ----- sebum_oiliness -----
    p["sebum_oiliness"] = {
        "shine_intensity_index": round005(mid("shine_intensity_bin", m["shine_intensity_bin"])),
        "shine_coverage_ratio": round005(mid("shine_coverage_bin", m["shine_coverage_bin"])),
        "t_zone_oil_bin": m["t_zone_oil_bin"],
        "cheek_oil_bin": m["cheek_oil_bin"],
        "shine_intensity_bin": m["shine_intensity_bin"],
        "shine_coverage_bin": m["shine_coverage_bin"],
        "surface_shine_vs_follicular_congestion_bin": m["surface_shine_vs_follicular_congestion_bin"],
        "borderline": False,
    }

    # ----- redness -----
    p["redness"] = {
        "diffuse_redness_bin": m["diffuse_redness_bin"],
        "vascular_pattern_bin": m["vascular_pattern_bin"],
        "subclinical_hotspots_bin": m["subclinical_hotspots_bin"],
        "diffuse_vs_vascular_dominance_bin": m["diffuse_vs_vascular_dominance_bin"],
        "borderline": bool(m.get("_red_borderline", False)),
    }

    # ----- pigmentation -----
    vmi = round005(mid("intensity_band", m["intensity_band"]))
    cti = round005(mid("contrast_band", m["contrast_band"]))
    cvi = round005(mid("coverage_band", m["coverage_band"]))
    iwb = round005(clip01(0.55 * mid("intensity_band", m["intensity_band"])
                          + 0.25 * mid("contrast_band", m["contrast_band"])
                          + 0.20 * mid("coverage_band", m["coverage_band"])))
    p["pigmentation"] = {
        "coverage_band": m["coverage_band"],
        "intensity_band": m["intensity_band"],
        "contrast_band": m["contrast_band"],
        "uniformity_band": m["uniformity_band"],
        "depth_band": m["depth_band"],
        "underlying_pigment_band": m["underlying_pigment_band"],
        "visible_mean_intensity_index": vmi,
        "contrast_to_surrounding_skin_index": cti,
        "coverage_index": cvi,
        "intensity_weighted_burden_index": iwb,
        "region_loads_0_1": m["_pig_loads"],
        "regional_intensity_map": m["_pig_inten"],
        "borderline": bool(m.get("_pig_borderline", False)),
    }

    # ----- pores_texture -----
    p["pores_texture"] = {
        "pore_visibility_bin": m["pore_visibility_bin"],
        "texture_roughness_bin": m["texture_roughness_bin"],
        "blackhead_congestion_bin": m["blackhead_congestion_bin"],
        "dominant_regions": m["_pores_dominant"],
        "borderline": False,
    }

    # ----- wrinkles -----
    wdi = m["_wrinkle_depth_index"]
    cui = m["_chronicity_uv_index"]
    p["wrinkles"] = {
        "wrinkle_line_count_bin": m["wrinkle_line_count_bin"],
        "wrinkle_depth_index": wdi,
        "chronicity_uv_index": cui,
        "structural_vs_dehydration_bin": m["structural_vs_dehydration_bin"],
        "dominant_regions": m["_wrinkle_dominant"],
        "borderline": False,
    }

    # ----- lips_pigmentation -----
    lip = m["_lip"]
    p["lips_pigmentation"] = {
        "woods_intensity": lip["woods"],
        "UV_absorption": lip["uv_abs"],
        "intrinsic_melanin_index": lip["melanin"],
        "vascular_congestion_index": lip["vascular"],
        "pigment_classification": m["_lip_class"],
        "borderline": True,
    }

    # ----- peri_orbital (formulas verbatim) -----
    under_eye = m["_peri_under"]
    peri_vasc = m["_peri_vasc"]
    hollow = round005(0.60 * wdi + 0.40 * cui)
    puffiness = round005(0.50 * mid("subclinical_hotspots_bin", m["subclinical_hotspots_bin"])
                         + 0.50 * (1.00 - bu))
    fine_line = round005(0.60 * ml + 0.40 * wdi)
    p["peri_orbital"] = {
        "under_eye_pigment_index": under_eye,
        "vascular_congestion_index": peri_vasc,
        "hollow_shadow_index": hollow,
        "puffiness_index": puffiness,
        "fine_line_texture_index": fine_line,
        "dominant_side": m["_peri_side"],
        "eyes_closed": bool(m["_eyes_closed"]),
        "borderline": True,
    }

    # ----- jawline_sagging (formulas verbatim) -----
    mand = round005(clip01(0.55 * cui + 0.45 * wdi))
    tr = mid("texture_roughness_bin", m["texture_roughness_bin"])
    cb = mid("coverage_band", m["coverage_band"])
    p["jawline_sagging"] = {
        "mandibular_line_deflection_angle_deg": round005(12.0 * mand),
        "mandibular_line_deflection_index_0_1": mand,
        "pre_jowl_sulcus_depth_index": round005(clip01(0.50 * mand + 0.30 * tr + 0.20 * cb)),
        "jowl_bulge_prominence_index": round005(clip01(0.55 * mand + 0.45 * ck)),
        "submental_fullness_index": round005(clip01(0.60 * mand + 0.40 * tz)),
        "dermal_collagen_thinning_index": round005(clip01(0.70 * cui + 0.30 * wdi)),
        "left_right_asymmetry_index": 0.15,
        "lower_face_visibility_ratio": 0.85,
        "jawline_edge_confidence": 0.80,
        "borderline": True,
    }

    # ----- firmness_elasticity (formulas verbatim) -----
    micro_laxity = round005(clip01(0.45 * ml + 0.35 * wdi + 0.20 * tr))
    collagen_unif = round005(clip01(0.55 * sr + 0.25 * bu + 0.20 * (1.00 - mid("uniformity_band", m["uniformity_band"]))))
    dermal_thin = round005(clip01(0.70 * cui + 0.30 * wdi))
    dermal_density = round005(clip01(0.60 * (1.00 - dermal_thin) + 0.40 * collagen_unif))
    elastic_recoil = round005(clip01(0.65 * (1.00 - micro_laxity) + 0.35 * dermal_density))
    firmness_unif = round005(clip01(0.60 * collagen_unif + 0.40 * (1.00 - tr)))
    p["firmness_elasticity"] = {
        "micro_laxity_pattern_index": micro_laxity,
        "collagen_reflectance_uniformity": collagen_unif,
        "elastic_recoil_proxy_index": elastic_recoil,
        "dermal_density_proxy_index": dermal_density,
        "firmness_uniformity_index": firmness_unif,
        "borderline": True,
    }

    # ----- wrinkles_plus (formulas verbatim) -----
    p["wrinkles_plus"] = {
        "regional_uniformity_index": round005(clip01(0.55 * bu + 0.45 * (1.00 - mid("uniformity_band", m["uniformity_band"])))),
        "wrinkle_microline_density_index": round005(clip01(0.70 * ml + 0.30 * wdi)),
        "borderline": False,
    }

    # ----- pores_texture_plus (formulas verbatim) -----
    pv = mid("pore_visibility_bin", m["pore_visibility_bin"])
    p["pores_texture_plus"] = {
        "pore_density_index": round005(pv),
        "pore_diameter_index": round005(clip01(0.70 * pv + 0.30 * tz)),
        "pore_clarity_index": round005(clip01(1.00 - 0.60 * tr - 0.40 * tz)),
        "borderline": False,
    }

    return p


# ----------------------------------------------------------------------------
# SCAN META (lightweight; your Scan QA module normally owns this)
# ----------------------------------------------------------------------------
def build_scan_meta(images, face_detected):
    issues = []
    gW = cv2.cvtColor(images["white"], cv2.COLOR_BGR2GRAY)
    blur = cv2.Laplacian(gW, cv2.CV_64F).var()
    mean_v = float(np.mean(gW))
    if blur < 60:
        issues.append("low_sharpness")
    if mean_v < 50:
        issues.append("underexposed")
    if mean_v > 220:
        issues.append("overexposed")
    if not face_detected:
        issues.append("face_not_detected")
    if len(issues) == 0:
        quality, conf = "pass", 0.95
    elif "face_not_detected" in issues or blur < 30:
        quality, conf = "fail", 0.55
    else:
        quality, conf = "caution", 0.75
    return {
        "scan_quality": quality,
        "pose_variation": "none",
        "confidence_multiplier": round(conf, 2),
        "quality_issues": issues,
    }


# ----------------------------------------------------------------------------
# IMAGE LOADING
# ----------------------------------------------------------------------------
def detect_mode(filename):
    f = filename.lower()
    if "woods" in f or "uv" in f or "wood" in f:
        return "woods_uv"
    if "subsurface" in f or "sub_surface" in f or "ssp" in f:
        return "subsurface_polarized"
    if "surface" in f or "_sp" in f or "surf" in f:
        return "surface_polarized"
    if "red" in f:
        return "red"
    if "white" in f or "normal" in f or "vis" in f:
        return "white"
    return None

def load_images(args):
    images = {}
    explicit = {k: getattr(args, k) for k in MODES if getattr(args, k)}
    if explicit:
        for mode, path in explicit.items():
            images[mode] = cv2.imread(path)
    elif args.dir:
        for fn in sorted(os.listdir(args.dir)):
            mode = detect_mode(fn)
            if mode and mode not in images:
                img = cv2.imread(os.path.join(args.dir, fn))
                if img is not None:
                    images[mode] = img
    elif args.positional:
        if len(args.positional) != 5:
            sys.exit("ERROR: provide exactly 5 positional paths in order: "
                     "red subsurface_polarized surface_polarized white woods_uv")
        for mode, path in zip(MODES, args.positional):
            images[mode] = cv2.imread(path)
    else:
        sys.exit("ERROR: provide --dir, explicit --<mode> paths, or 5 positional paths.")

    for mode, img in list(images.items()):
        if img is None:
            sys.exit(f"ERROR: failed to read image for mode '{mode}'.")
    if "white" not in images:
        sys.exit("ERROR: a 'white' mode image is required (used for face detection).")
    missing = [m for m in MODES if m not in images]
    if missing:
        print(f"WARNING: missing modes {missing} -> 'white' image substituted for them.", file=sys.stderr)
        for m in missing:
            images[m] = images["white"]
    return images


# ----------------------------------------------------------------------------
# PUBLIC API  ---  images in, JSON packet out
# ----------------------------------------------------------------------------
def generate_feature_packet(red=None, subsurface_polarized=None, surface_polarized=None,
                            white=None, woods_uv=None):
    """Take the 5 mode images and return the feature packet as a dict.

    Each argument can be EITHER a file path (str) OR an already-loaded image
    (numpy ndarray, BGR as from cv2.imread).

    'white' is required (used for face detection). Any missing mode falls back
    to the white image with a note in missing_data.

    Example:
        import json
        from feature_packet_cv import generate_feature_packet
        packet = generate_feature_packet(
            red="red.jpg",
            subsurface_polarized="ssp.jpg",
            surface_polarized="sp.jpg",
            white="white.jpg",
            woods_uv="uv.jpg",
        )
        print(json.dumps(packet, indent=2))
    """
    raw = {
        "red": red,
        "subsurface_polarized": subsurface_polarized,
        "surface_polarized": surface_polarized,
        "white": white,
        "woods_uv": woods_uv,
    }

    images = {}
    for mode, val in raw.items():
        if val is None:
            continue
        if isinstance(val, np.ndarray):
            images[mode] = val
        else:
            img = cv2.imread(val)
            if img is None:
                raise ValueError(f"Could not read image for mode '{mode}': {val}")
            images[mode] = img

    if "white" not in images:
        raise ValueError("A 'white' mode image is required (used for face detection).")

    missing = [m for m in MODES if m not in images]
    for m in missing:
        images[m] = images["white"]

    face_box, face_detected = detect_face_box(images["white"])
    rois = roi_rects(face_box)

    log = ["MODE_SUBSTITUTED:" + m for m in missing]
    measured = measure(images, rois, log)
    proxies = build_proxies(measured)
    scan_meta = build_scan_meta(images, face_detected)

    return {
        "feature_packet_version": "aia_fp_v1",
        "scan_meta": scan_meta,
        "regions": REGIONS,
        "proxies": proxies,
        "missing_data": {
            "fields_set_null_due_to_uncertainty": [],
            "notes": "; ".join(sorted(set(log))),
        },
    }


# ----------------------------------------------------------------------------
# MAIN  ---  command-line wrapper around generate_feature_packet()
# ----------------------------------------------------------------------------
def main():
    ap = argparse.ArgumentParser(description="Generate Bitmoji A5 feature packet via OpenCV.")
    ap.add_argument("positional", nargs="*", help="5 image paths in order: red subsurface_polarized surface_polarized white woods_uv")
    ap.add_argument("--dir", help="folder of mode-named images")
    for m in MODES:
        ap.add_argument(f"--{m}", help=f"path to the {m} image")
    ap.add_argument("--out", help="output JSON path (also printed to stdout)")
    args = ap.parse_args()

    images = load_images(args)  # validates inputs / handles --dir, --mode, positional
    packet = generate_feature_packet(
        red=images.get("red"),
        subsurface_polarized=images.get("subsurface_polarized"),
        surface_polarized=images.get("surface_polarized"),
        white=images["white"],
        woods_uv=images.get("woods_uv"),
    )

    text = json.dumps(packet, indent=2)
    print(text)
    if args.out:
        with open(args.out, "w") as f:
            f.write(text)
        print(f"\nSaved -> {args.out}", file=sys.stderr)


if __name__ == "__main__":
    main()
