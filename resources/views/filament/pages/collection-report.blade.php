<x-filament-panels::page>
    <style>
        .collection-stats-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(4, minmax(135px, 1fr));
        }

        @media (max-width: 768px) {
            .collection-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .collection-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .collection-stat-card {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.25rem 1rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .collection-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .collection-stat-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .collection-stat-value {
            margin-top: 0.5rem;
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
        }

        .dark .collection-stat-card {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .dark .collection-stat-title {
            color: #9ca3af;
        }

        .dark .collection-stat-value {
            color: #ffffff;
        }

        .stat-emerald {
            color: #10b981;
        }

        .dark .stat-emerald {
            color: #34d399;
        }

        .stat-blue {
            color: #3b82f6;
        }

        .dark .stat-blue {
            color: #60a5fa;
        }

        .stat-indigo {
            color: #6366f1;
        }

        .dark .stat-indigo {
            color: #818cf8;
        }

        .stat-purple {
            color: #a855f7;
        }

        .dark .stat-purple {
            color: #c084fc;
        }
    </style>

    @php
        $summary = $this->getSummaryData();
    @endphp

    <div class="collection-stats-grid">
        {{-- Today --}}
        <div class="collection-stat-card">
            <div class="collection-stat-title">Today's Collection</div>
            <div class="collection-stat-value stat-emerald">
                ₹{{ number_format($summary['today'], 2) }}
            </div>
        </div>

        {{-- This Week --}}
        <div class="collection-stat-card">
            <div class="collection-stat-title">This Week</div>
            <div class="collection-stat-value stat-blue">
                ₹{{ number_format($summary['this_week'], 2) }}
            </div>
        </div>

        {{-- This Month --}}
        <div class="collection-stat-card">
            <div class="collection-stat-title">This Month</div>
            <div class="collection-stat-value stat-indigo">
                ₹{{ number_format($summary['this_month'], 2) }}
            </div>
        </div>

        {{-- This Year --}}
        <div class="collection-stat-card">
            <div class="collection-stat-title">This Year</div>
            <div class="collection-stat-value stat-purple">
                ₹{{ number_format($summary['this_year'], 2) }}
            </div>
        </div>
    </div>

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($widgets = $this->getMiddleWidgets())
        <x-filament-widgets::widgets :columns="$this->getMiddleWidgetsColumns()" :data="$this->getWidgetData()"
            :widgets="$widgets" class="fi-page-widgets" />
    @endif

    {{ $this->table }}
</x-filament-panels::page>
