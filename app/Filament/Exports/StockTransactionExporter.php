<?php

namespace App\Filament\Exports;

use App\Models\StockTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StockTransactionExporter extends Exporter
{
    public static function getModel(): string
    {
        return StockTransaction::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('product.name')
                ->label('Product'),
            ExportColumn::make('quantity')
                ->label('Quantity'),
            ExportColumn::make('type')
                ->label('Type')
                ->formatStateUsing(fn ($state) => match ($state) {
                    'purchase' => 'Purchase',
                    'sale' => 'Sale',
                    'return_in' => 'Return In (Add)',
                    'return_out' => 'Return Out (Deduct)',
                    'damage' => 'Damage (Deduct)',
                    'internal_use' => 'Internal Use (Deduct)',
                    default => $state,
                }),
            ExportColumn::make('note')
                ->label('Note'),
            ExportColumn::make('created_at')
                ->label('Date')
                ->formatStateUsing(fn ($state) => $state->format('d M Y, h:i A')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your stock transaction export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
