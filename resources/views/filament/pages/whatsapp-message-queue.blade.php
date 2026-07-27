<x-filament-panels::page>
    @php
        $stats = [
            'pending' => \App\Models\WhatsAppMessage::where('status', 'pending')->count(),
            'failed' => \App\Models\WhatsAppMessage::where('status', 'failed')->count(),
            'sent' => \App\Models\WhatsAppMessage::whereIn('status', ['sent', 'delivered', 'read'])->count(),
        ];
    @endphp

    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; width: 100%;">
        <!-- Sent Messages Card -->
        <div style="flex: 1; min-width: 250px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Paper Airplane Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Total Sent</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($stats['sent']) }}
                    </h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #16a34a;">
                    <span>Messages successfully sent</span>
                    <!-- Trending Up Icon (Green) -->
                    <svg style="width: 16px; height: 16px; color: #16a34a; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                    </svg>
                </div>
            </x-filament::section>
        </div>

        <!-- Pending Messages Card -->
        <div style="flex: 1; min-width: 250px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Clock Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Pending</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($stats['pending']) }}
                    </h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #ca8a04;">
                    <span>Messages in queue</span>
                    <!-- Clock Icon (Yellow) -->
                    <svg style="width: 16px; height: 16px; color: #ca8a04; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </x-filament::section>
        </div>

        <!-- Failed Messages Card -->
        <div style="flex: 1; min-width: 250px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- X Circle Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Failed</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($stats['failed']) }}
                    </h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #dc2626;">
                    <span>Messages failed to send</span>
                    <!-- Warning Icon (Red) -->
                    <svg style="width: 16px; height: 16px; color: #dc2626; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
            </x-filament::section>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
