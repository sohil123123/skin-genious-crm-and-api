@php
    use App\Filament\Pages\AiDashboard;

    $counts = $this->getTabCounts();

    $tabs = [
        AiDashboard::TAB_PATIENTS => ['label' => 'Patients', 'icon' => 'heroicon-m-users'],
        AiDashboard::TAB_LEADS => ['label' => 'Meta Leads', 'icon' => 'heroicon-m-megaphone'],
    ];

    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
@endphp

<x-filament-panels::page>

    {{-- Tab switcher. The two queues stay entirely separate: switching tabs
         swaps which widgets the page returns, so the inactive queue is never
         queried. --}}
    <div style="display:flex; gap:.5rem; flex-wrap:wrap; border-bottom:1px solid {{ $hairline }}; padding-bottom:.75rem;">
        @foreach ($tabs as $key => $tab)
            @php $isActive = $this->activeTab === $key; @endphp

            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                style="
                    display:inline-flex; align-items:center; gap:.45rem;
                    padding:.5rem .875rem; border-radius:.5rem; cursor:pointer;
                    font-size:.8125rem; font-weight:600;
                    border:1px solid {{ $isActive ? 'var(--primary-500)' : $hairline }};
                    background:{{ $isActive ? 'color-mix(in srgb, var(--primary-500) 12%, transparent)' : 'transparent' }};
                    color:{{ $isActive ? 'var(--primary-600)' : 'inherit' }};
                    {{ $isActive ? '' : 'opacity:.7;' }}
                "
            >
                <x-filament::icon :icon="$tab['icon']" style="width:1rem; height:1rem;" />
                {{ $tab['label'] }}

                @if (($counts[$key] ?? 0) > 0)
                    <x-filament::badge :color="$isActive ? 'primary' : 'gray'" size="xs">
                        {{ $counts[$key] }}
                    </x-filament::badge>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Action queue for the active tab --}}
    @if ($widgets = $this->getMiddleWidgets())
        <x-filament-widgets::widgets
            :columns="$this->getMiddleWidgetsColumns()"
            :data="$this->getWidgetData()"
            :widgets="$widgets"
            class="fi-page-widgets"
        />
    @endif
</x-filament-panels::page>
