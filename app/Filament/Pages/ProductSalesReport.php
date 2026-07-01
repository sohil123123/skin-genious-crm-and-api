<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use App\Filament\ReportWidgets\ProductSalesChart;
use App\Filament\ReportWidgets\ProductSalesDistributionChart;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action as TableAction;
use App\Models\InvoiceItem;
use Filament\Schemas\Components\Grid;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Filament\Traits\HasReportDateFilters;

class ProductSalesReport extends Page implements HasTable, HasForms
{
    use HasPageShield;
    use InteractsWithTable;
    use InteractsWithForms;
    use HasReportDateFilters;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected string $view = 'filament.pages.product-sales-report';

    protected static ?string $title = 'Sales Reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 18;

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
            productType: $this->productType
        );
        $this->resetTable();
    }

    protected function showProductTypeFilter(): bool
    {
        return true;
    }

    public function getMiddleWidgets(): array
    {
        return [
            ProductSalesChart::class,
            ProductSalesDistributionChart::class,
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
        $filename = 'product-sales-report-' . \Illuminate\Support\Carbon::parse($this->startDate ?? now())->format('d-m-Y') . '-to-' . \Illuminate\Support\Carbon::parse($this->endDate ?? now())->format('d-m-Y') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductSalesReportExport($this->startDate, $this->endDate, $this->productType),
            $filename
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Product::query()
                    ->when($this->productType, fn(Builder $q) => $q->where('type', $this->productType))
                    ->withSum([
                        'invoiceItems' => function (Builder $query) {
                            $query->whereHas('invoice', function (Builder $q) {
                                $q->where('status', '!=', 'cancelled');
                                if ($this->startDate) {
                                    $q->whereDate('invoice_date', '>=', $this->startDate);
                                }
                                if ($this->endDate) {
                                    $q->whereDate('invoice_date', '<=', $this->endDate);
                                }
                            });
                        }
                    ], 'quantity')
                    ->withSum([
                        'invoiceItems' => function (Builder $query) {
                            $query->whereHas('invoice', function (Builder $q) {
                                $q->where('status', '!=', 'cancelled');
                                if ($this->startDate) {
                                    $q->whereDate('invoice_date', '>=', $this->startDate);
                                }
                                if ($this->endDate) {
                                    $q->whereDate('invoice_date', '<=', $this->endDate);
                                }
                            });
                        }
                    ], 'line_total')
                    ->having('invoice_items_sum_quantity', '>', 0);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'product' => 'info',
                        'service' => 'success',
                        'iv_product' => 'warning',
                    }),
                TextColumn::make('invoice_items_sum_quantity')
                    ->label('Quantity Sold')
                    ->badge()
                    ->numeric()
                    ->sortable()
                    ->default(0)
                    ->summarize(Sum::make()->label('Total Quantity')),
                TextColumn::make('invoice_items_sum_line_total')
                    ->label('Total Revenue')
                    ->money('INR')
                    ->sortable()
                    ->default(0)
                    ->summarize(Sum::make()->label('Total Revenue')->money('INR')),
            ])
            ->actions([
                TableAction::make('view_users')
                    ->label('Top Clients')
                    ->icon('heroicon-o-users')
                    ->tooltip('View purchasing clients')
                    ->color('info')
                    ->modalHeading(fn(Product $record) => "Purchasing Clients: " . ucfirst($record->name))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl')
                    ->modalContent(function (Product $record) {
                        $invoiceItems = InvoiceItem::query()
                            ->with(['invoice.client'])
                            ->where('product_id', $record->id)
                            ->whereHas('invoice', function ($q) {
                                $q->where('status', '!=', 'cancelled');
                                if ($this->startDate) {
                                    $q->whereDate('invoice_date', '>=', $this->startDate);
                                }
                                if ($this->endDate) {
                                    $q->whereDate('invoice_date', '<=', $this->endDate);
                                }
                            })
                            ->get();

                        $clients = $invoiceItems->groupBy(fn($item) => $item->invoice?->user_id)
                            ->map(function ($items) {
                                $firstItem = $items->first();
                                $client = $firstItem->invoice?->client;
                                return [
                                    'name' => $client?->name ?? 'N/A',
                                    'mobile' => $client?->mobile ?? 'N/A',
                                    'email' => $client?->email ?? 'N/A',
                                    'total_qty' => $items->sum('quantity'),
                                    'total_spent' => $items->sum('line_total'),
                                ];
                            })
                            ->sortByDesc('total_qty')
                            ->values();

                        return view('filament.pages.actions.product-users', [
                            'clients' => $clients,
                        ]);
                    })
            ])
            ->defaultSort('invoice_items_sum_line_total', 'desc');
    }
}
