<?php

namespace App\Filament\Exports;

use App\Models\Purchase;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseExporter extends Exporter
{
    public static function getModel(): string
    {
        return Purchase::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('clinic.name')
                ->label('Clinic'),
            ExportColumn::make('supplier_name')
                ->label('Supplier'),
            ExportColumn::make('purchase_date')
                ->label('Date')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d-m-Y') : ''),
            ExportColumn::make('payment_mode')
                ->label('Payment Mode'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('subtotal')
                ->label('Subtotal (Taxable)'),
            ExportColumn::make('total_gst')
                ->label('GST'),
            // ExportColumn::make('total_discount')
            //     ->label('Discount'),
            ExportColumn::make('total_amount')
                ->label('Grand Total'),
            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn ($state) => $state->format('d-m-Y H:i')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase report export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
