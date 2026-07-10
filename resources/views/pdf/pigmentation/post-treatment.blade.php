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
        font-size: 13px;
        color: #2D3748;
        line-height: 1.6;
        text-align: justify;
        margin-bottom: 30px;
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

    /* ─── Table Design ─── */
    table.comparison-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        border: 1px solid #E2E8F0;
        margin-bottom: 30px;
    }
    table.comparison-table th {
        padding: 10px 12px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #C29F5D;
        border-right: 1px solid #E2E8F0;
        color: #0E2B5C;
        background-color: #F8FAFC;
        font-weight: bold;
    }
    table.comparison-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #E2E8F0;
        border-right: 1px solid #E2E8F0;
        font-size: 12px;
        color: #1A202C;
        vertical-align: top;
        line-height: 1.5;
    }
    .badge-status {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 10px;
        margin-right: 5px;
        display: inline-block;
    }
</style>

@php
    $patient = $record->user;
    $reassessment = $post_diagnosis['reassessment'] ?? [];
    $overall = $reassessment['overall'] ?? [];
    
    $age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A';
    $gender = $patient->gender ? strtoupper(substr($patient->gender, 0, 1)) : 'N/A';
    
    $trajectory = strtolower($overall['trajectory'] ?? 'unknown');
    
    $trajColor = '#4A5568';
    $trajBg = '#F8FAFC';
    if ($trajectory === 'improving') {
        $trajColor = '#065F46';
        $trajBg = '#F0FDF4';
    } elseif ($trajectory === 'worsening') {
        $trajColor = '#9B1C1C';
        $trajBg = '#FDF2F2';
    } elseif ($trajectory === 'mixed' || $trajectory === 'plateaued') {
        $trajColor = '#B45309';
        $trajBg = '#FFFBEB';
    }
