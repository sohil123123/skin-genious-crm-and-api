{{--
    Calls table "Recording" cell: play the call without leaving the list.

    Reviewing calls means sampling a lot of them, and opening each one to press
    play made that a page load per call. The button here streams through the
    same authorised route the detail page uses — never the provider URL, which
    can carry a signed token, and never a storage path.

    Playback is handed to one shared controller (window.sgCallAudio, injected
    from AdminPanelProvider) rather than one <audio> element per row: a table of
    fifty rows would otherwise hold fifty media elements, and starting a second
    call would leave the first one talking over it.

    Only `isStored()` is consulted, never `fileExists()`. The latter stats the
    disk, and doing that once per row turns rendering a page into a few hundred
    filesystem calls. A file deleted outside the application is caught by the
    stream route, which 404s.
--}}
@php
    /** @var \App\Models\Call $record */
    $record = $getRecord();

    // Eager-loaded in CallResource::getEloquentQuery(), so this is not a query.
    $playable = $record->recordings->filter(fn ($recording): bool => $recording->isStored());
    $first = $playable->first();

    $pending = $record->recordings->isNotEmpty() && $playable->isEmpty();

    $seconds = $first?->duration_seconds ?? $record->talk_duration_seconds ?? $record->duration_seconds;

    $length = $seconds !== null
        ? sprintf('%d:%02d', intdiv((int) $seconds, 60), (int) $seconds % 60)
        : null;
@endphp

<div class="fi-ta-col">
    @if ($first !== null)
        <div class="sgc-rec">
            {{--
                A button element would be invalid here: the table sets a record
                URL, so Filament has already wrapped this cell in an <a>, and
                interactive elements nested in an anchor break the markup.
                `.prevent.stop` on both mousedown and click is what stops the
                row navigating — Livewire's wire:navigate starts on mousedown,
                so suppressing only the click is too late.
            --}}
            <span
                class="sgc-rec-btn"
                role="button"
                tabindex="0"
                title="Play this recording"
                data-sgc-audio="{{ route('calls.recordings.stream', ['recording' => $first->getKey()]) }}"
                onmousedown="event.preventDefault(); event.stopPropagation();"
                onclick="event.preventDefault(); event.stopPropagation(); window.sgCallAudio?.toggle(this);"
                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); event.stopPropagation(); window.sgCallAudio?.toggle(this); }"
            >
                <span class="sgc-rec-icon" data-sgc-icon="play">
                    <x-filament::icon icon="heroicon-m-play" />
                </span>
                <span class="sgc-rec-icon" data-sgc-icon="pause" hidden>
                    <x-filament::icon icon="heroicon-m-pause" />
                </span>
                <span class="sgc-rec-icon" data-sgc-icon="wait" hidden>
                    <x-filament::icon icon="heroicon-m-arrow-path" />
                </span>
            </span>

            <div class="sgc-rec-body">
                {{-- Shows elapsed time while playing, the total length otherwise. --}}
                <span class="sgc-rec-time" data-sgc-time>{{ $length ?? '—' }}</span>

                @if ($playable->count() > 1)
                    <span class="sgc-rec-note" title="This call has more than one recording; the detail page lists them all">
                        +{{ $playable->count() - 1 }} more
                    </span>
                @endif
            </div>
        </div>
    @elseif ($pending)
        {{-- The audio exists on the provider's server but is not ours yet. Worth
             stating rather than showing nothing, because it is a pipeline state
             somebody may need to chase. --}}
        <span class="sgc-rec-empty" title="{{ $record->recordings->first()?->error_message ?: 'Waiting for the download to finish' }}">
            <x-filament::icon icon="heroicon-m-cloud-arrow-down" />
            {{ $record->recording_status?->getLabel() ?? 'Pending' }}
        </span>
    @else
        <span class="sgc-rec-empty sgc-muted">—</span>
    @endif
</div>
