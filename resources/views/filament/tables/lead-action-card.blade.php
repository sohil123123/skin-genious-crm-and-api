@php
    use App\Models\LeadActionLog;
    use App\Models\LeadCustomField;
    use Illuminate\Support\Str;

    /** @var \App\Models\LeadActionLog $record */
    $record = $getRecord();
    $lead = $record->lead;

    // Colour comes from currentColor-derived tints rather than fixed hex
    // values: the panel serves a pre-built vendor theme, so utility classes
    // this application does not already use are absent from the compiled CSS,
    // and a hard-coded white background would stay white in dark mode.
    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
    $tint = 'color-mix(in srgb, currentColor 4%, transparent)';

    $stripe = match ($record->priority_color) {
        'danger' => 'var(--danger-500)',
        'warning' => 'var(--warning-500)',
        'info' => 'var(--info-500)',
        default => 'var(--gray-400)',
    };

    $name = $lead?->full_name ?: ($lead?->phone ?: 'Unnamed lead');
    $initials = Str::of($name)->explode(' ')->filter()->take(2)
        ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->join('') ?: 'L';

    // The lead's own form answers are what make the script specific, so they
    // are surfaced on the card rather than buried behind the detail toggle.
    $answers = collect($lead?->custom_answers ?? [])->take(3);
@endphp

