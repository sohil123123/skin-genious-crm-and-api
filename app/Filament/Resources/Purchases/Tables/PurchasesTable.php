<?php

namespace App\Filament\Resources\Purchases\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('clinic.name')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                \Filament\Tables\Columns\TextColumn::make('supplier_name')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->badge()
                    ->color('gray'),
                \Filament\Tables\Columns\TextColumn::make('items_summary')
                    ->label('Product Summary')
                    ->state(fn (\App\Models\Purchase $record): string => $record->items->map(fn ($item) => "{$item->product->name} (x{$item->quantity})")->join(', '))
                    ->limit(30)
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->whereHas('items.product', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->color('primary')
                    ->size('xs')
                    ->action(
                        Action::make('view_items')
                            ->modalHeading('Purchase Items')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->infolist([
                                \Filament\Infolists\Components\RepeatableEntry::make('items')
                                    ->label('Items List')
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('product.name')->label('Product Name'),
                                        \Filament\Infolists\Components\TextEntry::make('quantity')->label('Quantity'),
                                        \Filament\Infolists\Components\TextEntry::make('purchase_price')
                                            ->label('Rate')
                                            ->money('INR'),
                                        \Filament\Infolists\Components\TextEntry::make('total')
                                            ->label('Amount')
                                            ->money('INR'),
                                    ])
                                    ->columns(4)
                            ])
                    ),
                \Filament\Tables\Columns\TextColumn::make('purchase_date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('total_amount')
                    ->money('INR')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('payment_mode')
                    ->searchable()
                    ->badge(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Unpaid' => 'danger',
                        default => 'gray',
                    }),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('clinic_id')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),



                \Filament\Tables\Filters\Filter::make('purchase_date')
                    ->form([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\DatePicker::make('from')
                                ->label('Created from'),
                            \Filament\Forms\Components\DatePicker::make('until')
                                ->label('Created until'),
                        ])
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('purchase_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }
                        return $indicators;
                    }),
            ])
            // ->filtersFormColumns(2)
            ->filtersTriggerAction(fn (\Filament\Actions\Action $action) => $action->button()->label('Filters')->color('primary'))
            ->headerActions([
                // Moved to ListPurchases page header
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(fn (\App\Models\Purchase $record, \App\Services\PurchasePdfService $service) => $service->download($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\ExportBulkAction::make()
                        ->exporter(\App\Filament\Exports\PurchaseExporter::class)
                        ->label('Export Selected'),
                ]),
            ]);
    }
}
