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

    /* Stat badges */
    .stat-table { width: 100%; margin: 10px 0 0 0; }
    .stat-cell { text-align: center; padding: 6px 4px; }
    .stat-number {
        font-size: 30px;
        font-weight: bold;
        line-height: 1;
    }
    .stat-number.improved  { color: #4CAF50; }
    .stat-number.stable    { color: #C9A84C; }
    .stat-number.declined { color: #F44336; }
    .stat-label {
        font-size: 12px;
        color: #777;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-top: 3px;
    }

    /* Improvements + Maintenance side-by-side */
    .two-col-table { width: 100%; }
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
        border-bottom: 1px solid #C9A84C;
    }

    /* Improvement row */
    .improvement-row {
        padding: 6px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .improvement-name {
        font-size: 12px;
        font-weight: bold;
        color: #222;
    }
    .improvement-badge {
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .improvement-badge.improved { color: #4CAF50; }
    .improvement-badge.stable { color: #C9A84C; }
    .improvement-badge.declined { color: #F44336; }
    .improvement-scores {
        font-size: 10px;
        color: #888;
        margin-top: 2px;
    }

    /* Maintenance */
    .maintenance-item ul li {
        font-size: 12px;
        color: #444;
        padding: 4px 0 4px 10px;
        border-bottom: 1px dotted #eee;
        line-height: 1.5;
    }

    .maintenance-dot {
        color: #C9A84C;
        font-size: 18px;
    }

    /* Comparison Section */
    .comparison-wrapper {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background-color: #f8fafc;
        padding: 25px;
        margin-bottom: 30px;
        page-break-inside: avoid;
    }
    .comparison-header {
        text-align: center;
        margin-bottom: 25px;
    }
    .comparison-title {
        display: inline-block;
        font-size: 16px;
        font-weight: bold;
        color: #1B3A6B;
        letter-spacing: 3px;
        text-transform: uppercase;
        border-bottom: 2px solid #C9A84C;
        padding-bottom: 8px;
    }
    .comparison-col {
        vertical-align: top;
    }
    .label-before {
        font-size: 20px;
        font-weight: bold;
        color: #64748b;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        text-align: center;
    }
    .label-after {
        font-size: 20px;
        font-weight: bold;
        color: #1B3A6B;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        text-align: center;
    }
    .label-index-before { color: #cbd5e1; margin-right: 5px; }
    .label-index-after { color: #C9A84C; margin-right: 5px; }
    .label-sub {
        font-size: 18px;
        font-weight: normal;
        color: #94a3b8;
        text-transform: none;
        margin-left: 4px;
    }
    .image-frame-before {
        border: 1px solid #e2e8f0;
        padding: 5px;
        background: #ffffff;
    }
    .image-frame-after {
        border: 1px solid #C9A84C;
        padding: 5px;
        background: #ffffff;
    }
    .comparison-image {
        width: 100%;
        display: block;
    }
    .comparison-divider {
        width: 1px;
        height: 150px;
        background-color: #e2e8f0;
        margin: 0 auto;
        margin-top: 20px;
    }
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Title ── --}}
    <div class="report-header">
        <div class="report-title">RE-ASSESSMENT &amp; PROGRESS REPORT</div>
        <div class="report-subtitle">Before / after analysis with objective treatment response tracking</div>
    </div>
</div>


{{-- ── Patient Info ── --}}
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

{{-- ── Results Summary ── --}}
<div class="section-box">
    <div class="section-title">TREATMENT RESULTS SUMMARY</div>
    <div class="overview-text">
        This re-assessment report documents the changes in skin parameters following completion of
        your personalized four-session plan. The comparison between baseline and post-treatment
        findings provides objective evidence of treatment efficacy and maintenance priorities.
    </div>
</div>

<div class="section-box">
    <table class="stat-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="stat-cell" width="33%">
                <div class="stat-number improved">{{ $counts['improved'] ?? 0 }}</div>
                <div class="stat-label">PARAMETERS IMPROVED</div>
            </td>
            <td width="1px" style="border-left: 1px solid #ddd;"></td>
            <td class="stat-cell" width="33%">
                <div class="stat-number stable">{{ $counts['stable'] ?? 0 }}</div>
                <div class="stat-label" style="text-align:center;">PARAMETERS STABLE</div>
            </td>
            <td width="1px" style="border-left: 1px solid #ddd;"></td>
            <td class="stat-cell" width="33%">
                <div class="stat-number declined" style="text-align:right;">{{ $counts['declined'] ?? 0 }}</div>
                <div class="stat-label" style="text-align:right;">PARAMETERS DECLINED</div>
            </td>
        </tr>
    </table>
</div>

{{-- ── Key Improvements ── --}}
<pagebreak page-selector="report_content" />
<table width="100%" class="two-col-table" cellpadding="5" cellspacing="0">
    <tr>
        <td class="col-box">

            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding-bottom: 15px;" align="center"><div class="col-title">— KEY IMPROVEMENTS —</div></td>
                </tr>
                <tr>
                    <td>
                         @foreach ($reassessment as $item)
                        <div class="improvement-row">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="30%"><div class="improvement-name">{{ $item['parameter_name'] }}</div></td>
                                    @php
                                        $statusClass = 'improvement-badge';
                                        if (strtolower($item['status']) === 'improved') {
                                            $statusClass .= ' improved';
                                        } elseif (strtolower($item['status']) === 'stable') {
                                            $statusClass .= ' stable';
                                        } elseif (strtolower($item['status']) === 'declined') {
                                            $statusClass .= ' declined';
                                        }
                                    @endphp
                                    <!-- <td width="30%" align="right" rowspan="2"><div class="{{ $statusClass }}">{{ $item['status'] }}</div></td> -->
                                    <td width="70%">{{ $item['result'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 6px; padding-top: 2px;">
                                        <div class="improvement-scores">
                                            Before: {{ $item['before_treatment_score_or_label'] }} &nbsp;&nbsp; After: {{ $item['post_treatment_score_or_label'] }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        @endforeach
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── Maintenance ── --}}

<pagebreak page-selector="report_content" />
<table width="100%" class="two-col-table" cellpadding="5" cellspacing="0">
    <tr>
        <td class="col-box">
            <table width="100%">
                <tr>
                    <td style="padding-bottom: 15px;" align="center"><div class="col-title">— MAINTENANCE PLAN —</div></td>
                </tr>
                <tr>
                    <td>
                        <div class="maintenance-item">
                            <table width="100%">
                                <tr>
                                    <td width="10" valign="top" class="maintenance-dot">•</td>
                                    <td>
                                        Cleanse face twice daily using a gentle cleanser that maintains the natural moisture barrier.
                                    </td>
                                </tr>
                                <tr>
                                    <td width="10" valign="top" class="maintenance-dot">•</td>
                                    <td>
                                        Apply a broad-spectrum SPF 30+ sunscreen every morning, even on cloudy days
                                    </td>
                                </tr>
                                <tr>
                                    <td width="10" valign="top" class="maintenance-dot">•</td>
                                    <td>
                                        Use a hydrating serum containing hyaluronic acid to maintain moisture levels
                                    </td>
                                </tr>
                                <tr>
                                    <td width="10" valign="top" class="maintenance-dot">•</td>
                                    <td>
                                        Apply a retinoid product 2–3 times per week in the evening to support cell turnover
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Post Assessment Images --}}

@php
    // Image order mapping
    $imageOrder = config('project.assessment_image_order');

    // Create image maps
    $assessmentImageMap = [];
    foreach ($assessmentImages as $img) {
        $assessmentImageMap[$img['name']] = $img['url'];
    }

    $postAssessmentImageMap = [];
    foreach ($postAssessmentImages as $img) {
        $postAssessmentImageMap[$img['name']] = $img['url'];
    }

    // Prepare image pages data
    $imagePages = [];

    foreach ($imageOrder as $imageType) {
        // Get images for this type
        $beforeImageUrl = $assessmentImageMap[$imageType] ?? null;
        $afterImageUrl = $postAssessmentImageMap[$imageType] ?? null;

        // Only include page if there are images
        if ($beforeImageUrl || $afterImageUrl) {
            $imagePages[$imageType] = [
                'before_image' => $beforeImageUrl,
                'after_image' => $afterImageUrl,
            ];
        }
    }
@endphp

@foreach($imagePages as $imageType => $imageData)
    @php
        $pageClass = str_replace('_', '-', $imageType) . '-page';
        $beforeImageUrl = $imageData['before_image'] ?? null;
        $afterImageUrl = $imageData['after_image'] ?? null;
    @endphp

    <pagebreak page-selector="report_content" />

    <div class="comparison-wrapper">
        <div class="comparison-header">
            <div class="comparison-title">
                {{ str_replace('_', ' ', strtoupper($imageType)) }} LIGHT
            </div>
        </div>

        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="48%" align="center" class="comparison-col">
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 15px;">
                       <tr><td class="label-before"><span class="label-index-before">01.</span> BEFORE <span class="label-sub">Baseline</span></td></tr>
                    </table>
                    <div class="image-frame-before">
                        <img src="{{ $beforeImageUrl ?: public_path('images/no-image.jpg') }}" class="comparison-image" style="border-radius: 8px;">
                    </div>
                </td>

                <td width="4%" align="center" style="vertical-align: middle;">
                    <div class="comparison-divider"></div>
                </td>

                <td width="48%" align="center" class="comparison-col">
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 15px;">
                       <tr><td class="label-after"><span class="label-index-after">02.</span> AFTER <span class="label-sub">Post-Treatment</span></td></tr>
                    </table>
                    <div class="image-frame-after">
                        <img src="{{ $afterImageUrl ?: public_path('images/no-image.jpg') }}" class="comparison-image" style="border-radius: 8px;">
                    </div>
                </td>
            </tr>
        </table>
    </div>
@endforeach

<div class="generated-note">Generated via AI Aesthetics Re-Assessment System</div>

@endsection
