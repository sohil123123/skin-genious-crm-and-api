<?php

namespace App\Filament\Resources\UserPackages\Schemas;

use App\Enums\PackageDiscountType;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()
                ->schema([
                    // ─── Package Details ────────────────────────────────────
                    Section::make('Package Details')
                        ->icon('heroicon-o-rectangle-stack')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('package_name')
                                    ->label('Package Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Gold Facial Package - 5 Sessions')
                                    ->columnSpan(2),

                                Select::make('clinic_id')
                                    ->relationship('clinic', 'name')
                                    ->required()
                                    ->live()
                                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                                    ->columnSpan(1),

                                Select::make('user_id')
                                    ->label('Client')
                                    ->options(function (Get $get) {
                                        $clinicId = $get('clinic_id');

                                        if (auth()->user()->hasRole('super_admin') && !$clinicId) {
                                            return [];
                                        }

                                        $effectiveClinicId = $clinicId ?: auth()->user()->clinic_id;

                                        return User::active()
                                            ->role('client')
                                            ->when($effectiveClinicId, fn($q) => $q->where('clinic_id', $effectiveClinicId))
                                            ->get()
                                            ->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->preload()
                                    ->columnSpan(1),

                                Select::make('service_id')
                                    ->label('Service')
                                    ->options(fn () =>
                                        Product::active()
                                            ->where('type', 'service')
                                            ->get()
                                            ->mapWithKeys(fn ($p) => [$p->id => $p->name])
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        if (!$state) {
                                            $set('price_per_unit', null);
                                            $set('service_snapshot', null);
                                            return;
                                        }
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('price_per_unit', $product->sell_price);
                                            // Capture snapshot for historical preservation
                                            $set('service_snapshot', json_encode([
                                                'id'             => $product->id,
                                                'name'           => $product->name,
                                                'sku'            => $product->sku,
                                                'sell_price'     => $product->sell_price,
                                                'gst'            => $product->gst,
                                                'description'    => $product->description,
                                                'captured_at'    => now()->toDateTimeString(),
                                            ]));
                                        }
                                        // Recalculate totals when service changes
                                        self::recalculate($set, $get('price_per_unit'), $get('quantity'), $get('discount_type'), $get('discount_value'));
                                    })
                                    ->helperText('Only services are shown here.')
                                    ->columnSpan(1),

                                // Hidden snapshot field
                                Hidden::make('service_snapshot')->dehydrated(),
                            ]),
                        ]),

                    // ─── Sessions ───────────────────────────────────────────
                    Section::make('Sessions & Pricing')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('quantity')
                                    ->label('Total Sessions')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) =>
                                        self::recalculate($set, $get('price_per_unit'), $get('quantity'), $get('discount_type'), $get('discount_value'))
                                    )
                                    ->suffix('sessions'),

                                TextInput::make('price_per_unit')
                                    ->label('Price per Session')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) =>
                                        self::recalculate($set, $get('price_per_unit'), $get('quantity'), $get('discount_type'), $get('discount_value'))
                                    ),

                                DatePicker::make('expired_at')
                                    ->label('Expires On')
                                    ->nullable()
                                    ->minDate(now()),
                            ]),
                        ]),

                    // ─── Discount ───────────────────────────────────────────
                    Section::make('Discount')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('discount_type')
                                    ->label('Discount Type')
                                    ->options(PackageDiscountType::class)
                                    ->default(PackageDiscountType::Flat->value)
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) =>
                                        self::recalculate($set, $get('price_per_unit'), $get('quantity'), $get('discount_type'), $get('discount_value'))
                                    ),

                                TextInput::make('discount_value')
                                    ->label('Discount Value')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) =>
                                        self::recalculate($set, $get('price_per_unit'), $get('quantity'), $get('discount_type'), $get('discount_value'))
                                    )
                                    ->suffix(fn (Get $get) => ($get('discount_type') instanceof PackageDiscountType ? $get('discount_type')->value : $get('discount_type')) === 'percentage' ? '%' : '₹'),

                                TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated()
                                    ->numeric(),
                            ]),
                        ]),

                    Section::make('Notes')
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Textarea::make('notes')
                                ->label('Internal Notes')
                                ->rows(3)
                                ->placeholder('Add any relevant internal notes or client specific details...'),
                        ]),
                ])
                ->columnSpan(['lg' => 2]),

            // ─── Summary sidebar ────────────────────────────────────────────
            Group::make()
                ->schema([
                    Section::make('Summary')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            TextInput::make('total_amount')
                                ->label('Total (Before Discount)')
                                ->prefix('₹')
                                ->disabled()
                                ->dehydrated()
                                ->numeric(),

                            TextInput::make('final_amount')
                                ->label('Final Amount')
                                ->prefix('₹')
                                ->disabled()
                                ->dehydrated()
                                ->numeric(),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->inline(false),
                        ]),

                    Section::make('Usage Status')
                        ->icon('heroicon-o-chart-bar')
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            TextInput::make('used_sessions')
                                ->label('Sessions Used')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),

                            TextInput::make('quantity')
                                ->label('Total Sessions')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                        ]),
                ])
                ->columnSpan(['lg' => 1]),
        ])
        ->columns(3);
    }

    /**
     * Re-calculate derived fields: total_amount, discount_amount, final_amount.
     */
    public static function recalculate(Set $set, $pricePerUnit, $quantity, $discountType, $discountValue): void
    {
        $price    = (float) ($pricePerUnit ?? 0);
        $qty      = (int)   ($quantity     ?? 1);
        $dValue   = (float) ($discountValue ?? 0);
        $dType    = $discountType ?? 'flat';

        $totalBeforeDiscount = $price * $qty;

        $discountAmount = 0;
        $type = $dType instanceof PackageDiscountType ? $dType->value : $dType;

        if ($type === 'percentage') {
            $discountAmount = $totalBeforeDiscount * ($dValue / 100);
        } else {
            $discountAmount = $dValue;
        }
        $discountAmount = min($discountAmount, $totalBeforeDiscount);

        $finalAmount = max(0, $totalBeforeDiscount - $discountAmount);

        $set('total_amount',    number_format($totalBeforeDiscount, 2, '.', ''));
        $set('discount_amount', number_format($discountAmount,       2, '.', ''));
        $set('final_amount',    number_format($finalAmount,          2, '.', ''));
    }
}
