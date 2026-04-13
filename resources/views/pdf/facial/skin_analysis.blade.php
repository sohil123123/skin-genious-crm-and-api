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
    .causes-list {
        margin: 0;
        padding-left: 15px;
        font-size: 11px;
    }
    li {
        font-size: 12px;
        color: #4A5568;
        line-height: 2;
        text-align: justify;
        margin-bottom: 15px;
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
        {{ $diagnosis['script'] ?? 'N/A' }}
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

            <pagebreak />

            <!-- Header -->
            <div class="section-header">
                {{ $data['parameter_name'] ?? ucwords(str_replace('_', ' ', $key)) }}
            </div>

            <!-- Card -->
            <div class="section-card">

                <!-- Description -->
                <div class="section-label">ABOUT THIS PARAMETER</div>
                <div class="section-text" style="margin-bottom: 20px;">
                    {{ $data['description'] ?? 'No description available.' }}
                </div>

                <table width="100%">
                    <tr>

                        <!-- LEFT -->
                        <td width="45%" valign="top">
                            <table>
                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <div class="section-label">SCORE</div>
                                    </td>
                                </tr>
                            </table>
                            <table class="score-circle" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td align="center" valign="middle">
                                        <div class="score-inner">
                                            {{ $data['score_or_label'] ?? '-' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top:20px;">
                                <table>
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <div class="section-label">AFFECTED AREA</div>
                                        </td>
                                    </tr>
                                </table>

                                <div class="img-box">
                                    @php
                                        $imgIndex = isset($data['affected_area_image']) ? max(((int)$data['affected_area_image']) - 1, 0) : 0;
                                        $imageSrc = $sortedImages[$imgIndex]['url'] ?? ($sortedImages[0]['url'] ?? null);
                                    @endphp

                                    @if($imageSrc)
                                        <img src="{{ $imageSrc }}" style="width: 25%;">
                                    @else
                                        <div style="padding: 30px; color:#999;">NO IMAGE</div>
                                    @endif
                                </div>
                            </div>

                        </td>

                        <!-- DIVIDER -->
                        <td width="5%" class="divider"></td>

                        <!-- RIGHT -->
                        <td width="50%" valign="top">

                            <table>
                                <tr>
                                    <td style="padding-bottom: 5px;"><div class="section-label" style="margin-top: 20px;">SCORE EXPLANATION</div></td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 10px;"><div class="section-text" style="margin-bottom: 20px;">{{ $data['score_explanation'] ?? 'No explanation available.' }}</div></td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 5px;"><div class="section-label" style="margin-top: 20px;">POSSIBLE CAUSES</div></td>
                                </tr>
                                <tr>
                                    <td>
                                        @if(!empty($data['possible_causes']))
                                            <ul class="causes-list">
                                                @foreach($data['possible_causes'] as $cause)
                                                    <li>{{ $cause }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="section-text">No causes listed.</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>

                    </tr>
                </table>

            </div>

        @endforeach
    @endif

</div>

@endsection
