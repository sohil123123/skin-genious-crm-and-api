@extends('pdf.master')

@section('content')

<style>
    body {
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        color: #333333;
    }

    /* ─── Typography ─── */
    .report-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #0E2B5C;
        padding-bottom: 10px;
    }
    .report-title {
        font-size: 20px;
        font-weight: bold;
        color: #0E2B5C;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 6px;
    }
    .report-subtitle {
        font-size: 9.5px;
        font-weight: bold;
        color: #C29F5D;
        text-transform: uppercase;
        letter-spacing: 2.5px;
    }

    /* ─── Info Cards ─── */
    .info-card {
    }
    .info-label {
        font-size: 9px;
        color: #718096;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .info-value {
        font-size: 14px;
        color: #0E2B5C;
        font-weight: bold;
    }

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
        font-size: 14px;
        color: #4A5568;
        line-height: 1.5;
        text-align: justify;
        margin-bottom: 30px;
        padding: 0 5px;
    }

    /* ─── Severity Metrics ─── */
    .stat-box {
        border-radius: 6px;
        padding: 15px 10px;
        text-align: center;
    }
    .stat-number {
        font-size: 34px;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .stat-label {
        font-size: 16px;
        color: #4A5568;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    /* ─── Key Parameters ─── */
    .param-block {
        margin-bottom: 22px;
    }
    .param-name {
        font-size: 13px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 6px;
        text-transform: uppercase;
        border-bottom: 1px solid #E2E8F0;
        padding-bottom: 5px;
        letter-spacing: 1px;
    }
    .param-desc {
        font-size: 13px;
        color: #718096;
        line-height: 1.5;
    }
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">SKIN ANALYSIS DIAGNOSTIC REPORT</div>
        <div class="report-subtitle">AI-Powered Clinical Diagnostics</div>
    </div>

    {{-- ── Patient Info Cards ── --}}
    {{-- We use individual tables per row to guarantee mPDF doesn't collapse widths --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Patient Subject</div>
                    <div class="info-value">{{ $patient['name'] ?? 'Swati Mishra' }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Bio-Metrics</div>
                    <div class="info-value">{{ $patient['age'] ?? '46' }} YRS / {{ strtoupper(substr($patient['gender'] ?? 'Female', 0, 1)) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Analysis Date</div>
                    <div class="info-value">{{ $patient['report_date'] ?? '16/03/2026' }}</div>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Skin Phenotype</div>
                    <div class="info-value">{{ $patient['skin_profile'] ?? 'Combination' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Assessment Overview ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">EXECUTIVE SUMMARY</td>
        </tr>
    </table>

    <div class="overview-text">
        Your comprehensive dermal analysis has been processed by our proprietary multi-modal imaging AI. This report evaluates multiple distinct topographical and sub-surface parameters to project a transparent clinical picture of current cellular conditions, visible concerns, and predictive treatment trajectories. This data-driven intelligence directly guides your bespoke clinical action plan.
    </div>

    {{-- ── Condition Stats ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 35px;">
        <tr>
            <td width="31%" valign="top">
                <div class="stat-box">
                    <div class="stat-number" style="color: #38B2AC;">{{ str_pad($overview['mild'] ?? 6, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="stat-label">Mild Alerts</div>
                </div>
            </td>
            <td width="3.5%"></td>
            <td width="31%" valign="top">
                <div class="stat-box">
                    <div class="stat-number" style="color: #D69E2E;">{{ str_pad($overview['moderate'] ?? 8, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="stat-label">Moderate Risks</div>
                </div>
            </td>
            <td width="3.5%"></td>
            <td width="31%" valign="top">
                <div class="stat-box">
                    <div class="stat-number" style="color: #E53E3E;">{{ str_pad($overview['significant'] ?? 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="stat-label">Critical Zones</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Key Parameters ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">PARAMETRIC DATA POINTS</td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 15px;">
        @if(isset($parameters) && count($parameters) > 0)
            @foreach ($parameters->chunk(2) as $row)
            <tr>
                @foreach ($row as $param)
                <td width="48%" valign="top" style="padding-bottom: 10px;">
                    <div class="param-block">
                        <div class="param-name">
                            <span style="color: #C29F5D; margin-right: 5px;">[{{ str_pad($loop->parent->iteration * 2 + $loop->iteration - 1, 2, '0', STR_PAD_LEFT) }}]</span>
                            {{ $param['name'] }}
                        </div>
                        <div class="param-desc">{{ $param['description'] }}</div>
                    </div>
                </td>
                @if($loop->iteration == 1)
                    <td width="4%"></td>
                @endif
                @endforeach
                @if ($row->count() < 2)
                <td width="48%"></td>
                @endif
            </tr>
            @endforeach
        @endif
    </table>

</div>

@endsection
