<?php

namespace App\Filament\Resources\StockTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;
use Filament\Tables\Grouping\Group;

class StockTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase', 'return_in', 'adjustment_add', 'initial' => 'success',
                        'sale', 'return_out', 'adjustment_remove', 'damage', 'internal_use' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('note')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Product'),

                SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        'sale' => 'Sale',
                        'return_in' => 'Return In (Add)',
                        'return_out' => 'Return Out (Deduct)',
                        'damage' => 'Damage (Deduct)',
                        'internal_use' => 'Internal Use (Deduct)',
                    ])
                    ->searchable(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                // EditAction removed to enforce ledger integrity. Add correction instead.
            ])
            ->groups([
                Group::make('product_id')
                    ->label('Product')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->product_id ?? 'no_product')
                    ->getTitleFromRecordUsing(fn ($record) => $record->product?->name ?? 'Unassigned'),
                Group::make('type')->label('Type')->collapsible(),
                Group::make('created_at')->date(),
            ])
            ->headerActions([
                \Filament\Actions\ExportAction::make()
                    ->exporter(\App\Filament\Exports\StockTransactionExporter::class)
                    ->label('Export Report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}
