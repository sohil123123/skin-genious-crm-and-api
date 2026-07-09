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
        font-size: 9px;
        font-weight: bold;
        color: #C29F5D;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    .session-name {
        font-size: 12px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 2px;
    }
    .session-meta {
        font-size: 9px;
        color: #888;
        margin-bottom: 2px;
    }
    .session-focus {
        font-size: 10px;
        color: #555;
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
    $treatmentsList = $sessions['treatments'] ?? [];
    $treatmentGoals = collect($treatmentsList)
        ->pluck('concerns_addressed')
        ->flatten(1)
        ->unique()
        ->values()
        ->toArray();
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
                    <td width="50%" class="session-card" style="margin: 4px;">
                        <div class="session-label">Session {{ $session['session_number'] }} — Week {{ $session['week'] ?? $session['session_number'] }}</div>
                        <div class="session-name">{{ $session['title'] }}</div>
                        <div class="session-meta">Duration: {{ $session['treatment_time'] ?? '45' }} Mins</div>
                        <div class="session-focus">Focus: {{ collect($session['concerns_addressed'])->implode(', ') }}</div>
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

<div class="generated-note" style="margin-top: 15px;">Doctor-designed, AI Controlled and Human Delivered</div>

{{-- ── Session Procedural Pages ── --}}
@foreach ($treatmentsList as $session)
    <pagebreak />
    
    <div class="session-header-badge">
        {{ $session['title'] }}
        <span class="badge-timing">SESSION {{ $session['session_number'] }} &bull; WEEK {{ $session['week'] ?? $session['session_number'] }}</span>
    </div>

    <table class="two-col-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="col-box" width="48%">
                <div class="col-title">— Therapist Preparation —</div>
                <ul style="margin: 5px 0; padding-left: 15px; font-size: 11px;">
                    @foreach ($session['preparations_checklist_for_therapist'] as $item)
                        <li style="margin-bottom: 5px; line-height: 1.4;">{{ $item }}</li>
                    @endforeach
                </ul>
            </td>
            <td width="4%"></td>
            <td class="col-box" width="48%">
                <div class="col-title">— Focus Area —</div>
                <ul style="margin: 5px 0; padding-left: 15px; font-size: 11px;">
                    @foreach ($session['concerns_addressed'] as $concern)
                        <li style="margin-bottom: 5px; line-height: 1.4;">{{ is_array($concern) ? ($concern['concern'] ?? '') : $concern }}</li>
                    @endforeach
                </ul>
            </td>
        </tr>
    </table>

    <div style="font-weight: bold; color: #0E2B5C; font-size: 12px; margin-top: 15px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Procedure Execution Steps</div>
    
    @foreach ($session['steps'] as $step)
        <div class="procedure-step-card">
            <div>
                <span class="step-number-badge">Step {{ $step['step_number'] }}</span>
                <span class="step-duration-badge">{{ $step['duration'] }}</span>
            </div>
            
            <div style="margin-top: 8px; color: #4b5563; font-size: 11px;">
                <span class="detail-label">Equipment / Ingredients:</span>
                {{ is_array($step['ingredients_equipments']) ? implode(', ', $step['ingredients_equipments']) : $step['ingredients_equipments'] }}
            </div>
            
            <div style="margin-top: 6px; font-size: 12px; line-height: 1.4; color: #374151; white-space: pre-line;">{{ $step['how_to_do'] }}</div>
        </div>
    @endforeach
@endforeach

@endsection
