@extends('pdf.master')

@section('content')

<style>
    .roadmap-title {
        font-size: 22px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 25px;
        text-transform: capitalize;
    }
    .goal-box {
        border: 1px solid #e1ceb0;
        background-color: #fafafa;
        padding: 15px 20px;
        border-radius: 4px;
        margin-bottom: 30px;
    }
    .goal-header {
        font-size: 13px;
        font-weight: bold;
        color: #0E2B5C;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .goal-text {
        font-size: 12px;
        color: #334155;
        line-height: 1.5;
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
        background-color: #fcfaf7; /* Subtle warm tint for roadmap */
    }
    .phase-col { width: 15%; font-weight: bold; color: #0E2B5C; }
    .weeks-col { width: 12%; text-align: center; }
    .focus-col { width: 28%; }
    .understand-col { width: 45%; border-right: none; }

    .roadmap-table tr:last-child td {
        border-bottom: none;
    }

    /* ─── Additions for Timeline and Explanation ─── */
    .protocol-container {
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
        background-color: #ffffff;
        border-radius: 4px;
        overflow: hidden;
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
</style>

<pagebreak page-selector="report_content" />

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">IV Program Roadmap Report</div>
        <div class="report-subtitle">Program-selling report that converts one-off IV interest into a coherent, clinically managed multi-session roadmap.</div>
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

    {{-- ── Roadmap Content ── --}}
    <div class="roadmap-title">{{ $program['plan_duration_weeks'] ?? 'Multi' }}-Week Roadmap Overview</div>

    <div class="goal-box">
        <div class="goal-header">Program Goal</div>
        <div class="goal-text">
            {{ $program['client_facing_explanation']['roadmap_goal'] ?? ($program['schedule_description'] ?? 'Transition from one-off treatments into a sustained, clinically managed wellness progression.') }}
        </div>
    </div>

    @php
        $phases = [];
        if (isset($program['sessions']) && is_array($program['sessions'])) {
            foreach ($program['sessions'] as $session) {
                $phaseId = $session['phase_id'] ?? 'unknown_phase';
                if (!isset($phases[$phaseId])) {
                    // Clean up phase name: "phase_1_reset_4_weeks" -> "1. Reset"
                    $cleanName = ucwords(str_replace(['phase_', '_'], ['', ' '], $phaseId));
                    // Extract just the first word if it's long, or handle specific format
                    $parts = explode(' ', $cleanName);
                    if (is_numeric(trim($parts[0] ?? ''))) {
                         $cleanName = trim($parts[0]) . ". " . ucwords($parts[1] ?? 'Phase');
                    }

                    $phases[$phaseId] = [
                        'name' => $cleanName,
                        'weeks' => [],
                        'focus' => $session['session_goal_summary'] ?? 'N/A',
                        'understand' => $session['candidate_generation_hint'] ?? 'N/A'
                    ];
                }
                $phases[$phaseId]['weeks'][] = $session['week_index'];
            }
        }
    @endphp

    <table class="roadmap-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="phase-col">Phase</th>
                <th class="weeks-col">Weeks</th>
                <th class="focus-col">Focus</th>
                <th class="understand-col">What you should understand</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($phases as $details)
                @php
                    $minW = min($details['weeks']);
                    $maxW = max($details['weeks']);
                    $weekDisplay = ($minW == $maxW) ? $minW : "{$minW}–{$maxW}";
                @endphp
                <tr>
                    <td class="phase-col">{{ $details['name'] }}</td>
                    <td class="weeks-col">{{ $weekDisplay }}</td>
                    <td class="focus-col">{{ $details['focus'] }}</td>
                    <td class="understand-col">{{ $details['understand'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <pagebreak page-selector="report_content" />

    <div class="report_content_div">
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
</div>

@endsection
