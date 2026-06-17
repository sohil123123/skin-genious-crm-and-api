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
use App\Filament\ReportWidgets\ProductSalesChart;
use App\Filament\ReportWidgets\ProductSalesDistributionChart;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Grid;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Filament\Traits\HasReportDateFilters;

class ProductSalesReport extends Page implements HasTable, HasForms
{
    use HasPageShield;
    use InteractsWithTable;
    use InteractsWithForms;
    use HasReportDateFilters;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected string $view = 'filament.pages.product-sales-report';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

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
        $this->dispatch('updateReportDates', startDate: $this->startDate ?? now()->startOfMonth()->toDateString(), endDate: $this->endDate ?? now()->endOfMonth()->toDateString());
        $this->resetTable();
    }

    public function getMiddleWidgets(): array
    {
        return [
            ProductSalesChart::class,
            ProductSalesDistributionChart::class,
        ];
    }

    public function getMiddleWidgetsColumns(): int | array
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
        $filename = 'product-sales-report-' . \Illuminate\Support\Carbon::parse($this->startDate ?? now())->format('d-m-Y') . '-to-' . \Illuminate\Support\Carbon::parse($this->endDate ?? now())->format('d-m-Y') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductSalesReportExport($this->startDate, $this->endDate),
            $filename
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Product::query()
                    ->withSum(['invoiceItems' => function (Builder $query) {
                        $query->whereHas('invoice', function (Builder $q) {
                            if ($this->startDate) {
                                $q->whereDate('invoice_date', '>=', $this->startDate);
                            }
                            if ($this->endDate) {
                                $q->whereDate('invoice_date', '<=', $this->endDate);
                            }
                        });
                    }], 'quantity')
                    ->withSum(['invoiceItems' => function (Builder $query) {
                        $query->whereHas('invoice', function (Builder $q) {
                            if ($this->startDate) {
                                $q->whereDate('invoice_date', '>=', $this->startDate);
                            }
                            if ($this->endDate) {
                                $q->whereDate('invoice_date', '<=', $this->endDate);
                            }
                        });
                    }], 'line_total')
                    ->having('invoice_items_sum_quantity', '>', 0);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_items_sum_quantity')
                    ->label('Quantity Sold')
                    ->numeric()
                    ->sortable()
                    ->default(0),
                TextColumn::make('invoice_items_sum_line_total')
                    ->label('Total Revenue')
                    ->money('INR')
                    ->sortable()
                    ->default(0),
            ])
            ->defaultSort('invoice_items_sum_line_total', 'desc');
    }
}
