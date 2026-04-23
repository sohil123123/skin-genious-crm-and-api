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

    <pagebreak page-selector="report_content" />

    {{--  What was reviewed in the analysis  --}}
    <div class="profile-heading" style="margin-top: 10px; font-size: 24px;">What was reviewed in the analysis</div>

    <table class="profile-table" cellpadding="0" cellspacing="0" style="margin-bottom: 30px; margin-top: 20px;">
        <thead>
            <tr>
                <th>Input domain</th>
                <th>Why it matters</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background-color: #FFFFFF;">
                <td style="padding: 15px 12px; font-weight: 500; font-size: 13px; color: #2D3748;">Symptoms & intent</td>
                <td style="padding: 15px 12px; font-size: 13px; line-height: 1.5; color: #2D3748;">Fatigue, stress, brain fog tendency, standing dizziness, recovery quality, desired intensity.</td>
            </tr>
            <tr style="background-color: #FAF6F2;">
                <td style="padding: 15px 12px; font-weight: 500; font-size: 13px; color: #2D3748;">Vitals</td>
                <td style="padding: 15px 12px; font-size: 13px; line-height: 1.5; color: #2D3748;">Blood pressure, heart rate, oxygen saturation, temperature, orthostatic cues where available.</td>
            </tr>
            <tr style="background-color: #FFFFFF;">
                <td style="padding: 15px 12px; font-weight: 500; font-size: 13px; color: #2D3748;">Body composition</td>
                <td style="padding: 15px 12px; font-size: 13px; line-height: 1.5; color: #2D3748;">TBW%, BMI / visceral-fat context, muscle reserve and other body-composition readings.</td>
            </tr>
            <tr style="background-color: #FAF6F2;">
                <td style="padding: 15px 12px; font-weight: 500; font-size: 13px; color: #2D3748;">Autonomic metrics</td>
                <td style="padding: 15px 12px; font-size: 13px; line-height: 1.5; color: #2D3748;">HRV / lnRMSSD, perfusion index, resting strain, recovery indicators.</td>
            </tr>
            <tr style="background-color: #FFFFFF;">
                <td style="padding: 15px 12px; font-weight: 500; font-size: 13px; color: #2D3748;">Skin-linked refiners</td>
                <td style="padding: 15px 12px; font-size: 13px; line-height: 1.5; color: #2D3748;">Oxidative haze, barrier instability, dullness/clarity and other capped skin-derived refiners when available.</td>
            </tr>
        </tbody>
    </table>

    {{--  Key reasons the top scores are elevated  --}}
    <div class="profile-heading" style="font-size: 24px;">Key reasons the top scores are elevated</div>
    <div class="score-legend-container" style="margin-top: 15px;">
        <table cellpadding="0" cellspacing="0" style="margin-bottom: 5px; width: 100%;">
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 12px; color: #2D3748;">
                    <strong>Autonomic strain</strong> appears elevated because the pattern combines stress, recovery load, and HRV-linked fatigue.
                </td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 12px; color: #2D3748;">
                    <strong>Energy-output need</strong> is high because the profile suggests poor reserve rather than just temporary dehydration.
                </td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 12px; color: #2D3748;">
                    <strong>Hydration-recovery support</strong> matters because TBW% and symptoms suggest that comfort and replenishment should be built into the session.
                </td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 12px; color: #2D3748;">
                    <strong>Antioxidant support</strong> is relevant because lifestyle strain and oxidative proxies support a recovery-and-glow layer, not just a stimulant-style build.
                </td>
            </tr>
        </table>
    </div>

    <pagebreak page-selector="report_content" />

    {{-- Confidence and trust layer --}}
    @php
        $confValue = (float)($telemetry['confidence_0_1'] ?? 0);
        $confLabel = 'Good';
        $confDescription = 'Signals available from symptoms, vitals, body composition, and recovery metrics were sufficient to support a confident treatment-planning view.';

        if ($confValue >= 0.8) {
            $confLabel = 'Excellent';
        } elseif ($confValue >= 0.4) {
            $confLabel = 'Good';
        } else {
            $confLabel = 'Moderate';
            $confDescription = 'Some readings were estimated or missing; the engine has operated conservatively based on available signals.';
        }
    @endphp

    <div class="profile-heading" style="margin-top: 10px; font-size: 24px;">Confidence and trust layer</div>

    <div style="background-color: #f7f3ef; border-radius: 4px; padding: 25px; margin-top: 20px; width: 100%;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="25%" align="center" style="border-right: 1px solid #e2dfd2; padding-right: 15px;">
                    <div style="font-size: 11px; color: #718096; text-transform: uppercase; font-weight: bold; line-height: 1.4;">Interpretation<br>confidence</div>
                </td>
                <td width="25%" align="center" style="border-right: 1px solid #e2dfd2; padding: 0 15px;">
                    <div style="font-size: 32px; font-weight: bold; color: #1B3A6B;">{{ $confLabel }}</div>
                </td>
                <td style="padding-left: 20px;">
                    <div style="font-size: 13px; color: #2D3748; line-height: 1.5;">
                        {{ $confDescription }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="score-legend-container" style="margin-top: 25px;">
        <table cellpadding="0" cellspacing="0" style="margin-bottom: 5px; width: 100%;">
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 18px; color: #2D3748;">
                    If critical readings are missing, the engine can still operate, but confidence is presented more conservatively.
                </td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 18px; color: #2D3748;">
                    Hard safety checks and doctor-approval gates are not marketing language; they remain part of the actual protocol-selection system.
                </td>
            </tr>
            <tr>
                <td valign="top" style="color: #C9A84C; font-size: 20px; line-height: 20px; padding-top: 2px; padding-right: 12px; width: 15px;">&bull;</td>
                <td class="score-legend-item" style="font-size: 14px; line-height: 1.6; padding-bottom: 18px; color: #2D3748;">
                    The treatment recommendation is therefore designed to feel premium while remaining clinically constrained.
                </td>
            </tr>
        </table>
    </div>

    <div style="border: 1px solid #e2dfd2; border-radius: 4px; padding: 25px; margin-top: 20px;">
        <div style="font-size: 14px; font-weight: bold; color: #1B3A6B; margin-bottom: 8px;">Client-facing trust message</div>
        <div style="font-size: 14px; color: #2D3748; line-height: 1.6;">
            Your IV session is not chosen from a fixed menu alone. It is selected after today’s signals are reviewed, then filtered through safety constraints, dosing rules, and protocol logic before being recommended.
        </div>
    </div>

</div>

@endsection
