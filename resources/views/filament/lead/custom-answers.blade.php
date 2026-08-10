@php
    /** @var \App\Models\Lead $record */
    $answers = $record->loadMissing('fieldValues.customField')->custom_answers;

    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
@endphp

@if ($answers === [])
    <p style="font-size:.875rem; {{ $muted }}">
        This lead form did not collect any additional questions.
    </p>
@else
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(16rem, 1fr)); gap:.75rem;">
        @foreach ($answers as $answer)
            <div style="border:1px solid {{ $hairline }}; border-radius:.625rem; padding:.75rem .875rem;">
                <div style="font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; {{ $muted }}">
                    {{ $answer['label'] }}
                </div>

                <div style="margin-top:.5rem; display:flex; flex-wrap:wrap; gap:.3rem;">
                    @forelse ($answer['values'] as $value)
                        {{-- Multiple-choice answers render one badge each, so a
                             six-answer selection stays readable. --}}
                        <x-filament::badge color="primary" size="sm">{{ $value }}</x-filament::badge>
                    @empty
                        <span style="font-size:.875rem; opacity:.35;">—</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endif
