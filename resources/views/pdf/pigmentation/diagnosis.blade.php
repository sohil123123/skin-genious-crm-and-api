@extends('pdf.master')

@section('content')

<style>
    /* ─── Tech Sections ─── */
    .section-title-table {
        width: 100%;
        margin-bottom: 15px;
        background-color: #0E2B5C;
    }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #FFFFFF;
        text-transform: uppercase;
        letter-spacing: 2px;
        padding: 8px 15px;
    }

    .overview-text {
        font-size: 12px;
        color: #4A5568;
        line-height: 1.5;
        text-align: justify;
        margin-bottom: 25px;
        padding: 0 5px;
    }

    /* ─── Diagnosis Cards ─── */
    .section-label {
        font-size: 12px;
        color: #C29F5D;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 6px;
        border-bottom: 1px solid #E2E8F0;
        padding-bottom: 10px;
        line-height: 1.5;
    }
    .section-text {
        font-size: 12px;
        color: #4A5568;
        line-height: 1.5;
        text-align: justify;
        margin-bottom: 15px;
    }
    .section-card {
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px;
        margin-top: 10px;
        page-break-inside: avoid;
    }
    .section-header {
        background: #0E2B5C;
        color: #fff;
        font-size: 16px;
        font-weight: bold;
        padding: 12px 16px;
        border-radius: 8px;
    }
    .divider {
        border-left: 1px solid #E5E7EB;
    }
    .score-circle {
        width: 50px;
        text-align: center;
        vertical-align: middle;
        border: 4px solid #E0E7FF;
        border-radius: 50%;
        padding: 5px;
    }
    .score-inner {
        font-size: 30px;
        font-weight: bold;
        color: #0E2B5C;
    }
    .img-box {
        background: #FFFFFF;
        border: 1px dashed #D1D5DB;
        padding: 10px;
        text-align: center;
        border-radius: 8px;
    }
    .img-box img {
        max-width: 100%;
        height: 150px;
        display: inline-block;
    }
    .status-badge-blue {
        background-color: #E3F2FD;
        color: #1565C0;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 11px;
        display: inline-block;
    }
</style>

@php
    $patient = $record->user;
    $diagnosis = $record->diagnosis ?? [];

    $impression = $diagnosis['working_impression'] ?? [];
    $primaryDx = $impression['primary_category'] ?? 'N/A';
    $primaryDxLabel = ucwords(str_replace('_', ' ', $primaryDx));
    $confidence = $impression['primary_confidence_100'] ?? 0;

    $profile = $diagnosis['pigmentation_profile'] ?? [];
    $regional = $diagnosis['regional_interpretation'] ?? [];
    $summaries = $diagnosis['summaries'] ?? [];
    $clinicalActivity = $diagnosis['clinical_activity'] ?? [];
    $riskProfile = $diagnosis['risk_profile'] ?? [];

    $age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A';
    $gender = $patient->gender ? strtoupper(substr($patient->gender, 0, 1)) : 'N/A';
