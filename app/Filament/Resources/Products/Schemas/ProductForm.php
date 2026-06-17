<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Product;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Toggle;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Product Details')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Enter product name'),

                                    Select::make('type')
                                        ->options([
                                            'product' => 'Product',
                                            'service' => 'Service',
                                            'iv_product' => 'IV Product',
                                        ])
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $set('hsn_sac_code', config("project.hsn_sac_codes.{$state}"));
                                            }
                                        })
                                        ->placeholder('Select type'),
                                ]),
                                Grid::make(4)->schema([
                                    TextInput::make('barcode')
                                        ->label('Barcode (UPC/EAN)')
                                        ->maxLength(255)
                                        ->placeholder('Enter Barcode')
                                        ->visible(fn ($get) => in_array($get('type'), ['product', 'iv_product']))
                                        ->required(fn ($get) => in_array($get('type'), ['product', 'iv_product'])),
                                    TextInput::make('sell_price')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->placeholder('0.00')
                                        ->visible(fn ($get) => in_array($get('type'), ['product', 'service']))
                                        ->required(fn ($get) => in_array($get('type'), ['product', 'service'])),
                                    TextInput::make('hsn_sac_code')
                                        ->label('HSN/SAC Code')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Enter HSN/SAC Code'),
                                    TextInput::make('gst')
                                        ->label('GST (%)')
                                        ->numeric()
                                        ->suffix('%')
                                        ->default(18)
                                        ->required()
                                        ->placeholder('0'),
                                    Select::make('unit')
                                        ->options([
                                            'ml' => 'ML',
                                            'mg' => 'MG',
                                        ])
                                        ->visible(fn ($get) => $get('type') === 'iv_product')
                                        ->required(fn ($get) => $get('type') === 'iv_product')
                                        ->placeholder('Select unit'),
                                ]),
                                Grid::make(1)->schema([
                                    Textarea::make('description')
                                        ->placeholder('Enter product description')
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => fn (?Product $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->state(fn (Product $record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last Modified')
                            ->state(fn (Product $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Product $record) => $record === null),
            ])
            ->columns(3);
    }
}
