@extends('pdf.master')

@section('content')

<style>
    .section-title {
        font-size: 20px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 15px;
    }
    .reassessment-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }
    .reassessment-table th {
        background-color: #2c3645;
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        padding: 12px 15px;
        text-align: left;
        border-right: 1px solid #475569;
    }
    .reassessment-table th:last-child {
        border-right: none;
    }
    .reassessment-table td {
        font-size: 12px;
        color: #334155;
        padding: 12px 15px;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }
    .reassessment-table td:last-child {
        border-right: none;
    }
    .reassessment-table tr:nth-child(even) td {
        background-color: #f6f2eb;
    }
    .reassessment-table tr:nth-child(odd) td {
        background-color: #ffffff;
    }
    .interpretation-box {
        border: 1px solid #e2ceaf;
        background-color: #ffffff;
        padding: 15px 20px;
        margin-bottom: 30px;
    }
    .interpretation-header {
        font-size: 14px;
        font-weight: bold;
        color: #0E2B5C;
        margin-bottom: 8px;
    }
    .interpretation-text {
        font-size: 12px;
        color: #334155;
        line-height: 1.5;
    }
</style>

<div class="report_content_div">
    {{-- ── Report Header ── --}}
    <div class="report-header">
        <div class="report-title">IV Progress & Reassessment Report</div>
        <div class="report-subtitle">Example medium-term progress report showing how IV outcomes can be reviewed after a few sessions rather than after every visit.</div>
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

    <div class="section-title">Baseline vs current profile</div>
    
    <table class="reassessment-table">
        <thead>
            <tr>
                <th style="width: 45%;">Axis</th>
                <th style="width: 15%;">Baseline</th>
                <th style="width: 15%;">Current</th>
                <th style="width: 25%;">Direction</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Fluid & Electrolyte Need</td>
                <td>68</td>
                <td>42</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Circulation & Tolerance Profile</td>
                <td>49</td>
                <td>38</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Stress & Nervous System Load</td>
                <td>72</td>
                <td>41</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Energy Output Need</td>
                <td>76</td>
                <td>48</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Oxidative / Detox Burden</td>
                <td>64</td>
                <td>39</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Inflammation / Immune Load</td>
                <td>38</td>
                <td>30</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Metabolic Stability</td>
                <td>57</td>
                <td>44</td>
                <td>Improved</td>
            </tr>
            <tr>
                <td>Skin Glow Readiness</td>
                <td>61</td>
                <td>36</td>
                <td>Improved</td>
            </tr>
        </tbody>
    </table>

    <div class="interpretation-box">
        <div class="interpretation-header">Headline interpretation</div>
        <div class="interpretation-text">
            This example shows a meaningful reduction in stress-load, energy-output need, oxidative burden, and skin-readiness strain after a short IV sequence. The client story can therefore shift from 'reset' toward 'maintain and refine'.
        </div>
    </div>

    <pagebreak page-selector="report_content" />

    <div class="section-title" style="margin-top: 30px;">Where the biggest movement occurred</div>
    
    <ul style="color: #334155; font-size: 13px; line-height: 1.6; margin-bottom: 40px; padding-left: 18px;">
        <li style="margin-bottom: 12px; color: #C29F5D;"><span style="color: #334155;">Stress & nervous system load improved materially, suggesting the plan is helping the client feel less strained and more recovered.</span></li>
        <li style="margin-bottom: 12px; color: #C29F5D;"><span style="color: #334155;">Energy-output need also reduced, which is often one of the most meaningful client-perceived changes.</span></li>
        <li style="margin-bottom: 12px; color: #C29F5D;"><span style="color: #334155;">Oxidative burden and skin-glow strain moved in the right direction, supporting a visible-refreshed narrative without overclaiming.</span></li>
        <li style="color: #C29F5D;"><span style="color: #334155;">Residual moderate scores are still useful because they guide what the next phase should focus on.</span></li>
    </ul>

    <div class="interpretation-box">
        <div class="interpretation-header" style="font-size: 14px; margin-bottom: 6px;">What the client should hear</div>
        <div class="interpretation-text" style="font-size: 13px; line-height: 1.6;">
            'You appear to be moving out of a high-burden, catch-up state and into a steadier place. That means the next phase can focus more on maintaining and refining the gains rather than only resetting you each time.'
        </div>
    </div>

    <pagebreak page-selector="report_content" />

    <div class="section-title" style="margin-top: 30px;">Subjective and objective change summary</div>
    
    <table class="reassessment-table">
        <thead>
            <tr>
                <th style="width: 25%;">Domain</th>
                <th style="width: 37.5%;">Baseline impression</th>
                <th style="width: 37.5%;">Current impression</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Recovery quality</td>
                <td>Feels drained after social / work load</td>
                <td>More stable recovery after similar weeks</td>
            </tr>
            <tr>
                <td>Calm / stress feel</td>
                <td>Wired-tired, poorly reset</td>
                <td>Noticeably steadier and calmer<br>post-treatment</td>
            </tr>
            <tr>
                <td>Energy consistency</td>
                <td>Boost needed frequently</td>
                <td>Better baseline steadiness</td>
            </tr>
            <tr>
                <td>Visible freshness</td>
                <td>Dull and tired look after load</td>
                <td>More refreshed / clearer overall<br>presentation</td>
            </tr>
        </tbody>
    </table>

    <ul style="color: #334155; font-size: 13px; line-height: 1.6; margin-top: 25px; padding-left: 18px;">
        <li style="margin-bottom: 12px; color: #C29F5D;"><span style="color: #334155;">This page helps connect the numbers back to lived experience.</span></li>
        <li style="color: #C29F5D;"><span style="color: #334155;">That is important because IV clients often judge value from how they feel first, and from the data second.</span></li>
    </ul>

</div>
@endsection
