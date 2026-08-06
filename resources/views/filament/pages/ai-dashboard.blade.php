<x-filament-panels::page>
    {{-- Middle widgets: Action Queue Table --}}
    @if ($widgets = $this->getMiddleWidgets())
        <x-filament-widgets::widgets
            :columns="$this->getMiddleWidgetsColumns()"
            :data="$this->getWidgetData()"
            :widgets="$widgets"
            class="fi-page-widgets"
        />
    @endif
</x-filament-panels::page>
