<?php

namespace App\Filament\Resources\UserPackages\Schemas;

use App\Enums\PackageDiscountType;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

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
                            Grid::make(3)->schema([
                                TextInput::make('package_name')
                                    ->label('Package Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Skin Glow Combo')
                                    ->columnSpan(3),

                                Select::make('clinic_id')
                                    ->relationship('clinic', 'name')
                                    ->required()
                                    ->live()
                                    ->visible(fn($livewire) => auth()->user()->hasRole('super_admin') && !($livewire instanceof RelationManager))
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
                                            ->mapWithKeys(fn($u) => [$u->id => $u->name]);
                                    })
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->preload()
                                    ->hidden(fn($livewire) => $livewire instanceof RelationManager)
                                    ->columnSpan(1),

                                DatePicker::make('expired_at')
                                    ->label('Expires On')
                                    ->nullable()
                                    ->minDate(now())
                                    ->columnSpan(1),
                            ]),
                        ]),

                    // ─── Package Services (Multi-Service Repeater) ─────────
                    Section::make('Package Services')
                        ->icon('heroicon-o-sparkles')
                        ->description('Add one or more services to this package. Each service tracks its own sessions and pricing.')
                        ->schema([
                            Repeater::make('items')
                                ->relationship()
                                ->label('')
                                ->schema([
                                    Grid::make(12)->schema([
                                        Select::make('service_id')
                                            ->label('Service')
                                            ->options(function () {
                                                $icon = @file_get_contents(public_path('images/service.svg')) ?: '';
                                                return Product::active()
                                                    ->where('type', 'service')
                                                    ->orderBy('name', 'asc')
                                                    ->get()
                                                    ->mapWithKeys(function ($p) use ($icon) {
                                                        $price = number_format($p->sell_price, 0);
                                                        $html = '<div style="display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 12px;">' .
                                                            '<div style="display: flex; align-items: center; gap: 8px;">' .
                                                            $icon .
                                                            '<span style="font-weight: 500; color: inherit;">' . e($p->name) . '</span>' .
                                                            '</div>' .
                                                            '<span style="font-size: 0.8rem; color: #10b981; font-weight: 600;">(₹' . $price . ')</span>' .
                                                            '</div>';
                                                        return [$p->id => $html];
                                                    });
                                            })
                                            ->allowHtml()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->searchable()
                                            ->native(false)
                                            ->required()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if (!$state) {
                                                    $set('price_per_unit', null);
                                                    $set('service_snapshot', null);
                                                    return;
                                                }
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('price_per_unit', $product->sell_price);
                                                    // Capture snapshot
                                                    $set('service_snapshot', json_encode([
                                                        'id' => $product->id,
                                                        'name' => $product->name,
                                                        'sku' => $product->sku,
                                                        'sell_price' => $product->sell_price,
                                                        'gst' => $product->gst,
                                                        'description' => $product->description,
                                                        'captured_at' => now()->toDateTimeString(),
                                                    ]));
                                                }
                                                // Recalculate item + package totals
                                                self::recalculateAllFromItem($set, $get);
                                            })
                                            // ->helperText('Only services are shown.')
                                            ->columnSpan(5),

                                        TextInput::make('quantity')
                                            ->label('Sessions')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                if ((int) $state < 1) {
                                                    $set('quantity', 1);
                                                }
                                                self::recalculateAllFromItem($set, $get);
                                            })
                                            ->extraInputAttributes(['min' => 1, 'step' => 1])
                                            ->columnSpan(2),

                                        TextInput::make('price_per_unit')
                                            ->label('Price / Session')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateAllFromItem($set, $get))
                                            ->extraInputAttributes(['min' => 1, 'step' => 1])
                                            ->columnSpan(2),

                                        TextInput::make('total_amount')
                                            ->label('Item Total')
                                            ->prefix('₹')
                                            ->disabled()
                                            ->dehydrated()
                                            ->numeric()
                                            ->columnSpan(3),

                                        // Hidden snapshot field
                                        Hidden::make('service_snapshot')->dehydrated(),
                                    ]),
                                ])
                                ->defaultItems(1)
                                ->minItems(1)
                                ->addActionLabel('+ Add Service')
                                ->addAction(
                                    fn($action) => $action
                                        ->before(function (Repeater $component, $action) {
                                            $items = $component->getState();
                                            foreach ($items as $item) {
                                                if (empty($item['service_id']) || empty($item['quantity']) || (int) $item['quantity'] < 1) {
                                                    Notification::make()
                                                        ->title('Incomplete Service')
                                                        ->body('Please select a service and enter sessions for all existing items before adding a new one.')
                                                        ->warning()
                                                        ->send();
                                                    $action->halt();
                                                    return;
                                                }
                                            }
                                        })
                                )
                                ->reorderable(false)
                                ->collapsible()
                                ->deleteAction(
                                    fn($action) => $action->hidden(
                                        fn(Repeater $component): bool => count($component->getState()) <= 1
                                    )
                                )
                                ->itemLabel(
                                    fn(array $state): ?string =>
                                    ($state['service_id'] ?? null)
                                    ? Product::find($state['service_id'])?->name ?? 'Service'
                                    : 'New Service'
                                )
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculatePackageTotals($set, $get);
                                })
                                ->columnSpanFull(),
                        ]),

                    // ─── Discount ───────────────────────────────────────────
                    Section::make('Discount')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('discount_type')
                                    ->label('Discount Type')
                                    ->options(PackageDiscountType::class)
                                    ->default(PackageDiscountType::Percentage->value)
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        self::recalculatePackageTotals($set, $get)
                                    ),

                                TextInput::make('discount_value')
                                    ->label('Discount Value')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        self::recalculatePackageTotals($set, $get)
                                    )
                                    ->suffix(fn(Get $get) => ($get('discount_type') instanceof PackageDiscountType ? $get('discount_type')->value : $get('discount_type')) === 'percentage' ? '%' : '₹'),

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
                    Section::make('Package Summary')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Placeholder::make('services_count_display')
                                ->label('Total Services')
                                ->content(function (Get $get) {
                                    $items = $get('items') ?? [];
                                    $count = count(array_filter($items, fn($i) => !empty($i['service_id'])));
                                    return new HtmlString('<span class="text-lg font-bold text-primary-600">' . $count . ' service(s)</span>');
                                }),

                            Placeholder::make('total_sessions_display')
                                ->label('Total Sessions')
                                ->content(function (Get $get) {
                                    $items = $get('items') ?? [];
                                    $total = collect($items)->sum(fn($i) => (int) ($i['quantity'] ?? 0));
                                    return new HtmlString('<span class="text-lg font-bold text-info-600">' . $total . ' sessions</span>');
                                }),

                            TextInput::make('subtotal')
                                ->label('Subtotal')
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

                    Section::make('Usage Overview')
                        ->icon('heroicon-o-chart-bar')
                        ->visible(fn($record) => $record !== null)
                        ->schema([
                            Placeholder::make('usage_stats')
                                ->label('')
                                ->content(function ($record) {
                                    if (!$record) {
                                        return 'No usage data yet.';
                                    }

                                    $record->load('items.service');
                                    $rows = $record->items->map(function ($item) {
                                        $serviceName = $item->service?->name ?? ($item->service_snapshot['name'] ?? 'Unknown');
                                        $remaining = $item->getRemainingSessions();
                                        $color = $remaining > 0 ? 'text-success-600' : 'text-danger-600';
                                        return "<div class='flex justify-between py-1 border-b border-gray-200 dark:border-gray-700'>
                                            <span class='text-sm font-medium'>{$serviceName}</span>
                                            <span class='text-sm'>
                                                <span class='text-warning-600'>{$item->used_sessions}</span>
                                                / {$item->quantity}
                                                (<span class='{$color}'>{$remaining} left</span>)
                                            </span>
                                        </div>";
                                    })->join('');

                                    return new HtmlString("<div class='space-y-1'>{$rows}</div>");
                                }),
                        ]),
                ])
                ->columnSpan(['lg' => 1]),
        ])
            ->columns(3);
    }

    /**
     * Recalculate a single item's total (price × quantity).
     */
    public static function recalculateItemTotal(Set $set, Get $get): void
    {
        $price = (float) ($get('price_per_unit') ?? 0);
        $qty = (int) ($get('quantity') ?? 1);
        $total = $price * $qty;

        $set('total_amount', number_format($total, 2, '.', ''));
    }

    /**
     * Called from INSIDE a repeater item field.
     * Recalculates both the current item total AND the package-level totals.
     * Uses relative paths: ../../ = all items, ../../../ = form root.
     */
    public static function recalculateAllFromItem(Set $set, Get $get): void
    {
        // 1. Recalculate current item total
        $price = (float) ($get('price_per_unit') ?? 0);
        $qty = (int) ($get('quantity') ?? 1);
        $currentItemTotal = $price * $qty;
        $set('total_amount', number_format($currentItemTotal, 2, '.', ''));

        // 2. Recalculate package subtotal from ALL items
        //    ../../ navigates from field → item → repeater (array of all items)
        $allItems = $get('../../') ?? [];
        $subtotal = collect($allItems)->sum(function ($item) {
            return (float) ($item['price_per_unit'] ?? 0) * (int) ($item['quantity'] ?? 1);
        });

        // 3. Apply package-level discount
        //    ../../../ navigates from field → item → repeater → form root
        $discountType = $get('../../../discount_type') ?? 'flat';
        $discountValue = (float) ($get('../../../discount_value') ?? 0);

        $type = $discountType instanceof PackageDiscountType ? $discountType->value : $discountType;

        $discountAmount = $type === 'percentage'
            ? $subtotal * ($discountValue / 100)
            : $discountValue;

        $discountAmount = min($discountAmount, $subtotal);
        $finalAmount = max(0, $subtotal - $discountAmount);

        $set('../../../subtotal', number_format($subtotal, 2, '.', ''));
        $set('../../../discount_amount', number_format($discountAmount, 2, '.', ''));
        $set('../../../final_amount', number_format($finalAmount, 2, '.', ''));
    }

    /**
     * Called from form-root-level fields (discount type/value, repeater add/remove).
     * Recalculates package-level totals: subtotal, discount_amount, final_amount.
     */
    public static function recalculatePackageTotals(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];
        $subtotal = collect($items)->sum(function ($item) {
            return (float) ($item['price_per_unit'] ?? 0) * (int) ($item['quantity'] ?? 1);
        });

        $discountType = $get('discount_type') ?? 'flat';
        $discountValue = (float) ($get('discount_value') ?? 0);

        $type = $discountType instanceof PackageDiscountType ? $discountType->value : $discountType;

        $discountAmount = $type === 'percentage'
            ? $subtotal * ($discountValue / 100)
            : $discountValue;

        $discountAmount = min($discountAmount, $subtotal);
        $finalAmount = max(0, $subtotal - $discountAmount);

        $set('subtotal', number_format($subtotal, 2, '.', ''));
        $set('discount_amount', number_format($discountAmount, 2, '.', ''));
        $set('final_amount', number_format($finalAmount, 2, '.', ''));
    }
}
