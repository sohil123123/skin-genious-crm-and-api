<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @php
        $data = $this->data ?? [];
        $dateFrom = $data['date_from'] ?? now()->subMonth()->format('Y-m-d');
        $dateTo = $data['date_to'] ?? now()->format('Y-m-d');
        $status = $data['status'] ?? null;
        $template = $data['template_name'] ?? null;

        $query = \App\Models\WhatsAppMessage::query();
        if ($dateFrom)
            $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)
            $query->whereDate('created_at', '<=', $dateTo);
        if ($status)
            $query->where('status', $status);
        if ($template)
            $query->where('template_name', $template);

        $totalMessages = (clone $query)->count();
        $sentCount = (clone $query)->sent()->count();
        $deliveredCount = (clone $query)->delivered()->count();
        $readCount = (clone $query)->read()->count();
        $failedCount = (clone $query)->failed()->count();
        $successRate = $totalMessages > 0 ? round(($sentCount / $totalMessages) * 100, 1) : 0;
    @endphp

    <div style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
        <!-- Total Messages Card -->
        <div style="flex: 1; min-width: 180px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Message Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l3.255-3.843a.98.98 0 01.865-.501 54.8 54.8 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Total Messages</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($totalMessages) }}</h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #6b7280;">
                    <span>Overall messages</span>
                </div>
            </x-filament::section>
        </div>

        <!-- Sent Card -->
        <div style="flex: 1; min-width: 180px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Paper Airplane Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Sent</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($sentCount) }}</h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #16a34a;">
                    <span>Successfully sent</span>
                    <svg style="width: 16px; height: 16px; color: #16a34a; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                    </svg>
                </div>
            </x-filament::section>
        </div>

        <!-- Delivered Card -->
        <div style="flex: 1; min-width: 180px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Check Circle Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Delivered</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($deliveredCount) }}</h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #2563eb;">
                    <span>Delivered to device</span>
                    <svg style="width: 16px; height: 16px; color: #2563eb; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
            </x-filament::section>
        </div>

        <!-- Read Card -->
        <div style="flex: 1; min-width: 180px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Eye Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Read</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">
                        {{ number_format($readCount) }}</h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #7c3aed;">
                    <span>Read by recipient</span>
                    <svg style="width: 16px; height: 16px; color: #7c3aed; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    </svg>
                </div>
            </x-filament::section>
        </div>

        <!-- Failed Card -->
        <div style="flex: 1; min-width: 180px;">
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
                        {{ number_format($failedCount) }}</h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #dc2626;">
                    <span>Failed to send</span>
                    <svg style="width: 16px; height: 16px; color: #dc2626; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
            </x-filament::section>
        </div>

        <!-- Success Rate Card -->
        <div style="flex: 1; min-width: 180px;">
            <x-filament::section>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Pie Chart Icon (Outline, Grey) -->
                    <svg style="width: 20px; height: 20px; color: #9ca3af; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                    </svg>
                    <span style="font-size: 0.875rem; font-weight: 500; opacity: 0.85;">Success Rate</span>
                </div>
                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="font-size: 2.25rem; font-weight: 700; line-height: 2.5rem;">{{ $successRate }}%</h3>
                </div>
                <div
                    style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: {{ $successRate >= 90 ? '#16a34a' : ($successRate >= 70 ? '#ca8a04' : '#dc2626') }};">
                    <span>Overall success</span>
                    <svg style="width: 16px; height: 16px; color: {{ $successRate >= 90 ? '#16a34a' : ($successRate >= 70 ? '#ca8a04' : '#dc2626') }}; display: inline-block; flex-shrink: 0;"
                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                    </svg>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
