{{--
    What the AI made of the call that raised this action.

    One partial for both queues. The patient card and the lead card are already
    deliberate mirrors of each other — same grid, same outcome buttons — and a
    staff member moving between the Patients and Meta Leads tabs must not have
    to read the same information in two different shapes.

    Shown only where a call actually raised or informed the action, so the 90%
    of cards built from appointment dates and package history are unchanged.

    Inline styles rather than Tailwind utilities, matching the cards around it:
    this panel registers no viteTheme, so utility class names resolve to
    nothing here.

    @param \App\Models\AiActionLog|\App\Models\LeadActionLog $record
--}}
@php
    $call = $record->relatedCall;
    $analysis = $call?->currentAnalysis;

    // The signals stored on the action itself, not re-read from the call. This
    // is the basis the engine actually scored on at 07:00 — re-reading the call
    // now would show today's signals against yesterday's decision, which is the
    // one thing an explanation must never do.
    $basis = collect($record->call_signals ?? [])
        ->map(fn (array $signal): ?array => ($key = \App\Enums\Call\CallSignalKey::tryFrom($signal['key'] ?? ''))
            ? ['key' => $key, 'confidence' => $signal['confidence'] ?? null]
            : null)
        ->filter()
        ->values();
@endphp

@if ($call)
    <div style="border: 1px solid #ddd6fe; background: #faf5ff; border-radius: 0.375rem; padding: 0.5rem;" class="dark:!border-purple-400/20 dark:!bg-purple-400/5">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.375rem; margin-bottom: 0.25rem;">
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                <x-filament::icon icon="heroicon-m-sparkles" class="h-3 w-3 text-purple-600 dark:text-purple-400" style="flex-shrink: 0;" />
                <span style="font-size: 0.5625rem; font-weight: 700; color: #7e22ce; text-transform: uppercase; letter-spacing: 0.05em;" class="dark:!text-purple-300">
                    AI analysis of the call
                </span>
            </div>

            {{-- Straight to the recording and the full transcript. The card
                 carries the conclusion; anybody who wants to hear it said is
                 one click away rather than searching the call list. --}}
            <a
                href="{{ \App\Filament\Resources\Calls\CallResource::getUrl('view', ['record' => $call]) }}"
                style="font-size: 0.5625rem; font-weight: 600; text-decoration: none; white-space: nowrap;"
                class="text-primary-600 dark:text-primary-400 hover:underline"
            >
                Open call &rarr;
            </a>
        </div>

        <div style="font-size: 0.5625rem; color: #9ca3af; margin-bottom: 0.25rem;">
            {{ $call->direction?->getLabel() }}
            &middot; {{ $call->duration_for_humans ?? 'no duration' }}
            &middot; {{ $call->started_at?->timezone(app_timezone())->format(app_datetime_format()) }}
        </div>

        @if (filled($analysis?->summary))
            <p style="font-size: 0.6875rem; line-height: 1.45; margin: 0 0 0.375rem; color: #374151; overflow-wrap: anywhere;" class="dark:!text-gray-300">
                {{ $analysis->summary }}
            </p>
        @endif

        @if ($basis->isNotEmpty())
            <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; margin-bottom: 0.25rem;">
                @foreach ($basis as $signal)
                    <x-filament::badge :color="$signal['key']->type()->getColor()" size="sm">
                        {{ $signal['key']->getLabel() }}@if ($signal['confidence'] !== null) · {{ round($signal['confidence'] * 100) }}%@endif
                    </x-filament::badge>
                @endforeach
            </div>
        @endif

        @if (filled($analysis?->next_best_action))
            <div style="font-size: 0.625rem; display: flex; gap: 0.25rem;">
                <strong style="color: #7e22ce; flex-shrink: 0;" class="dark:!text-purple-300">Next step:</strong>
                <span style="color: #6b7280;" class="dark:!text-gray-400">{{ $analysis->next_best_action }}</span>
            </div>
        @endif

        @if ($basis->isNotEmpty())
            {{-- Said plainly, because a badge that looks like a fact invites
                 being read as one. These are the model's reading of a
                 transcript, matched against a fixed vocabulary — not something
                 anybody wrote down in the CRM. --}}
            <p style="font-size: 0.5625rem; color: #a1a1aa; margin: 0.25rem 0 0; line-height: 1.4;">
                The model's reading of the transcript, matched to the vocabulary the action engine scores on.
            </p>
        @endif
    </div>
@endif
