@php
    use App\Enums\CrmLeadField;
    use App\Enums\PhoneStatus;
    use App\Models\LeadCustomField;
    use Illuminate\Support\Str;

    /** @var \App\Models\LeadImport|null $import */
    /** @var \App\DTOs\Lead\CsvAnalysisResult|null $analysis */
    /** @var array $rows */
    /** @var array $coreColumns */
    /** @var array $customColumns */

    $hiddenCoreColumns ??= [];

    // Layout and colour are expressed inline rather than with utility classes.
    // The admin panel loads a pre-built vendor theme whose CSS was compiled from
    // that package's own templates, so any class not already used by Filament is
    // absent from the stylesheet and silently does nothing.
    //
    // Tints derive from currentColor rather than Filament's --gray-* palette,
    // because those palette values are fixed and do not invert for dark mode,
    // whereas currentColor is already the correct text colour for either theme.
    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
    $tint = 'color-mix(in srgb, currentColor 4%, transparent)';

    $th = "padding:.5rem .75rem; text-align:left; font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; {$muted}";
    $td = "padding:.5rem .75rem; vertical-align:top; font-size:.8125rem; border-top:1px solid {$hairline};";
@endphp

@if (! $import || ! $analysis)
    <p style="font-size:.875rem; {{ $muted }}">Upload a lead export on the first step to see a preview.</p>
