<?php

namespace App\Filament\Exports;

use App\Models\ConsumableTransfer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Carbon;

class ConsumableTransferExporter extends Exporter
{
    public static function getModel(): string
    {
        return ConsumableTransfer::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('clinic.name')
                ->label('Clinic'),
            ExportColumn::make('transfer_date')
                ->label('Transfer Date')
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d-m-Y') : ''),
            ExportColumn::make('items_summary')
                ->label('Products Used')
                ->state(fn (ConsumableTransfer $record): string =>
                    $record->items->load('product')->map(fn ($item) => "{$item->product->name} (x{$item->quantity_used})")->join(', ')
                ),
            ExportColumn::make('total_items')
                ->label('Total Items')
                ->state(fn (ConsumableTransfer $record): int => $record->items->count()),
            ExportColumn::make('creator.first_name')
                ->label('Created By'),
            ExportColumn::make('notes')
                ->label('Notes'),
            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn ($state) => $state->format('d-m-Y H:i')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your consumable transfer export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
