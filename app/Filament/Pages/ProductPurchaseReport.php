<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use App\Filament\ReportWidgets\ProductPurchaseChart;
use App\Filament\ReportWidgets\ProductPurchaseDistributionChart;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Grid;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ProductPurchaseReport extends Page implements HasTable, HasForms
{
    use HasPageShield;
    use InteractsWithTable;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected string $view = 'filament.pages.product-purchase-report';
    
    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $clinicId = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
        $this->form->fill([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'clinicId' => null,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(4)
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->startDate = $state;
                                $this->dispatch('updateReportDates', 
                                    startDate: $this->startDate, 
                                    endDate: $this->endDate ?? now()->endOfMonth()->toDateString(),
                                    clinicId: $this->clinicId
                                );
                            }),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->endDate = $state;
                                $this->dispatch('updateReportDates', 
                                    startDate: $this->startDate ?? now()->startOfMonth()->toDateString(), 
                                    endDate: $this->endDate,
                                    clinicId: $this->clinicId
                                );
                            }),
                        \Filament\Forms\Components\Select::make('clinicId')
                            ->label('Clinic')
                            ->options(\App\Models\Clinic::pluck('name', 'id'))
                            ->placeholder('All Clinics')
                            ->searchable()
                            ->reactive()
                            ->visible(fn () => auth()->user()->hasRole('super_admin'))
                            ->afterStateUpdated(function ($state) {
                                $this->clinicId = $state;
                                $this->dispatch('updateReportDates', 
                                    startDate: $this->startDate ?? now()->startOfMonth()->toDateString(), 
                                    endDate: $this->endDate ?? now()->endOfMonth()->toDateString(),
                                    clinicId: $this->clinicId
                                );
                            }),
                    ]),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProductPurchaseChart::class,
            ProductPurchaseDistributionChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 2;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Product::query()
                    ->whereIn('type', ['product', 'iv_product'])
                    ->withSum(['purchaseItems as total_purchased_qty' => function (Builder $query) {
                        $query->whereHas('purchase', function ($q) {
                            if ($this->startDate) {
                                $q->whereDate('purchase_date', '>=', $this->startDate);
                            }
                            if ($this->endDate) {
                                $q->whereDate('purchase_date', '<=', $this->endDate);
                            }
                            if ($this->clinicId) {
                                $q->where('clinic_id', $this->clinicId);
                            } elseif (!auth()->user()->hasRole('super_admin')) {
                                $q->where('clinic_id', auth()->user()->clinic_id);
                            }
                        });
                    }], 'quantity')
                    ->withSum(['purchaseItems as actual_cost' => function (Builder $query) {
                        $query->whereHas('purchase', function ($q) {
                            if ($this->startDate) {
                                $q->whereDate('purchase_date', '>=', $this->startDate);
                            }
                            if ($this->endDate) {
                                $q->whereDate('purchase_date', '<=', $this->endDate);
                            }
                            if ($this->clinicId) {
                                $q->where('clinic_id', $this->clinicId);
                            } elseif (!auth()->user()->hasRole('super_admin')) {
                                $q->where('clinic_id', auth()->user()->clinic_id);
                            }
                        });
                    }], 'total')
                    ->withSum(['clinicInventories as current_stock' => function (Builder $query) {
                        if ($this->clinicId) {
                            $query->where('clinic_id', $this->clinicId);
                        } elseif (!auth()->user()->hasRole('super_admin')) {
                            $query->where('clinic_id', auth()->user()->clinic_id);
                        }
                    }], 'stock_quantity');
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->state(fn (Product $record) => "{$record->name} (" . str_replace('_', ' ', $record->type) . ")")
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_purchased_qty')
                    ->label('Quantity Purchased')
                    ->numeric()
                    ->sortable()
                    ->default(0),
                TextColumn::make('actual_cost')
                    ->label('Total Cost')
                    ->money('INR')
                    ->sortable()
                    ->default(0),
                TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->default(0),
            ])
            ->defaultSort('name');
    }
}
