@extends('pdf.master')

@section('content')

<style>
    .section-box {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 12px 14px;
        margin-bottom: 14px;
        background: #fff;
    }
    .section-title {
        font-size: 16px;
        font-weight: bold;
        color: #1B3A6B;
        text-align: center;
        letter-spacing: 1px;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #C9A84C;
    }
    .overview-text {
        font-size: 14px;
        color: #444;
        line-height: 1.6;
        text-align: justify;
    }
    .journey-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        margin-bottom: 20px;
    }
    .journey-table th {
        background-color: #1B3A6B;
        color: #ffffff;
        font-weight: bold;
        font-size: 11px;
        text-transform: uppercase;
        padding: 8px 6px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .journey-table td {
        padding: 8px 6px;
        border: 1px solid #ddd;
        font-size: 11px;
        color: #333;
    }
    .journey-table tr:nth-child(even) {
        background-color: #f8fafc;
    }
    .parameter-name {
        font-weight: bold;
        color: #1B3A6B;
    }
    .score-badge {
        display: inline-block;
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: bold;
    }
    .score-improved {
        color: #4CAF50;
    }
    .score-stable {
        color: #C9A84C;
    }
    .score-declined {
        color: #F44336;
    }
    .score-column-header {
        text-align: center !important;
    }
    .score-value {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
    }
</style>

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">CLIENT TREATMENT JOURNEY REPORT</div>
        <div class="report-subtitle">Consolidated session-by-session parameter tracking and skin improvement progress</div>
    </div>
</div>

{{-- ── Patient Info ── --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
    <tr>
        <td width="48%" valign="top">
            <div class="info-card">
                <div class="info-label">PATIENT NAME</div>
                <div class="info-value">{{ $patient['name'] ?? 'N/A' }}</div>
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top">
            <div class="info-card">
                <div class="info-label">AGE / GENDER</div>
                <div class="info-value">{{ $patient['age'] ?? 'N/A' }} / {{ strtoupper(substr($patient['gender'] ?? 'N/A', 0, 1)) }}</div>
            </div>
        </td>
    </tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
    <tr>
        <td width="48%" valign="top">
            <div class="info-card">
                <div class="info-label">Report Date</div>
                <div class="info-value">{{ date('d/m/Y') }}</div>
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top">
            <div class="info-card">
                <div class="info-label">SKIN PROFILE</div>
                <div class="info-value">{{ $patient['skin_type'] ?? 'N/A' }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ── Journey Overview ── --}}
<div class="section-box">
    <div class="section-title">CLINICAL JOURNEY OVERVIEW</div>
    <div class="overview-text">
        This consolidated progress report illustrates the session-by-session evolution of your diagnostic skin parameters.
        By tracking scores sequentially from the initial baseline scan through each completed treatment session, we demonstrate 
        objective progression and guide the ongoing care plan.
    </div>
</div>

{{-- ── Journey Progression Table ── --}}
@php
    // Get all parameters from baseline diagnosis report dynamically
    $parameters = [];
    if (isset($assessment->diagnosis['diagnosis_report'])) {
        foreach ($assessment->diagnosis['diagnosis_report'] as $key => $param) {
            if ($key === 'skin_type') {
                continue; // Skip skin type classification from the scoring progress table
            }
            $parameters[$key] = [
                'name' => $param['parameter_name'] ?? ucfirst(str_replace('_', ' ', $key)),
                'baseline' => $param['score_or_label'] ?? 'N/A'
            ];
        }
    }

    // Fallback static list with correct database keys if the baseline report structure is empty/missing
    if (empty($parameters)) {
        $fallbackKeys = [
            'superficial_pigmentation_score' => 'Superficial Pigmentation Score',
            'visual_acne_grading' => 'Visual Acne Grading',
            'texture_open_pores_scoring' => 'Texture / Open Pores Grading',
            'superficial_wrinkles_scoring' => 'Superficial Wrinkles',
            'jawline_sagging_score' => 'Jawline Sagging',
            'skin_hydration_score' => 'Skin Hydration',
            'skin_sebum_content' => 'Skin Sebum Content',
            'barrier_health_sensitivity' => 'Barrier Health & Sensitivity',
            'vascularity_redness_profiling' => 'Vascularity / Redness Profiling',
        ];
        foreach ($fallbackKeys as $key => $name) {
            $parameters[$key] = [
                'name' => $name,
                'baseline' => 'N/A'
            ];
        }
    }

    // Populate baseline values from diagnosis_report if missing or N/A
    foreach ($parameters as $key => &$data) {
        if (($data['baseline'] === 'N/A' || $data['baseline'] === '') && isset($assessment->diagnosis['diagnosis_report'][$key])) {
            $diag = $assessment->diagnosis['diagnosis_report'][$key];
            $data['baseline'] = $diag['score_or_label'] ?? $diag['value'] ?? 'N/A';
        }
    }
    unset($data);
@endphp

<div class="section-box">
    <div class="section-title">SKIN PROGRESSION BY PARAMETER</div>
    
    <table class="journey-table">
        <thead>
            <tr>
                <th>Diagnostic Parameter</th>
                <th class="score-column-header">Baseline</th>
                @foreach($sessions as $session)
                    <th class="score-column-header">Session {{ $session->session_number }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($parameters as $key => $paramData)
                <tr>
                    <td class="parameter-name">{{ $paramData['name'] }}</td>
                    <td class="score-value">{{ $paramData['baseline'] }}</td>
                    @foreach($sessions as $session)
                        @php
                            $sessVal = '—';
                            $resultClass = 'score-stable';
                            $postDiag = $session->post_diagnosis;
                            if (isset($postDiag['reassessment'][$key])) {
                                $sessVal = $postDiag['reassessment'][$key]['post_treatment_score_or_label'] ?? '—';
                                $res = strtolower($postDiag['reassessment'][$key]['result'] ?? '');
                                if ($res === 'improved') {
                                    $resultClass = 'score-improved';
                                } elseif ($res === 'declined') {
                                    $resultClass = 'score-declined';
                                }
                            }
                        @endphp
                        <td class="score-value {{ $resultClass }}">{{ $sessVal }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="generated-note">Generated via AI Aesthetics Client Journey Tracking System</div>

@endsection
