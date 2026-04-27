<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Product;
use App\Models\User;
use App\Models\ClinicInventory;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Hidden;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
         return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Invoice Details')
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('clinic_id')
                                    ->relationship('clinic', 'name')
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),
                                TextInput::make('invoice_number')
                                    ->label('Invoice #')
                                    ->placeholder('Auto-generated')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn ($record) => $record !== null)
                                    ->columnSpan(1),
                                DatePicker::make('invoice_date')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('source_note')
                                    ->placeholder('e.g. RWA Saket camp')
                                    ->columnSpan(1),
                            ]),
                            Grid::make(4)->schema([
                                Select::make('user_id')
                                    ->label('Client')
                                    ->options(function (callable $get) {
                                        $clinicId = $get('clinic_id');
                                        if (!$clinicId)
                                            $clinicId = auth()->user()->clinic_id;

                                        return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->reactive()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $user = User::find($state);
                                            if ($user) {
                                                $set('patient_phone', $user->mobile);
                                                $set('patient_email', $user->email);
                                            }
                                        }
                                    })
                                    ->columnSpan(1),
                                TextInput::make('patient_phone')
                                    ->label('Phone')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('e.g. 9876543210')
                                    ->columnSpan(1),
                                TextInput::make('patient_email')
                                    ->label('Email')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('e.g. abc@xyz.com')
                                    ->columnSpan(1),
                                // Select::make('status')
                                //     ->options([
                                //         'draft' => 'Draft',
                                //         'paid' => 'Paid',
                                //         'partial' => 'Partial',
                                //         'unpaid' => 'Unpaid',
                                //         'pending' => 'Pending',
                                //         'cancelled' => 'Cancelled',
                                //     ])
                                //     ->required()
                                //     ->default('paid')
                                //     ->columnSpan(1),
                            ]),
                        ]),

                    Section::make('Line Items')
                        ->headerActions([
                            // Actions specific to the section if needed
                        ])
                        ->schema([
                            Repeater::make('items')
                                ->relationship()
                                ->table([
                                    TableColumn::make('Product')->width(200),
                                    TableColumn::make('Quantity')->width(80),
                                    TableColumn::make('Unit Price')->width(80),
                                    TableColumn::make('Discount')->width(150),
                                    TableColumn::make('GST %')->width(80),
                                    TableColumn::make('GST Amt')->width(80),
                                    TableColumn::make('Total')->width(80),
                                ])
                                ->schema([
                                    Select::make('product_id')
                                        ->label('Product')
                                        ->placeholder(fn (Get $get) => empty($get('../../clinic_id')) ? 'Select Clinic first' : 'Select Product')
                                        ->disabled(fn (Get $get) => empty($get('../../clinic_id')))
                                        ->options(function (Get $get) {
                                            $clinicId = $get('../../clinic_id');

                                            if (!$clinicId) {
                                                return [];
                                            }

                                            return Product::active()->get()->mapWithKeys(function ($product) use ($clinicId) {
                                                $stockLabel = '';
                                                if ($product->type !== 'service') {
                                                    $inventory = ClinicInventory::where('clinic_id', $clinicId)
                                                        ->where('product_id', $product->id)
                                                        ->first();
                                                    $stock = $inventory?->stock_quantity ?? 0;
                                                    $stockLabel = $stock <= 0 ? ' (Out of Stock)' : " ({$stock} available)";
                                                }
                                                return [$product->id => $product->name . $stockLabel];
                                            });
                                        })
                                        ->disableOptionWhen(function ($value, $state, Get $get) {
                                            if (empty($value)) return false;
                                            $product = Product::find($value);
                                            if ($product && $product->type !== 'service') {
                                                $clinicId = $get('../../clinic_id');
                                                if (!$clinicId) return true;

                                                $inventory = ClinicInventory::where('clinic_id', $clinicId)
                                                    ->where('product_id', $value)
                                                    ->first();
                                                if (!$inventory || $inventory->stock_quantity <= 0) return true;
                                            }
                                            $selectedProductIds = collect($get('../../items'))->pluck('product_id')->filter()->unique();
                                            return $selectedProductIds->contains($value) && $value != $state;
                                        })
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            if (!$state) {
                                                // Product cleared - reset all fields
                                                $set('unit_price', 0);
                                                $set('gst_percentage', 0);
                                                $set('gst_amount', 0);
                                                $set('discount_value', 0);
                                                $set('valid_discount_amount', 0);
                                                $set('line_total', 0);
                                                $set('quantity', 1); // Reset quantity to default
                                            } else {
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('unit_price', $product->sell_price);
                                                    $set('gst_percentage', $product->gst ?? 18);
                                                }
                                            }
                                            self::updateLineTotal($get, $set);
                                            self::updateGrandTotal($get, $set);
                                        })
                                        ->distinct()
                                        ->searchable(),

                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->default(1)
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            self::updateLineTotal($get, $set);
                                            self::updateGrandTotal($get, $set);
                                        })
                                        ->required(),

                                    TextInput::make('unit_price')
                                        ->disabled()
                                        ->prefix('₹')
                                        ->dehydrated()
                                        ->numeric()
                                        ->required(),

                                    Group::make()
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    Select::make('discount_type')
                                                        ->label('Type')
                                                        ->options(['flat' => 'Flat', 'percentage' => '%'])
                                                        ->default('flat')
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                            self::updateLineTotal($get, $set);
                                                            self::updateGrandTotal($get, $set);
                                                        }),
                                                    TextInput::make('discount_value')
                                                        ->label('Value')
                                                        ->numeric()
                                                        ->default(0)
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                            self::updateLineTotal($get, $set);
                                                            self::updateGrandTotal($get, $set);
                                                        }),
                                                ]),
                                            Hidden::make('valid_discount_amount')->default(0)->dehydrated(),
                                        ]),

                                    TextInput::make('gst_percentage')
                                        ->label('GST')
                                        ->disabled()
                                        ->dehydrated()
                                        ->default(18) // Default GST if not set
                                        ->numeric(),

                                    TextInput::make('gst_amount')
                                        ->label('GST Amt')
                                        ->prefix('₹')
                                        ->disabled()
                                        ->dehydrated()
                                        ->default(0)
                                        ->numeric(),

                                    TextInput::make('line_total')
                                        ->label('Amount')
                                        ->prefix('₹')
                                        ->disabled()
                                        ->dehydrated()
                                        ->default(0)
                                        ->numeric(),
                                ])
                                // FIXED: Added afterStateUpdated to Repeater for add/delete/reorder triggers
                                // This ensures grand total updates on row removal (fresh $get('items') available here)
                                ->afterStateUpdated(function (Set $set, Get $get) {
                                    self::updateGrandTotal($get, $set);
                                })
                                ->live()
                                ->defaultItems(1)
                                ->minItems(1)
                                ->addActionLabel('Add Product')
                                ->maxItems(fn (Get $get) => collect($get('items'))->contains(fn ($item) => empty($item['product_id'])) ? count($get('items')) : 100)
                                // Hide delete button if only one item remains
                                ->deleteAction(fn ($action) => $action->hidden(fn (Get $get) => count($get('items')) <= 1))
                                ->hiddenLabel(),
                        ]),

                        Grid::make(12)->schema([
                            Group::make()->columnSpan(8),
                            Section::make()
                                ->schema([
                                    // ADDED: Loading overlay on summaries using Alpine + hidden state
                                    TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric(),

                                    TextInput::make('taxable_value')
                                        ->label('Taxable Value')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric(),

                                    TextInput::make('gst_total')
                                        ->label('GST')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric(),

                                    TextInput::make('discount_total')
                                        ->label('Discount')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric(),

                                    TextInput::make('grand_total')
                                        ->label('Grand Total')
                                        ->prefix('₹')
                                        ->inlineLabel()
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric(),
                                ])
                                ->columnSpan(4),
                        ]),


                    ])
                    ->columnSpan(['lg' => 3]),
            ]);
    }

    public static function updateLineTotal(Get $get, Set $set): void
    {
        $price = (float) $get('unit_price') ?: 0;
        $qty = (int) $get('quantity') ?: 1;
        $discountType = $get('discount_type');
        $discountValue = (float) $get('discount_value') ?: 0;
        $gstPercent = (float) $get('gst_percentage') ?: 0;

        // Total inclusive of tax before discount
        $totalIncludingTaxBeforeDiscount = $price * $qty;

        $discountAmount = 0;
        if ($discountType === 'percentage') {
            $discountAmount = $totalIncludingTaxBeforeDiscount * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }

        $finalLineTotal = max(0, $totalIncludingTaxBeforeDiscount - $discountAmount);

        // Custom GST calculation: GST = Total * (Percent / 100)
        // Base = Total - GST
        $gstAmount = $finalLineTotal * ($gstPercent / 100);
        $taxableValue = $finalLineTotal - $gstAmount;

        $set('gst_amount', number_format($gstAmount, 2, '.', ''));
        $set('valid_discount_amount', number_format($discountAmount, 2, '.', ''));
        $set('line_total', number_format($finalLineTotal, 2, '.', ''));
    }

    public static function updateGrandTotal(Get $get, Set $set): void
    {
        $items = $get('items') ?? $get('../../items');

        if (!is_array($items)) {
            return;
        }

        $subtotalInclusive = 0;
        $discountTotal = 0;
        $taxableValueTotal = 0;
        $gstTotal = 0;
        $grandTotal = 0;

        foreach ($items as $item) {
            if (empty($item['unit_price']) && !empty($item['product_id'])) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $item['unit_price'] = $product->sell_price;
                    $item['gst_percentage'] ??= $product->gst ?? 18;
                }
            }

            $price = (float) ($item['unit_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $gstP = (float) ($item['gst_percentage'] ?? 0);

            $lineInclusiveBeforeDiscount = $price * $qty;

            $disc = (float) ($item['discount_value'] ?? 0);
            $dType = $item['discount_type'] ?? 'flat';

            $dAmount = 0;
            if ($dType === 'percentage') {
                $dAmount = $lineInclusiveBeforeDiscount * ($disc / 100);
            } else {
                $dAmount = $disc;
            }

            $lineInclusiveFinal = max(0, $lineInclusiveBeforeDiscount - $dAmount);

            // Custom GST calculation matching row logic
            $itemGstAmount = $lineInclusiveFinal * ($gstP / 100);
            $itemTaxableValue = $lineInclusiveFinal - $itemGstAmount;

            $subtotalInclusive += $lineInclusiveFinal; // Now subtracting discount from subtotal as well
            $discountTotal += $dAmount;
            $taxableValueTotal += $itemTaxableValue;
            $gstTotal += $itemGstAmount;
            $grandTotal += $lineInclusiveFinal;
        }

        $subtotalPath = $get('items') ? 'subtotal' : '../../subtotal';
        $set($subtotalPath, number_format($subtotalInclusive, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'discount_total' : '../../discount_total', number_format($discountTotal, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'taxable_value' : '../../taxable_value', number_format($taxableValueTotal, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'gst_total' : '../../gst_total', number_format($gstTotal, 2, '.', ''));
        $set($subtotalPath === 'subtotal' ? 'grand_total' : '../../grand_total', number_format($grandTotal, 2, '.', ''));
    }
}
