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

    .analysis-card {
        background: #ffffff;
        text-align: center;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 5px;
    }

    .status-bar {
        height: 5px;
        width: 100%;
    }

    .status-bar-low { background: #48BB78; }
    .status-bar-moderate { background: #ED8936; }
    .status-bar-high { background: #E53E3E; }

    .card-content {
        padding: 15px 8px;
    }

    .card-label {
        font-size: 9px;
        color: #718096;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        height: 28px;
        line-height: 1.2;
        margin-bottom: 10px;
    }

    .card-score {
        font-size: 32px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 8px;
    }

    .card-status-text {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    .bg-low { background: #F0FFF4; color: #2F855A; }
    .bg-moderate { background: #FFFAF0; color: #C05621; }
    .bg-high { background: #FFF5F5; color: #C53030; }

    .grid-table {
        width: 100%;
        border-spacing: 12px;
        margin-left: -12px;
        margin-right: -12px;
    }

    .profile-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #dcdcdc;
    }
    .profile-table th {
        background-color: #333d47;
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        text-align: left;
        padding: 10px 12px;
        border: 1px solid #dcdcdc;
    }
    .profile-table td {
        padding: 5px;
        font-size: 12px;
        color: #2D3748;
        border: 1px solid #dcdcdc;
        vertical-align: middle;
    }
    .profile-table th.axis-col { width: 22%; }
    .profile-table th.score-col { width: 9%; }
    .profile-table td.score-col {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        color: #1A202C;
    }
    .profile-table th.meaning-col { width: 35%; }
    .profile-table th.signals-col { width: 34%; }

    .profile-table td.meaning-col,
    .profile-table td.signals-col {
        line-height: 1.4;
    }

    .axis-label {
        font-weight: bold;
        color: #1A202C;
        margin-bottom: 3px;
        font-size: 11px;
    }
    .axis-code {
        color: #A0AEC0;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .profile-heading {
        font-size: 18px;
        font-weight: bold;
        color: #1B3A6B;
        margin-top: 15px;
        margin-bottom: 8px;
    }
    .profile-subheading {
        font-size: 12px;
        color: #718096;
        margin-bottom: 15px;
        line-height: 1.4;
    }
    .score-legend-container {
        margin-top: 15px;
    }
    .score-legend-heading {
        font-size: 16px;
        font-weight: bold;
        color: #1B3A6B;
        margin-bottom: 12px;
    }
    .score-legend-item {
        font-size: 13px;
        color: #2D3748;
        padding-bottom: 10px;
    }
    .clinical-note-box {
        border: 1px solid #E2DFD2;
        background-color: #FFFFFF;
        padding: 10px;
        margin-top: 5px;
    }
    .clinical-note-heading {
        font-size: 14px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 6px;
    }
    .clinical-note-text {
        font-size: 13px;
        color: #2D3748;
        line-height: 1.5;
    }
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">IV Wellness Analysis Report</div>
        <div class="report-subtitle">Translating complex biomarker data into actionable clinical insights for personalized wellness.</div>
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

    {{-- ── Wellness Analysis Summary ── --}}
    <div class="section-box">
        <table class="section-title-table" style="margin-bottom: 20px;" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="section-title">ASSESSMENT OVERVIEW</td>
            </tr>
        </table>

        @php
            if (!function_exists('getStatusInfo')) {
                function getStatusInfo($score) {
                    if ($score < 30) {
                        return [
                            'text' => 'Low Need / Burden',
                            'bar_class' => 'status-bar-low',
                            'bg_class' => 'bg-low'
                        ];
                    }
                    if ($score < 65) {
                        return [
                            'text' => 'Moderate Need / Burden',
                            'bar_class' => 'status-bar-moderate',
                            'bg_class' => 'bg-moderate'
                        ];
                    }
                    return [
                        'text' => 'High Need / Burden',
                        'bar_class' => 'status-bar-high',
                        'bg_class' => 'bg-high'
                    ];
                }
            }
        @endphp

        <table class="grid-table" cellpadding="0" cellspacing="0">
            @foreach(collect($iv_scors)->chunk(4) as $chunk)
                <tr>
                    @foreach($chunk as $item)
                        @php $status = getStatusInfo($item['score']); @endphp
                        <td width="25%" valign="top" align="center">
                            <div class="analysis-card">
                                <div class="status-bar {{ $status['bar_class'] }}"></div>
                                <div class="card-content">
                                    <div class="card-label">{{ $item['label'] }}</div>
                                    <div class="card-score">{{ $item['score'] }}</div>
                                    <div class="card-status-text {{ $status['bg_class'] }}">
                                        {{ $status['text'] }}
                                    </div>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if($chunk->count() < 4)
                        @for($i = 0; $i < (4 - $chunk->count()); $i++)
                            <td width="25%"></td>
                        @endfor
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    <pagebreak page-selector="report_content" />

    {{--  Eight-axis wellness profile  --}}
    <div class="profile-heading">Today’s eight-axis wellness profile</div>
    <div class="profile-subheading">Client-facing labels are designed to be understandable while remaining faithful to the internal IV engine structure.</div>

    <table class="profile-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="axis-col">Axis</th>
                <th class="score-col">Score</th>
                <th class="meaning-col">What it means</th>
                <th class="signals-col">Primary signals reviewed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($iv_scors as $index => $item)
            <tr style="background-color: {{ $index % 2 == 0 ? '#FFFFFF' : '#FAF6F2' }};">
                <td>
                    <div class="axis-label">{{ $item['label'] ?? '' }}</div>
                    <div class="axis-code">{{ $item['code'] ?? '' }}</div>
                </td>
                <td class="score-col">{{ $item['score'] ?? '0' }}</td>
                <td class="meaning-col">{{ $item['what_it_means'] ?? '' }}</td>
                <td class="signals-col">{{ $item['primary_signals_reviewed'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="score-legend-container">
        <div class="score-legend-heading">How to read the scores</div>
        <table cellpadding="0" cellspacing="0" style="margin-bottom: 5px;">
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 18px; line-height: 14px; padding-right: 8px;">&bull;</td>
                <td class="score-legend-item">0–34 suggests a relatively low need / burden at this visit.</td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 18px; line-height: 14px; padding-right: 8px;">&bull;</td>
                <td class="score-legend-item">35–64 suggests a moderate contribution worth acknowledging in treatment design.</td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 18px; line-height: 14px; padding-right: 8px;">&bull;</td>
                <td class="score-legend-item">65–100 suggests a strong driver that should materially shape today’s IV logic.</td>
            </tr>
        </table>

        <div class="clinical-note-box">
            <div class="clinical-note-heading">Clinical interpretation note</div>
            <div class="clinical-note-text">
                These scores are not a diagnosis. They are a structured treatment-planning view built from today’s symptoms, vitals, body composition, autonomic signals, and optional skin-linked recovery signals.
            </div>
        </div>
    </div>

</div>

@endsection