@endphp

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">PIGMENTATION REASSESSMENT REPORT</div>
        <div class="report-subtitle">Treatment Trajectory & Comparative Analysis</div>
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

    {{-- ── Overall Progress Status ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">OVERALL PROGRESS STATUS</td>
        </tr>
    </table>

    <div style="background-color: {{ $trajBg }}; border: 1px solid {{ $trajColor }}; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
        <div style="font-size: 13px; font-weight: bold; color: {{ $trajColor }}; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
            Overall Trajectory: {{ ucwords(str_replace('_', ' ', $trajectory)) }}
        </div>
        <div style="font-size: 12px; color: #4A5568; line-height: 1.5;">
            {{ $overall['summary'] ?? 'No summary provided.' }}
        </div>
    </div>

    {{-- ── Diagnosis Re-examine Warning ── --}}
    @if(!empty($reassessment['diagnosis_reexamine']['needed']))
    <div style="background-color: #FDF2F2; border-left: 4px solid #9B1C1C; padding: 12px; margin-bottom: 25px; border-radius: 4px;">
        <div style="font-size: 12px; font-weight: bold; color: #9B1C1C; margin-bottom: 3px;">
            ⚠️ ALERT: DIAGNOSIS RE-EXAMINATION REQUIRED
        </div>
        <div style="font-size: 11px; color: #7F1D1D; line-height: 1.4;">
            {{ $reassessment['diagnosis_reexamine']['reason'] ?? '' }}
        </div>
    </div>
    @endif

    {{-- ── Goal-by-Goal Scorecard ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">GOAL-BY-GOAL SCORECARD</td>
        </tr>
    </table>

    <table class="comparison-table">
        <thead>
            <tr>
                <th align="left">Metric / Parameter</th>
                <th align="center" style="width: 12%;">Baseline</th>
                <th align="center" style="width: 18%;">Target</th>
                <th align="center" style="width: 18%;">Current</th>
                <th align="center" style="width: 12%;">Delta</th>
                <th align="left">Status / Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reassessment['goals'] ?? [] as $g)
            @php
                $status = strtolower($g['status'] ?? 'unknown');
                $stColor = '#4A5568';
                if ($status === 'met' || $status === 'on_track') $stColor = '#065F46';
                elseif ($status === 'worsening') $stColor = '#9B1C1C';
                elseif ($status === 'plateaued') $stColor = '#B45309';
            @endphp
            <tr>
                <td style="font-weight: bold; color: #0E2B5C;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $g['metric'] ?? '')) }}
                </td>
                <td align="center">
                    {{ $g['baseline'] ?? '—' }}
                </td>
                <td align="center">
                    {{ ucwords(str_replace(['_', '-'], ' ', $g['target'] ?? '—')) }}
                </td>
                <td align="center" style="font-weight: bold;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $g['current'] ?? '—')) }}
                </td>
                <td align="center" style="font-weight: bold; color: #C29F5D;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $g['delta'] ?? '—')) }}
                </td>
                <td>
                    <span class="badge-status" style="color: {{ $stColor }};">
                        [{{ str_replace('_', ' ', $status) }}]
                    </span>
                    {{ $g['comment'] ?? '' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" align="center" style="color: #6B7280;">No goal metrics comparison found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Regional Changes ── --}}
    @if(!empty($reassessment['regional_changes']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">REGIONAL METRIC COMPARISON</td>
        </tr>
    </table>

    <table class="comparison-table">
        <thead>
            <tr>
                <th align="left">Facial Zone</th>
                <th align="center" style="width: 25%;">Melanin (Baseline → Current)</th>
                <th align="center" style="width: 25%;">Erythema (Baseline → Current)</th>
                <th align="left">Zone Trajectory &amp; Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reassessment['regional_changes'] as $reg)
            @php
                $traj = strtolower($reg['trajectory'] ?? 'unknown');
                $trColor = '#4A5568';
                if ($traj === 'improving') $trColor = '#065F46';
                elseif ($traj === 'worsening') $trColor = '#9B1C1C';
                elseif ($traj === 'mixed' || $traj === 'plateaued') $trColor = '#B45309';
            @endphp
            <tr>
                <td style="font-weight: bold; color: #0E2B5C;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $reg['region'] ?? '')) }}
                </td>
                <td align="center">
                    {{ $reg['baseline_melanin_load'] ?? '—' }} → {{ $reg['current_melanin_load'] ?? '—' }}
                </td>
                <td align="center">
                    {{ $reg['baseline_erythema_load'] ?? '—' }} → {{ $reg['current_erythema_load'] ?? '—' }}
                </td>
                <td>
                    <span class="badge-status" style="color: {{ $trColor }};">
                        [{{ $traj }}]
                    </span>
                    {{ $reg['comment'] ?? '' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── Treatment Adjustment Suggestions ── --}}
    @if(!empty($reassessment['treatment_adjustment_suggestion']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">CLINICAL TREATMENT PROTOCOL ADJUSTMENTS</td>
        </tr>
    </table>

    <table class="comparison-table">
        <thead>
            <tr>
                <th width="35%" align="left">Modality / Technology</th>
                <th width="65%" align="left">Suggested Adaptation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reassessment['treatment_adjustment_suggestion'] as $modality => $suggestion)
            <tr>
                <td style="font-weight: bold; color: #0E2B5C;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $modality)) }}
                </td>
                <td style="font-weight: bold;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $suggestion)) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ── Clinician Recommendations ── --}}
    @if(!empty($reassessment['recommendation']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">CLINICIAN RECOMMENDATION</td>
        </tr>
    </table>

    <div style="border: 1px solid #C29F5D; border-radius: 8px; padding: 15px; margin-bottom: 25px; background-color: #FFFDF9;">
        <div style="font-size: 12px; font-weight: bold; color: #0E2B5C; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
            Action Mode: {{ ucwords(str_replace('_', ' ', $reassessment['recommendation']['action'] ?? '')) }}
        </div>
        <div style="font-size: 12px; color: #4A5568; line-height: 1.5; text-align: justify;">
            {{ $reassessment['recommendation']['detail'] ?? '' }}
        </div>
    </div>
    @endif

    {{-- ── Patient Summary ── --}}
    @if(!empty($reassessment['patient_summary']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">SUMMARY FOR THE PATIENT</td>
        </tr>
    </table>

    <div class="overview-text" style="margin-bottom: 25px;">
        {{ $reassessment['patient_summary'] }}
    </div>
    @endif

    {{-- ── Clinical Uncertainties ── --}}
    @if(!empty($reassessment['uncertainties']) && count($reassessment['uncertainties']) > 0)
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">CLINICAL UNCERTAINTIES &amp; DETECTED GAPS</td>
        </tr>
    </table>

    <ul style="font-size: 12px; color: #4A5568; line-height: 1.6; margin-bottom: 25px; padding-left: 20px;">
        @foreach ($reassessment['uncertainties'] as $u)
            <li style="margin-bottom: 4px;">{{ $u }}</li>
        @endforeach
    </ul>
    @endif

    {{-- ── Disclaimer ── --}}
    @if(!empty($reassessment['disclaimer']))
    <div style="font-size: 10px; color: #888888; font-style: italic; margin-top: 30px;">
        * {{ $reassessment['disclaimer'] }}
    </div>
    @endif
</div>

@endsection
