@extends('pdf.master')

@section('content')

<style>
    .protocol-container {
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
        background-color: #ffffff;
        border-radius: 4px;
        overflow: hidden;
    }
    .protocol-header {
        background-color: #0E2B5C;
        color: #ffffff;
        padding: 12px 15px;
        font-size: 16px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .protocol-body {
        padding: 20px;
    }
    .section-title {
        color: #0E2B5C;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        border-bottom: 2px solid #C29F5D;
        padding-bottom: 4px;
        margin-bottom: 15px;
        display: inline-block;
    }
    .rationale-box {
        background-color: #f8fafc;
        /* border-left: 4px solid #C29F5D; */
        padding: 15px;
        margin-bottom: 25px;
        font-size: 13px;
        line-height: 1.6;
        color: #334155;
    }
    .outcome-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 25px;
    }
    .outcome-card {
        background-color: #ffffff;
        /* border: 1px solid #e2e8f0; */
        padding: 15px;
        height: 100%;
    }
    .outcome-header {
        font-size: 11px;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 10px;
        letter-spacing: 0.5px;
    }
    .outcome-list {
        margin: 0;
        padding-left: 18px;
        font-size: 12px;
        color: #334155;
    }
    .outcome-list li {
        margin-bottom: 6px;
    }

    /* ─── Formulation Table ─── */
    .formula-section {
        margin-top: 10px;
    }
    .bag-item {
        border: 1px solid #e2e8f0;
        margin-bottom: 15px;
        page-break-inside: avoid;
    }
    .bag-header {
        background-color: #f1f5f9;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: bold;
        color: #0E2B5C;
        border-bottom: 1px solid #e2e8f0;
    }
    .ingredient-table {
        width: 100%;
        border-collapse: collapse;
    }
    .ingredient-table th {
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        color: #64748b;
        padding: 8px 12px;
        background-color: #fafafa;
        border-bottom: 1px solid #f1f5f9;
    }
    .ingredient-table td {
        padding: 10px 12px;
        font-size: 12px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
    }
    .dose-col {
        width: 120px;
        font-weight: bold;
        color: #0E2B5C;
    }
    .admin-notes {
        padding: 10px 12px;
        background-color: #fffdfa;
        font-style: italic;
        font-size: 11px;
        color: #92400e;
        border-top: 1px solid #fef3c7;
    }

    /* ─── Roadmap Table Additions ─── */
    .roadmap-title {
        font-size: 22px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 25px;
        text-transform: capitalize;
    }
    .roadmap-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #e2e8f0;
    }
    .roadmap-table th {
        background-color: #0E2B5C;
        color: #FFFFFF;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        padding: 12px 10px;
        text-align: left;
        border-right: 1px solid #1a3a6e;
    }
    .roadmap-table td {
        background-color: #FFFFFF;
        font-size: 11px;
        color: #334155;
        padding: 15px 10px;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
        line-height: 1.4;
    }
    .roadmap-table tr:nth-child(even) td {
        background-color: #fcfaf7;
    }
    .roadmap-table tr:last-child td {
        border-bottom: none;
    }
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">IV SELECTED PROTOCOL PROGRAM REPORT</div>
        <div class="report-subtitle">{{ $program['name'] ?? 'N/A' }}</div>
    </div>

    {{-- ── Patient Info Summary ── --}}
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
                    <div class="info-value">{{ $patient['age'] }} / {{ strtoupper(substr($patient['gender'] ?? 'N/A', 0, 1)) }}</div>
                </div>
            </td>
        </tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Report Date</div>
                    <div class="info-value">{{ date('d/m/Y', strtotime($report_date)) ?? 'N/A' }}</div>
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

    {{-- ── Protocol Overview ── --}}
    <div class="protocol-container" style="page-break-inside: avoid;">
        <div class="protocol-header">
            {{ $program['name'] ?? 'Personalized IV Protocol' }}
        </div>
        <div class="protocol-body">
            <div><span class="section-title">Clinical Rationale</span></div>
            <div class="rationale-box">
                {{ $program['client_facing_explanation']['why_today'] ?? 'Targeted IV support based on your clinical assessment profile.' }}
            </div>

            {{-- Dynamic data mapped to the timeline table below --}}

            <div><span class="section-title">Session Formulation</span></div>
            <div class="formula-section">
                @if(isset($program['protocols'][0]['bags']))
                    @foreach($program['protocols'][0]['bags'] as $index => $bag)
                        <div class="bag-item">
                            <div class="bag-header">
                                BAG {{ $index + 1 }}: {{ $bag['ui_bag_summary']['display_title'] ?? ($bag['bag_size_ml'] . 'ml ' . $bag['carrier']) }}
                            </div>
                            <table class="ingredient-table">
                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th class="dose-col">Concentration / Dose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bag['ingredients'] as $ing)
                                        <tr>
                                            <td>{{ $ing['name'] }}</td>
                                            <td class="dose-col">
                                                {{ $ing['dose_mg_optional'] ?? $ing['dose_optional'] ?? 'Standard' }} {{ $ing['dose_units_optional'] ?? '' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            {{-- @if(isset($bag['admin_notes_optional']) && count($bag['admin_notes_optional']) > 0)
                                <div class="admin-notes">
                                    <strong>Administration Notes:</strong> {{ implode(' ', $bag['admin_notes_optional']) }}
                                </div>
                            @endif --}}
                        </div>
                    @endforeach
                @else
                    <div style="font-size:12px; color:#64748b;">Protocol formulation details to be finalized by clinical staff.</div>
                @endif
            </div>

           {{-- @if(isset($program['client_facing_explanation']['who_should_not_take_today']) && count($program['client_facing_explanation']['who_should_not_take_today']) > 0)
                <div style="margin-top: 20px;">
                    <div style="font-size: 11px; font-weight: bold; color: #ef4444; text-transform: uppercase; margin-bottom: 8px;">Safety & Contraindications</div>
                    <ul style="margin: 0; padding-left: 18px; font-size: 11px; color: #475569;">
                        @foreach($program['client_facing_explanation']['who_should_not_take_today'] as $contra)
                            <li>{{ $contra }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif --}}
        </div>
    </div>

    <pagebreak page-selector="report_content" />

    <div class="roadmap-title">Expected timeline of change</div>

    <table class="roadmap-table" cellpadding="0" cellspacing="0" style="margin-bottom: 25px;">
        <thead>
            <tr>
                <th style="width: 20%;">When</th>
                <th style="width: 40%;">Most likely early wins</th>
                <th style="width: 40%;">Often takes longer</th>
            </tr>
        </thead>
        <tbody>
            @php
                $feels = isset($program['client_facing_explanation']['what_you_may_feel_today']) ? $program['client_facing_explanation']['what_you_may_feel_today'] : [];
                $sees = isset($program['client_facing_explanation']['what_you_may_see_over_7_14_days']) ? $program['client_facing_explanation']['what_you_may_see_over_7_14_days'] : [];
                $maxCount = max(count($feels), count($sees));
                $whenLabels = ['After session 1', 'After sessions 2–3', 'After sessions 4–6'];
            @endphp
            @if($maxCount > 0)
                @for ($i = 0; $i < $maxCount; $i++)
                <tr>
                    <td style="font-weight: bold; color: #0E2B5C;">{{ $whenLabels[$i] ?? ('Phase ' . ($i + 1)) }}</td>
                    <td>{{ $feels[$i] ?? '' }}</td>
                    <td>{{ $sees[$i] ?? '' }}</td>
                </tr>
                @endfor
            @else
                <tr>
                    <td style="font-weight: bold; color: #0E2B5C;">After session 1</td>
                    <td>Hydration comfort, calm, less drained feeling</td>
                    <td>Consistent energy and visible freshness</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; color: #0E2B5C;">After sessions 2–3</td>
                    <td>Better recovery quality, steadier weekly feel</td>
                    <td>More reliable resilience and visible clarity</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; color: #0E2B5C;">After sessions 4–6</td>
                    <td>More stable overall experience, clearer pattern of response</td>
                    <td>Longer-term maintenance strategy</td>
                </tr>
            @endif
        </tbody>
    </table>

    <ul style="color: #64748b; font-size: 12px; margin-bottom: 30px; padding-left: 20px;">
        <li style="margin-bottom: 8px;">Expectation-setting should remain aspirational but honest.</li>
        <li>This report helps sell programs without sounding salesy because it frames the package as a monitored progression.</li>
    </ul>

</div>
@endsection
