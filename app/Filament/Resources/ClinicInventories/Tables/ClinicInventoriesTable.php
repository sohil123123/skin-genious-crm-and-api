<?php

namespace App\Filament\Resources\ClinicInventories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ClinicInventoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('clinic.name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('stock_quantity')
                    ->sortable()
                    ->badge(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('clinic_id')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                \Filament\Tables\Filters\SelectFilter::make('product_id')
                    ->relationship('product', 'name', modifyQueryUsing: fn ($query) => $query->whereIn('type', ['product', 'iv_product']))
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\Product $record) => "{$record->name} (" . str_replace('_', ' ', $record->type) . ")")
                    ->searchable()
                    ->preload(),
            ])
            // ->filtersFormColumns(3)
            ->filtersTriggerAction(fn (\Filament\Actions\Action $action) => $action->button()->label('Filters')->color('primary'));
            // ->recordActions([
            //     EditAction::make(),
            // ])
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ]);
    }
}
