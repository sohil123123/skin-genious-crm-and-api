<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($widgets = $this->getMiddleWidgets())
        <x-filament-widgets::widgets
            :columns="$this->getMiddleWidgetsColumns()"
            :data="$this->getWidgetData()"
            :widgets="$widgets"
            class="fi-page-widgets"
        />
    @endif

    {{ $this->table }}
</x-filament-panels::page>
