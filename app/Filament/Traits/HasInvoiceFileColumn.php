<?php

namespace App\Filament\Traits;

use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Model;

trait HasInvoiceFileColumn
{
    /**
     * Download icon for the record's invoice media. Opens a new tab that
     * downloads the file; rows without an invoice show "-".
     *
     * Eager load "media" on the table query to avoid a query per row.
     *
     * @param  string  $type  Route segment: "purchases" or "expenses".
     */
    protected static function invoiceFileColumn(string $type): IconColumn
    {
        $hasInvoice = fn (Model $record): bool => (bool) $record->getFirstMedia('invoice');

        return IconColumn::make('invoice')
            ->label('Invoice')
            ->state(fn (Model $record): ?bool => $hasInvoice($record) ?: null)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->tooltip('Download invoice')
            ->url(fn (Model $record): ?string => $hasInvoice($record)
                ? route('invoice-files.download', ['type' => $type, 'id' => $record->getKey()])
                : null)
            ->openUrlInNewTab()
            ->placeholder('-')
            ->alignCenter()
            ->toggleable();
    }
}
