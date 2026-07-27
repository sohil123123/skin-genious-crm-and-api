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
use App\Filament\Traits\HasReportDateFilters;

class ProductPurchaseReport extends Page implements HasTable, HasForms
{
    use HasPageShield;
    use InteractsWithTable;
    use InteractsWithForms;
    use HasReportDateFilters;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected string $view = 'filament.pages.product-purchase-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 19;

    public function mount(): void
    {
        $this->initReportFilters();
        $this->form->fill($this->getReportFiltersFormData());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema($this->getReportFilterSchema());
    }

    public function onReportFilterUpdated(): void
    {
        $this->dispatch(
            'updateReportDates',
            startDate: $this->startDate ?? now()->startOfMonth()->toDateString(),
            endDate: $this->endDate ?? now()->endOfMonth()->toDateString(),
            clinicId: $this->clinicId
        );
        $this->resetTable();
    }

    public function getMiddleWidgets(): array
    {
        return [
            ProductPurchaseChart::class,
            ProductPurchaseDistributionChart::class,
        ];
    }

    public function getMiddleWidgetsColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_excel')
                ->label('Export Excel (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportExcel'),
        ];
    }

    public function exportExcel()
    {
        $filename = 'product-purchase-report-' . \Illuminate\Support\Carbon::parse($this->startDate ?? now())->format('d-m-Y') . '-to-' . \Illuminate\Support\Carbon::parse($this->endDate ?? now())->format('d-m-Y') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductPurchaseReportExport($this->startDate, $this->endDate, $this->clinicId),
            $filename
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Product::query()
                    ->whereIn('type', ['product', 'iv_product'])
                    ->withSum([
                        'purchaseItems as total_purchased_qty' => function (Builder $query) {
                            $query->whereHas('purchase', function ($q) {
                                if ($this->startDate) {
                                    $q->whereDate('purchase_date', '>=', $this->startDate);
                                }
                                if ($this->endDate) {
                                    $q->whereDate('purchase_date', '<=', $this->endDate);
                                }
                                if ($this->clinicId) {
                                    $q->where('clinic_id', $this->clinicId);
                                } elseif (!check_role('super_admin')) {
                                    $q->where('clinic_id', auth()->user()->clinic_id);
                                }
                            });
                        }
                    ], 'quantity')
                    ->withSum([
                        'purchaseItems as actual_cost' => function (Builder $query) {
                            $query->whereHas('purchase', function ($q) {
                                if ($this->startDate) {
                                    $q->whereDate('purchase_date', '>=', $this->startDate);
                                }
                                if ($this->endDate) {
                                    $q->whereDate('purchase_date', '<=', $this->endDate);
                                }
                                if ($this->clinicId) {
                                    $q->where('clinic_id', $this->clinicId);
                                } elseif (!check_role('super_admin')) {
                                    $q->where('clinic_id', auth()->user()->clinic_id);
                                }
                            });
                        }
                    ], 'total')
                    ->withSum([
                        'clinicInventories as current_stock' => function (Builder $query) {
                            if ($this->clinicId) {
                                $query->where('clinic_id', $this->clinicId);
                            } elseif (!check_role('super_admin')) {
                                $query->where('clinic_id', auth()->user()->clinic_id);
                            }
                        }
                    ], 'stock_quantity')
                    ->having('total_purchased_qty', '>', 0);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    // ->state(fn(Product $record) => "{$record->name} (" . str_replace('_', ' ', $record->type) . ")")
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
            ->defaultSort('total_purchased_qty', 'desc');
    }
}
