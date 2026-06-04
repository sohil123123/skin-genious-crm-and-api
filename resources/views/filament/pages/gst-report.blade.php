<x-filament-panels::page>
    <style>
        .gst-stats-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
            margin-bottom: 1.5rem;
        }
        .gst-stat-card {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        .gst-stat-title {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .gst-stat-value {
            margin-top: 0.25rem;
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
        }

        .dark .gst-stat-card {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .dark .gst-stat-title {
            color: #9ca3af;
        }
        .dark .gst-stat-value {
            color: #ffffff;
        }
        .stat-emerald { color: #059669; }
        .dark .stat-emerald { color: #34d399; }

        .stat-orange { color: #ea580c; }
        .dark .stat-orange { color: #fb923c; }

        .stat-blue { color: #2563eb; }
        .dark .stat-blue { color: #60a5fa; }

        .stat-indigo { color: #4f46e5; }
        .dark .stat-indigo { color: #818cf8; }
    </style>

    {{-- Filter Section --}}
    <x-filament::section>
        <x-slot name="heading">
            Report Filters
        </x-slot>
        {{ $this->form }}
    </x-filament::section>

    {{-- Summary Stats --}}
    @php
        $summary = $this->getSummaryData();
    @endphp

    <div class="gst-stats-grid">
        {{-- Total Taxable Amount --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Total Taxable</div>
            <div class="gst-stat-value">
                ₹{{ number_format($summary['total_taxable'], 2) }}
            </div>
        </div>

        {{-- CGST --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Total CGST</div>
            <div class="gst-stat-value stat-emerald">
                ₹{{ number_format($summary['total_cgst'], 2) }}
            </div>
        </div>

        {{-- SGST --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Total SGST</div>
            <div class="gst-stat-value stat-emerald">
                ₹{{ number_format($summary['total_sgst'], 2) }}
            </div>
        </div>

        {{-- IGST --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Total IGST</div>
            <div class="gst-stat-value stat-orange">
                ₹{{ number_format($summary['total_igst'], 2) }}
            </div>
        </div>

        {{-- Total GST --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Total GST</div>
            <div class="gst-stat-value stat-blue">
                ₹{{ number_format($summary['total_gst'], 2) }}
            </div>
        </div>

        {{-- Grand Total --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Grand Total</div>
            <div class="gst-stat-value stat-indigo">
                ₹{{ number_format($summary['grand_total'], 2) }}
            </div>
        </div>

        {{-- Total Invoices --}}
        <div class="gst-stat-card">
            <div class="gst-stat-title">Total Invoices</div>
            <div class="gst-stat-value">
                {{ number_format($summary['total_invoices']) }}
            </div>
        </div>
    </div>

    {{-- Export Button --}}
    <div class="flex justify-end mt-4" style="margin-top: 1rem; display: flex; justify-content: flex-end;">
        <x-filament::button
            wire:click="exportExcel"
            icon="heroicon-m-arrow-down-tray"
            color="success"
            size="lg"
        >
            Export Excel (.xlsx)
        </x-filament::button>
    </div>

    {{-- Data Table --}}
    <div style="margin-top: 1rem;">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
