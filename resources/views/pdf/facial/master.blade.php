<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page {
    size: 215.9mm 279.4mm;
    margin: 6mm 7mm 11mm 7mm;
    background-color: #061421;
}
html, body {
    margin: 0;
    padding: 0;
    font-family: dejavusans, sans-serif;
    font-size: 9pt;
    line-height: 1.32;
    color: #eef6fb;
    background-color: #061421;
}
table { border-collapse: collapse; }
td { vertical-align: top; }
.header-table { width: 100%; }
.header-icon-cell { width: 12mm; vertical-align: middle; }
.header-icon { width: 9mm; height: 9mm; }
.brand-name { font-size: 13.5pt; line-height: 1; font-weight: bold; color: #e7c879; letter-spacing: 0.6px; }
.brand-byline { margin-top: 0.5mm; font-size: 6pt; color: #aebfca; text-transform: uppercase; letter-spacing: 0.6px; }
.header-kicker { text-align: right; vertical-align: middle; font-size: 6.4pt; color: #63d8cf; text-transform: uppercase; letter-spacing: 0.7px; }
.header-rule { margin: 2.2mm 0 3mm; border-top: 0.28mm solid #29495f; }
.cover-brand { width: 25mm; height: 25mm; }
.cover-title { font-family: playfair, dejavuserif, serif; font-size: 29pt; line-height: 1; color: #f5f7fa; }
.cover-title .ai { color: #71e0dc; }
.cover-subtitle { margin-top: 1.5mm; font-size: 8.5pt; color: #c2d1da; }
.cover-subtitle .dot { color: #63d8cf; padding: 0 1.5mm; }
.passport-prefix { font-size: 8pt; color: #d1dfe7; text-transform: uppercase; letter-spacing: 0.9px; }
.passport-display { margin-top: 0.4mm; font-family: playfair, dejavuserif, serif; font-style: italic; font-size: 30pt; line-height: 0.98; color: #f1d29b; }
.passport-tagline { margin-top: 1.2mm; font-size: 15pt; line-height: 1.08; font-weight: bold; color: #ffffff; }
.eyebrow { font-size: 6.7pt; color: #e5c477; text-transform: uppercase; letter-spacing: 0.85px; font-weight: bold; }
.section-title { margin-top: 0.7mm; font-size: 17pt; line-height: 1.08; font-weight: bold; color: #ffffff; }
.section-title .accent, .accent { color: #63d8cf; }
.page-subtitle { margin-top: 1mm; font-size: 8pt; line-height: 1.35; color: #a8bbc7; }
.section-copy { font-size: 8pt; line-height: 1.42; color: #c2d0d8; }
.section-copy-lg { font-size: 9.2pt; line-height: 1.5; color: #e0ebf1; }
.card { background-color: #0b2033; border: 0.3mm solid #2b4a60; }
.card-soft { background-color: #091b2c; border: 0.28mm solid #223f55; }
.card-teal { border-color: #31847f; }
.card-gold { border-color: #816d43; }
.card-purple { border-color: #545083; }
.card-green { border-color: #2f765f; }
.pad { padding: 3.2mm; }
.pad-lg { padding: 4mm; }
.pad-sm { padding: 2.2mm 2.5mm; }
.mini-label { font-size: 6pt; line-height: 1.2; color: #859baa; text-transform: uppercase; letter-spacing: 0.5px; }
.mini-value { font-size: 8.6pt; line-height: 1.26; color: #f4f8fb; font-weight: bold; }
.mini-value-lg { font-size: 11pt; line-height: 1.22; color: #ffffff; font-weight: bold; }
.metric-number { font-size: 33pt; line-height: 1; font-weight: bold; color: #ffffff; }
.metric-label { font-size: 6.4pt; line-height: 1.25; color: #a9bdc9; text-transform: uppercase; letter-spacing: 0.6px; }
.thin-rule { border-top: 0.25mm solid #2a485e; }
.hero-box, .image-box { background-color: #050e18; border: 0.3mm solid #2c5268; padding: 1mm; text-align: center; }
.no-image { color: #718796; font-size: 6.2pt; text-align: center; text-transform: uppercase; padding: 10mm 1mm; }
.pill { display: inline-block; padding: 1mm 2mm; border: 0.24mm solid #35566b; color: #cce0eb; background-color: #112f45; font-size: 6pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
.pill-green { color: #85e6c1; border-color: #2f745f; background-color: #12342d; }
.pill-gold { color: #f0d18a; border-color: #7d6942; background-color: #362f21; }
.pill-red { color: #ffb0b0; border-color: #875057; background-color: #3d2528; }
.pill-purple { color: #c6b7ff; border-color: #675d9b; background-color: #252443; }
.pill-blue { color: #9ed7f6; border-color: #376384; background-color: #112d45; }
.icon-circle { width: 13mm; height: 13mm; }
.icon-circle-sm { width: 9mm; height: 9mm; }
.arrow-icon { width: 7mm; height: 2.5mm; }
.arrow-icon-sm { width: 5mm; height: 1.8mm; }
.score-number { font-size: 15pt; line-height: 1; color: #ffffff; font-weight: bold; }
.score-caption { margin-top: 0.5mm; font-size: 6.4pt; line-height: 1.2; color: #9cb0bd; }
.score-direction { margin-top: 0.45mm; font-size: 6.2pt; line-height: 1.2; color: #63d8cf; font-weight: bold; }
.score-dot { width: 4.3mm; height: 2.2mm; border: 0.2mm solid #3b596d; background-color: #10293b; }
.score-dot-on-health { background-color: #36cfc6; border-color: #62eee4; }
.score-dot-on-severity { background-color: #8f74e8; border-color: #b7a7ff; }
.score-dot-on-balance { background-color: #e2bd68; border-color: #f1d78e; }
.dashboard-card { width: 100%; height: 24.5mm; background-color: #0b2033; border: 0.28mm solid #2b4a60; margin-bottom: 2mm; }
.dashboard-card td { padding: 2.3mm; }
.dashboard-name { font-size: 8.2pt; line-height: 1.15; color: #ffffff; font-weight: bold; }
.dashboard-copy { margin-top: 0.7mm; font-size: 6.4pt; line-height: 1.28; color: #b1c2cc; }
.priority-card { width: 100%; height: 51mm; background-color: #0b2033; border: 0.3mm solid #2b4a60; margin-top: 3mm; }
.priority-card td { padding: 3mm; }
.priority-title { font-size: 12pt; line-height: 1.15; color: #ffffff; font-weight: bold; }
.priority-copy { margin-top: 1mm; font-size: 7.6pt; line-height: 1.35; color: #c1d0d8; }
.priority-why { margin-top: 1mm; font-size: 6.6pt; line-height: 1.3; color: #8fa4b2; }
.mode-tile { width: 100%; height: 71mm; background-color: #0b2033; border: 0.28mm solid #2b4a60; }
.mode-title { font-size: 10.5pt; line-height: 1.15; color: #ffffff; font-weight: bold; }
.mode-copy { margin-top: 0.7mm; font-size: 7.2pt; line-height: 1.3; color: #a9bcc7; }
.parameter-grid-card { width: 100%; height: 101mm; background-color: #0b2033; border: 0.3mm solid #2b4a60; }
.parameter-grid-card td { padding: 2.7mm; }
.parameter-name { font-size: 11.2pt; line-height: 1.18; color: #ffffff; font-weight: bold; }
.parameter-copy { margin-top: 1.2mm; font-size: 8.5pt; line-height: 1.38; color: #c0ced6; }
.parameter-causes { margin-top: 1.5mm; font-size: 7.25pt; line-height: 1.34; color: #8fa4b2; }
.result-card { width: 100%; height: 27mm; background-color: #0b2033; border: 0.28mm solid #2b4a60; margin-bottom: 2mm; }
.result-card td { padding: 3.8mm 3mm; }
.result-name { font-size: 9.2pt; line-height: 1.15; color: #ffffff; font-weight: bold; }
.result-copy { margin-top: 0.7mm; font-size: 7pt; line-height: 1.25; color: #aebfca; }
.improvement-card-large { width: 100%; height: 99mm; background-color: #0b2033; border: 0.3mm solid #2f817c; }
.improvement-card-compact { width: 100%; height: 58mm; background-color: #0b2033; border: 0.3mm solid #2f817c; }
.improve-title { font-size: 11.4pt; line-height: 1.18; color: #ffffff; font-weight: bold; }
.improve-copy { margin-top: 1.1mm; font-size: 8pt; line-height: 1.34; color: #c0ced6; }
.change-number { font-size: 22pt; line-height: 1; color: #ffffff; font-weight: bold; }
.stable-mini { width: 100%; height: 32mm; background-color: #0b2033; border: 0.28mm solid #2b4a60; }
.stable-mini td { padding: 3.8mm 3mm; }
.comparison-row { width: 100%; height: 66mm; background-color: #0b2033; border: 0.3mm solid #2b4a60; margin-bottom: 3mm; }
.comparison-row td { padding: 2.8mm; vertical-align: middle; }
.comparison-title { font-size: 11.8pt; line-height: 1.15; color: #ffffff; font-weight: bold; }
.comparison-copy { margin-top: 0.8mm; font-size: 7.9pt; line-height: 1.3; color: #a8bbc6; }
.maintenance-card { width: 100%; height: 71mm; background-color: #0b2033; border: 0.3mm solid #2b4a60; }
.maintenance-title { font-size: 12.3pt; line-height: 1.15; color: #ffffff; font-weight: bold; }
.maintenance-copy { margin-top: 1.4mm; font-size: 9pt; line-height: 1.4; color: #c1d0d8; }

.cover-mode-label { font-size: 6.8pt; line-height: 1.2; color: #9fb4c1; text-transform: uppercase; letter-spacing: 0.45px; text-align: center; }
.scoreboard-skin-card { width:100%; height:25mm; background-color:#0b2033; border:0.3mm solid #31847f; }
.scoreboard-skin-card td { padding:3mm; }
.scoreboard-improved-card { width:100%; height:33mm; background-color:#0b2033; border:0.3mm solid #2f817c; }
.scoreboard-improved-card td { padding:2.6mm; }
.scoreboard-improved-name { font-size:9.5pt; line-height:1.16; color:#ffffff; font-weight:bold; }
.scoreboard-improved-copy { margin-top:0.7mm; font-size:6.8pt; line-height:1.28; color:#aebfca; }
.scoreboard-stable-card { width:100%; height:18.5mm; background-color:#0a1e31; border:0.26mm solid #29495f; }
.scoreboard-stable-card td { padding:1.8mm 2.2mm; }
.scoreboard-stable-name { font-size:7.7pt; line-height:1.12; color:#ffffff; font-weight:bold; }
.scoreboard-stable-copy { margin-top:0.45mm; font-size:6.15pt; line-height:1.2; color:#9eb2bf; }
.scoreboard-summary-strip { width:100%; height:16mm; background-color:#0b2033; border:0.3mm solid #545083; }
.stable-strength-card-v54 { width:100%; height:31mm; background-color:#0b2033; border:0.3mm solid #31847f; }
.stable-strength-card-v54 td { padding:3mm; }
.monitor-card-v54 { width:100%; height:30mm; background-color:#0b2033; border:0.28mm solid #2b4a60; }
.monitor-card-v54 td { padding:3mm; }
.monitor-title-v54 { font-size:8.8pt; line-height:1.14; color:#ffffff; font-weight:bold; }
.monitor-copy-v54 { margin-top:0.7mm; font-size:7.05pt; line-height:1.28; color:#afc0ca; }
.maintenance-card-v54 { width:100%; height:72mm; background-color:#0b2033; border:0.3mm solid #2b4a60; }
.maintenance-card-v54 td { padding:4mm; }
.footer-table { width: 100%; border-top: 0.24mm solid #29465b; }
.footer-table td { padding-top: 1.5mm; }
.footer-copy { font-size: 5.4pt; color: #748a98; }
.footer-page { font-size: 6pt; color: #e4c477; font-weight: bold; text-align: right; }
</style>
</head>
<body>
<htmlpagefooter name="reportFooter">
<table class="footer-table" cellpadding="0" cellspacing="0">
<tr>
<td width="82%" class="footer-copy">5-mode imaging | personalised analysis | doctor-reviewable output | objective reassessment</td>
<td width="18%" class="footer-page">PAGE {PAGENO} / {nbpg}</td>
</tr>
</table>
</htmlpagefooter>
<sethtmlpagefooter name="reportFooter" value="on" show-this-page="1" />
@yield('content')
</body>
</html>
