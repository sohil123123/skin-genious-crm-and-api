<x-filament-widgets::widget>
    <style>
        .capacity-clinics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }

        @media (max-width: 1024px) {
            .capacity-clinics-grid {
                grid-template-columns: 1fr;
            }
        }

        .capacity-clinic-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.2s, transform 0.15s;
        }

        .capacity-clinic-card:hover {
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .dark .capacity-clinic-card {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .capacity-clinic-header {
            padding: 0.875rem 1.25rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dark .capacity-clinic-header {
            background: rgba(255, 255, 255, 0.05);
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .capacity-clinic-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dark .capacity-clinic-title {
            color: #ffffff;
        }

        .capacity-days-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            padding: 1.25rem;
        }

        @media (max-width: 640px) {
            .capacity-days-grid {
                grid-template-columns: 1fr;
            }
        }

        .capacity-day-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.625rem;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .dark .capacity-day-card {
            background: rgba(255, 255, 255, 0.02);
            border-color: rgba(255, 255, 255, 0.06);
        }

        .capacity-day-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .capacity-day-name {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .dark .capacity-day-name {
            color: #f3f4f6;
        }

        .capacity-day-date {
            font-size: 0.6875rem;
            font-weight: 500;
            color: #6b7280;
        }

        .dark .capacity-day-date {
            color: #9ca3af;
        }

        .capacity-progress-bg {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
            margin: 0.625rem 0;
        }

        .dark .capacity-progress-bg {
            background: rgba(255, 255, 255, 0.1);
        }

        .capacity-progress-bar {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.5s ease;
        }

        .capacity-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            text-align: center;
            margin-top: 0.5rem;
        }

        .capacity-stat-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.5rem 0.25rem;
        }

        .dark .capacity-stat-box {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .capacity-stat-num {
            font-size: 1.125rem;
            font-weight: 800;
            line-height: 1.2;
            color: #111827;
        }

        .dark .capacity-stat-num {
            color: #ffffff;
        }

        .capacity-stat-lbl {
            font-size: 0.625rem;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.125rem;
        }

        .capacity-apt-list {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e5e7eb;
        }

        .dark .capacity-apt-list {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .capacity-apt-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            padding: 0.375rem 0.625rem;
            border-radius: 0.375rem;
            background: #ffffff;
            border: 1px solid #f3f4f6;
            margin-bottom: 0.375rem;
        }

        .dark .capacity-apt-item {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.06);
        }
    </style>

    <x-filament::section icon="heroicon-o-chart-bar-square">
        <x-slot name="heading">
            Clinic Capacity — Today & Tomorrow
        </x-slot>

        <x-slot name="description">
            Live appointment slot utilisation and empty slot recovery gaps across clinics.
        </x-slot>

        @php $capacityData = $this->getCapacityData(); @endphp

        @if (empty($capacityData))
            <div style="text-align: center; padding: 3rem 1rem;">
                <x-filament::icon icon="heroicon-o-building-office" class="w-8 h-8 mx-auto text-gray-400" />
                <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem;">No active clinics found.</p>
            </div>
        @else
            {{-- 2-Column Grid for Clinics --}}
            <div class="capacity-clinics-grid">
                @foreach ($capacityData as $clinic)
                    <div class="capacity-clinic-card">
                        {{-- Clinic Header --}}
                        <div class="capacity-clinic-header">
                            <div class="capacity-clinic-title">
                                <x-filament::icon icon="heroicon-o-building-office-2" class="w-4 h-4 text-primary-500" />
                                <span>{{ $clinic['clinic_name'] }}</span>
                            </div>
                        </div>

                        {{-- Today & Tomorrow 2-Column Sub-Grid --}}
                        <div class="capacity-days-grid">
                            @foreach (['today' => 'Today', 'tomorrow' => 'Tomorrow'] as $periodKey => $periodTitle)
                                @php $slots = $clinic[$periodKey]; @endphp
                                <div class="capacity-day-card">
                                    <div>
                                        {{-- Header & Badge --}}
                                        <div class="capacity-day-header">
                                            <div class="capacity-day-name">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: {{ $periodKey === 'today' ? '#3b82f6' : '#6366f1' }};"></span>
                                                <span>{{ $periodTitle }}</span>
                                                <span class="capacity-day-date">({{ $slots['date_label'] }})</span>
                                            </div>

                                            @if ($slots['is_closed'])
                                                <x-filament::badge color="danger" size="sm">CLOSED</x-filament::badge>
                                            @else
                                                @php
                                                    $badgeColor = match (true) {
                                                        $slots['utilisation'] < 60 => 'success',
                                                        $slots['utilisation'] < 85 => 'warning',
                                                        default => 'danger',
                                                    };
                                                @endphp
                                                <x-filament::badge :color="$badgeColor" size="sm">
                                                    {{ $slots['utilisation'] }}% Full
                                                </x-filament::badge>
                                            @endif
                                        </div>

                                        @if ($slots['is_closed'])
                                            <div style="text-align: center; font-size: 0.75rem; color: #9ca3af; padding: 1.5rem 0;">
                                                Clinic is closed on this day.
                                            </div>
                                        @else
                                            {{-- Progress Bar --}}
                                            @php
                                                $barColor = match (true) {
                                                    $slots['utilisation'] < 60 => '#10b981',
                                                    $slots['utilisation'] < 85 => '#f59e0b',
                                                    default => '#ef4444',
                                                };
                                            @endphp
                                            <div class="capacity-progress-bg">
                                                <div class="capacity-progress-bar" style="width: {{ min(100, $slots['utilisation']) }}%; background: {{ $barColor }};"></div>
                                            </div>

                                            {{-- 3-Stat Metric Cards --}}
                                            <div class="capacity-stat-grid">
                                                <div class="capacity-stat-box">
                                                    <div class="capacity-stat-num">{{ $slots['total_slots'] }}</div>
                                                    <div class="capacity-stat-lbl">Total</div>
                                                </div>
                                                <div class="capacity-stat-box">
                                                    <div class="capacity-stat-num" style="color: #2563eb;">{{ $slots['booked_slots'] }}</div>
                                                    <div class="capacity-stat-lbl">Booked</div>
                                                </div>
                                                <div class="capacity-stat-box">
                                                    @php
                                                        $emptyColor = match (true) {
                                                            $slots['empty_slots'] > 3 => '#16a34a',
                                                            $slots['empty_slots'] > 0 => '#d97706',
                                                            default => '#dc2626',
                                                        };
                                                    @endphp
                                                    <div class="capacity-stat-num" style="color: {{ $emptyColor }};">{{ $slots['empty_slots'] }}</div>
                                                    <div class="capacity-stat-lbl">Empty</div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Appointments Drawer --}}
                                    @if (!$slots['is_closed'] && !empty($slots['booked_appointments']))
                                        <div x-data="{ showList: false }" class="capacity-apt-list">
                                            <button
                                                type="button"
                                                @click="showList = !showList"
                                                style="width: 100%; display: flex; align-items: center; justify-content: space-between; font-size: 0.6875rem; font-weight: 600; background: none; border: none; cursor: pointer; padding: 0;"
                                                class="text-primary-600 dark:text-primary-400"
                                            >
                                                <span style="display: flex; align-items: center; gap: 0.25rem;">
                                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3 transition-transform duration-200" x-bind:class="showList && 'rotate-90'" />
                                                    <span x-text="showList ? 'Hide Appointments' : 'Show {{ count($slots['booked_appointments']) }} Appointments'"></span>
                                                </span>
                                            </button>

                                            <div x-show="showList" x-collapse style="margin-top: 0.5rem;">
                                                @foreach ($slots['booked_appointments'] as $apt)
                                                    <div class="capacity-apt-item">
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <strong style="color: #111827; font-size: 0.6875rem;" class="dark:!text-white">{{ $apt['time'] }}</strong>
                                                            <span style="color: #4b5563; font-size: 0.6875rem;" class="dark:!text-gray-300">{{ $apt['client'] }}</span>
                                                        </div>
                                                        @php
                                                            $aptStatusColor = match ($apt['status']) {
                                                                'confirmed' => 'success',
                                                                'pending' => 'gray',
                                                                'in_progress' => 'warning',
                                                                default => 'info',
                                                            };
                                                        @endphp
                                                        <x-filament::badge :color="$aptStatusColor" size="sm">
                                                            {{ ucfirst(str_replace('_', ' ', $apt['status'])) }}
                                                        </x-filament::badge>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>