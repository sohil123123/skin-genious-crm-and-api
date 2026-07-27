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

    /* Goals & Modalities side by side */
    .two-col-table { width: 100%; margin-bottom: 14px; }
    .col-box {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 10px 12px;
        background: #fff;
        vertical-align: top;
    }
    .col-title {
        font-size: 14px;
        font-weight: bold;
        color: #0E2B5C;
        letter-spacing: 1px;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #C29F5D;
    }
    .goal-item {
        font-size: 12px;
        color: #444;
        padding: 4px 0 4px 10px;
        position: relative;
        line-height: 1.5;
        border-bottom: 1px dotted #eee;
    }

    /* Session cards */
    .session-grid { width: 100%; }
    .session-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px 10px;
        background: #fafafa;
        vertical-align: top;
    }
    .session-label {
        font-size: 11px;
        font-weight: bold;
        color: #C29F5D;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 4px;
        line-height: 1.4;
    }
    .session-name {
        font-size: 14px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 4px;
        line-height: 1.4;
    }
    .session-meta {
        font-size: 11px;
        color: #666;
        margin-bottom: 4px;
        line-height: 1.4;
    }
    .session-focus {
        font-size: 12px;
        color: #444;
        line-height: 1.5;
    }

    /* Detailed procedure styles */
    .session-header-badge {
        background: #0E2B5C;
        color: #fff;
        font-size: 14px;
        font-weight: bold;
        padding: 10px 14px;
        border-radius: 6px;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .badge-timing {
        float: right;
        font-size: 10px;
        background: #C29F5D;
        padding: 2px 6px;
        border-radius: 4px;
        color: #fff;
    }
    .procedure-step-card {
        border: 1px solid #E2E8F0;
        border-left: 4px solid #0E2B5C;
        padding: 12px;
        margin-bottom: 12px;
        border-radius: 6px;
        background: #FFFFFF;
        page-break-inside: avoid;
    }
    .step-number-badge {
        background: #E0E7FF;
        color: #0E2B5C;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 4px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .step-duration-badge {
        background: #FFFBEB;
        color: #B45309;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 4px;
        font-weight: bold;
        margin-left: 5px;
    }
    .detail-label {
        font-weight: bold;
        color: #4b5563;
        font-size: 11px;
    }
</style>

@php
    $plan = $recommended_full_plan['linear_treatment_plan'] ?? $recommended_full_plan ?? [];
    $treatmentsList = $sessions['treatments'] ?? $plan['current_treatment_block']['sessions'] ?? $plan['sessions'] ?? [];

    $treatmentGoals = [];
    if (!empty($plan['treatment_goals'])) {
        foreach ($plan['treatment_goals'] as $timeframe => $g) {
            if (!empty($g['clinical_goal'])) {
                $treatmentGoals[] = $g['clinical_goal'];
            }
        }
    } elseif (!empty($plan['measurable_goals'])) {
        foreach ($plan['measurable_goals'] as $timeframe => $g) {
            if (!empty($g['clinical_goal'])) {
                $treatmentGoals[] = $g['clinical_goal'];
            }
        }
    }

    if (empty($treatmentGoals)) {
        $treatmentGoals = collect($treatmentsList)
            ->pluck('concerns_addressed')
            ->flatten(1)
            ->unique()
            ->values()
            ->toArray();
    }

    $clientReport = $recommended_full_plan['client_report'] ?? $plan['client_report'] ?? [];
    $clientRoadmap = $clientReport['component_roadmap'] ?? $clientReport['roadmap'] ?? [];
    $futureBlocks = $recommended_full_plan['future_treatment_roadmap']['future_blocks'] ?? $plan['future_treatment_roadmap']['future_blocks'] ?? [];
@endphp

{{-- ── Title ── --}}
<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">PIGMENTATION TREATMENT PROTOCOL</div>
        <div class="report-subtitle">Personalized Laser and Clinical Treatment Sequencing</div>
    </div>
</div>

{{-- ── Patient Info ── --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
    <tr>
        <td width="48%" valign="top">
            <div class="info-card">
                <div class="info-label">PATIENT NAME</div>
                <div class="info-value">{{ $client['name'] ?? 'N/A' }}</div>
            </div>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top">
            <div class="info-card">
                <div class="info-label">AGE / GENDER</div>
                <div class="info-value">{{ $client['age'] ?? 'N/A' }} / {{ strtoupper(substr($client['gender'] ?? 'N/A', 0, 1)) }}</div>
            </div>
        </td>
    </tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
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
                <div class="info-label">CLINIC</div>
                <div class="info-value">{{ $client['clinic'] ?? 'Main Clinic' }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ── Goals ── --}}
<table class="two-col-table" cellpadding="0" cellspacing="0" style="page-break-inside: avoid;">
    <tr>
        <td class="col-box">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="100%" class="col-title">— TREATMENT GOALS —</td>
                </tr>
                <tr><td style="padding-bottom: 8px;"></td></tr>
                @forelse ($treatmentGoals as $goal)
                <tr>
                    <td width="100%" class="goal-item">• &nbsp;{{ is_array($goal) ? ($goal['concern'] ?? 'Pigmentation Treatment') : $goal }}</td>
                </tr>
                @empty
                <tr>
                    <td width="100%" class="goal-item">Pigmentation reduction & skin rejuvenation</td>
                </tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

{{-- ── Session Timeline ── --}}
<table class="section-box" width="100%" cellpadding="0" cellspacing="0" style="page-break-inside: avoid; margin-top: 10px;">
    <tr>
        <td width="100%" class="col-title">— SESSION TIMELINE —</td>
    </tr>
    <tr>
        <td style="padding-top: 10px;">
            <table class="session-grid" cellpadding="5" cellspacing="0">
                @php
                    $chunks = collect($treatmentsList)->chunk(2);
                @endphp
                @foreach ($chunks as $row)
                <tr>
                    @foreach ($row as $session)
                    @php
                        $timingLabel = str_replace('week_', 'Week ', $session['timing'] ?? $session['week'] ?? $session['session_number']);
                        $focusLabel = '';
                        if (!empty($session['treated_component_ids'])) {
                            $focusLabel = implode(', ', array_map(function($id) { return str_replace('_', ' ', ucwords($id)); }, $session['treated_component_ids']));
                        } elseif (!empty($session['concerns_addressed'])) {
                            $focusLabel = collect($session['concerns_addressed'])->implode(', ');
                        }
                    @endphp
                    <td width="50%" class="session-card" style="margin: 4px;">
                        <div class="session-label">Session {{ $session['session_number'] }} — {{ $timingLabel }}</div>
                        <div class="session-name">{{ $session['goal'] ?? $session['title'] ?? 'Treatment Session' }}</div>
                        <div class="session-meta">Duration: {{ $session['treatment_time'] ?? '45' }} Mins</div>
                        <div class="session-focus">Focus: {{ $focusLabel ?: 'Pigmentation treatment' }}</div>
                    </td>
                    @endforeach
                    @if($row->count() == 1)
                        <td width="50%" style="border: none; background: transparent;"></td>
                    @endif
                </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

@if (!empty($futureBlocks))
{{-- ── Future Treatment Roadmap ── --}}
<table class="section-box" width="100%" cellpadding="0" cellspacing="0" style="page-break-inside: avoid; margin-top: 15px;">
    <tr>
        <td width="100%" class="col-title">— FUTURE TREATMENT ROADMAP (PROVISIONAL) —</td>
    </tr>
    <tr>
        <td style="padding-top: 6px;">
            <p style="font-size: 11px; color: #666; margin-bottom: 12px; font-style: italic; line-height: 1.5;">
                These subsequent blocks are provisional. Detailed parameters will be generated dynamically post-reassessment based on patient response.
            </p>
        </td>
    </tr>
    <tr>
        <td>
            <table width="100%" cellpadding="0" cellspacing="0">
                @foreach ($futureBlocks as $index => $block)
                @if ($index > 0)
                <tr>
                    <td style="height: 12px; font-size: 1px; line-height: 1px;">&nbsp;</td>
                </tr>
                @endif
                <tr>
                    <td style="background: #fafafa; border: 1px solid #e2e8f0; border-left: 3.5px solid #C29F5D; border-radius: 4px; padding: 12px;">
                        <div style="font-size: 15px; font-weight: bold; color: #0E2B5C; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                            {{ str_replace('_', ' ', ucwords($block['provisional_block_id'] ?? '')) }}
                        </div>
                        @if(!empty($block['expected_objectives']))
                        <div style="font-size: 13px; color: #333; line-height: 1.7; margin-bottom: 6px;">
                            <span style="font-weight: bold; color: #475569;">Expected Objectives:</span> {{ implode(', ', $block['expected_objectives']) }}
                        </div>
                        @endif
                        @if(!empty($block['likely_modality_categories']))
                        <div style="font-size: 13px; color: #333; line-height: 1.7; margin-bottom: 6px;">
                            <span style="font-weight: bold; color: #475569;">Likely Modalities:</span> {{ implode(', ', array_map(function($m) { return ucwords(str_replace('_', ' ', $m)); }, $block['likely_modality_categories'])) }}
                        </div>
                        @endif
                        @if(!empty($block['expected_response']))
                        <div style="font-size: 13px; color: #333; line-height: 1.7; margin-bottom: 6px;">
                            <span style="font-weight: bold; color: #475569;">Expected Response:</span> {{ $block['expected_response'] }}
                        </div>
                        @endif
                        @if(!empty($block['finalization_rule']))
                        <div style="font-size: 11.5px; color: #888; font-style: italic; margin-top: 6px; border-top: 1px dashed #eee; padding-top: 4px;">
                            * {{ $block['finalization_rule'] }}
                        </div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
@endif

@if (!empty($clientReport))
{{-- ── Client Communication & Report ── --}}
<table class="section-box" width="100%" cellpadding="0" cellspacing="0" style="page-break-inside: avoid; margin-top: 15px;">
    <tr>
        <td width="100%" class="col-title">— CLIENT COMMUNICATION &amp; REPORT —</td>
    </tr>
    <tr>
        <td style="padding: 12px 0 0 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
                @if (!empty($clientReport['headline']))
                <tr>
                    <td style="padding-bottom: 18px;">
                        <div style="font-size: 17px; font-weight: bold; color: #0E2B5C; font-style: italic; line-height: 1.6; padding: 10px 14px; background: #F8FAFC; border-left: 3px solid #C29F5D; border-radius: 0 4px 4px 0;">
                            "{{ $clientReport['headline'] }}"
                        </div>
                    </td>
                </tr>
                @endif

                @if (!empty($clientReport['simple_explanation']))
                <tr>
                    <td style="font-size: 14.5px; color: #2D3748; line-height: 1.7; padding-bottom: 24px;">
                        {{ $clientReport['simple_explanation'] }}
                    </td>
                </tr>
                @endif

                @if (!empty($clientRoadmap))
                <tr>
                    <td style="font-size: 14.5px; font-weight: bold; color: #0E2B5C; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 12px;">
                        Patient Roadmap Milestones:
                    </td>
                </tr>
                <tr>
                    <td style="padding-bottom: 15px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            @foreach ($clientRoadmap as $index => $step)
                            <tr>
                                <td valign="top" style="width: 24px; padding-bottom: 12px; font-size: 16px; font-weight: bold; color: #0E2B5C; line-height: 1.7;">
                                    {{ $index + 1 }}.
                                </td>
                                <td valign="top" style="font-size: 14.5px; color: #2D3748; line-height: 1.7; padding-bottom: 12px; padding-left: 4px;">
                                    {{ $step }}
                                </td>
                            </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                @endif

                @if (!empty($clientReport['disclaimer']))
                <tr>
                    <td style="font-size: 12px; color: #718096; font-style: italic; border-top: 1px solid #E2E8F0; padding-top: 10px; line-height: 1.6;">
                        * {{ $clientReport['disclaimer'] }}
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
@endif

<div class="generated-note" style="margin-top: 15px;">Doctor-designed, AI Controlled and Human Delivered</div>

@endsection
