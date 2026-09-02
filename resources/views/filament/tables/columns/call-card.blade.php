{{--
    Calls table "Call" cell: the whole conversation as one card.

    Follows the pattern the Ads table uses for its listing card — a view column
    rather than a strip of text columns, because judging a call is a judgement
    about the whole thing at once: who rang, whether anyone spoke to them, how
    long for, and what came of it. Reassembling that from six narrow columns is
    what the previous layout asked a receptionist to do.

    A ViewColumn rather than a TextColumn: TextColumn runs html() state through
    Str::sanitizeHtml() (which strips <svg>) and escapes its description slot
    with e(), so neither can carry icons or this layout.

    Presentation lives in the `.sgc-*` rules injected by AdminPanelProvider, not
    in Tailwind utilities: this panel registers no viteTheme, so no compiled
    Tailwind reaches it and utility class names resolve to nothing.
--}}
@php
    /** @var \App\Models\Call $record */
    $record = $getRecord();

    $direction = $record->direction;
    $status = $record->call_status;

    // The glyph carries direction and outcome together, because that pairing is
    // the first thing read: an arrow in is a call we received, and its colour
    // says whether anybody actually spoke to them.
    $missed = $status?->isUnanswered() ?? false;

    $tone = match (true) {
        $missed => 'bad',
        $record->is_connected => 'ok',
        default => 'idle',
    };

    $icon = match (true) {
        $missed && $direction === \App\Enums\Call\CallDirection::Incoming => 'heroicon-m-phone-x-mark',
        $direction === \App\Enums\Call\CallDirection::Incoming => 'heroicon-m-arrow-down-left',
        $direction === \App\Enums\Call\CallDirection::Outgoing => 'heroicon-m-arrow-up-right',
        default => 'heroicon-m-phone',
    };

    $name = $record->customer_name;
    $phone = $record->client_phone_normalized ?: $record->client_phone;

    // Only surfaced when it needs a human. A matched call showing a "Matched"
    // pill on every row would be noise on the 95% of rows that are fine.
    $needsMatch = $record->matching_status?->needsAttention() ?? false;

    $outcome = $record->crm_outcome ?: $record->provider_crm_status;

    $duration = $record->duration_for_humans;
@endphp

<div class="fi-ta-col">
    <div class="sgc">
        <span class="sgc-glyph sgc-glyph--{{ $tone }}" title="{{ $direction?->getLabel() }} · {{ $status?->getLabel() }}">
            <x-filament::icon :icon="$icon" />
        </span>

        <div class="sgc-body">
            <div class="sgc-head">
                {{--
                    A span, not an anchor: the table sets a record URL, so
                    Filament has already wrapped this cell in an <a>, and a
                    second anchor inside it would be invalid markup.
                --}}
                <span class="sgc-name" title="{{ $name }}">{{ $name }}</span>

                @if ($needsMatch)
                    <x-filament::badge
                        :color="$record->matching_status?->getColor()"
                        :title="$record->matching_status === \App\Enums\Call\CallMatchingStatus::Ambiguous
                            ? 'This number matches more than one record'
                            : 'Not linked to a patient or lead'"
                    >
                        {{ $record->matching_status?->getLabel() }}
                    </x-filament::badge>
                @endif
            </div>

            <div class="sgc-meta">
                <span class="sgc-meta-item sgc-num" title="Caller number">
                    <x-filament::icon icon="heroicon-m-phone" />
                    {{ $phone ?: 'Number withheld' }}
                </span>

                @if ($record->virtual_number_normalized)
                    <span class="sgc-sep">|</span>
                    {{--
                        Deliberately not the word "to". Exotel's own Inbox
                        labels the *agent* as "To", while its webhook labels the
                        *Exophone* as "To" — so writing "to <exophone>" here
                        read as a contradiction against the screen next door.
                        This is the clinic line the caller rang; who answered it
                        is the Handled by column.
                    --}}
                    <span class="sgc-meta-item sgc-muted" title="Clinic line the caller rang (Exophone)">
                        <x-filament::icon icon="heroicon-m-building-office-2" />
                        {{ $record->virtual_number_normalized }}
                    </span>
                @endif
            </div>

            <div class="sgc-meta">
                <x-filament::badge :color="$status?->getColor()">
                    {{ $status?->getLabel() }}
                </x-filament::badge>

                @if ($duration)
                    <span class="sgc-sep">|</span>
                    <span class="sgc-meta-item" title="Talk time">
                        <x-filament::icon icon="heroicon-m-clock" />
                        {{ $duration }}
                    </span>
                @endif

                <span class="sgc-sep">|</span>

                <span class="sgc-meta-item sgc-muted" title="{{ $record->started_at?->timezone(app_timezone())->format(app_datetime_format()) }}">
                    {{ $record->started_at?->diffForHumans() ?? 'No start time' }}
                </span>
            </div>

            <div class="sgc-foot">
                @if ($outcome)
                    <x-filament::badge
                        :color="$record->crm_outcome ? 'primary' : 'gray'"
                        :title="$record->crm_outcome ? 'Recorded in the CRM' : 'From the provider app'"
                    >
                        {{ $outcome }}
                    </x-filament::badge>
                @endif

                {{-- No recording badge here: the Recording column next door is
                     a working player, and a badge restating what it already
                     shows was the first thing that read as clutter. --}}
                @if ($record->transcription_status === \App\Enums\Call\TranscriptionStatus::Completed)
                    <x-filament::badge color="info" icon="heroicon-m-document-text" title="Transcribed">
                        Text
                    </x-filament::badge>
                @endif

                @if ($record->analysis_status === \App\Enums\Call\CallAnalysisStatus::Completed)
                    <x-filament::badge color="info" icon="heroicon-m-sparkles" title="AI analysed">
                        AI
                    </x-filament::badge>
                @endif

                @if ($record->follow_up_required && $record->follow_up_completed_at === null)
                    <x-filament::badge
                        color="warning"
                        icon="heroicon-m-flag"
                        :title="$record->follow_up_at
                            ? 'Follow-up due ' . $record->follow_up_at->timezone(app_timezone())->format(app_datetime_format())
                            : 'Follow-up needed'"
                    >
                        Follow-up
                    </x-filament::badge>
                @endif
            </div>
        </div>
    </div>
</div>
