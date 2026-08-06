<x-filament-widgets::widget>
    <style>
        .ai-action-card {
            position: relative;
            border-radius: 0.75rem;
            border: 1px solid rgba(229, 231, 235, 0.8);
            background: #ffffff;
            overflow: hidden;
            transition: box-shadow 0.2s ease, transform 0.15s ease;
        }
        .ai-action-card:hover {
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
            transform: translateY(-1px);
        }
        .dark .ai-action-card {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .dark .ai-action-card:hover {
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.3);
        }

        /* Priority stripe */
        .ai-priority-stripe {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 0.75rem 0 0 0.75rem;
        }
        .ai-priority-danger { background: #ef4444; }
        .ai-priority-warning { background: #f59e0b; }
        .ai-priority-info { background: #3b82f6; }
        .ai-priority-gray { background: #9ca3af; }

        /* Avatar */
        .ai-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
            flex-shrink: 0;
        }
        .ai-avatar-rescue { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; }
        .ai-avatar-conversion { background: #fffbeb; color: #d97706; border: 1.5px solid #fde68a; }
        .ai-avatar-retention { background: #eff6ff; color: #2563eb; border: 1.5px solid #bfdbfe; }
        .ai-avatar-capacity { background: #f0fdf4; color: #16a34a; border: 1.5px solid #bbf7d0; }
        .dark .ai-avatar-rescue { background: rgba(220, 38, 38, 0.12); color: #fca5a5; border-color: rgba(220, 38, 38, 0.3); }
        .dark .ai-avatar-conversion { background: rgba(217, 119, 6, 0.12); color: #fcd34d; border-color: rgba(217, 119, 6, 0.3); }
        .dark .ai-avatar-retention { background: rgba(37, 99, 235, 0.12); color: #93c5fd; border-color: rgba(37, 99, 235, 0.3); }
        .dark .ai-avatar-capacity { background: rgba(22, 163, 74, 0.12); color: #86efac; border-color: rgba(22, 163, 74, 0.3); }

        /* Why-today callout */
        .ai-why-today {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid rgba(253, 230, 138, 0.5);
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
        }
        .dark .ai-why-today {
            background: linear-gradient(135deg, rgba(120, 53, 15, 0.15) 0%, rgba(146, 64, 14, 0.1) 100%);
            border-color: rgba(217, 119, 6, 0.2);
        }

        /* Script box */
        .ai-script-box {
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem;
        }
        .dark .ai-script-box {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* Outcome bar */
        .ai-outcome-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f3f4f6;
        }
        .dark .ai-outcome-bar {
            border-top-color: rgba(255, 255, 255, 0.06);
        }

        /* Completed outcome banner */
        .ai-outcome-done {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }
        .dark .ai-outcome-done {
            background: rgba(22, 163, 74, 0.1);
            border-color: rgba(22, 163, 74, 0.25);
        }

        /* Meta info row */
        .ai-meta-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.375rem;
        }
    </style>

    <x-filament::section icon="heroicon-o-queue-list">
        <x-slot name="heading">
            Action Queue — {{ now()->format('l, d M Y') }}
        </x-slot>

        <x-slot name="description">
            Prioritised actions to bring more patients through the door today.
        </x-slot>

        <x-slot name="headerActions">
            <x-filament::button
                wire:click="regenerateActions"
                wire:loading.attr="disabled"
                icon="heroicon-o-arrow-path"
                color="primary"
                size="sm"
            >
                <span wire:loading.remove wire:target="regenerateActions">Regenerate</span>
                <span wire:loading wire:target="regenerateActions">Generating…</span>
            </x-filament::button>
        </x-slot>

        {{-- ── Filter Tabs ── --}}
        @php $counts = $this->getFilterCounts(); @endphp
        <div class="mb-5">
            <x-filament::tabs>
                <x-filament::tabs.item :active="$activeFilter === 'all'" wire:click="setFilter('all')" icon="heroicon-o-squares-2x2" :badge="$counts['all'] ?? 0">
                    All
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$activeFilter === 'rescue'" wire:click="setFilter('rescue')" icon="heroicon-o-shield-exclamation" :badge="$counts['rescue'] ?? 0" badge-color="danger">
                    Rescue
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$activeFilter === 'conversion'" wire:click="setFilter('conversion')" icon="heroicon-o-arrow-right-circle" :badge="$counts['conversion'] ?? 0" badge-color="warning">
                    Conversion
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$activeFilter === 'retention'" wire:click="setFilter('retention')" icon="heroicon-o-arrow-path-rounded-square" :badge="$counts['retention'] ?? 0" badge-color="info">
                    Retention
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$activeFilter === 'capacity'" wire:click="setFilter('capacity')" icon="heroicon-o-chart-bar" :badge="$counts['capacity'] ?? 0" badge-color="success">
                    Capacity
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$activeFilter === 'completed'" wire:click="setFilter('completed')" icon="heroicon-o-check-badge" :badge="$counts['completed'] ?? 0" badge-color="success">
                    Completed
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- ── Action Cards ── --}}
        @php $actions = $this->getActions(); @endphp

        @if ($actions->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="p-4 rounded-full bg-gray-100 dark:bg-gray-800 mb-3">
                    <x-filament::icon icon="heroicon-o-sparkles" class="h-8 w-8 text-primary-500" />
                </div>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">
                    {{ $activeFilter === 'completed' ? 'No completed actions yet today' : 'No actions pending' }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xs">
                    {{ $activeFilter === 'completed'
                        ? 'Record outcomes on action cards to see them listed here.'
                        : 'Click "Regenerate" above to analyse CRM data and build today\'s queue.' }}
                </p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($actions as $action)
                    @php
                        $priorityColor = match (true) {
                            $action->priority_score >= 80 => 'danger',
                            $action->priority_score >= 60 => 'warning',
                            $action->priority_score >= 40 => 'info',
                            default => 'gray',
                        };
                        $categoryColor = match ($action->action_category) {
                            'rescue' => 'danger',
                            'conversion' => 'warning',
                            'retention' => 'info',
                            'capacity' => 'success',
                            default => 'gray',
                        };
                        $avatarClass = 'ai-avatar-' . $action->action_category;
                        $firstName = $action->client?->first_name ?? 'U';
                        $lastName = $action->client?->last_name ?? '';
                        $initials = strtoupper(substr($firstName, 0, 1) . ($lastName ? substr($lastName, 0, 1) : ''));
                        $fullName = trim(($action->client?->first_name ?? 'Unknown') . ' ' . ($action->client?->last_name ?? ''));
                    @endphp

                    <div class="ai-action-card">
                        {{-- Priority stripe --}}
                        <div class="ai-priority-stripe ai-priority-{{ $priorityColor }}"></div>

                        <div style="padding: 1rem 1rem 1rem 1.25rem;">
                            {{-- ── Row 1: Avatar + Name + Badges ── --}}
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem;">
                                {{-- Avatar --}}
                                <div class="ai-avatar {{ $avatarClass }}">{{ $initials }}</div>

                                {{-- Name + badges column --}}
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                        <span style="font-size: 0.9375rem; font-weight: 700; color: #111827;" class="dark:!text-white">
                                            {{ $fullName }}
                                        </span>
                                        <x-filament::badge :color="$priorityColor" size="sm">
                                            {{ $action->priority_score }}/100
                                        </x-filament::badge>
                                        <x-filament::badge :color="$categoryColor" size="sm">
                                            {{ $action->category_label }}
                                        </x-filament::badge>
                                    </div>
                                    {{-- Trigger label + Channel + Time --}}
                                    <div class="ai-meta-row">
                                        <span style="font-size: 0.6875rem; font-weight: 600; color: #6b7280;" class="dark:!text-gray-400">
                                            {{ $action->trigger_label }}
                                        </span>
                                        <span style="color: #d1d5db; font-size: 0.625rem;">•</span>
                                        @if ($action->recommended_channel === 'whatsapp')
                                            <x-filament::badge color="success" size="sm" icon="heroicon-m-chat-bubble-left-right">
                                                WhatsApp
                                            </x-filament::badge>
                                        @elseif ($action->recommended_channel === 'call')
                                            <x-filament::badge color="info" size="sm" icon="heroicon-m-phone">
                                                Call
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning" size="sm" icon="heroicon-m-chat-bubble-left">
                                                SMS
                                            </x-filament::badge>
                                        @endif
                                        @if ($action->recommended_time)
                                            <x-filament::badge color="gray" size="sm" icon="heroicon-m-clock">
                                                {{ $action->recommended_time }}
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- ── Row 2: Why Today callout ── --}}
                            <div class="ai-why-today" style="margin-bottom: 0.625rem;">
                                <p style="font-size: 0.75rem; line-height: 1.5; margin: 0;">
                                    <strong style="color: #92400e;" class="dark:!text-amber-300">Why today:</strong>
                                    <span style="color: #78350f;" class="dark:!text-amber-200/80">{{ $action->reason }}</span>
                                </p>
                            </div>

                            {{-- ── Row 3: Expandable details ── --}}
                            <div x-data="{ open: false }" style="margin-bottom: 0.5rem;">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 600; background: none; border: none; cursor: pointer; padding: 0;"
                                    class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                                >
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3 transition-transform duration-200" x-bind:class="open && 'rotate-90'" />
                                    <span x-text="open ? 'Hide script & details' : 'Show script & details'"></span>
                                </button>

                                <div x-show="open" x-collapse style="margin-top: 0.5rem;">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #f3f4f6;" class="dark:!border-t-white/5">
                                        @if ($action->goal)
                                            <div style="font-size: 0.6875rem; display: flex; gap: 0.375rem;">
                                                <strong style="color: #374151; flex-shrink: 0;" class="dark:!text-gray-300">🎯 Goal:</strong>
                                                <span style="color: #6b7280;" class="dark:!text-gray-400">{{ $action->goal }}</span>
                                            </div>
                                        @endif

                                        @if ($action->suggested_message)
                                            <div class="ai-script-box">
                                                <div style="font-size: 0.625rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                                                    💬 Suggested Script
                                                </div>
                                                <p style="font-size: 0.75rem; font-style: italic; color: #374151; line-height: 1.5; margin: 0;" class="dark:!text-gray-200">
                                                    "{{ $action->suggested_message }}"
                                                </p>
                                            </div>
                                        @endif

                                        @if ($action->avoid_notes)
                                            <div style="font-size: 0.6875rem; display: flex; gap: 0.375rem;">
                                                <strong style="color: #dc2626; flex-shrink: 0;" class="dark:!text-red-400">⚠️ Avoid:</strong>
                                                <span style="color: #6b7280;" class="dark:!text-gray-400">{{ $action->avoid_notes }}</span>
                                            </div>
                                        @endif

                                        @if ($action->expires_at)
                                            <div style="font-size: 0.6875rem; display: flex; gap: 0.375rem;">
                                                <strong style="color: #374151; flex-shrink: 0;" class="dark:!text-gray-300">⏰ Expires:</strong>
                                                <span style="color: #6b7280;" class="dark:!text-gray-400">{{ $action->expires_at->format('d M Y, g:i A') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- ── Row 4: Outcome buttons OR completed banner ── --}}
                            @if ($action->staff_outcome)
                                <div class="ai-outcome-done">
                                    <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4 text-green-600 dark:text-green-400" style="flex-shrink: 0;" />
                                    <span style="font-size: 0.75rem; font-weight: 700; color: #16a34a;" class="dark:!text-green-400">
                                        {{ \App\Models\AiActionLog::outcomeOptions()[$action->staff_outcome] ?? $action->staff_outcome }}
                                    </span>
                                    @if ($action->outcome_at)
                                        <span style="font-size: 0.6875rem; color: #9ca3af;">
                                            at {{ $action->outcome_at->format('g:i A') }}
                                        </span>
                                    @endif
                                    @if ($action->outcome_notes)
                                        <span style="font-size: 0.6875rem; color: #6b7280; font-style: italic;" class="dark:!text-gray-400">
                                            — "{{ $action->outcome_notes }}"
                                        </span>
                                    @endif
                                </div>
                            @else
                                <div class="ai-outcome-bar">
                                    <x-filament::button size="xs" color="info" icon="heroicon-m-phone" wire:click="quickOutcome({{ $action->id }}, 'called')">
                                        Called
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" icon="heroicon-m-phone-x-mark" wire:click="quickOutcome({{ $action->id }}, 'no_answer')">
                                        No Answer
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="success" icon="heroicon-m-chat-bubble-left-right" wire:click="quickOutcome({{ $action->id }}, 'whatsapp_sent')">
                                        WhatsApp
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="success" icon="heroicon-m-check-circle" wire:click="quickOutcome({{ $action->id }}, 'booked')">
                                        Booked
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="danger" icon="heroicon-m-x-mark" wire:click="quickOutcome({{ $action->id }}, 'not_interested')">
                                        Not Interested
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="warning" icon="heroicon-m-clock" wire:click="quickOutcome({{ $action->id }}, 'call_later')">
                                        Call Later
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" icon="heroicon-m-pencil-square" wire:click="openNotesModal({{ $action->id }})">
                                        + Notes
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ── Notes Modal ── --}}
        @if ($selectedActionId)
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);"
                wire:click.self="closeNotesModal"
            >
                <div style="width: 100%; max-width: 28rem;">
                    <x-filament::section icon="heroicon-o-pencil-square">
                        <x-slot name="heading">Record Outcome with Notes</x-slot>

                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #374151; margin-bottom: 0.25rem;" class="dark:!text-gray-300">
                                    Staff Notes (optional)
                                </label>
                                <textarea
                                    wire:model="outcomeNotes"
                                    rows="3"
                                    style="width: 100%; border-radius: 0.5rem; font-size: 0.75rem;"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                                    placeholder="e.g. Client requested callback on Monday after 2 PM…"
                                ></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem;">
                                @foreach (\App\Models\AiActionLog::outcomeOptions() as $key => $label)
                                    @php
                                        $btnColor = match ($key) {
                                            'booked' => 'success',
                                            'not_interested', 'do_not_contact', 'wrong_recommendation' => 'danger',
                                            'called', 'whatsapp_sent' => 'info',
                                            'call_later' => 'warning',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <x-filament::button size="xs" :color="$btnColor" wire:click="submitOutcomeWithNotes('{{ $key }}')">
                                        {{ $label }}
                                    </x-filament::button>
                                @endforeach
                            </div>

                            <div style="display: flex; justify-content: flex-end; padding-top: 0.5rem;">
                                <x-filament::button size="xs" color="gray" wire:click="closeNotesModal">
                                    Cancel
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
