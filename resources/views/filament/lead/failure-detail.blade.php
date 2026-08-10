@php
    use Illuminate\Support\Str;

    /** @var \App\Models\LeadImportFailure $record */
    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
    $tint = 'color-mix(in srgb, currentColor 4%, transparent)';
@endphp

<div style="display:flex; flex-direction:column; gap:1rem;">

    {{-- Reason --}}
    <div style="border:1px solid {{ $hairline }}; border-left:3px solid var(--danger-500); border-radius:.625rem; padding:.875rem 1rem;">
        <x-filament::badge :color="$record->reason_code->getColor()" :icon="$record->reason_code->getIcon()" size="sm">
            {{ $record->reason_code->getLabel() }}
        </x-filament::badge>

        <p style="margin-top:.5rem; font-size:.875rem;">{{ $record->reason }}</p>
    </div>

    {{-- Field errors --}}
    @if (is_array($record->errors) && $record->errors !== [])
        <div>
            <strong style="font-size:.875rem;">Field errors</strong>
            <ul style="margin-top:.35rem; display:flex; flex-direction:column; gap:.25rem; list-style:none; padding:0;">
                @foreach ($record->errors as $field => $messages)
                    <li style="font-size:.8125rem;">
                        <code style="font-size:.75rem; font-weight:600;">{{ $field }}</code>
                        <span style="{{ $muted }}"> — {{ implode(' ', (array) $messages) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- The original row, so the offending cell can be identified --}}
    <div>
        <strong style="font-size:.875rem;">Original row</strong>

        <div style="margin-top:.35rem; overflow-x:auto; border:1px solid {{ $hairline }}; border-radius:.625rem;">
            <table style="width:100%; border-collapse:collapse; font-size:.8125rem;">
                <tbody>
                    @foreach (($record->raw_row ?? []) as $column => $value)
                        <tr style="border-top:1px solid {{ $hairline }};">
                            <td style="width:38%; padding:.4rem .75rem; vertical-align:top; font-size:.75rem; background:{{ $tint }}; {{ $muted }}">
                                {{ Str::limit($column, 46) }}
                            </td>
                            <td style="padding:.4rem .75rem; vertical-align:top;">
                                @if ((string) $value === '')
                                    <span style="opacity:.35;">—</span>
                                @else
                                    {{ Str::limit((string) $value, 120) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
