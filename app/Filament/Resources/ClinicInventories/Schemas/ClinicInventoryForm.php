<?php

namespace App\Filament\Resources\ClinicInventories\Schemas;

use Filament\Schemas\Schema;

class ClinicInventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('clinic_id')
                    ->relationship('clinic', 'name', fn ($query, $record) => $query->activeOrCurrent($record?->clinic_id))
                    ->required()
                    ->searchable(),
                \Filament\Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable(),
                \Filament\Forms\Components\TextInput::make('stock_quantity')
                    ->numeric()
                    ->required()
                    ->default(0),
            ]);
    }
}
