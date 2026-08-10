@php
    use App\Enums\LeadImportStatus;

    /** @var \App\Models\LeadImport $record */
    $progress = \App\DTOs\Lead\ImportProgressDto::fromImport($record);
    $status = $progress->status;

    $muted = 'opacity:.62;';
    $hairline = 'color-mix(in srgb, currentColor 14%, transparent)';
    $track = 'color-mix(in srgb, currentColor 12%, transparent)';

    $barColor = match (true) {
        $status === LeadImportStatus::Failed => 'var(--danger-500)',
        $status === LeadImportStatus::CompletedWithErrors => 'var(--warning-500)',
        $status === LeadImportStatus::Cancelled => 'var(--gray-400)',
        $status->isFinished() => 'var(--success-500)',
        default => 'var(--primary-500)',
    };

    $tiles = [
        ['label' => 'Imported', 'value' => $progress->importedRows, 'color' => 'success'],
        ['label' => 'Updated', 'value' => $progress->updatedRows, 'color' => 'info'],
        ['label' => 'Skipped', 'value' => $progress->skippedRows, 'color' => 'gray'],
        ['label' => 'Failed', 'value' => $progress->failedRows, 'color' => $progress->failedRows > 0 ? 'danger' : 'gray'],
    ];

    $width = max($progress->percentage, $status->isFinished() ? 100 : 2);
@endphp

<div style="border:1px solid {{ $hairline }}; border-radius:.75rem; padding:1.25rem;">
    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem;">
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.625rem;">
            <x-filament::badge :color="$status->getColor()" :icon="$status->getIcon()" size="lg">
                {{ $status->getLabel() }}
            </x-filament::badge>

            @if ($status->isRunning())
                <span style="font-size:.8125rem; {{ $muted }}">
                    {{ number_format($progress->processedRows) }} of {{ number_format($progress->totalRows) }} rows
                    @if ($progress->etaForHumans)
                        · about {{ $progress->etaForHumans }} remaining
                    @endif
                </span>
            @elseif ($record->duration_for_humans)
                <span style="font-size:.8125rem; {{ $muted }}">
                    Finished in {{ $record->duration_for_humans }}
                </span>
            @endif
        </div>

        <span style="font-size:1.5rem; font-weight:600; font-variant-numeric:tabular-nums;">
            {{ number_format($progress->percentage, $progress->percentage < 100 ? 1 : 0) }}%
        </span>
    </div>

    <div style="margin-top:.875rem; height:.625rem; width:100%; overflow:hidden; border-radius:9999px; background:{{ $track }};">
        <div style="height:100%; border-radius:9999px; transition:width .7s ease; width:{{ $width }}%; background:{{ $barColor }};"></div>
    </div>

    <div style="margin-top:1.25rem; display:grid; grid-template-columns:repeat(auto-fit, minmax(8rem, 1fr)); gap:.75rem;">
        @foreach ($tiles as $tile)
            <div style="border:1px solid {{ $hairline }}; border-radius:.5rem; padding:.75rem;">
                <div style="font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; {{ $muted }}">
                    {{ $tile['label'] }}
                </div>
                <div style="margin-top:.35rem;">
                    <x-filament::badge :color="$tile['color']" size="lg">
                        {{ number_format($tile['value']) }}
                    </x-filament::badge>
                </div>
            </div>
        @endforeach
    </div>

    @if ($record->failures()->where('is_resolved', false)->exists())
        <p style="margin-top:1rem; font-size:.8125rem; {{ $muted }}">
            Failed rows are listed below with a reason for each. Fix the underlying data and use
            <strong>Retry failed rows</strong>, or download them as a CSV to correct outside the CRM.
        </p>
    @endif
</div>
