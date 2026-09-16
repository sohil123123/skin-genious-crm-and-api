{{--
    A stat's description as a row of badges.

    Rendered to a string and handed to Stat::description() as an HtmlString —
    Filament prints the description with {{ }}, which leaves an Htmlable alone,
    so this is the supported way to put markup there without a custom widget
    view.

    Real <x-filament::badge> components rather than hand-rolled pills: they
    already carry the panel's colours, radius and dark-mode handling, and this
    panel ships no compiled Tailwind of its own, so utility classes written here
    would resolve to nothing.

    @param array<int, array{label: string, value: string, color?: string}> $items
--}}
<span style="display: inline-flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
    @foreach ($items as $item)
        <x-filament::badge
            :color="$item['color'] ?? 'gray'"
            :icon="$item['icon'] ?? null"
            size="sm"
        >
            {{ $item['value'] }} {{ $item['label'] }}
        </x-filament::badge>
    @endforeach
</span>
