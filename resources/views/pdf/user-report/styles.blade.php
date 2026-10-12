{{-- Plain CSS: written to mPDF in HEADER_CSS mode, so no <style> tag. mPDF supports a subset of CSS — layout is done with tables, not flex/grid. --}}
body {
    font-family: dejavusans;
    font-size: 8.6pt;
    color: #1f2937;
    line-height: 1.45;
}

table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: top; }

.muted { color: #9ca3af; }
.small { font-size: 7.6pt; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.bold { font-weight: bold; }
.nowrap { white-space: nowrap; }

/* ---------------- Page header / footer ---------------- */
.page-header td { vertical-align: middle; }
.page-header { border-bottom: 1px solid #e0e7ff; }
.brand { font-size: 9pt; font-weight: bold; color: #312e81; }
.doc { font-size: 7.6pt; color: #6b7280; text-align: right; }
.page-footer td { font-size: 7pt; color: #9ca3af; vertical-align: middle; }
.page-footer { border-top: 1px solid #e5e7eb; }

/* ---------------- Hero ---------------- */
.hero {
    background-color: #312e81;
    background-gradient: linear #312e81 #6d28d9 0 0 1 1;
    color: #ffffff;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 14px;
}
.hero td { vertical-align: middle; color: #ffffff; }
.hero .logo-box { background-color: #ffffff; border-radius: 8px; padding: 6px; }
.hero .eyebrow { font-size: 7.6pt; letter-spacing: 1.5px; text-transform: uppercase; color: #c7d2fe; }
.hero .title { font-size: 20pt; font-weight: bold; color: #ffffff; }
.hero .subtitle { font-size: 9pt; color: #e0e7ff; }
.hero .meta { font-size: 7.6pt; color: #c7d2fe; text-align: right; }

/* ---------------- Section banners ---------------- */
.section-banner {
    border-radius: 8px;
    padding: 10px 14px;
    margin: 0 0 12px 0;
    color: #ffffff;
}
.section-banner .title { font-size: 14pt; font-weight: bold; color: #ffffff; }
.section-banner .subtitle { font-size: 8pt; color: #ffffff; }
.banner-packages { background-color: #b45309; background-gradient: linear #b45309 #f59e0b 0 0 1 0; }
.banner-assessments { background-color: #4338ca; background-gradient: linear #4338ca #7c3aed 0 0 1 0; }
.banner-sessions { background-color: #0f766e; background-gradient: linear #0f766e #14b8a6 0 0 1 0; }
.banner-invoices { background-color: #047857; background-gradient: linear #047857 #10b981 0 0 1 0; }
.banner-profile { background-color: #334155; background-gradient: linear #334155 #64748b 0 0 1 0; }

/* ---------------- Headings ---------------- */
h2.block-title {
    font-size: 10.5pt;
    color: #312e81;
    margin: 12px 0 6px 0;
    padding-bottom: 3px;
    border-bottom: 1.5px solid #c7d2fe;
}
.sub-title {
    font-size: 9pt;
    font-weight: bold;
    color: #374151;
    margin: 10px 0 4px 0;
}

/* ---------------- Stat cards ---------------- */
.stats { margin: 4px 0 10px 0; }
.stats td.stat {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 7px 9px;
}
.stats td.gap { width: 6px; }
.stat-label { font-size: 6.8pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; }
.stat-value { font-size: 13pt; font-weight: bold; color: #0f172a; }
.stat-note { font-size: 6.8pt; color: #94a3b8; }
.stats td.stat-indigo { border-top: 3px solid #6366f1; }
.stats td.stat-amber { border-top: 3px solid #f59e0b; }
.stats td.stat-teal { border-top: 3px solid #14b8a6; }
.stats td.stat-green { border-top: 3px solid #10b981; }
.stats td.stat-red { border-top: 3px solid #ef4444; }
.stats td.stat-slate { border-top: 3px solid #64748b; }

/* ---------------- Record cards ---------------- */
.record {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin: 0 0 12px 0;
    padding: 0;
}
.record-head {
    background-color: #f5f7ff;
    border-bottom: 1px solid #e0e7ff;
    padding: 8px 12px;
    border-radius: 8px;
}
.record-head td { vertical-align: middle; }
.record-title { font-size: 11.5pt; font-weight: bold; color: #1e1b4b; }
.record-sub { font-size: 7.6pt; color: #6b7280; }
.record-body { padding: 6px 12px 10px 12px; }
.record-body-cont { padding-top: 0; }
/* A card taller than a page: no outer border to break across pages. */
.record-flow { border: none; border-radius: 0; }

/* Moved to the next page whole rather than split, when it fits on one. */
.keep { page-break-inside: avoid; }
td.index-badge {
    background-color: #4f46e5;
    color: #ffffff;
    font-weight: bold;
    font-size: 11pt;
    text-align: center;
    vertical-align: middle;
    width: 28px;
    height: 28px;
    padding: 0;
}

/* ---------------- Info grid (label / value pairs, 2 per row) ---------------- */
.info-grid td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
.info-grid td.label { width: 17%; color: #6b7280; font-size: 7.6pt; }
.info-grid td.value { width: 33%; color: #111827; font-weight: bold; }

/* ---------------- Data tables ---------------- */
.data-table { margin: 4px 0 8px 0; border: 1px solid #e5e7eb; }
.data-table th {
    background-color: #eef2ff;
    color: #3730a3;
    font-size: 7pt;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    text-align: left;
    padding: 5px 6px;
    border-bottom: 1px solid #c7d2fe;
}
.data-table td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; font-size: 8pt; }
.data-table tr.alt td { background-color: #fafafa; }
.data-table tfoot td { background-color: #f8fafc; font-weight: bold; border-top: 1px solid #e5e7eb; }

/* ---------------- Totals ---------------- */
.totals td { padding: 3px 6px; }
.totals td.t-label { color: #6b7280; text-align: right; }
.totals td.t-value { text-align: right; font-weight: bold; width: 26%; }
.totals tr.grand td.t-label, .totals tr.grand td.t-value { font-size: 10.5pt; color: #1e1b4b; border-top: 1.5px solid #c7d2fe; padding-top: 5px; }

/* ---------------- Chips ---------------- */
.chip {
    background-color: #eef2ff;
    color: #3730a3;
    font-size: 7.2pt;
    padding: 1px 5px;
    font-weight: bold;
}
.chips { line-height: 1.9; }
.chip-green { background-color: #dcfce7; color: #166534; }
.chip-amber { background-color: #fef3c7; color: #92400e; }
.chip-red { background-color: #fee2e2; color: #991b1b; }
.chip-blue { background-color: #dbeafe; color: #1e40af; }
.chip-gray { background-color: #f1f5f9; color: #475569; }
.chip-teal { background-color: #ccfbf1; color: #115e59; }
.chip-purple { background-color: #ede9fe; color: #5b21b6; }


/* ---------------- Callouts ---------------- */
.note {
    background-color: #fffbeb;
    border-left: 3px solid #f59e0b;
    padding: 6px 9px;
    margin: 6px 0;
    color: #78350f;
}
.note-indigo { background-color: #eef2ff; border-left-color: #6366f1; color: #312e81; }
.note-teal { background-color: #f0fdfa; border-left-color: #14b8a6; color: #134e4a; }
.empty {
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 18px;
    text-align: center;
    color: #9ca3af;
    margin: 6px 0 12px 0;
}

/* ---------------- Photos ---------------- */
.photos td { padding: 3px; text-align: center; vertical-align: top; width: 25%; }
.photos img { border: 1px solid #e5e7eb; border-radius: 4px; }
.caption { font-size: 6.6pt; color: #6b7280; }

/* ---------------- Structured (JSON) data ---------------- */
.kv-table { margin: 2px 0 6px 0; }
.kv-table td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
.kv-table td.kv-label { width: 32%; color: #6b7280; font-size: 7.6pt; }
.kv-table td.kv-value { color: #111827; }
.list { margin: 2px 0 6px 0; padding-left: 14px; }
.list li { margin-bottom: 2px; }
.nest { margin: 4px 0 6px 0; }
.nest-1, .nest-2, .nest-3, .nest-4 { margin-left: 6px; padding-left: 7px; border-left: 2px solid #e0e7ff; }
.nest-title { font-weight: bold; color: #312e81; margin: 5px 0 3px 0; }
.nest-title-0 {
    font-size: 9.5pt;
    color: #ffffff;
    background-color: #6366f1;
    padding: 3px 8px;
    border-radius: 4px;
}
.nest-title-1 { font-size: 8.8pt; color: #4338ca; }
.nest-title-2 { font-size: 8.4pt; color: #6d28d9; }
.nest-title-3, .nest-title-4 { font-size: 8pt; color: #475569; }
.card-item {
    border: 1px solid #eef2ff;
    background-color: #fcfcff;
    border-radius: 5px;
    padding: 5px 8px;
    margin: 4px 0;
}
.card-item-index { font-size: 7pt; font-weight: bold; color: #6366f1; }
.clinical-block { margin: 8px 0 10px 0; }
.clinical-title {
    font-size: 10pt;
    font-weight: bold;
    color: #ffffff;
    background-color: #4338ca;
    padding: 5px 10px;
    border-radius: 5px;
    margin-bottom: 4px;
}
