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
                                TableColumn::make('Qty Used')->width(150),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(function (Get $get) {
                                        $clinicId = $get('../../clinic_id') ?: auth()->user()->clinic_id;

                                        if (!$clinicId) {
                                            return [];
                                        }

                                        $items = $get('../../items') ?? [];
                                        $currentId = $get('product_id');
                                        $others = collect($items)->pluck('product_id')->filter()->reject(fn($id) => $id == $currentId)->toArray();

                                        $query = Product::whereIn('type', ['product', 'iv_product'])
                                            ->whereHas('clinicInventories', fn ($q) => $q->where('clinic_id', $clinicId));

                                        if (!empty($others)) {
                                            $query->whereNotIn('id', $others);
                                        }

                                        return $query->get()->mapWithKeys(function ($product) use ($clinicId) {
                                            $inventory = ClinicInventory::where('clinic_id', $clinicId)
                                                ->where('product_id', $product->id)
                                                ->first();
                                            $stock = $inventory?->stock_quantity ?? 0;
                                            $stockLabel = $stock <= 0 ? ' (Out of Stock)' : " ({$stock} available)";
                                            return [$product->id => "{$product->name} (" . str_replace('_', ' ', $product->type) . "){$stockLabel}"];
                                        });
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                TextInput::make('quantity_used')
                                    ->label('Qty Used')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(function (Get $get) {
                                        $productId = $get('product_id');
                                        $clinicId = $get('../../clinic_id') ?: auth()->user()->clinic_id;

                                        if (!$productId || !$clinicId) {
                                            return null;
                                        }

                                        $inventory = ClinicInventory::where('clinic_id', $clinicId)
                                            ->where('product_id', $productId)
                                            ->first();
                                        return $inventory?->stock_quantity ?? 0;
                                    }),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1),
                    ]),
            ]);
    }
}
