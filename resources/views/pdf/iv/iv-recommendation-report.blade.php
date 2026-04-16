@extends('pdf.master')

@section('content')

<style>
    .option-container {
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
        background-color: #ffffff;
        border-radius: 4px; /* Subtle rounding for professional look */
        overflow: hidden;
        page-break-inside: avoid;
        break-inside: avoid-page;
    }
    .option-header {
        background-color: #0E2B5C;
        color: #ffffff;
        padding: 10px 15px;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .option-body {
        padding: 15px;
    }
    .column-title {
        color: #0E2B5C;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        border-bottom: 2px solid #C29F5D;
        padding-bottom: 4px;
        display: inline-block;
        margin-bottom: 5px;
        line-height: 1.5;
    }
    .explanation-text {
        font-size: 12px;
        line-height: 1.5;
        color: #334155;
    }
    .ingredient-tag {
        display: inline-block;
        padding: 4px 8px;
        line-height: 16px; /* IMPORTANT */
    }
    .duration-badge {
        margin-top: 15px;
        font-size: 11px;
        color: #64748b;
        font-style: italic;
    }
    .expectation-list {
        margin: 0;
        padding-left: 15px;
        list-style-type: none;
        font-size: 12px;
    }
    .expectation-list li {
        position: relative;
        margin-bottom: 8px;
        color: #475569;
        line-height: 1.3;
    }
    .expectation-list li:before {
        content: "•";
        color: #C29F5D;
        font-weight: bold;
        display: inline-block;
        width: 1em;
        margin-left: -1em;
    }
    .expectation-section {
        margin-bottom: 20px;
    }

    /* ─── Comparison Table ─── */
    .comparison-section {
        margin-top: 20px;
        page-break-inside: avoid;
    }
    .comparison-title {
        font-size: 16px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-left: 4px solid #C29F5D;
        padding-left: 10px;
    }
    .comparison-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1px solid #e2e8f0;
    }
    .comparison-table th {
        background-color: #0E2B5C;
        color: #FFFFFF;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        padding: 10px 8px;
        text-align: left;
        letter-spacing: 0.5px;
        border-right: 1px solid #1a3a6e;
    }
    .comparison-table td {
        background-color: #FFFFFF;
        font-size: 12px;
        color: #334155;
        padding: 12px 8px;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
        line-height: 1.4;
    }
    .comparison-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }
    .comparison-table .option-name-col {
        font-weight: bold;
        color: #0E2B5C;
        width: 14%;
        background-color: #f1f5f9 !important;
    }
    .comparison-table .emphasis-col { width: 23%; }
    .comparison-table .feel-col { width: 23%; }
    .comparison-table .expectation-col { width: 25%; }
    .comparison-table .length-col { width: 15%; border-right: none; text-align: center; }
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">Personalized IV Recommendation Report</div>
        <div class="report-subtitle">Premium recommendation report showing how AI Aesthetics can present option logic, same-day expectations, and near-term outcomes.</div>
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
                    <div class="info-value">{{ $age ?? 'N/A' }} / {{ strtoupper(substr($patient['gender'] ?? 'N/A', 0, 1)) }}</div>
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

    {{-- ── Recommended options ── --}}
    @foreach ($options as $option)
        <div class="option-container">
            <div class="option-header">
                {{ $option['name'] }}
            </div>
            <div class="option-body">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="55%" valign="top" style="padding-right: 20px; border-right: 2px solid #f1f5f9;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td><div class="column-title">Why This Protocol Today</div></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 8px; padding-bottom: 20px;">
                                        <div class="explanation-text">{{$option['client_facing_explanation']['why_today']}}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><div class="column-title">Hero Ingredients</div></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 8px; padding-bottom: 10px;">
                                        @foreach ($option['protocols'][0]['hero_ingredients'] as $ingredient)
                                            <table style="display:inline-table; margin-right:6px; margin-bottom:6px;">
                                                <tr>
                                                    <td style="
                                                        background:#f1f5f9;
                                                        border-left:3px solid #C29F5D;
                                                        padding:4px 8px;
                                                        font-size:11px;
                                                        color:#0E2B5C;
                                                        font-weight:bold;
                                                    ">
                                                        {{$ingredient}}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endforeach
                                    </td>
                                </tr>
                                <tr>
                                    <td class="duration-badge">
                                        <strong>Estimated Treatment Time:</strong> {{$option['protocols'][0]['ui_summary']['estimated_total_duration_minutes']}} minutes
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td width="45%" valign="top" style="padding-left: 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td><div class="column-title">Post-Treatment Experience (Today)</div></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 8px; padding-bottom: 20px;">
                                        <ul class="expectation-list">
                                            @foreach ($option['client_facing_explanation']['what_you_may_feel_today'] as $feeling)
                                                <li>{{$feeling}}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                                <tr>
                                    <td><div class="column-title">Clinical Outlook (Next 7-14 Days)</div></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 8px;">
                                        <ul class="expectation-list">
                                            @foreach ($option['client_facing_explanation']['what_you_may_see_over_7_14_days'] as $feeling)
                                                <li>{{$feeling}}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach


    @if(isset($options) && is_array($options) && count($options) > 0)
        <div class="comparison-section">
            <div class="comparison-title">At-a-glance comparison</div>
            <table class="comparison-table" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th class="option-name-col">Option</th>
                        <th class="emphasis-col">Primary Emphasis</th>
                        <th class="feel-col">Same-day Feel</th>
                        <th class="expectation-col">Near-term Expectation</th>
                        <th class="length-col">Typical Session length</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($options as $row)
                        @php
                            $optionLabel = 'Option';
                            $type = $row['option_type'] ?? '';
                            if (str_contains($type, 'option_1')) $optionLabel = 'Option 1';
                            elseif (str_contains($type, 'option_2')) $optionLabel = 'Option 2';
                            elseif ($type === 'plan_option') $optionLabel = 'Recommended Program';
                            elseif ($type === 'budget_option') $optionLabel = 'Core Option';
                            else $optionLabel = ucwords(str_replace('_', ' ', $type));
                        @endphp
                        <tr>
                            <td class="option-name-col">{{ $optionLabel }}</td>
                            <td>{{ $row['name'] ?? 'N/A' }}</td>
                            <td>
                                {{ implode(', ', $row['client_facing_explanation']['what_you_may_feel_today'] ?? []) }}
                            </td>
                            <td>
                                {{ implode(', ', $row['client_facing_explanation']['what_you_may_see_over_7_14_days'] ?? []) }}
                            </td>
                            <td class="length-col">{{ $row['protocols'][0]['ui_summary']['estimated_total_duration_minutes'] ?? 'N/A' }} min</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
