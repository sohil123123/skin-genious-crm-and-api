{{--
    Calls table "Handled by" cell: who took the call, and which provider
    identity it came in under.

    Mirrors the Ads table's owner cell. A view column for the same reason:
    TextColumn's description slot is escaped with e() and its html() state runs
    through Str::sanitizeHtml(), so neither can carry an icon or this layout.

    An unmapped agent is stated rather than left blank. A blank cell reads as
    "no agent was involved"; what it actually means is that a number placed the
    call and nobody has told the CRM whose number it is — which is a job on the
    Agent Mapping screen, and silently wrong performance figures until someone
    does it.
--}}
@php
    /** @var \App\Models\Call $record */
    $record = $getRecord();

    $agent = $record->agent;

    // Deterministic tint, so the same person always draws the same colour and
    // stays recognisable down the column.
    $palette = ['primary', 'success', 'warning', 'danger', 'info'];
    $tint = $agent ? $palette[crc32((string) $agent->id) % count($palette)] : 'gray';

    $initials = $agent
        ? \Illuminate\Support\Str::of($agent->name ?? '')
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('')
        : '';

    // What the provider called them, when the CRM has no user of its own.
    $providerName = $record->employee_name ?: $record->employee_code;
    $agentPhone = $record->employee_phone_normalized ?: $record->employee_phone;
@endphp

<div class="fi-ta-col">
    <div class="sgc-own">
        <span class="sgc-own-avatar sgc-own-avatar-{{ $tint }}">
            {{ $initials !== '' ? $initials : '?' }}
        </span>

        <div class="sgc-own-body">
            <span @class(['sgc-own-name', 'sgc-muted' => ! $agent]) title="{{ $agent?->name ?: $providerName }}">
                @if ($agent)
                    {{ $agent->name ?: 'Unnamed user' }}
                @elseif ($providerName)
                    {{ $providerName }}
                @else
                    Unassigned
                @endif
            </span>

            @if (! $agent && ($providerName || $agentPhone))
                {{-- The row that turns an invisible data gap into a task. --}}
                <span class="sgc-own-row sgc-warn" title="This provider number is not mapped to a CRM user, so the call is attributed to nobody">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                    Not mapped
                </span>
            @endif

            @if ($agentPhone)
                <span class="sgc-own-row sgc-muted sgc-num" title="Number the provider dialled">
                    <x-filament::icon icon="heroicon-m-device-phone-mobile" />
                    {{ $agentPhone }}
                </span>
            @endif

            <span class="sgc-own-row sgc-muted">
                <x-filament::icon :icon="$record->provider?->getIcon() ?? 'heroicon-m-signal'" />
                {{ $record->provider?->getLabel() }}
                @if ($record->employee_code)
                    · {{ $record->employee_code }}
                @endif
            </span>
        </div>
    </div>
</div>
