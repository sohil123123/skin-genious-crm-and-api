<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page {
    size: 215.9mm 279.4mm;
    margin: 6mm 7mm 16mm 7mm;
    background-color: #061421;
}
html, body {
    margin: 0;
    padding: 0;
    font-family: montserrattreatmentv1, dejavusans, sans-serif;
    font-size: 8.15pt;
    line-height: 1.32;
    color: #eef6fb;
    background-color: #061421;
}
table { border-collapse: collapse; }
td { vertical-align: top; }
.header-table { width: 100%; }
.header-icon-cell { width: 12mm; vertical-align: middle; }
.header-icon { width: 9.5mm; height: 9.5mm; }
.brand-name { font-size: 13pt; line-height: 1; font-weight: bold; color: #45b9ff; letter-spacing: 0.55px; }
.brand-byline { margin-top: 0.5mm; font-size: 5.8pt; color: #d8dde3; letter-spacing: 0.35px; }
.header-kicker { text-align: right; vertical-align: middle; font-size: 6.1pt; color: #63d8cf; text-transform: uppercase; letter-spacing: 0.65px; }
.header-page { margin-top: 0.8mm; font-size: 5.8pt; color: #caa3db; text-align: right; }
.header-rule { margin: 2mm 0 2.5mm; border-top: 0.27mm solid #29495f; }
.cover-logo { width: 22mm; height: 22mm; }
.cover-brand-name { font-size: 11.8pt; font-weight: bold; line-height: 1.05; color: #45b9ff; letter-spacing: 0.4px; }
.cover-brand-byline { margin-top: 0.8mm; font-size: 5.9pt; color: #d8dde3; letter-spacing: 0.35px; }
.cover-page-number { font-size: 7pt; color: #caa3db; text-align: right; }
.cover-title { font-family: playfairtreatmentv1, dejavuserif, serif; font-size: 27.5pt; line-height: 1.0; color: #f5f7fa; }
.cover-kicker { font-size: 12pt; color: #ced8df; margin-top: 1.1mm; }
.cover-subtitle { margin-top: 1.6mm; padding-top: 1.5mm; border-top: 0.25mm solid #2a5067; font-size: 7.4pt; color: #b6c4cd; }
.gold { color: #efc26f; }
.purple { color: #b99cff; }
.cyan { color: #56ded5; }
.coral { color: #ff8a7e; }
.blue { color: #85cfff; }
.eyebrow { font-size: 6.2pt; color: #e5c477; text-transform: uppercase; letter-spacing: 0.72px; font-weight: bold; }
.section-title { margin-top: 0.4mm; font-family: playfairtreatmentv1, dejavuserif, serif; font-size: 20.2pt; line-height: 1.06; color: #ffffff; }
.page-subtitle { margin-top: 0.8mm; font-size: 7.4pt; color: #a9bbc6; }
.card { background-color: #0b2033; border: 0.28mm solid #2b4a60; }
.card-soft { background-color: #091b2c; border: 0.25mm solid #223f55; }
.card-gold { border-color: #806c42; }
.card-purple { border-color: #584f89; }
.card-cyan { border-color: #31847f; }
.card-coral { border-color: #865158; }
.card-blue { border-color: #376384; }
.pad { padding: 3mm; }
.pad-lg { padding: 3.7mm; }
.pad-sm { padding: 2.2mm 2.5mm; }
.panel-title { font-size: 11pt; line-height: 1.15; font-weight: bold; color: #ffffff; }
.panel-title-sm { font-size: 9.2pt; line-height: 1.15; font-weight: bold; color: #ffffff; }
.copy { font-size: 7.2pt; line-height: 1.38; color: #c2d0d8; }
.copy-lg { font-size: 8.3pt; line-height: 1.43; color: #e0ebf1; }
.micro { font-size: 6.1pt; line-height: 1.27; color: #9eb2bf; }
.mini-label { font-size: 5.7pt; line-height: 1.2; color: #859baa; text-transform: uppercase; letter-spacing: 0.4px; }
.mini-value { font-size: 8.2pt; line-height: 1.22; color: #ffffff; font-weight: bold; }
.mini-value-lg { font-size: 10pt; line-height: 1.18; color: #ffffff; font-weight: bold; }
.metric-number { font-size: 30pt; line-height: 1; color: #ffffff; font-weight: bold; }
.metric-caption { font-size: 6pt; color: #a7bac6; text-transform: uppercase; letter-spacing: 0.45px; }
.icon-sm { width: 8.5mm; height: 8.5mm; }
.icon-md { width: 12mm; height: 12mm; }
.icon-lg { width: 15mm; height: 15mm; }
.icon-xl { width: 19mm; height: 19mm; }
.rule { border-top: 0.24mm solid #29475d; }
.footer-table { width: 100%; border-top: 0.23mm solid #29465b; }
.footer-table td { padding-top: 1.25mm; vertical-align: middle; }
.footer-label { font-size: 4.9pt; color: #748a98; text-transform: uppercase; letter-spacing: 0.3px; }
.footer-value { margin-top: 0.35mm; font-size: 6.1pt; line-height: 1.18; color: #eef5fa; font-weight: bold; }
.footer-page { font-size: 6pt; color: #e4c477; font-weight: bold; text-align: right; }
.check-table td { vertical-align: middle; }
.check-icon { width: 5.3mm; height: 5.3mm; }
.session-number { font-size: 22pt; line-height: 1; font-weight: bold; }
.timeline-dot { width: 9mm; height: 9mm; }
.timeline-card { height: 37mm; }
.course-metric { text-align: center; vertical-align: middle; height: 38mm; }
.course-metric-number { font-size: 27pt; line-height: 1; font-weight: bold; }
.course-metric-label { margin-top: 1.4mm; font-size: 6.2pt; text-transform: uppercase; letter-spacing: 0.5px; color: #a8bac6; }
.quote { font-family: playfairtreatmentv1, dejavuserif, serif; font-style: italic; font-size: 14pt; line-height: 1.15; color: #efc26f; }
</style>
</head>
<body>
<htmlpagefooter name="treatmentFooter">
<table class="footer-table" cellpadding="0" cellspacing="0">
<tr>
<td width="24%"><div class="footer-label">Patient</div><div class="footer-value">{{ $patient['name'] }} | Age {{ $patient['age'] }} | {{ $patient['gender'] }}</div></td>
<td width="29%"><div class="footer-label">Report Type</div><div class="footer-value">Pigmentation Treatment Roadmap</div></td>
<td width="23%"><div class="footer-label">Course</div><div class="footer-value">{{ $totalSessions }} sessions | {{ $courseDuration }}</div></td>
<td width="16%"><div class="footer-label">Report Date</div><div class="footer-value">{{ $reportDate }}</div></td>
<td width="8%" class="footer-page">{PAGENO}/7</td>
</tr>
</table>
</htmlpagefooter>
<sethtmlpagefooter name="treatmentFooter" value="on" show-this-page="1" />
@yield('content')
</body>
</html>
