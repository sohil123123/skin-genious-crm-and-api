<style>
@page {
    footer: mainFooter;
}

body {
    font-family: sans-serif;
    font-size: 13px;
    color: #1f2937;
}

.header {
    text-align: center;
    margin-bottom: 18px;
}

.header h1 {
    font-size: 18px;
    margin: 0;
}

.header p {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.patient {
    text-align: center;
    font-size: 12px;
    margin-bottom: 14px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #0f4c75;
    color: #ffffff;
    padding: 6px;
    font-size: 12px;
    text-align: center;
}

td {
    border: 1px solid #d1d5db;
    padding: 6px;
    text-align: center;
    font-size: 12px;
}

td.param {
    text-align: left;
    font-weight: 600;
}

.font_bold {
    font-weight: bold;
}

.muted {
    color: #6b7280;
    font-size: 12px;
}
.green {
    color: #065f46;
}
.stable {
    color: #374151;
}
</style>

<htmlpagefooter name="mainFooter">
    <table width="100%" style="font-size: 10pt; border: none;">
        <tr>
            <td align="left" style="border: none;">
                <span class="muted">Generated via AI Aesthetics Skin Reassessment System</span>
            </td>
            <td align="right" style="border: none;">
                <span class="muted">Page {PAGENO} / {nbpg}</span>
            </td>
        </tr>
    </table>
</htmlpagefooter>

<div class="header">
    <h1>AI AESTHETICS</h1>
    <p>Baseline vs Post-Treatment Comparative Score Report</p>
</div>

<div class="patient">
    Patient: {{ $patient->name }}
    | Age/Gender:
    {{ $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : '—' }}
    / {{ $patient->gender ?? '—' }}
</div>

<table>
    <thead>
        <tr>
            <th>Parameter</th>
            <th>Before Treatment</th>
            <th>Post Treatment</th>
            <th>Change / Interpretation</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($post_diagnosis['reassessment'] as $row)
            @php $badge_class = \Str::lower($row['result']) === 'improved' ? 'green' : 'stable'; @endphp
            <tr>
                <td class="param {{ $badge_class }}">{{ $row['parameter_name'] }}</td>
                <td class="{{ $badge_class }}">{{ $row['before_treatment_score_or_label'] == 'insufficient_data' ? 'N/A' : $row['before_treatment_score_or_label'] }}</td>
                <td class="{{ $badge_class }}">{{ $row['post_treatment_score_or_label'] == 'insufficient_data' ? 'N/A' : $row['post_treatment_score_or_label'] }}</td>
                <td class="font_bold {{ $badge_class }}">{{ $row['result'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
