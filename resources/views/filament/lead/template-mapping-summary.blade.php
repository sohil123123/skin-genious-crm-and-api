@php
    use App\DTOs\Lead\ColumnMappingDto;
    use App\Models\LeadCustomField;
    use Illuminate\Support\Str;

    /** @var \App\Models\LeadMappingTemplate $record */
    $mapping = $record->mapping ?? [];

    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
@endphp

@if ($mapping === [])
    <p style="font-size:.875rem; {{ $muted }}">This template has no saved column decisions.</p>
@else
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(22rem, 1fr)); gap:.4rem;">
        @foreach ($mapping as $column => $definition)
            @php
                $dto = ColumnMappingDto::fromArray(is_array($definition) ? $definition : ['csv_column' => $column]);
            @endphp

            <div style="display:flex; align-items:center; gap:.5rem; padding:.4rem .625rem; border:1px solid {{ $hairline }}; border-radius:.5rem;">
                <span style="flex:1 1 0; min-width:0; font-size:.75rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; {{ $muted }}"
                      title="{{ $dto->csvColumn }}">
                    {{ $dto->csvColumn }}
                </span>

                <x-filament::icon icon="heroicon-m-arrow-right" style="width:.75rem; height:.75rem; flex:none; opacity:.4;" />

                <span style="flex:none;">
                    @if ($dto->isCore())
                        <x-filament::badge color="primary" size="xs">{{ $dto->coreField()?->getLabel() }}</x-filament::badge>
                    @elseif ($dto->isCustom())
                        <x-filament::badge color="info" size="xs">
                            {{ Str::limit(LeadCustomField::humanizeLabel($dto->resolvedCustomLabel()), 34) }}
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="gray" size="xs">Ignored</x-filament::badge>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
@endif