<div style="position:relative; border:1px solid {{ $hairline }}; border-radius:.75rem; overflow:hidden; height:100%;">

    <div style="position:absolute; left:0; top:0; bottom:0; width:4px; background:{{ $stripe }};"></div>

    <div style="padding:1rem 1rem .875rem 1.25rem; display:flex; flex-direction:column; gap:.625rem;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-start; gap:.625rem;">
            <div style="width:40px; height:40px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.75rem; background:{{ $tint }}; border:1px solid {{ $hairline }};">
                {{ $initials }}
            </div>

            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:.375rem; flex-wrap:wrap;">
                    <span style="font-size:.875rem; font-weight:700;">{{ Str::limit($name, 26) }}</span>
                    <x-filament::badge :color="$record->priority_color" size="sm">
                        {{ $record->priority_score }}/100
                    </x-filament::badge>
                </div>

                <div style="display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; margin-top:.25rem;">
                    <x-filament::badge color="primary" size="sm" icon="heroicon-m-megaphone">Lead</x-filament::badge>

                    <x-filament::badge :color="$record->category_color" size="sm">
                        {{ $record->category_label }}
                    </x-filament::badge>

                    @if ($record->matched_user_id)
                        {{-- The single most important thing a caller can know
                             before dialling: this person is already a patient. --}}
                        <x-filament::badge color="warning" size="sm" icon="heroicon-m-identification">
                            Existing Patient
                        </x-filament::badge>
                    @endif
                </div>

                <div style="font-size:.625rem; font-weight:600; margin-top:.25rem; {{ $muted }}">
                    {{ $record->trigger_label }}
                </div>
            </div>
        </div>

        {{-- Channel / time / phone --}}
        <div style="display:flex; align-items:center; gap:.3rem; flex-wrap:wrap;">
            @if ($record->recommended_channel === 'whatsapp')
                <x-filament::badge color="success" size="sm" icon="heroicon-m-chat-bubble-left-right">WhatsApp</x-filament::badge>
            @elseif ($record->recommended_channel === 'call')
                <x-filament::badge color="info" size="sm" icon="heroicon-m-phone">Call</x-filament::badge>
            @else
                <x-filament::badge color="warning" size="sm" icon="heroicon-m-chat-bubble-left">SMS</x-filament::badge>
            @endif

            @if ($record->recommended_time)
                <x-filament::badge color="gray" size="sm" icon="heroicon-m-clock">{{ $record->recommended_time }}</x-filament::badge>
            @endif

            @if ($lead?->phone)
                <x-filament::badge color="gray" size="sm">{{ $lead->phone }}</x-filament::badge>
            @endif
        </div>

        {{-- What they told the form --}}
        @if ($answers->isNotEmpty())
            <div style="display:flex; flex-wrap:wrap; gap:.25rem;">
                @foreach ($answers as $answer)
                    @foreach (($answer['values'] ?? []) as $value)
                        <x-filament::badge color="gray" size="xs">
                            {{ Str::limit($value, 26) }}
                        </x-filament::badge>
                    @endforeach
                @endforeach
            </div>
        @endif

        {{-- Why today --}}
        <div style="border:1px solid {{ $hairline }}; border-left:3px solid {{ $stripe }}; border-radius:.5rem; padding:.5rem .625rem; background:{{ $tint }};">
            <p style="font-size:.6875rem; line-height:1.5; margin:0;">
                <strong>Why today:</strong>
                <span style="{{ $muted }}">{{ $record->reason }}</span>
            </p>
        </div>

        {{-- Details --}}
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open"
                style="display:inline-flex; align-items:center; gap:.25rem; font-size:.625rem; font-weight:600; background:none; border:none; cursor:pointer; padding:0; color:var(--primary-600);">
                <x-filament::icon icon="heroicon-m-chevron-right" style="width:.75rem; height:.75rem;" x-bind:style="open && 'transform: rotate(90deg)'" />
                <span x-text="open ? 'Hide details' : 'Show details'"></span>
            </button>

            <div x-show="open" x-collapse style="margin-top:.5rem;">
                <div style="display:flex; flex-direction:column; gap:.5rem; padding-top:.5rem; border-top:1px solid {{ $hairline }};">
                    @if ($record->goal)
                        <div style="font-size:.625rem;">
                            <strong>🎯 Goal:</strong> <span style="{{ $muted }}">{{ $record->goal }}</span>
                        </div>
                    @endif

                    @if ($record->suggested_message)
                        <div style="border:1px dashed {{ $hairline }}; border-radius:.375rem; padding:.5rem;">
                            <div style="font-size:.5625rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.2rem; {{ $muted }}">
                                💬 Suggested script
                            </div>
                            <p style="font-size:.6875rem; font-style:italic; line-height:1.45; margin:0;">
                                "{{ $record->suggested_message }}"
                            </p>
                        </div>
                    @endif

                    @if ($record->avoid_notes)
                        <div style="font-size:.625rem;">
                            <strong style="color:var(--danger-600);">⚠️ Avoid:</strong>
                            <span style="{{ $muted }}">{{ $record->avoid_notes }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Outcome --}}
        @if ($record->staff_outcome)
            <div style="padding:.4375rem .5rem; border-radius:.375rem; border:1px solid {{ $hairline }}; background:{{ $tint }};">
                <div style="display:flex; align-items:center; gap:.375rem; flex-wrap:wrap;">
                    <x-filament::icon icon="heroicon-m-check-circle" style="width:.875rem; height:.875rem; color:var(--success-500); flex-shrink:0;" />
                    <span style="font-size:.6875rem; font-weight:700; color:var(--success-600);">
                        {{ LeadActionLog::outcomeOptions()[$record->staff_outcome] ?? $record->staff_outcome }}
                    </span>

                    @if ($record->outcome_at)
                        {{-- Time alone is ambiguous once a card is more than a day
                             old, so the date is shown unless it was logged today. --}}
                        <span style="font-size:.625rem; {{ $muted }}" title="{{ $record->outcome_at->format('D, j M Y g:i A') }}">
                            {{ $record->outcome_at->isToday()
                                ? $record->outcome_at->format('g:i A')
                                : $record->outcome_at->format('j M, g:i A') }}
                        </span>
                    @endif
                </div>

                @if (filled($record->outcome_notes))
                    {{-- What staff actually typed is the most useful part of a
                         completed card — a callback time, an objection — so it is
                         shown in full rather than truncated. --}}
                    <div style="display:flex; gap:.375rem; margin-top:.375rem; padding-top:.375rem; border-top:1px solid {{ $hairline }};">
                        <x-filament::icon icon="heroicon-m-chat-bubble-bottom-center-text" style="width:.75rem; height:.75rem; flex-shrink:0; margin-top:.0625rem; {{ $muted }}" />
                        <p style="font-size:.6875rem; line-height:1.45; margin:0; white-space:pre-line; overflow-wrap:anywhere;">{{ $record->outcome_notes }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
