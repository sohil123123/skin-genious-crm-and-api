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

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
        $this->form->fill([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->startDate = $state;
                                $this->dispatch('updateReportDates', startDate: $this->startDate, endDate: $this->endDate ?? now()->endOfMonth()->toDateString());
                            }),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->endDate = $state;
                                $this->dispatch('updateReportDates', startDate: $this->startDate ?? now()->startOfMonth()->toDateString(), endDate: $this->endDate);
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
                    ->withSum(['transactions' => function (Builder $query) {
                        $query->where('type', 'purchase');
                        if ($this->startDate) {
                            $query->whereDate('created_at', '>=', $this->startDate);
                        }
                        if ($this->endDate) {
                            $query->whereDate('created_at', '<=', $this->endDate);
                        }
                    }], 'quantity');
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transactions_sum_quantity')
                    ->label('Quantity Purchased')
                    ->numeric()
                    ->sortable()
                    ->default(0),
                TextColumn::make('estimated_cost')
                    ->label('Estimated Cost')
                    ->money('INR')
                    ->state(fn (Product $record) => ($record->transactions_sum_quantity ?? 0) * $record->purchase_price)
                    ->tooltip('Calculated based on current purchase price'),
                TextColumn::make('stock')
                    ->label('Current Stock')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('name');
    }
}
