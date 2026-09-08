<div class="ai-action-card" style="position: relative; border-radius: 0.75rem; border: 1px solid rgba(229, 231, 235, 0.8); background: #ffffff; overflow: hidden; transition: box-shadow 0.2s ease, transform 0.15s ease;">

    @php
        $record = $getRecord();
        $priorityColor = match (true) {
            $record->priority_score >= 80 => 'danger',
            $record->priority_score >= 60 => 'warning',
            $record->priority_score >= 40 => 'info',
            default => 'gray',
        };
        $stripeColor = match ($priorityColor) {
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            default => '#9ca3af',
        };
        $categoryColor = match ($record->action_category) {
            'rescue' => 'danger',
            'conversion' => 'warning',
            'retention' => 'info',
            'capacity' => 'success',
            default => 'gray',
        };
        $avatarBg = match ($record->action_category) {
            'rescue' => 'background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca;',
            'conversion' => 'background: #fffbeb; color: #d97706; border: 1.5px solid #fde68a;',
            'retention' => 'background: #eff6ff; color: #2563eb; border: 1.5px solid #bfdbfe;',
            'capacity' => 'background: #f0fdf4; color: #16a34a; border: 1.5px solid #bbf7d0;',
            default => 'background: #f3f4f6; color: #6b7280; border: 1.5px solid #e5e7eb;',
        };
        $firstName = $record->client?->first_name ?? 'U';
        $lastName = $record->client?->last_name ?? '';
        $initials = strtoupper(substr($firstName, 0, 1) . ($lastName ? substr($lastName, 0, 1) : ''));
        $fullName = trim(($record->client?->first_name ?? 'Unknown') . ' ' . ($record->client?->last_name ?? ''));
    @endphp

    {{-- Priority stripe --}}
    <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 0.75rem 0 0 0.75rem; background: {{ $stripeColor }};"></div>

    <div style="padding: 1rem 1rem 0.75rem 1.25rem;">
        {{-- ── Header: Avatar + Name + Badges ── --}}
        <div style="display: flex; align-items: flex-start; gap: 0.625rem; margin-bottom: 0.625rem;">
            {{-- Avatar --}}
            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; letter-spacing: 0.02em; flex-shrink: 0; {{ $avatarBg }}">
                {{ $initials }}
            </div>

            {{-- Name + meta --}}
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; margin-bottom: 0.125rem;">
                    <span style="font-size: 0.875rem; font-weight: 700; color: #111827; line-height: 1.2;" class="dark:!text-white">
                        {{ $fullName }}
                    </span>
                    <x-filament::badge :color="$priorityColor" size="sm">
                        {{ $record->priority_score }}/100
                    </x-filament::badge>
                </div>
                <div style="display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;">
                    <x-filament::badge :color="$categoryColor" size="sm">
                        {{ $record->category_label }}
                    </x-filament::badge>
                    <span style="color: #d1d5db; font-size: 0.5rem;">•</span>
                    <span style="font-size: 0.625rem; font-weight: 600; color: #9ca3af;" class="dark:!text-gray-400">
                        {{ $record->trigger_label }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Channel + Time row ── --}}
        <div style="display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.5rem;">
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
            @if ($record->clinic)
                <x-filament::badge color="gray" size="sm" icon="heroicon-m-building-office-2">{{ $record->clinic->name }}</x-filament::badge>
            @endif
        </div>

        {{-- ── Why Today ── --}}
        <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid rgba(253, 230, 138, 0.5); border-radius: 0.5rem; padding: 0.5rem 0.625rem; margin-bottom: 0.5rem;">
            <p style="font-size: 0.6875rem; line-height: 1.5; margin: 0;">
                <strong style="color: #92400e;" class="dark:!text-amber-300">Why today:</strong>
                <span style="color: #78350f;" class="dark:!text-amber-200/80">{{ $record->reason }}</span>
            </p>
        </div>

        {{-- ── Expandable script & details ── --}}
        <div x-data="{ open: false }" style="margin-bottom: 0.375rem;">
            <button
                type="button"
                @click="open = !open"
                style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.625rem; font-weight: 600; background: none; border: none; cursor: pointer; padding: 0;"
                class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
            >
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3 transition-transform duration-200" x-bind:class="open && 'rotate-90'" />
                <span x-text="open ? 'Hide details' : 'Show details'"></span>
            </button>

            <div x-show="open" x-collapse style="margin-top: 0.375rem;">
                <div style="display: flex; flex-direction: column; gap: 0.375rem; padding-top: 0.375rem; border-top: 1px solid #f3f4f6;" class="dark:!border-t-white/5">
                    @if ($record->goal)
                        <div style="font-size: 0.625rem; display: flex; gap: 0.25rem;">
                            <strong style="color: #374151; flex-shrink: 0;" class="dark:!text-gray-300">🎯 Goal:</strong>
                            <span style="color: #6b7280;" class="dark:!text-gray-400">{{ $record->goal }}</span>
                        </div>
                    @endif

                    @if ($record->suggested_message)
                        <div style="background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 0.375rem; padding: 0.5rem;">
                            <div style="font-size: 0.5625rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.125rem;">
                                💬 Suggested Script
                            </div>
                            <p style="font-size: 0.6875rem; font-style: italic; color: #374151; line-height: 1.45; margin: 0;" class="dark:!text-gray-200">
                                "{{ $record->suggested_message }}"
                            </p>
                        </div>
                    @endif

                    {{-- What the call said, where a call is why this card exists. --}}
                    @include('filament.tables.partials.action-call-analysis', ['record' => $record])

                    @if ($record->avoid_notes)
                        <div style="font-size: 0.625rem; display: flex; gap: 0.25rem;">
                            <strong style="color: #dc2626; flex-shrink: 0;" class="dark:!text-red-400">⚠️ Avoid:</strong>
                            <span style="color: #6b7280;" class="dark:!text-gray-400">{{ $record->avoid_notes }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Completed outcome banner ── --}}
        @if ($record->staff_outcome)
            <div style="padding: 0.4375rem 0.5rem; border-radius: 0.375rem; background: #f0fdf4; border: 1px solid #bbf7d0; margin-top: 0.25rem;">
                <div style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
                    <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5 text-green-600 dark:text-green-400" style="flex-shrink: 0;" />
                    <span style="font-size: 0.6875rem; font-weight: 700; color: #16a34a;" class="dark:!text-green-400">
                        {{ \App\Models\AiActionLog::outcomeOptions()[$record->staff_outcome] ?? $record->staff_outcome }}
                    </span>
                    @if ($record->outcome_at)
                        {{-- Time alone is ambiguous once a card is more than a day
                             old, so the date shows unless it was logged today. --}}
                        <span style="font-size: 0.625rem; color: #9ca3af;" title="{{ $record->outcome_at->format('D, j M Y g:i A') }}">
                            {{ $record->outcome_at->isToday()
                                ? $record->outcome_at->format('g:i A')
                                : $record->outcome_at->format('j M, g:i A') }}
                        </span>
                    @endif
                </div>

                @if (filled($record->outcome_notes))
                    {{-- The note staff typed is the most useful part of a completed
                         card (a callback time, an objection), so show it in full. --}}
                    <div style="display: flex; gap: 0.375rem; margin-top: 0.375rem; padding-top: 0.375rem; border-top: 1px solid #bbf7d0;">
                        <x-filament::icon icon="heroicon-m-chat-bubble-bottom-center-text" class="h-3 w-3" style="flex-shrink: 0; margin-top: 0.0625rem; color: #6b7280;" />
                        <p style="font-size: 0.6875rem; line-height: 1.45; margin: 0; color: #374151; white-space: pre-line; overflow-wrap: anywhere;" class="dark:!text-gray-300">{{ $record->outcome_notes }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
