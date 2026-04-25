<?php

namespace App\Filament\Resources\ConsumableTransfers\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Hidden;
use App\Models\Product;
use App\Models\ClinicInventory;

class ConsumableTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transfer Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                auth()->user()->hasRole('super_admin')
                                    ? Select::make('clinic_id')
                                        ->relationship('clinic', 'name')
                                        ->required()
                                        // ->searchable()
                                        ->native(false)
                                        ->reactive()
                                        ->disabledOn('edit')
                                        ->columnSpan(1)
                                    : Hidden::make('clinic_id')
                                        ->default(fn() => auth()->user()->clinic_id),
                                DatePicker::make('transfer_date')
                                    ->label('Usage Date')
                                    ->default(now())
                                    ->required(),
                                TextInput::make('temp_notes')
                                    ->label('Internal Ref')
                                    ->placeholder('e.g. Patient Treatment')
                                    ->dehydrated(false),
                            ]),
                        Textarea::make('notes')
                            ->label('Usage Description / Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Usage Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->table([
                                TableColumn::make('Product')->width(400),
                                TableColumn::make('Available')->width(150),
                                TableColumn::make('Qty Used')->width(150),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function ($query, Get $get) {
                                            $query->whereIn('type', ['product', 'iv_product']);
                                            $clinicId = $get('../../clinic_id');
                                            $query->whereHas('clinicInventories', fn ($q) => $q->where('clinic_id', $clinicId));

                                            // Exclude already selected
                                            $items = $get('../../items') ?? [];
                                            $currentId = $get('product_id');
                                            $others = collect($items)->pluck('product_id')->filter()->reject(fn($id) => $id == $currentId)->toArray();
                                            if (!empty($others)) $query->whereNotIn('id', $others);
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} (" . str_replace('_', ' ', $record->type) . ")")
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!$state) {
                                            $set('available_stock', 0);
                                            return;
                                        }
                                        $clinicId = $get('../../clinic_id');
                                        $stock = ClinicInventory::where('clinic_id', $clinicId)
                                            ->where('product_id', $state)
                                            ->first()?->stock_quantity ?? 0;
                                        $set('available_stock', $stock);
                                    }),
                                TextInput::make('available_stock')
                                    ->label('Available')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->afterStateHydrated(function (Set $set, Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return;

                                        $clinicId = $get('../../clinic_id');
                                        $stock = ClinicInventory::where('clinic_id', $clinicId)
                                            ->where('product_id', $productId)
                                            ->first()?->stock_quantity ?? 0;
                                        $set('available_stock', $stock);
                                    }),
                                TextInput::make('quantity_used')
                                    ->label('Qty Used')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $available = $get('available_stock') ?? 0;
                                            if ($value > $available) {
                                                $fail("Insufficient stock ({$available} left).");
                                            }
                                        },
                                    ]),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1),
                    ]),
            ]);
    }
}
