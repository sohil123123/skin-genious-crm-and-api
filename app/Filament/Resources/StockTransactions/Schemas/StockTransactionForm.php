<?php

namespace App\Filament\Resources\StockTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\StockTransaction;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;

class StockTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Transaction Details')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('product_id')
                                        ->relationship('product', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->placeholder('Select product'),
                                    Select::make('type')
                                        ->options([
                                            'purchase' => 'Purchase (Add)',
                                            // 'sale' => 'Sale (Deduct)',
                                            'return_in' => 'Return In (Add)',
                                            'return_out' => 'Return Out (Deduct)',
                                            // 'adjustment_add' => 'Adjustment (Add)',
                                            // 'adjustment_remove' => 'Adjustment (Deduct)',
                                            'damage' => 'Damage (Deduct)',
                                            'internal_use' => 'Internal Use (Deduct)',
                                        ])
                                        ->required()
                                        ->placeholder('Select transaction type'),
                                    TextInput::make('quantity')
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->placeholder('Enter quantity'),
                                ]),
                                Grid::make(1)->schema([
                                    Textarea::make('note')
                                        ->columnSpanFull()
                                        ->placeholder('Enter transaction notes (optional)'),
                                ]),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => fn (?StockTransaction $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Transaction Date')
                            ->state(fn (StockTransaction $record): ?string => $record->created_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?StockTransaction $record) => $record === null),
            ])
            ->columns(3);
    }
}
