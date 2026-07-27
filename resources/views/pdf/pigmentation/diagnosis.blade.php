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
    $components = $diagnosis['diagnostic_components'] ?? [];
    $dominantId = $impression['dominant_treatable_component_id'] ?? '';
    $dominantComp = null;
    foreach ($components as $comp) {
        if (($comp['diagnostic_component_id'] ?? '') === $dominantId) {
            $dominantComp = $comp;
            break;
        }
    }
    if (!$dominantComp && !empty($components)) {
        $dominantComp = $components[0];
    }

    $primaryDx = $dominantComp['family'] ?? $dominantComp['subtype'] ?? $impression['primary_category'] ?? 'N/A';
    $primaryDxLabel = ucwords(str_replace('_', ' ', $primaryDx));
    $confidence = $dominantComp['confidence_100'] ?? $impression['primary_confidence_100'] ?? 0;

    $profile = $diagnosis['pigmentation_profile'] ?? [];
    $inputs = $record->pigmentation_inputs ?? [];
    $aiAnalysis = $inputs['aiAnalysis']['data'] ?? [];
    $regional = $aiAnalysis['regional_interpretation'] ?? $diagnosis['regional_interpretation'] ?? [];
    $summaries = $diagnosis['summaries'] ?? [];

    // Support V2 image metrics and scores
    $metrics = $diagnosis['immutable_image_metrics'] ?? [];
    $scores = $diagnosis['scores'] ?? [];
    $melaninLoad = $metrics['global_background_melanin_load_index'] ?? $scores['melanin_load_index'] ?? $profile['melanin_load_index'] ?? 'N/A';
    $erythemaLoad = $metrics['global_background_erythema_load_index'] ?? $scores['erythema_load_index'] ?? $profile['erythema_load_index'] ?? 'N/A';
    $melaninPercent = $scores['composition_melanin_percent'] ?? $profile['composition']['melanin_percent'] ?? 'N/A';
    $vascularPercent = $scores['composition_vascular_percent'] ?? $profile['composition']['vascular_percent'] ?? 'N/A';

    $fitzType = $inputs['formData']['fitz'] ?? $diagnosis['image_analysis']['global_background_indices']['estimated_fitzpatrick']['type'] ?? $diagnosis['image_analysis']['global_indices']['estimated_fitzpatrick']['type'] ?? $profile['estimated_fitzpatrick']['type'] ?? 'N/A';
    $fitzDisplay = ucwords(str_replace('_', ' ', $fitzType));

    $depthType = $inputs['formData']['depth'] ?? ($dominantComp ? ($dominantComp['depth'] ?? '') : ($profile['estimated_depth']['patient_label'] ?? 'N/A'));
    $depthLabel = ucwords(str_replace('_', ' ', $depthType));
    $depthDesc = $dominantComp['patient_explanation'] ?? ($profile['estimated_depth']['patient_explanation'] ?? '');

    $clinicalActivity = $diagnosis['clinical_activity'] ?? [];
    $stabilityStatus = $clinicalActivity['global_stability_status'] ?? $clinicalActivity['stability_status'] ?? 'N/A';
    $activeAcne = !empty($clinicalActivity['active_acne_present']) || !empty($clinicalActivity['active_acne_driver']);
    $inflammationControl = !empty($clinicalActivity['inflammation_first_required_any_component']) || !empty($clinicalActivity['inflammation_first_required']);
    $barrierRepair = !empty($clinicalActivity['barrier_repair_first_required_any_component']) || !empty($clinicalActivity['barrier_repair_first_required']);

    $riskProfile = $diagnosis['risk_profile'] ?? [];
    $redFlagRisk = $riskProfile['medically_atypical_lesion_risk'] ?? $riskProfile['red_flag_lesion_risk'] ?? 'N/A';

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
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $melaninLoad }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Erythema Load Index</td>
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $erythemaLoad }}</td>
                    </tr>
                    <tr style="background-color: #F8FAFC;">
                        <td style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">Melanin Percent</td>
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $melaninPercent }}{{ is_numeric($melaninPercent) ? '%' : '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 11px;">Vascular Percent</td>
                        <td align="right" style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 12px;">{{ $vascularPercent }}{{ is_numeric($vascularPercent) ? '%' : '' }}</td>
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
                        <td align="right" style="padding: 8px; border-bottom: 1px solid #E2E8F0; font-weight: bold; color: #0E2B5C; font-size: 11px;">{{ $fitzDisplay }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 11px;">Estimated Depth</td>
                        <td align="right" style="padding: 8px; font-weight: bold; color: #0E2B5C; font-size: 11px;">{{ $depthLabel }}</td>
                    </tr>
                </table>
                <div style="font-size: 10px; color: #4A5568; line-height: 1.4; text-align: justify; font-style: italic;">
                    <b>Depth Explanation:</b> {{ $depthDesc }}
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
                                    {{ ucfirst($stabilityStatus) }}
                                </span>
                            </td>
                        </tr>
                    </table>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">Acne Driver:</td>
                            <td align="right" style="font-size: 11px; font-weight: bold; color: #1E293B; padding: 6px 0; border-bottom: 1px solid #F1F5F9; text-transform: uppercase;">
                                {{ $activeAcne ? 'ACTIVE' : 'NONE' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #4A5568; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">Inflammation Control:</td>
                            <td align="right" style="font-size: 11px; font-weight: bold; color: #1E293B; padding: 6px 0; border-bottom: 1px solid #F1F5F9; text-transform: uppercase;">
                                {{ $inflammationControl ? 'REQUIRED' : 'NO' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 11.5px; color: #4A5568; padding: 6px 0;">Barrier Repair First:</td>
                            <td align="right" style="font-size: 11px; font-weight: bold; color: #1E293B; padding: 6px 0; text-transform: uppercase;">
                                {{ $barrierRepair ? 'REQUIRED' : 'NO' }}
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
                                {{ ucwords(str_replace('_', ' ', $redFlagRisk)) }}
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

    {{-- ── mMASI Assessment ── --}}
    @if(!empty($diagnosis['mmasi']))
    <div style="page-break-inside: avoid; margin-bottom: 25px;">
        <table class="section-title-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="section-title">mMASI ASSESSMENT</td>
            </tr>
        </table>
        <div class="section-card" style="margin-top: 0; padding: 15px; background-color: {{ !empty($diagnosis['mmasi']['applicable']) ? '#F0FDF4' : '#F8FAFC' }}; border-left: 4px solid {{ !empty($diagnosis['mmasi']['applicable']) ? '#166534' : '#C29F5D' }};">
            @if(!empty($diagnosis['mmasi']['applicable']))
                <div style="font-size: 13px; font-weight: bold; color: #166534;">
                    mMASI Total Score: {{ $diagnosis['mmasi']['score_0_24'] }}/24 (Confidence: {{ $diagnosis['mmasi']['confidence_100'] }}%)
                </div>
            @else
                <div style="font-size: 11.5px; color: #4A5568; line-height: 1.5;">
                    <strong>Not Applicable:</strong> {{ $diagnosis['mmasi']['reason'] ?? 'Not applicable for this pigmentation type.' }}
                </div>
            @endif
        </div>
    </div>
    @endif

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

    @if(!empty($components))
        @foreach($components as $comp)
        <div class="section-card" style="padding: 15px; margin-bottom: 15px; border-left: 4px solid #0E2B5C; background: #FFFFFF; page-break-inside: avoid;">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 5px;">
                <tr>
                    <td style="font-size: 13px; font-weight: bold; color: #0E2B5C; text-transform: uppercase;">
                        {{ $comp['patient_title'] ?? ucwords(str_replace('_', ' ', $comp['family'] ?? '')) }}
                        @if(($comp['diagnostic_component_id'] ?? '') === ($impression['dominant_treatable_component_id'] ?? ''))
                            <span style="background-color: #E3F2FD; color: #1565C0; padding: 2px 6px; font-size: 9px; border-radius: 4px; font-weight: bold; margin-left: 5px; text-transform: uppercase;">Dominant</span>
                        @endif
                    </td>
                    <!-- <td align="right" style="font-size: 10px; color: #718096; font-weight: bold;">
                        {{ $comp['diagnostic_status'] ?? '' }}
                    </td> -->
                </tr>
            </table>

            <div style="font-size: 10.5px; color: #4A5568; margin-top: 4px; line-height: 1.4; text-align: justify; margin-bottom: 8px;">
                {{ $comp['patient_explanation'] ?? '' }}
            </div>

            @if(!empty($comp['evidence_for']))
            <div style="margin-top: 6px; margin-bottom: 6px;">
                <div style="font-size: 9px; font-weight: bold; color: #166534; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 3px;">Supporting Evidence:</div>
                <table width="100%" cellpadding="0" cellspacing="0">
                    @foreach($comp['evidence_for'] as $index => $evidence)
                    <tr>
                        <td valign="top" style="width: 15px; font-size: 10px; color: #166534; line-height: 1.4;">{{ $index + 1 }}.</td>
                        <td valign="top" style="font-size: 10px; color: #4A5568; line-height: 1.4; padding-bottom: 2px;">{{ $evidence }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            @endif

            @if(!empty($comp['evidence_against']))
            <div style="margin-top: 6px; margin-bottom: 6px;">
                <div style="font-size: 9px; font-weight: bold; color: #991b1b; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 3px;">Alternative/Contra-Evidence:</div>
                <table width="100%" cellpadding="0" cellspacing="0">
                    @foreach($comp['evidence_against'] as $evidence)
                    <tr>
                        <td valign="top" style="width: 15px; font-size: 10px; color: #991b1b; line-height: 1.4;">•</td>
                        <td valign="top" style="font-size: 10px; color: #4A5568; line-height: 1.4; padding-bottom: 2px;">{{ $evidence }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            @endif

            @if(!empty($comp['missing_discriminators']))
            <div style="margin-top: 6px; margin-bottom: 8px;">
                <div style="font-size: 9px; font-weight: bold; color: #475569; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 3px;">To Refine Further:</div>
                <table width="100%" cellpadding="0" cellspacing="0">
                    @foreach($comp['missing_discriminators'] as $disc)
                    <tr>
                        <td valign="top" style="width: 15px; font-size: 10px; color: #475569; line-height: 1.4;">▪</td>
                        <td valign="top" style="font-size: 10px; color: #4A5568; line-height: 1.4; padding-bottom: 2px;">{{ $disc }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            @endif

            <div style="font-size: 9.5px; color: #334155; border-top: 1px dashed #e2e8f0; padding-top: 6px; margin-top: 6px; background-color: #f8fafc; padding: 5px 8px; border-radius: 4px; line-height: 1.4;">
                <strong>Regions:</strong> {{ implode(', ', array_map(fn($r) => ucwords(str_replace('_', ' ', $r)), $comp['regions'] ?? [])) }} &nbsp;•&nbsp;
                <strong>Depth:</strong> {{ ucwords($comp['depth'] ?? 'N/A') }} &nbsp;•&nbsp;
                <strong>Activity:</strong> {{ ucwords($comp['activity'] ?? 'N/A') }} &nbsp;•&nbsp;
                <strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $comp['direct_cosmetic_treatment_status'] ?? 'N/A')) }}
            </div>
        </div>
        @endforeach
    @endif

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
    {{-- ── Patient-Facing Explanations ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">PATIENT-FACING EXPLANATIONS</td>
        </tr>
    </table>

    @php
        $rawComponents = $diagnosis['diagnostic_components'] ?? $diagnosis['patient_facing_components'] ?? [];
        $sortedComponents = collect($rawComponents)->map(function($comp) {
            return [
                'title' => $comp['patient_title'] ?? $comp['title'] ?? ucwords(str_replace('_', ' ', $comp['family'] ?? $comp['component'] ?? '')),
                'support_level' => $comp['diagnostic_status'] ?? $comp['support_level'] ?? '',
                'explanation' => $comp['patient_explanation'] ?? $comp['explanation'] ?? '',
                'treatment_meaning' => ucwords(str_replace('_', ' ', $comp['direct_cosmetic_treatment_status'] ?? $comp['treatment_meaning'] ?? '')),
            ];
        })->sortBy(function($comp) {
            $level = strtolower(str_replace(' ', '_', $comp['support_level'] ?? ''));
            if (str_contains($level, 'strong') || str_contains($level, 'probable')) {
                return 1;
            } elseif (str_contains($level, 'moderate') || str_contains($level, 'possible')) {
                return 2;
            } elseif (str_contains($level, 'weak')) {
                return 3;
            }
            return 4;
        });
    @endphp

    <div style="margin-bottom: 20px;">
        @forelse($sortedComponents as $comp)
        <div class="section-card" style="padding: 15px; margin-bottom: 15px; border-left: 4px solid #14b8a6; margin-top: 5px; page-break-inside: avoid; background-color: #FFFFFF;">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 5px;">
                <tr>
                    <td style="font-size: 12.5px; font-weight: bold; color: #0f766e; text-transform: uppercase;">
                        {{ $comp['title'] }}
                    </td>
                    <td align="right" style="font-size: 9.5px; color: #14b8a6; font-weight: bold; text-transform: uppercase;">
                        {{ str_replace('_', ' ', $comp['support_level'] ?? '') }}
                    </td>
                </tr>
            </table>
            <div style="font-size: 11px; color: #334155; margin-top: 4px; line-height: 1.45; text-align: justify; margin-bottom: 8px;">
                {{ $comp['explanation'] }}
            </div>
            <div style="font-size: 10.5px; color: #0f766e; line-height: 1.4; text-align: justify; font-style: italic; background-color: #F8FAFC; padding: 8px 12px; border-radius: 6px; border-left: 3px solid #14b8a6;">
                <b>Treatment Implication:</b> {{ $comp['treatment_meaning'] }}
            </div>
        </div>
        @empty
        <div align="center" style="padding: 20px; color: #999; font-size: 11px;">No explanations available.</div>
        @endforelse
    </div>
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
