<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Pigmentation Reassessment Report</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1f2937;
            line-height: 1.5;
            font-size: 13px;
        }
        @page {
            margin-top: 30mm;
            margin-bottom: 20mm;
            margin-left: 15mm;
            margin-right: 15mm;
            header: mainHeader;
            footer: mainFooter;
        }
        .header-content {
            text-align: center;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 8px;
        }
        .header-content h1 {
            font-size: 20px;
            font-weight: bold;
            margin: 0;
            color: #0d9488;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .footer-text {
            font-size: 9px;
            color: #6b7280;
        }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f766e;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        .patient-card {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            padding: 6px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            color: #4b5563;
        }
        .value {
            color: #111827;
        }
        table.comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.comparison-table th {
            background-color: #0f766e;
            color: #ffffff;
            font-weight: bold;
            padding: 8px;
            font-size: 12px;
            text-align: left;
            border: 1px solid #0d9488;
        }
        table.comparison-table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            font-size: 12px;
        }
        .green-badge {
            color: #065f46;
            font-weight: bold;
        }
        .stable-badge {
            color: #374151;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <htmlpageheader name="mainHeader">
        <div class="header-content">
            <h1>AI Aesthetics — Pigmentation Reassessment</h1>
        </div>
    </htmlpageheader>

    <htmlpagefooter name="mainFooter">
        <table width="100%" style="border: none;" class="footer-text">
            <tr>
                <td align="left" style="border: none;">Confidential - Comparative Progress Assessment</td>
                <td align="right" style="border: none;">Page {PAGENO} of {nbpg}</td>
            </tr>
        </table>
    </htmlpagefooter>

    <div class="patient-card">
        <table class="grid">
            <tr>
                <td width="15%"><span class="label">Patient:</span></td>
                <td width="35%"><span class="value">{{ $patient->name }}</span></td>
                <td width="15%"><span class="label">Gender/Age:</span></td>
                <td width="35%"><span class="value">{{ $patient->gender ? ucfirst($patient->gender) : '—' }} / {{ $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age . ' yrs' : '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Date:</span></td>
                <td><span class="value">{{ $record->created_at->format('M d, Y') }}</span></td>
                <td><span class="label">Clinic:</span></td>
                <td><span class="value">{{ $record->clinic->name ?? 'Main Clinic' }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section-title">Comparative Analysis (Baseline vs Post-Treatment)</div>
    
    <table class="comparison-table">
        <thead>
            <tr>
                <th width="30%">Clinical Parameter</th>
                <th width="20%">Baseline (Before)</th>
                <th width="20%">Post-Treatment (After)</th>
                <th width="30%">Progress / Interpretation</th>
            </tr>
        </thead>
        <tbody>
            @php
                $reassessments = $post_diagnosis['reassessment'] ?? [];
            @endphp
            @forelse ($reassessments as $row)
                @php 
                    $isImproved = \Str::lower($row['result'] ?? '') === 'improved';
                    $badgeClass = $isImproved ? 'green-badge' : 'stable-badge'; 
                @endphp
                <tr>
                    <td style="font-weight: bold; color: #374151;">{{ $row['parameter_name'] }}</td>
                    <td>{{ $row['before_treatment_score_or_label'] == 'insufficient_data' ? 'N/A' : $row['before_treatment_score_or_label'] }}</td>
                    <td>{{ $row['post_treatment_score_or_label'] == 'insufficient_data' ? 'N/A' : $row['post_treatment_score_or_label'] }}</td>
                    <td class="{{ $badgeClass }}">{{ strtoupper($row['result'] ?? 'STABLE') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" align="center">No reassessment comparison rows found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
