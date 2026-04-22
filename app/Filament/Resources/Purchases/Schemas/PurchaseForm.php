<?php

namespace App\Filament\Resources\Purchases\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Product;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Purchase Details')
                            ->schema([
                                Grid::make(4)->schema([
                                    auth()->user()->hasRole('super_admin')
                                        ? Select::make('clinic_id')
                                            ->relationship('clinic', 'name')
                                            ->required()
                                            ->searchable()
                                            ->disabledOn('edit')
                                            ->columnSpan(1)
                                        : Hidden::make('clinic_id')
                                            ->default(fn () => auth()->user()->clinic_id),
                                    DatePicker::make('purchase_date')
                                        ->label('Invoice Date')
                                        ->required()
                                        ->default(now())
                                        ->columnSpan(1),
                                    Select::make('payment_mode')
                                        ->options([
                                            'cash' => 'Cash',
                                            'card' => 'Card',
                                            'upi' => 'UPI',
                                            'bank_transfer' => 'Bank Transfer',
                                        ])
                                        ->default('cash')
                                        ->columnSpan(1),
                                    TextInput::make('notes')
                                        ->label('Source note')
                                        ->placeholder('e.g. Supplier Invoice #')
                                        ->columnSpan(1),
                                    TextInput::make('supplier_name')
                                        ->label('Supplier')
                                        ->placeholder('e.g. ABC Pharma')
                                        ->maxLength(255)
                                        ->columnSpan(1),
                                ]),
                                // Grid::make(4)->schema([
                                //     TextInput::make('supplier_name')
                                //         ->label('Supplier')
                                //         ->placeholder('e.g. ABC Pharma')
                                //         ->maxLength(255)
                                //         ->columnSpan(1),
                                //     // Select::make('status')
                                //     //     ->options([
                                //     //         'Paid' => 'Paid',
                                //     //         'Unpaid' => 'Unpaid',
                                //     //         'Partial' => 'Partial',
                                //     //     ])
                                //     //     ->default('Paid')
                                //     //     ->required()
                                //     //     ->columnSpan(1),
                                // ]),
                            ]),

                        Section::make('Line Items')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->table([
                                        TableColumn::make('Product')->width(200),
                                        TableColumn::make('Quantity')->width(80),
                                        TableColumn::make('Rate')->width(80),
                                        TableColumn::make('GST %')->width(80),
                                        TableColumn::make('GST Amt')->width(80),
                                        TableColumn::make('Amount')->width(80),
                                    ])
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->relationship(
                                                name: 'product',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query, Get $get) {
                                                    $query->whereIn('type', ['product', 'iv_product']); // Filter only products and iv_products
                                                    
                                                    $items = $get('../../items') ?? [];
                                                    $currentProductId = $get('product_id');

                                                    $otherProductIds = collect($items)
                                                        ->pluck('product_id')
                                                        ->filter()
                                                        ->reject(fn ($id) => $id == $currentProductId)
                                                        ->toArray();

                                                    if (!empty($otherProductIds)) {
                                                        $query->whereNotIn('id', $otherProductIds);
                                                    }
                                                }
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Product $record) => "{$record->name} (" . str_replace('_', ' ', $record->type) . ")")
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if ($state) {
                                                    $product = Product::find($state);
                                                    if ($product) {
                                                        // $set('purchase_price', $product->purchase_price ?? 0);
                                                        $set('gst', $product->gst ?? 0);
                                                    }
                                                }
                                                self::calculateItemTotal($set, $get);
                                                self::updateParentTotals($get, $set);
                                            }),
                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->minValue(1)
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Set $set, Get $get) {
                                                self::calculateItemTotal($set, $get);
                                                self::updateParentTotals($get, $set);
                                            }),
                                        TextInput::make('purchase_price')
                                            ->label('Rate')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->prefix('₹')
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Set $set, Get $get) {
                                                self::calculateItemTotal($set, $get);
                                                self::updateParentTotals($get, $set);
                                            }),
                                        TextInput::make('gst')
                                            ->label('GST %')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Set $set, Get $get) {
                                                self::calculateItemTotal($set, $get);
                                                self::updateParentTotals($get, $set);
                                            }),
                                        TextInput::make('gst_amount')
                                            ->label('GST Amt')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(0),
                                        TextInput::make('total')
                                            ->label('Amount')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(0),
                                    ])
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::updateParentTotals($get, $set))
                                    ->live()
                                    ->defaultItems(1)
                                    ->minItems(1)
                                    ->addActionLabel('Add Product')
                                    ->hiddenLabel(),
                            ]),

                        Grid::make(12)->schema([
                            Group::make()->columnSpan(8),
                            Section::make()
                                ->schema([
                                    TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->readOnly()
                                        ->default(0)
                                        ->numeric(),

                                    TextInput::make('total_gst')
                                        ->label('GST')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->readOnly()
                                        ->default(0)
                                        ->numeric(),

                                    TextInput::make('round_off')
                                        ->label('Round Off')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->readOnly()
                                        ->default(0)
                                        ->numeric(),

                                    TextInput::make('total_amount')
                                        ->label('Grand Total')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->readOnly()
                                        ->default(0)
                                        ->numeric(),
                                ])
                                ->columnSpan(4),
                        ]),

                    ])
                    ->columnSpan(['lg' => 3]),
            ])
            ->columns(3);
    }

    public static function calculateItemTotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?: 0);
        $price = (float) ($get('purchase_price') ?: 0);
        $gstPercent = (float) ($get('gst') ?: 0);

        // Unit Price is EXCLUSIVE of GST
        $taxableValue = $quantity * $price;
        $gstAmount = $taxableValue * ($gstPercent / 100);

        $set('gst_amount', number_format($gstAmount, 2, '.', ''));
        $set('total', number_format($taxableValue, 2, '.', '')); // Display taxable value in row 'Total'
    }

    public static function updateParentTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? $get('../../items');

        if (!is_array($items)) {
            return;
        }

        $subtotal = 0;
        $totalGst = 0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['purchase_price'] ?? 0);
            $gstPercent = (float) ($item['gst'] ?? 0);

            $taxableValue = $quantity * $price;
            $gstAmount = $taxableValue * ($gstPercent / 100);

            $subtotal += $taxableValue;
            $totalGst += $gstAmount;
        }

        $rawGrandTotal = $subtotal + $totalGst;
        $roundedGrandTotal = round($rawGrandTotal);
        $roundOff = $roundedGrandTotal - $rawGrandTotal;

        $subtotalPath = $get('items') ? 'subtotal' : '../../subtotal';
        $set($subtotalPath, number_format($subtotal, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'total_gst' : '../../total_gst', number_format($totalGst, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'total_amount' : '../../total_amount', number_format($roundedGrandTotal, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'round_off' : '../../round_off', number_format($roundOff, 2, '.', ''));
    }
}
