<?php

namespace App\Filament\Resources\ClinicInventories\Tables;

use App\Models\ClinicInventory;
use App\Models\ConsumableTransfer;
use App\Models\ConsumableTransferItem;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;

class ClinicInventoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->badge()
                    ->color(fn($state) => $state === 'iv_product' ? 'warning' : 'gray'),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => $state <= 5 ? 'danger' : ($state <= 20 ? 'warning' : 'success')),
            ])
            ->filters([
                SelectFilter::make('clinic_id')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
                SelectFilter::make('product_id')
                    ->relationship('product', 'name', modifyQueryUsing: fn($query) => $query->whereIn('type', ['product', 'iv_product']))
                    ->getOptionLabelFromRecordUsing(fn(Product $record) => "{$record->name} (" . str_replace('_', ' ', $record->type) . ")")
                    ->searchable()
                    ->preload(),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary'))
            ->recordActions([
                Action::make('create_transfers')
                    ->label('C. Transfers')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->form(fn(ClinicInventory $record) => [
                        Placeholder::make('product_info')
                            ->label('Product')
                            ->content("{$record->product->name} (" . str_replace('_', ' ', $record->product->type) . ")"),
                        Placeholder::make('available_stock')
                            ->label('Available Stock')
                            ->content((string) $record->stock_quantity),
                        DatePicker::make('transfer_date')
                            ->label('Usage Date')
                            ->default(now())
                            ->required(),
                        TextInput::make('quantity_used')
                            ->label('Quantity to Consume')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue($record->stock_quantity)
                            ->helperText("Max available: {$record->stock_quantity}"),
                        Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->action(function (ClinicInventory $record, array $data) {
                        // Validate stock
                        if ($data['quantity_used'] > $record->stock_quantity) {
                            Notification::make()
                                ->title('Insufficient Stock')
                                ->body("Only {$record->stock_quantity} units available.")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Create the transfer header
                        $transfer = ConsumableTransfer::create([
                            'clinic_id' => $record->clinic_id,
                            'transfer_date' => $data['transfer_date'],
                            'notes' => $data['notes'] ?? null,
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id(),
                        ]);

                        // Create the line item (stock deduction handled by model hook)
                        ConsumableTransferItem::create([
                            'consumable_transfer_id' => $transfer->id,
                            'product_id' => $record->product_id,
                            'quantity_used' => $data['quantity_used'],
                        ]);

                        Notification::make()
                            ->title('Consumable Transfer Created ✅')
                            ->body("{$data['quantity_used']} unit(s) of {$record->product->name} transfered. Stock updated.")
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Create Consumable Transfers')
                    ->modalSubmitActionLabel('Submit')
                    ->modalWidth('md'),
            ]);
    }
}
