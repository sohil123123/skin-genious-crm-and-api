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

    /* ─── Diagnosis Cards ─── */
    .parameter-title {
        background-color: #0E2B5C;
        color: #FFFFFF;
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        padding: 8px 15px;
        margin-top: 20px;
        margin-bottom: 15px;
        letter-spacing: 1.5px;
    }
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
        line-height: 2;
        text-align: justify;
        margin-bottom: 15px;
    }
    .score-text{
        font-size: 20px;
        font-weight: bold;
        color: #297ab1;
    }
    .score-circle {
        margin-bottom: 15px;
    }
    .score-cell {
        background-color: #FFFFFF;
        border: 1.5px solid #C29F5D;
        text-align: center;
        padding: 6px 15px;
    }
    .score-cell span {
        color: #0E2B5C;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .score-cell span.text-small {
        font-size: 13px;
    }
    .grid-layout {
        width: 100%;
        margin-top: 10px;
    }
    .grid-layout-td-left {
        vertical-align: top;
        padding-right: 15px;
    }
    .grid-layout-td-right {
        vertical-align: top;
        padding-left: 15px;
    }
    .score-circle {
        margin-bottom: 15px;
    }
    .score-cell {
        background-color: #FFFFFF;
        border: 1.5px solid #C29F5D;
        text-align: center;
        padding: 6px 15px;
    }
    .score-cell span {
        color: #0E2B5C;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .score-cell span.text-small {
        font-size: 13px;
    }
    .img-box {
        background: #FFFFFF;
        padding: 5px;
        border: 1px solid #E2E8F0;
        text-align: center;
    }
    .img-box img {
        max-width: 100%;
        height: 150px;
        display: inline-block;
    }
    .causes-list {
        margin: 0;
        padding-left: 20px;
    }
    .causes-list li {
        margin-bottom: 6px;
        color: #4A5568;
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
    {{-- We use individual tables per row to guarantee mPDF doesn't c style="page-break-inside: avoid;"ollapse widths --}}
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
                    <div class="info-value">{{ $data['age'] ?? 'N/A' }} / {{ strtoupper(substr($patient['gender'] ?? 'N/A', 0, 1)) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td width="48%" valign="top">
                <div class="info-card">
                    <div class="info-label">Report Date</div>
                    <div class="info-value">{{ date('d/m/Y', strtotime($data['created_at'])) ?? 'N/A' }}</div>
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

    {{-- ── Assessment Overview ── --}}
    <table class="section-title-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="section-title">ASSESSMENT OVERVIEW</td>
        </tr>
    </table>

    <div class="overview-text">
        {{ $diagnosis['script'] }}
    </div>

    {{-- ── Key Parameters ── --}}
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="section-title-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="section-title">KEY PARAMETERS</td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 15px;">
                    @if(isset($key_parametrs) && count($key_parametrs) > 0)
                        @foreach ($key_parametrs->chunk(2) as $row)
                        <tr>
                            @foreach ($row as $param)
                            <td width="48%" valign="top" style="padding-bottom: 10px;">
                                <div class="param-block">
                                    <div class="param-name">
                                        <span style="color: #C29F5D; margin-right: 5px;">[{{ str_pad($loop->parent->iteration * 2 + $loop->iteration - 1, 2, '0', STR_PAD_LEFT) }}]</span>
                                        {{ $param['parameter'] }}
                                    </div>
                                    <div class="param-desc">{{ $param['reason_for_selection'] }}</div>
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
            </td>
        </tr>
    </table>

    {{-- ── Skin Images ── --}}

    @php
        $report = $diagnosis['diagnosis_report'] ?? [];

        use Illuminate\Support\Str;

        $imageOrder = config('project.assessment_image_order');

        $sortedImages = [];
        if ($data->images && count($data->images)) {
            foreach ($imageOrder as $key) {
                $found = collect($data->images)->first(function ($img) use ($key) {
                    return Str::contains(Str::lower($img['url']), $key . '.');
                });

                if ($found) {
                    $sortedImages[] = $found;
                }
            }
        }
    @endphp

    @if(empty($report))
        <table class="section-title-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="section-title" style="text-align: center;">No diagnosis data available.</td>
            </tr>
        </table>
    @else
        @foreach($report as $key => $data)

        <!-- Force each parameter to start on its own full page -->
        <pagebreak />

        <table style="width: 100%; page-break-inside: avoid;">
            <tr>
                <td>
                    <table class="section-title-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="section-title">{{ $data['parameter_name'] ?? ucwords(str_replace('_', ' ', $key)) }}</td>
                        </tr>
                    </table>
                    <table style="width: 100%;">
                        <tr>
                            <td>
                                <div class="section-label">EXPLANATION OF WHAT THE PARAMETER ENTAILS</div>
                                <div class="section-text" style="margin-bottom: 20px;">
                                    {{ $data['description'] ?? 'No description available for this parameter.' }}
                                </div>

                                <table class="grid-layout" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <!-- Left Column -->
                                        <td class="grid-layout-td-left" width="45%">
                                            <div class="section-label">SCORE / TEXT / SKIN TYPE</div>

                                            <!-- Populated the badge structure to make UI better as requested -->
                                            <table class="score-circle" cellpadding="0" cellspacing="0" style="margin-top: 15px;">
                                                <tr>
                                                    <td class="score-cell">
                                                        @php
                                                            $score = $data['score_or_label'] ?? '-';
                                                        @endphp
                                                        <span class="score-text">{{ $score }}</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>

                                        <!-- Divider Column -->
                                        <td width="3%" style="border-left: 2px solid #E2E8F0;"></td>

                                        <!-- Right Column -->
                                        <td class="grid-layout-td-right" width="52%">
                                            <div class="section-label">EXPLANATION OF SCORE</div>

                                            <!-- Populated the badge structure to make UI better as requested -->
                                            <table class="score-badge-table" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
                                                <tr>
                                                    <td class="score-badge-cell">
                                                        <div class="section-text" style="margin-bottom: 20px;">
                                                            {{ $data['score_explanation'] ?? 'No explanation available for this score.' }}
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <!-- Left Column -->
                                        <td class="grid-layout-td-left" width="45%">
                                            <div class="section-label">FACE IMAGE SHOWING AFFECTED AREAS</div>

                                            <!-- Populated the badge structure to make UI better as requested -->
                                            <table class="score-badge-table" cellpadding="0" cellspacing="0" style="margin-top: 15px;">
                                                <tr>
                                                    <td>
                                                        <div class="img-box">
                                                    @php
                                                        $imgIndex = isset($data['affected_area_image']) ? max(((int)$data['affected_area_image']) - 1, 0) : 0;
                                                        $imageSrc = $sortedImages[$imgIndex]['url'] ?? ($sortedImages[0]['url'] ?? null);
                                                    @endphp

                                                    @if($imageSrc)
                                                        <img src="{{ $imageSrc }}" style="width: 30%;">
                                                    @else
                                                        <div style="padding: 50px 20px; text-align:center; color: #999; font-size: 10px;">
                                                            NO IMAGE FOUND
                                                        </div>
                                                    @endif
                                                </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>

                                        <!-- Divider Column -->
                                        <td width="3%" style="border-left: 2px solid #E2E8F0;"></td>

                                        <!-- Right Column -->
                                        <td class="grid-layout-td-right" width="52%" style="padding-top: 10px;">
                                            <div class="section-label">POSSIBLE CAUSES OF THE ISSUE SEEN</div>

                                            <!-- Populated the badge structure to make UI better as requested -->
                                            <table class="score-badge-table" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
                                                <tr>
                                                    <td class="score-badge-cell">
                                                        <div class="section-text" style="margin-bottom: 20px;">
                                                            @if(!empty($data['possible_causes']) && is_array($data['possible_causes']))
                                                                <ul class="causes-list">
                                                                    @foreach($data['possible_causes'] as $cause)
                                                                        <li>{{ $cause }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            @else
                                                                No specific causes listed.
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        @endforeach
    @endif

</div>

@endsection
