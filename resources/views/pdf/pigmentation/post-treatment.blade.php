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

    /* ─── Image Comparison ─── */
    .image-frame-before {
        border: 1px solid #E2E8F0;
        padding: 5px;
        background: #FFFFFF;
        border-radius: 6px;
    }
    .image-frame-after {
        border: 1px solid #C29F5D;
        padding: 5px;
        background: #FFFFFF;
        border-radius: 6px;
    }
    .comparison-image {
        width: 100%;
        display: block;
        border-radius: 4px;
    }
    .comparison-divider {
        width: 1px;
        height: 180px;
        background-color: #E2E8F0;
        margin: 0 auto;
    }
    .comparison-wrapper {
        margin-top: 20px;
        page-break-inside: avoid;
    }
</style>

@php
    $patient = $record->user;
    $reassessment = $post_diagnosis['reassessment'] ?? [];
    $comparison = $reassessment['reassessment_comparison'] ?? $reassessment;
    $overall = $comparison['overall'] ?? [];
    
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

    // Determine diagnosis recheck warning
    $diagnosisRecheckNeeded = !empty($reassessment['diagnosis_reexamine']['needed']);
    $recheckReason = $reassessment['diagnosis_reexamine']['reason'] ?? '';
    
    if (!$diagnosisRecheckNeeded && !empty($reassessment['component_decisions'])) {
        foreach ($reassessment['component_decisions'] as $cd) {
            if (!empty($cd['diagnosis_recheck_triggered'])) {
                $diagnosisRecheckNeeded = true;
                $compName = ucwords(str_replace(['_', '-'], ' ', $cd['diagnostic_component_id'] ?? ''));
                if (empty($recheckReason)) {
                    $recheckReason = "Diagnosis recheck triggered for component: " . $compName;
                } else {
                    $recheckReason .= ", " . $compName;
                }
            }
        }
    }

    // Unify goals scorecard: global_metrics (V2) or goals (V1)
    $goalsList = $comparison['global_metrics'] ?? $comparison['goals'] ?? [];

    // Unify recommendation action & detail
    $recAction = $reassessment['recommendation']['action'] ?? $reassessment['continuity_with_master_roadmap']['action'] ?? '';
    $recDetail = $reassessment['recommendation']['detail'] ?? $reassessment['continuity_with_master_roadmap']['detail'] ?? '';

    // Unify patient summary
    $patSummary = $reassessment['patient_summary'] ?? $overall['summary'] ?? $reassessment['continuity_with_master_roadmap']['changes_explained'] ?? '';
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

    {{-- ── Block Closure & Continuity ── --}}
    @if(!empty($reassessment['previous_block_closure']) || !empty($reassessment['continuity_with_master_roadmap']))
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 25px; page-break-inside: avoid;">
        <tr>
            @if(!empty($reassessment['previous_block_closure']))
            <td width="{{ !empty($reassessment['continuity_with_master_roadmap']) ? '48%' : '100%' }}" valign="top" style="background: #F0FDF4; border: 1px solid #115E59; border-left: 4px solid #115E59; border-radius: 6px; padding: 12px;">
                <div style="font-size: 12px; font-weight: bold; color: #115E59; text-transform: uppercase; margin-bottom: 5px;">
                    Previous Block Closure ({{ str_replace('_', ' ', ucwords($reassessment['previous_block_closure']['block_id'] ?? '')) }})
                </div>
                <div style="font-size: 11px; color: #4A5568; margin-bottom: 4px;">
                    <strong>Completed Sessions:</strong> {{ $reassessment['previous_block_closure']['completed_sessions'] ?? 0 }}
                </div>
                @if(!empty($reassessment['previous_block_closure']['deviations_from_plan']))
                    <ul style="font-size: 10px; color: #4B5563; margin-top: 4px; padding-left: 15px; margin-bottom: 6px; line-height: 1.4;">
                        @foreach($reassessment['previous_block_closure']['deviations_from_plan'] as $dev)
                            <li>{{ $dev }}</li>
                        @endforeach
                    </ul>
                @endif
                <div style="font-size: 11px; color: #374151; line-height: 1.45;">
                    {{ $reassessment['previous_block_closure']['block_outcome_summary'] ?? '' }}
                </div>
            </td>
            @endif

            @if(!empty($reassessment['previous_block_closure']) && !empty($reassessment['continuity_with_master_roadmap']))
            <td width="4%"></td>
            @endif

            @if(!empty($reassessment['continuity_with_master_roadmap']))
            <td width="{{ !empty($reassessment['previous_block_closure']) ? '48%' : '100%' }}" valign="top" style="background: #EFF6FF; border: 1px solid #1D4ED8; border-left: 4px solid #1D4ED8; border-radius: 6px; padding: 12px;">
                <div style="font-size: 12px; font-weight: bold; color: #1D4ED8; text-transform: uppercase; margin-bottom: 5px;">
                    Roadmap Continuity (Action: {{ str_replace('_', ' ', ucwords($reassessment['continuity_with_master_roadmap']['action'] ?? '')) }})
                </div>
                <div style="font-size: 11px; color: #374151; line-height: 1.45; margin-bottom: 6px;">
                    {{ $reassessment['continuity_with_master_roadmap']['detail'] ?? '' }}
                </div>
                <div style="font-size: 10px; color: #4B5563; font-style: italic; border-top: 1px dashed #BFDBFE; padding-top: 4px;">
                    <strong>Changes Explained:</strong> {{ $reassessment['continuity_with_master_roadmap']['changes_explained'] ?? '' }}
                </div>
            </td>
            @endif
        </tr>
    </table>
    @endif

    {{-- ── Diagnosis Re-examine Warning ── --}}
    @if($diagnosisRecheckNeeded)
    <div style="background-color: #FDF2F2; border-left: 4px solid #9B1C1C; padding: 12px; margin-bottom: 25px; border-radius: 4px;">
        <div style="font-size: 12px; font-weight: bold; color: #9B1C1C; margin-bottom: 3px;">
            ⚠️ ALERT: DIAGNOSIS RE-EXAMINATION REQUIRED
        </div>
        <div style="font-size: 11px; color: #7F1D1D; line-height: 1.4;">
            {{ $recheckReason }}
        </div>
    </div>
    @endif

    <pagebreak page-selector="report_content" />

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
            @forelse ($goalsList as $g)
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

    {{-- ── Component Outcomes (V2) or Regional changes (V1) ── --}}
    @if(!empty($comparison['component_outcomes']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">COMPONENT CLINICAL OUTCOMES</td>
        </tr>
    </table>

    <table class="comparison-table">
        <thead>
            <tr>
                <th align="left">Component ID</th>
                <th align="left" style="width: 30%;">Working Diagnosis</th>
                <th align="center" style="width: 15%;">Trajectory</th>
                <th align="center" style="width: 15%;">Status</th>
                <th align="left">Clinical Comments</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comparison['component_outcomes'] as $comp)
            @php
                $trajVal = strtolower($comp['trajectory'] ?? 'unknown');
                $trColor = '#4A5568';
                if ($trajVal === 'improving') $trColor = '#065F46';
                elseif ($trajVal === 'worsening') $trColor = '#9B1C1C';
                elseif ($trajVal === 'mixed' || $trajVal === 'plateaued') $trColor = '#B45309';

                $statusVal = strtolower($comp['target_status'] ?? 'unknown');
                $stColor = '#4A5568';
                if ($statusVal === 'met' || $statusVal === 'on_track') $stColor = '#065F46';
                elseif ($statusVal === 'worsening') $stColor = '#9B1C1C';
            @endphp
            <tr>
                <td style="font-weight: bold; color: #0E2B5C;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $comp['diagnostic_component_id'] ?? '')) }}
                </td>
                <td>
                    {{ ucwords(str_replace(['_', '-'], ' ', $comp['diagnosis'] ?? '—')) }}
                </td>
                <td align="center" style="font-weight: bold; color: {{ $trColor }};">
                    {{ ucwords($trajVal) }}
                </td>
                <td align="center">
                    <span class="badge-status" style="color: {{ $stColor }}; font-size: 10px;">
                        [{{ str_replace('_', ' ', $statusVal) }}]
                    </span>
                </td>
                <td style="font-size: 11px;">
                    {{ $comp['comment'] ?? '' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @elseif(!empty($comparison['regional_changes']))
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
            @foreach ($comparison['regional_changes'] as $reg)
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

    {{-- ── New or Changed Morphology Groups ── --}}
    @if(!empty($comparison['new_or_changed_morphology_groups']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">⚠️ NEW OR CHANGED MORPHOLOGY GROUPS</td>
        </tr>
    </table>
    <div style="background-color: #FFFBEB; border: 1px solid #D97706; border-left: 4px solid #D97706; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
        @foreach($comparison['new_or_changed_morphology_groups'] as $mg)
        <div style="margin-bottom: 8px; font-size: 12px;">
            <strong style="color: #B45309;">Group {{ $mg['group_id'] }}: {{ ucwords($mg['change'] ?? '') }}</strong>
            <div style="color: #4A5568; margin-top: 3px; line-height: 1.45;">
                {{ $mg['clinical_implication'] ?? '' }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Component Decisions (V2) or Treatment Adjustments (V1) ── --}}
    @if(!empty($reassessment['component_decisions']))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">COMPONENT DECISIONS &amp; ADJUSTMENTS</td>
        </tr>
    </table>

    <table class="comparison-table">
        <thead>
            <tr>
                <th width="20%" align="left">Component ID</th>
                <th width="15%" align="left">Decision</th>
                <th width="30%" align="left">Reasoning / Rationale</th>
                <th width="35%" align="left">Preferred Modality &amp; Target</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reassessment['component_decisions'] as $cd)
            <tr>
                <td style="font-weight: bold; color: #0E2B5C;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $cd['diagnostic_component_id'] ?? '')) }}
                </td>
                <td style="font-weight: bold; color: #C29F5D;">
                    {{ ucwords(str_replace(['_', '-'], ' ', $cd['decision'] ?? '')) }}
                </td>
                <td style="font-size: 11px;">
                    {{ $cd['reason'] ?? '' }}
                </td>
                <td style="font-size: 11px;">
                    <strong>Modality:</strong> {{ ucwords(str_replace(['_', '-'], ' ', $cd['updated_preferred_modality'] ?? '—')) }}
                    @if(!empty($cd['updated_target']))
                        <br/><strong style="color: #4A5568;">Target:</strong> {{ $cd['updated_target'] }}
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @elseif(!empty($reassessment['treatment_adjustment_suggestion']))
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
    @if(!empty($recAction))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">CLINICIAN RECOMMENDATION</td>
        </tr>
    </table>

    <div style="border: 1px solid #C29F5D; border-radius: 8px; padding: 15px; margin-bottom: 25px; background-color: #FFFDF9;">
        <div style="font-size: 12px; font-weight: bold; color: #0E2B5C; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
            Action Mode: {{ ucwords(str_replace('_', ' ', $recAction)) }}
        </div>
        <div style="font-size: 12px; color: #4A5568; line-height: 1.5; text-align: justify;">
            {{ $recDetail }}
        </div>
    </div>
    @endif

    {{-- ── Patient Summary ── --}}
    @if(!empty($patSummary))
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">SUMMARY FOR THE PATIENT</td>
        </tr>
    </table>

    <div class="overview-text" style="margin-bottom: 25px;">
        {{ $patSummary }}
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

    {{-- ── Comparative Scans ── --}}
    @php
        $imageOrder = config('project.assessment_image_order_5');

        // Create image maps using custom properties 'mode' or name
        $assessmentImageMap = [];
        if (isset($assessmentImages)) {
            foreach ($assessmentImages as $img) {
                $mode = $img['custom_properties']['mode'] ?? $img['name'];
                $assessmentImageMap[$mode] = $img['url'];
            }
        }

        $postAssessmentImageMap = [];
        if (isset($postAssessmentImages)) {
            foreach ($postAssessmentImages as $img) {
                $mode = $img['custom_properties']['mode'] ?? $img['name'];
                $postAssessmentImageMap[$mode] = $img['url'];
            }
        }

        // Prepare image pages data
        $imagePages = [];
        foreach ($imageOrder as $imageType) {
            $beforeImageUrl = $assessmentImageMap[$imageType] ?? null;
            $afterImageUrl = $postAssessmentImageMap[$imageType] ?? null;

            if ($beforeImageUrl || $afterImageUrl) {
                $imagePages[$imageType] = [
                    'before_image' => $beforeImageUrl,
                    'after_image' => $afterImageUrl,
                ];
            }
        }
    @endphp

    @if(count($imagePages) > 0)
        @foreach($imagePages as $imageType => $imageData)
            @php
                $beforeImageUrl = $imageData['before_image'] ?? null;
                $afterImageUrl = $imageData['after_image'] ?? null;
            @endphp

            <pagebreak page-selector="report_content" />

            <div class="comparison-wrapper" style="margin-top: 20px;">
                <div style="background-color: #0E2B5C; color: white; padding: 10px; border-radius: 4px; font-weight: bold; font-size: 14px; text-align: center; margin-bottom: 20px;">
                    {{ str_replace('_', ' ', strtoupper($imageType)) }} LIGHT
                </div>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="48%" align="center" style="vertical-align: top;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
                                <tr>
                                    <td style="font-weight: bold; color: #4A5568; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        01. BEFORE ({{ isset($compare_type) && $compare_type === 'baseline' ? 'Baseline' : 'Previous Session' }})
                                    </td>
                                </tr>
                            </table>
                            <div class="image-frame-before">
                                <img src="{{ $beforeImageUrl ?: public_path('images/no-image.jpg') }}" class="comparison-image">
                            </div>
                        </td>

                        <td width="4%" align="center" style="vertical-align: middle;">
                            <div class="comparison-divider"></div>
                        </td>

                        <td width="48%" align="center" style="vertical-align: top;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
                                <tr>
                                    <td style="font-weight: bold; color: #C29F5D; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        02. AFTER (Current Session)
                                    </td>
                                </tr>
                            </table>
                            <div class="image-frame-after">
                                <img src="{{ $afterImageUrl ?: public_path('images/no-image.jpg') }}" class="comparison-image">
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endif
</div>

@endsection
