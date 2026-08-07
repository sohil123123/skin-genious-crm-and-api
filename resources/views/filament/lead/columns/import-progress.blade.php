@php
    use App\Enums\LeadImportStatus;

    /** @var \App\Models\LeadImport $record */
    $percentage = $record->progress_percentage;
    $status = $record->status;
    $eta = $record->eta_for_humans;

    $track = 'color-mix(in srgb, currentColor 14%, transparent)';

    $barColor = match (true) {
        $status === LeadImportStatus::Failed => 'var(--danger-500)',
        $status === LeadImportStatus::CompletedWithErrors => 'var(--warning-500)',
        $status === LeadImportStatus::Cancelled => 'var(--gray-400)',
        $status->isFinished() => 'var(--success-500)',
        default => 'var(--primary-500)',
    };

    $width = max($percentage, $status->isFinished() ? 100 : 2);
@endphp

<div style="width:9rem;">
    <div style="height:.375rem; width:100%; overflow:hidden; border-radius:9999px; background:{{ $track }};">
        <div style="height:100%; border-radius:9999px; transition:width .5s ease; width:{{ $width }}%; background:{{ $barColor }};"></div>
    </div>

    <div style="margin-top:.25rem; display:flex; align-items:center; justify-content:space-between; gap:.25rem; font-size:.6875rem; opacity:.62; font-variant-numeric:tabular-nums;">
        <span>{{ number_format($percentage, $percentage < 100 ? 1 : 0) }}%</span>

        @if ($status->isRunning())
            <span style="white-space:nowrap;">
                {{ number_format($record->processed_rows) }}/{{ number_format($record->total_rows) }}@if ($eta) · {{ $eta }}@endif
            </span>
        @endif
    </div>
</div>
