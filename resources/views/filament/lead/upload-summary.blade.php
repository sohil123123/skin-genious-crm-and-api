@php
    use Illuminate\Support\Str;

    /** @var \App\Models\LeadImport|null $import */
    $analysis = $import?->analysis ? \App\DTOs\Lead\CsvAnalysisResult::fromArray($import->analysis) : null;

    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
@endphp

@if ($import && $analysis)
    <div style="border:1px solid {{ $hairline }}; border-radius:.625rem; padding:1rem;">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <x-filament::icon icon="heroicon-o-document-magnifying-glass" style="width:1.125rem; height:1.125rem; color:var(--primary-500);" />
            <strong style="font-size:.875rem;">Detected file format</strong>
        </div>

        <p style="margin-top:.25rem; font-size:.75rem; {{ $muted }}">
            Facebook exports are UTF-16 and tab-separated. If any of this looks wrong, the file is probably not a standard export.
        </p>

        <div style="margin-top:.875rem; display:grid; grid-template-columns:repeat(auto-fit, minmax(8rem, 1fr)); gap:.875rem;">
            @foreach ([
                'Encoding' => $analysis->encoding . ($analysis->hasBom ? ' + BOM' : ''),
                'Delimiter' => $analysis->delimiterLabel(),
                'Columns' => number_format(count($analysis->headers)),
                'Data rows' => number_format($analysis->totalRows),
                'Blank rows' => number_format($analysis->blankRows),
                'Repeats in file' => number_format($analysis->duplicateRows),
            ] as $label => $value)
                <div>
                    <div style="font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; {{ $muted }}">
                        {{ $label }}
                    </div>
                    <div style="margin-top:.15rem; font-size:.875rem; font-weight:600;">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        @if ($analysis->invalidPhoneRows > 0 || $analysis->needsReviewPhoneRows > 0)
            <div style="margin-top:.875rem; display:flex; flex-wrap:wrap; gap:.4rem;">
                @if ($analysis->needsReviewPhoneRows > 0)
                    <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle" size="sm">
                        {{ $analysis->needsReviewPhoneRows }}
                        {{ Str::plural('number', $analysis->needsReviewPhoneRows) }} will be repaired and flagged
                    </x-filament::badge>
                @endif

                @if ($analysis->invalidPhoneRows > 0)
                    <x-filament::badge color="danger" icon="heroicon-m-x-circle" size="sm">
                        {{ $analysis->invalidPhoneRows }}
                        {{ Str::plural('row', $analysis->invalidPhoneRows) }} will fail — no usable phone number
                    </x-filament::badge>
                @endif
            </div>
        @endif
    </div>
@endif