@else
    @php
        $stats = [
            ['label' => 'Total rows', 'value' => $analysis->totalRows, 'color' => 'gray'],
            ['label' => 'Will import', 'value' => $analysis->importableRows(), 'color' => 'success'],
            ['label' => 'Blank rows', 'value' => $analysis->blankRows, 'color' => 'gray'],
            ['label' => 'Repeats in file', 'value' => $analysis->duplicateRows, 'color' => $analysis->duplicateRows > 0 ? 'info' : 'gray'],
            ['label' => 'Phones to review', 'value' => $analysis->needsReviewPhoneRows, 'color' => $analysis->needsReviewPhoneRows > 0 ? 'warning' : 'gray'],
            ['label' => 'Will fail', 'value' => $analysis->invalidPhoneRows, 'color' => $analysis->invalidPhoneRows > 0 ? 'danger' : 'gray'],
        ];
    @endphp

    <div style="display:flex; flex-direction:column; gap:1.25rem;">

        {{-- ── Headline numbers ─────────────────────────────────── --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(9rem, 1fr)); gap:.75rem;">
            @foreach ($stats as $stat)
                <div style="border:1px solid {{ $hairline }}; border-radius:.625rem; padding:.75rem .875rem; display:flex; flex-direction:column; gap:.4rem;">
                    <span style="font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; {{ $muted }}">
                        {{ $stat['label'] }}
                    </span>
                    <span>
                        <x-filament::badge :color="$stat['color']" size="lg">
                            {{ number_format($stat['value']) }}
                        </x-filament::badge>
                    </span>
                </div>
            @endforeach
        </div>

        {{-- ── Blocking problem ─────────────────────────────────── --}}
        @if ($analysis->missingRequiredFields !== [])
            <div style="border:1px solid {{ $hairline }}; border-left:3px solid var(--danger-500); border-radius:.625rem; padding:.875rem 1rem;">
                <div style="display:flex; align-items:center; gap:.5rem;">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width:1.125rem; height:1.125rem; color:var(--danger-500);" />
                    <strong style="font-size:.875rem;">A required field is not mapped</strong>
                </div>
                <p style="margin-top:.35rem; font-size:.8125rem; {{ $muted }}">
                    Nothing maps to <strong>{{ implode(', ', $analysis->missingRequiredFields) }}</strong>.
                    Go back to the mapping step — leads without this cannot be contacted.
                </p>
            </div>
        @endif

        {{-- ── Duplicate handling ───────────────────────────────── --}}
        <div style="border:1px solid {{ $hairline }}; border-radius:.625rem; padding:.875rem 1rem;">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; font-size:.8125rem;">
                <span style="{{ $muted }}">Duplicates matched on</span>

                @foreach ($import->duplicate_match_fields ?? [] as $matchField)
                    <x-filament::badge color="primary" size="sm">
                        {{ CrmLeadField::tryFrom($matchField)?->getLabel() ?? $matchField }}
                    </x-filament::badge>
                @endforeach

                <span style="{{ $muted }}">within</span>
                <x-filament::badge color="info" size="sm">{{ $import->clinic?->name ?? 'this clinic' }}</x-filament::badge>

                <x-filament::icon icon="heroicon-m-arrow-right" style="width:.875rem; height:.875rem; {{ $muted }}" />

                <x-filament::badge :color="$import->duplicate_strategy?->getColor() ?? 'gray'" size="sm">
                    {{ $import->duplicate_strategy?->getLabel() }}
                </x-filament::badge>
            </div>

            <p style="margin-top:.5rem; font-size:.75rem; {{ $muted }}">
                {{ $import->duplicate_strategy?->getDescription() }}
            </p>
        </div>

        {{-- ── Row preview ──────────────────────────────────────── --}}
        <div>
            <div style="display:flex; flex-wrap:wrap; align-items:baseline; gap:.5rem; margin-bottom:.5rem;">
                <strong style="font-size:.875rem;">First {{ count($rows) }} {{ Str::plural('row', count($rows)) }}</strong>
                <span style="font-size:.75rem; {{ $muted }}">
                    struck-through grey is the original value; below it is what will be saved
                </span>
            </div>

            @if ($hiddenCoreColumns !== [])
                <p style="font-size:.75rem; margin-bottom:.5rem; {{ $muted }}">
                    {{ count($hiddenCoreColumns) }} Facebook ID {{ Str::plural('column', count($hiddenCoreColumns)) }}
                    ({{ Str::limit(implode(', ', $hiddenCoreColumns), 80) }})
                    are imported but hidden here — a nineteen-digit number is not something you can check by eye.
                </p>
            @endif

            {{-- Horizontal scroll only: all preview rows stay visible, which
                 avoids a sticky header needing an opaque background that no
                 theme-agnostic variable can supply. --}}
            <div style="overflow-x:auto; border:1px solid {{ $hairline }}; border-radius:.625rem;">
                <table style="width:100%; border-collapse:collapse; font-size:.8125rem;">
                    <thead>
                        <tr style="background:{{ $tint }};">
                            <th style="{{ $th }}">#</th>

                            @foreach ($coreColumns as $label)
                                <th style="{{ $th }}">{{ $label }}</th>
                            @endforeach

                            @foreach ($customColumns as $label)
                                <th style="{{ $th }} color:var(--primary-600);"
                                    title="{{ LeadCustomField::humanizeLabel($label) }}">
                                    {{ Str::limit(LeadCustomField::humanizeLabel($label), 26) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td style="{{ $td }} {{ $muted }} font-variant-numeric:tabular-nums;">{{ $row['number'] }}</td>

                                @foreach ($coreColumns as $key => $label)
                                    @php
                                        $value = $row['attributes'][$key] ?? null;
                                        $rawValue = $key === 'phone' ? ($row['attributes']['phone_raw'] ?? null) : null;
                                        $phoneResult = $key === 'phone' ? $row['phone'] : null;
                                        $display = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
                                    @endphp

                                    <td style="{{ $td }}">
                                        @if ($display === '')
                                            <span style="opacity:.35;">—</span>
                                        @else
                                            <div style="font-weight:500; white-space:nowrap;" title="{{ $display }}">
                                                {{ Str::limit($display, 32) }}
                                            </div>
                                        @endif

                                        @if ($rawValue && $rawValue !== $value)
                                            <div style="font-size:.6875rem; text-decoration:line-through; white-space:nowrap; {{ $muted }}">
                                                {{ Str::limit($rawValue, 32) }}
                                            </div>
                                        @endif

                                        @if ($phoneResult && $phoneResult->status !== PhoneStatus::Valid)
                                            <div style="margin-top:.25rem;">
                                                <x-filament::badge :color="$phoneResult->status->getColor()" size="xs">
                                                    {{ $phoneResult->status->getLabel() }}
                                                </x-filament::badge>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach

                                @foreach ($customColumns as $label)
                                    @php
                                        $answer = collect($row['custom'])->firstWhere('label', $label);
                                        $separator = config('leads.csv.multi_value_separator', '|');
                                        $values = $answer
                                            ? (Str::contains($answer['value'], $separator)
                                                ? explode($separator, $answer['value'])
                                                : [$answer['value']])
                                            : [];
                                    @endphp

                                    <td style="{{ $td }}">
                                        @forelse ($values as $value)
                                            <div style="margin-bottom:.2rem;">
                                                <x-filament::badge color="primary" size="xs">
                                                    {{ Str::limit(LeadCustomField::humanizeValue($value), 24) }}
                                                </x-filament::badge>
                                            </div>
                                        @empty
                                            <span style="opacity:.35;">—</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
