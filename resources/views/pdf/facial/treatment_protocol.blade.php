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
    .section-box {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 12px 14px;
        margin-bottom: 14px;
        background: #fff;
    }
    .section-title {
        font-size: 13px;
        font-weight: 900;
        color: #1B3A6B;
        text-align: center;
        letter-spacing: 1px;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #C9A84C;
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
        color: #1B3A6B;
        letter-spacing: 1px;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #C9A84C;
    }
    .goal-item {
        font-size: 12px;
        color: #444;
        padding: 3px 0 3px 10px;
        position: relative;
        line-height: 1.5;
        border-bottom: 1px dotted #eee;
    }
    .modality-text {
        font-size: 12px;
        color: #444;
        line-height: 1.5;
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
        color: #C9A84C;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    .session-name {
        font-size: 12px;
        font-weight: bold;
        color: #1B3A6B;
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
</style>

{{-- ── Title ── --}}
<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">PERSONALIZED TREATMENT PROTOCOL</div>
        <div class="report-subtitle">Luxury aesthetic planning with dermatologist-designed sequencing</div>
    </div>
</div>

{{-- ── Patient Info ── --}}
<table width="100%" cel{{-- ── Key Parameters ── --}}lpadding="0" cellspacing="0" style="margin-bottom: 10px;">
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
                <div class="info-label">SKIN PROFILE</div>
                <div class="info-value">{{ $patient['skin_type'] ?? 'N/A' }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ── Goals + Modalities ── --}}
<table class="two-col-table" cellpadding="5" cellspacing="0" style="page-break-inside: avoid;">
    <tr>
        <td width="48%" class="col-box">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="100%" class="col-title">— TREATMENT GOALS —</td>
                </tr>
                <tr><td style="padding-bottom: 10px;"></td></tr>
                @foreach ($treatment_goals as $goal)
                <tr>
                    <td width="100%" class="goal-item">• &nbsp;{{ $goal['concern'] }}</td>
                </tr>
                @endforeach
            </table>
        </td>
        <td width="4%"></td>
        <td width="48%" class="col-box">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="100%" class="col-title">— TREATMENT MODALITIES —</td>
                </tr>
                <tr><td style="padding-bottom: 10px;"></td></tr>
                <tr>
                    <td width="100%" class="modality-text">{{ $protocol['modalities'] ?? 'N/A' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── Session Timeline ── --}}
<table class="section-box" width="100%" cellpadding="0" cellspacing="0" style="page-break-inside: avoid;">
    <tr>
        <td width="100%" class="col-title">— SESSION TIMELINE —</td>
    </tr>
    <tr>
        <td style="padding-top: 10px;">
            <table class="session-grid" cellpadding="5" cellspacing="0">
                @foreach ($sessions->chunk(2) as $row)
                <tr>
                    @foreach ($row as $session)
                    <td width="50%" class="session-card" style="margin: 4px;">
                        <div class="session-label">Session {{ $session['session_number'] }} — Week {{ $session['week'] }}</div>
                        <div class="session-name">{{ $session['title'] }}</div>
                        <div class="session-meta">Duration: {{ $session['treatment_time'] }} Mins</div>
                        <div class="session-focus">Focus: {{ collect($session['concerns_addressed'])->pluck('concern')->implode(', ') }}</div>
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<div class="generated-note">Generated via AI Aesthetics Treatment Planning System</div>

@endsection