@endphp

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">PIGMENTATION DIAGNOSTIC REPORT</div>
        <div class="report-subtitle">AI-Powered Clinical Diagnostics</div>
    </div>

    {{-- ── Patient Info Cards ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">PATIENT NAME</div>
                    <div class="info-value">{{ $patient->name }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">AGE / GENDER</div>
                    <div class="info-value">{{ $age }} / {{ $gender }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Report Date</div>
                    <div class="info-value">{{ $record->created_at->format('d/m/Y') }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">CLINIC</div>
                    <div class="info-value">{{ $record->clinic->name ?? 'Main Clinic' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Clinical Working Impression ── --}}
    <!-- <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">WORKING IMPRESSION</td>
        </tr>
    </table>
    <div class="section-card" style="margin-bottom: 25px; padding: 15px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="70%" valign="top">
                    <div style="font-size: 15px; font-weight: bold; color: #0E2B5C; text-transform: uppercase;">
                        {{ $primaryDxLabel }}
                    </div>
                    <div style="font-size: 11px; color: #718096; margin-top: 5px;">
                        Working Category (Not final diagnosis)
                    </div>
                </td>
                <td width="30%" align="right" valign="top">
                    <table class="score-circle" cellpadding="0" cellspacing="0" style="display: inline-block;">
                        <tr>
                            <td align="center" valign="middle">
                                <div class="score-inner" style="font-size: 20px;">
                                    {{ $confidence }}%
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div style="font-size: 9px; color: #718096; text-transform: uppercase; font-weight: bold; text-align: right; margin-top: 4px; margin-right: 10px;">
                        Confidence
                    </div>
                </td>
            </tr>
        </table>
    </div> -->

    {{-- ── Clinical Profiles Table & Depth ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 25px; page-break-inside: avoid;">
        <tr>
            <td width="48%" valign="top">
                <table class="section-title-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="section-title">CLINICAL METRICS</td>
                    </tr>
                </table>
                <table width="100%" style="border: 1px solid #E2E8F0; border-collapse: collapse;">
                    <tr style="background-color: #F8FAFC;">
                        <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Melanin Load Index</td>
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $profile['melanin_load_index'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Erythema Load Index</td>
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $profile['erythema_load_index'] ?? 'N/A' }}</td>
                    </tr>
                    <tr style="background-color: #F8FAFC;">
                        <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Melanin Percent</td>
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $profile['composition']['melanin_percent'] ?? 'N/A' }}%</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 11px;">Vascular Percent</td>
                        <td align="right" style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $profile['composition']['vascular_percent'] ?? 'N/A' }}%</td>
                    </tr>
                </table>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <table class="section-title-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="section-title">SKIN PHOTOTYPE & DEPTH</td>
                    </tr>
                </table>
                <table width="100%" style="border: 1px solid #E2E8F0; border-collapse: collapse; margin-bottom: 10px;">
                    <tr style="background-color: #F8FAFC;">
                        <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Fitzpatrick Type</td>
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">{{ $profile['estimated_fitzpatrick']['patient_display'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 11px;">Estimated Depth</td>
                        <td align="right" style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 11px;">{{ $profile['estimated_depth']['patient_label'] ?? 'N/A' }}</td>
                    </tr>
                </table>
                <div style="font-size: 10px; color: #4A5568; line-height: 1.4; text-align: justify; font-style: italic;">
                    <b>Depth Explanation:</b> {{ $profile['estimated_depth']['patient_explanation'] ?? '' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Clinical Activity & Risk Profile ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 25px; page-break-inside: avoid;">
        <tr>
            <td width="48%" valign="top">
                <table class="section-title-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="section-title">CLINICAL ACTIVITY</td>
                    </tr>
                </table>
                <div class="section-card" style="margin-top: 0; padding: 15px; min-height: 155px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
                        <tr>
                            <td style="font-size: 11.5px; color: #2D3748; padding: 4px 0; vertical-align: middle;">
                                <strong>Stability Status:</strong>
                                <span class="status-badge-blue" style="margin-left: 8px;">
                                    {{ ucfirst($clinicalActivity['stability_status'] ?? 'N/A') }}
                                </span>
                            </td>
                        </tr>
                    </table>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">Acne Driver:</td>
                            <td align="right" style="font-size: 11px; font-weight: bold; color: #1E293B; padding: 6px 0; border-bottom: 1px solid #F1F5F9; text-transform: uppercase;">
                                {{ !empty($clinicalActivity['active_acne_driver']) ? 'ACTIVE' : 'NONE' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">Inflammation Control:</td>
                            <td align="right" style="font-size: 11px; font-weight: bold; color: #1E293B; padding: 6px 0; border-bottom: 1px solid #F1F5F9; text-transform: uppercase;">
                                {{ !empty($clinicalActivity['inflammation_first_required']) ? 'REQUIRED' : 'NO' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #4A5568; padding: 6px 0;">Barrier Repair First:</td>
                            <td align="right" style="font-size: 11px; font-weight: bold; color: #1E293B; padding: 6px 0; text-transform: uppercase;">
                                {{ !empty($clinicalActivity['barrier_repair_first_required']) ? 'REQUIRED' : 'NO' }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <table class="section-title-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="section-title">RISK PROFILE</td>
                    </tr>
                </table>
                <div class="section-card" style="margin-top: 0; padding: 15px; min-height: 155px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size: 11.5px; color: #2D3748; padding: 6px 0; border-bottom: 1px solid #F1F5F9;"><strong>Recurrence:</strong></td>
                            <td align="right" style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                                {{ ucwords(str_replace('_', ' ', $riskProfile['recurrence_risk'] ?? 'N/A')) }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #2D3748; padding: 6px 0; border-bottom: 1px solid #F1F5F9;"><strong>Procedure Risk:</strong></td>
                            <td align="right" style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                                {{ ucwords(str_replace('_', ' ', $riskProfile['procedure_risk'] ?? 'N/A')) }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #2D3748; padding: 6px 0; border-bottom: 1px solid #F1F5F9;"><strong>Sunscreen compliance:</strong></td>
                            <td align="right" style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                                {{ ucwords(str_replace('_', ' ', $riskProfile['sunscreen_compliance_risk'] ?? 'N/A')) }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #2D3748; padding: 6px 0; border-bottom: 1px solid #F1F5F9;"><strong>PIH Risk:</strong></td>
                            <td align="right" style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                                {{ ucwords(str_replace('_', ' ', $riskProfile['pih_risk'] ?? 'N/A')) }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #2D3748; padding: 6px 0;"><strong>Red flag lesion risk:</strong></td>
                            <td align="right" style="font-size: 11.5px; color: #4A5568; padding: 6px 0;">
                                {{ ucwords(str_replace('_', ' ', $riskProfile['red_flag_lesion_risk'] ?? 'N/A')) }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Doctor clinical impression summary ── --}}
    <!-- <div style="page-break-inside: avoid;">
        <table class="section-title-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="section-title">CLINICAL SUMMARY (DOCTOR-FACING)</td>
            </tr>
        </table>
        <div style="font-size: 11px; color: #2D3748; line-height: 1.5; text-align: justify; margin-bottom: 25px; border-left: 3px solid #0E2B5C; padding-left: 10px;">
            {!! preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', e($summaries['clinical_summary_for_doctor'] ?? 'N/A')) !!}
        </div>
    </div> -->

    {{-- ── Patient-Facing summary ── --}}
    <div style="page-break-inside: avoid;">
        <table class="section-title-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="section-title">SUMMARY FOR THE PATIENT</td>
            </tr>
        </table>
        <div class="overview-text" style="margin-bottom: 10px;">
            {{ $summaries['patient_summary'] ?? 'N/A' }}
        </div>
    </div>

</div>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Regional Interpretation Image Analysis ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">REGIONAL INTERPRETATION & IMAGE ANALYSIS</td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 15px;">
        <tr>
            <td width="48%">
                <div class="info-card" style="padding: 10px;">
                    <div class="info-label" style="font-size: 8px;">OVERALL DISTRIBUTION</div>
                    <div class="info-value" style="font-size: 12px; text-transform: uppercase;">
                        {{ str_replace('_', ' ', $regional['overall_distribution'] ?? 'N/A') }}
                    </div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%">
                <div class="info-card" style="padding: 10px;">
                    <div class="info-label" style="font-size: 8px;">SYMMETRY</div>
                    <div class="info-value" style="font-size: 12px; text-transform: uppercase;">
                        {{ str_replace('_', ' ', $regional['symmetry'] ?? 'N/A') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div style="font-size: 11px; color: #2D3748; margin-bottom: 20px;">
        <b>Dominant Regions:</b>
        @if(!empty($regional['dominant_regions']))
            {{ implode(', ', array_map(fn($r) => ucwords(str_replace('_', ' ', $r)), $regional['dominant_regions'])) }}
        @else
            N/A
        @endif
    </div>

    {{-- ── Detailed Table ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E2E8F0; border-collapse: collapse; margin-bottom: 25px; page-break-inside: avoid;">
        <thead>
            <tr style="background-color: #F8FAFC; color: #0E2B5C;">
                <th align="left" style="padding: 8px; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #C29F5D; border-right: 1px solid #E2E8F0; width: 25%;">Region</th>
                <th align="left" style="padding: 8px; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #C29F5D; border-right: 1px solid #E2E8F0; width: 37%;">Patient-Facing Findings</th>
                <th align="left" style="padding: 8px; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #C29F5D; width: 38%;">Clinical Interpretation</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($regional['regions'] ?? []) as $reg)
            <tr style="background-color: {{ $loop->iteration % 2 == 0 ? '#F8FAFC' : '#FFFFFF' }};">
                <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 10px;">
                    {{ ucwords(str_replace('_', ' ', $reg['region'] ?? '')) }}
                    <div style="font-size: 8px; color: #C29F5D; font-weight: bold; text-transform: uppercase; margin-top: 3px;">
                        {{ str_replace('_', ' ', $reg['support_level'] ?? '') }}
                    </div>
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; font-size: 10px; color: #4A5568; line-height: 1.4;">
                    {{ $reg['patient_description'] ?? 'N/A' }}
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-size: 10px; color: #2D3748; line-height: 1.4;">
                    {{ $reg['clinical_interpretation'] ?? 'N/A' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" align="center" style="padding: 20px; color: #999; font-size: 11px;">No regional findings available.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Local modifiers ── --}}
    @if(!empty($diagnosis['localized_restrictions']['local_modifiers']))
    <div style="page-break-inside: avoid;">
        <div style="font-size: 11px; font-weight: bold; color: #0E2B5C; margin-bottom: 8px;">LOCAL ANATOMICAL MODIFIERS / CONFOUNDERS</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E2E8F0; border-collapse: collapse;">
            @foreach($diagnosis['localized_restrictions']['local_modifiers'] as $mod)
            <tr style="background-color: #FFFDF9;">
                <td style="padding: 8px; font-weight: bold; font-size: 9.5px; color: #C29F5D; border-bottom: 1px solid #E2E8F0; border-right: 1px solid #E2E8F0; width: 30%;">
                    {{ ucwords(str_replace('_', ' ', $mod['region'] ?? '')) }} ({{ ucwords(str_replace('_', ' ', $mod['modifier_type'] ?? '')) }})
                </td>
                <td style="padding: 8px; font-size: 9.5px; color: #4A5568; border-bottom: 1px solid #E2E8F0; line-height: 1.4;">
                    {{ $mod['treatment_implication'] ?? '' }}
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif
</div>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Pigmentation Components & Treatment Implications ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">PIGMENTATION COMPONENTS &amp; CLINICAL SIGNIFICANCE</td>
        </tr>
    </table>

    <div style="margin-bottom: 20px;">
        @forelse(($diagnosis['patient_facing_components'] ?? []) as $comp)
        <div class="section-card" style="padding: 15px; margin-bottom: 15px; border-left: 4px solid #C29F5D; margin-top: 5px; page-break-inside: avoid;">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 5px;">
                <tr>
                    <td style="font-size: 12px; font-weight: bold; color: #0E2B5C; text-transform: uppercase;">
                        {{ $comp['title'] ?? ucwords(str_replace('_', ' ', $comp['component'] ?? '')) }}
                    </td>
                    <td align="right" style="font-size: 9px; color: #718096; font-weight: bold; text-transform: uppercase;">
                        {{ str_replace('_', ' ', $comp['support_level'] ?? '') }}
                    </td>
                </tr>
            </table>
            <div style="font-size: 10.5px; color: #4A5568; margin-top: 4px; line-height: 1.4; text-align: justify;">
                <b>Description:</b> {{ $comp['explanation'] ?? '' }}
            </div>
            <div style="font-size: 10.5px; color: #2D3748; margin-top: 8px; line-height: 1.4; text-align: justify; font-style: italic; background-color: #F8FAFC; padding: 8px 12px; border-radius: 6px;">
                <b>Treatment Implication:</b> {{ $comp['treatment_meaning'] ?? '' }}
            </div>
        </div>
        @empty
        <div align="center" style="padding: 20px; color: #999; font-size: 11px;">No pigmentation component details available.</div>
        @endforelse
    </div>

    {{-- ── Doctor Review Safety Alert Note ── --}}
    @if(!empty($diagnosis['patient_doctor_review_note']['required']) && $diagnosis['patient_doctor_review_note']['required'] === true)
    <div style="background-color: #FFF5F5; border: 1px solid #FEB2B2; border-radius: 8px; padding: 15px; margin-top: 15px; page-break-inside: avoid;">
        <div style="font-size: 12px; font-weight: bold; color: #9B1C1C; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
            ⚠️ {{ $diagnosis['patient_doctor_review_note']['headline'] ?? 'Doctor Review Required Before Spot Treatment' }}
        </div>
        <div style="font-size: 10.5px; color: #742A2A; line-height: 1.4; margin-bottom: 8px;">
            {{ $diagnosis['patient_doctor_review_note']['summary'] ?? '' }}
        </div>
        <div style="font-size: 9.5px; color: #9B2C2C; font-style: italic; margin-bottom: 10px;">
            * {{ $diagnosis['patient_doctor_review_note']['reassurance'] ?? '' }}
        </div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #FED7D7; padding-top: 8px;">
            @foreach(($diagnosis['patient_doctor_review_note']['areas'] ?? []) as $area)
            <tr>
                <td style="font-size: 10px; font-weight: bold; color: #9B1C1C; padding: 4px 0; width: 45%; vertical-align: top;">
                    • {{ $area['natural_location'] ?? '' }}
                </td>
                <td style="font-size: 10px; color: #742A2A; padding: 4px 0; vertical-align: top;">
                    {{ $area['instruction'] ?? '' }}
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif
</div>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Patient Scan Images ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">PATIENT SCAN IMAGES (BASELINE)</td>
        </tr>
    </table>

    <div class="section-card">
        <div class="section-label">CLINICAL PHOTOGRAPHIC DOCUMENTATION</div>
        <div class="section-text" style="margin-bottom: 20px;">
            The following diagnostic images were captured during the baseline scan using multiple light wavelengths (polarized, UV, and Wood's lamp) to map superficial and deep pigment distribution:
        </div>

        <table width="100%" cellpadding="0" cellspacing="0">
            @php
                $sortedImages = collect($record->images ?? []);
                $chunks = $sortedImages->chunk(2);
            @endphp
            @forelse($chunks as $chunk)
                <tr>
                    @foreach($chunk as $img)
                        <td width="48%" align="center" style="padding: 10px; border: 1px solid #E2E8F0; border-radius: 8px; background: #FFFFFF; vertical-align: top;">
                            <div class="img-box" style="border: none; padding: 0;">
                                @if(!empty($img['url']))
                                    <img src="{{ $img['url'] }}" style="max-height: 250px; max-width: 100%;">
                                @else
                                    <div style="padding: 50px; color: #999;">NO IMAGE</div>
                                @endif
                            </div>
                            <div class="info-label" style="margin-top: 8px; font-size: 10px;">{{ ucwords(str_replace('_', ' ', $img['name'] ?? 'Face Scan')) }}</div>
                        </td>
                        @if($loop->first && $chunk->count() == 1)
                            <td width="4%"></td>
                            <td width="48%"></td>
                        @elseif($loop->first)
                            <td width="4%"></td>
                        @endif
                    @endforeach
                </tr>
                @if(!$loop->last)
                    <tr><td height="15" colspan="3"></td></tr>
                @endif
            @empty
                <tr>
                    <td align="center" style="padding: 30px; color: #999;">No photographic scans available.</td>
                </tr>
            @endforelse
        </table>
    </div>
</div>

@endsection
