<style>
@page {
    footer: mainFooter;
}
body {
    font-family: sans-serif;
    font-size: 12px;
    color: #1f2937;
}

.header {
    text-align: center;
    margin-bottom: 20px;
}

.header h1 {
    font-size: 20px;
    margin: 0;
}

.header .sub {
    font-size: 11px;
    color: #6b7280;
}

.card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}

.label {
    font-size: 10px;
    color: #6b7280;
}

.value {
    font-size: 13px;
    font-weight: bold;
}

.row {
    width: 100%;
    margin-bottom: 12px;
}

.footer {
    position: absolute;
    bottom: 20px;
    width: 100%;
    text-align: center;
    font-size: 9px;
    color: rgba(0,0,0,0.4);
}
</style>

<htmlpagefooter name="mainFooter">
    <table width="100%" style="font-size: 10pt; border: none;">
        <tr>
            <td align="left" style="border: none;">
                <span class="muted">Confidential – For Professional Use Only</span>
            </td>
            <td align="center" style="border: none;">
                <span class="muted">Page {PAGENO} / {nbpg}</span>
            </td>
            <td align="right" style="border: none;">
                <span class="muted">Generated on: {{ date('m/d/Y') }}</span>
            </td>
        </tr>
    </table>
</htmlpagefooter>

<div class="header">
    <h1>AI AESTHETICS</h1>
    <div class="sub">Treatment Plan</div>
</div>

<div class="card">
    <div class="row">
        <span class="label">Name</span><br>
        <span class="value">{{ $client['name'] }}</span>
    </div>

    <div class="row">
        <span class="label">Age</span><br>
        <span class="value">{{ $client['age'] ?? 'Not specified' }}</span>
    </div>

    <div class="row">
        <span class="label">Gender</span><br>
        <span class="value">{{ $client['gender'] ?? 'Not specified' }}</span>
    </div>
</div>

<div class="card">
    <div class="row">
        <span class="label">Treatment Duration</span><br>
        <span class="value">{{ $summary['duration'] ?? '—' }}</span>
    </div>

    <div class="row">
        <span class="label">Total Sessions</span><br>
        <span class="value">{{ $summary['total_sessions'] }}</span>
    </div>
</div>
