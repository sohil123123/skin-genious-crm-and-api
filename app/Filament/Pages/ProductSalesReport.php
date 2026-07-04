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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action as TableAction;
use Filament\Actions\Action as HeaderAction;
use App\Models\InvoiceItem;
use Filament\Schemas\Components\Grid;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Filament\Traits\HasReportDateFilters;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

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
            productType: $this->productType,
            clinicId: $this->clinicId
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
            HeaderAction::make('export_excel')
                ->label('Export Excel (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportExcel'),
        ];
    }

    public function exportExcel()
    {
        $filename = 'product-sales-report-' . Carbon::parse($this->startDate ?? now())->format('d-m-Y') . '-to-' . Carbon::parse($this->endDate ?? now())->format('d-m-Y') . '.xlsx';

        return Excel::download(
            new \App\Exports\ProductSalesReportExport($this->startDate, $this->endDate, $this->productType, $this->clinicId),
            $filename
        );
    }

    public static function getSalesReportQuery(?string $startDate, ?string $endDate, ?int $clinicId = null, ?string $productType = null): Builder
    {
        if ($clinicId === null && !check_role('super_admin') && auth()->check()) {
            $clinicId = auth()->user()->clinic_id;
        }

        $invoiceQtySubquery = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoice_items.product_id', '=', DB::raw('products.id'))
            ->when($startDate, fn($q) => $q->whereDate('invoices.invoice_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('invoices.invoice_date', '<=', $endDate))
            ->when($clinicId, fn($q) => $q->where('invoices.clinic_id', $clinicId));

        $invoiceRevenueSubquery = clone $invoiceQtySubquery;

        $invoiceQtySubquery->selectRaw('COALESCE(SUM(invoice_items.quantity), 0)');
        $invoiceRevenueSubquery->selectRaw('COALESCE(SUM(invoice_items.line_total), 0)');

        $packageQtySubquery = DB::table('user_package_items')
            ->join('user_packages', 'user_package_items.user_package_id', '=', 'user_packages.id')
            ->where('user_package_items.service_id', '=', DB::raw('products.id'))
            ->when($startDate, fn($q) => $q->whereDate('user_packages.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('user_packages.created_at', '<=', $endDate))
            ->when($clinicId, fn($q) => $q->where('user_packages.clinic_id', $clinicId));

        $packageRevenueSubquery = clone $packageQtySubquery;

        $packageQtySubquery->selectRaw('COALESCE(SUM(user_package_items.quantity), 0)');
        $packageRevenueSubquery->selectRaw('COALESCE(SUM(user_package_items.total_amount * COALESCE(user_packages.final_amount / NULLIF(user_packages.subtotal, 0), 1)), 0)');

        $baseQuery = Product::query()
            ->select('products.*')
            ->selectRaw("
                CASE
                    WHEN products.type = 'service' THEN ({$packageQtySubquery->toSql()})
                    ELSE ({$invoiceQtySubquery->toSql()})
                END as sales_qty_sold
            ", array_merge(
                $packageQtySubquery->getBindings(),
                $invoiceQtySubquery->getBindings()
            ))
            ->selectRaw("
                CASE
                    WHEN products.type = 'service' THEN ({$packageRevenueSubquery->toSql()})
                    ELSE ({$invoiceRevenueSubquery->toSql()})
                END as sales_revenue
            ", array_merge(
                $packageRevenueSubquery->getBindings(),
                $invoiceRevenueSubquery->getBindings()
            ))
            ->when($productType, fn($q) => $q->where('products.type', $productType));

        return Product::query()
            ->fromSub($baseQuery, 'products')
            ->where('sales_revenue', '>', 0);
    }

    private function calculateRevenueForRange(string $start, string $end): float
    {
        $clinicId = $this->clinicId;
        if (!$clinicId && !check_role('super_admin') && auth()->check()) {
            $clinicId = auth()->user()->clinic_id;
        }

        // Product/IV Product revenue from invoices
        $productRevenue = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereIn('products.type', ['product', 'iv_product'])
            ->whereDate('invoices.invoice_date', '>=', $start)
            ->whereDate('invoices.invoice_date', '<=', $end)
            ->when($clinicId, fn($q) => $q->where('invoices.clinic_id', $clinicId))
            ->sum('invoice_items.line_total');

        // Service revenue from packages
        $serviceRevenue = DB::table('user_package_items')
            ->join('user_packages', 'user_package_items.user_package_id', '=', 'user_packages.id')
            ->join('products', 'user_package_items.service_id', '=', 'products.id')
            ->where('products.type', '=', 'service')
            ->whereDate('user_packages.created_at', '>=', $start)
            ->whereDate('user_packages.created_at', '<=', $end)
            ->when($clinicId, fn($q) => $q->where('user_packages.clinic_id', $clinicId))
            ->sum(DB::raw('user_package_items.total_amount * COALESCE(user_packages.final_amount / NULLIF(user_packages.subtotal, 0), 1)'));

        return (float) $productRevenue + (float) $serviceRevenue;
    }

    public function getSummaryData(): array
    {
        $todayStart = now()->toDateString();
        $todayEnd = now()->toDateString();

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $yearStart = now()->startOfYear()->toDateString();
        $yearEnd = now()->endOfYear()->toDateString();

        return [
            'today' => $this->calculateRevenueForRange($todayStart, $todayEnd),
            'this_week' => $this->calculateRevenueForRange($weekStart, $weekEnd),
            'this_month' => $this->calculateRevenueForRange($monthStart, $monthEnd),
            'this_year' => $this->calculateRevenueForRange($yearStart, $yearEnd),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return self::getSalesReportQuery($this->startDate, $this->endDate, $this->clinicId, $this->productType);
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
                TextColumn::make('sales_qty_sold')
                    ->label('Quantity Sold')
                    ->badge()
                    ->sortable()
                    ->placeholder('')
                    ->summarize(Sum::make()->label('Total Quantity')->query(fn(QueryBuilder $query) => $query->where('type', 'product'))),
                TextColumn::make('sales_revenue')
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
                        if ($record->type === 'service') {
                            $packageItems = \App\Models\UserPackageItem::query()
                                ->with(['package.user'])
                                ->where('service_id', $record->id)
                                ->whereHas('package', function ($q) {
                                    $clinicId = $this->clinicId;
                                    if (!$clinicId && !check_role('super_admin') && auth()->check()) {
                                        $clinicId = auth()->user()->clinic_id;
                                    }
                                    if ($clinicId) {
                                        $q->where('clinic_id', $clinicId);
                                    }
                                    if ($this->startDate) {
                                        $q->whereDate('created_at', '>=', $this->startDate);
                                    }
                                    if ($this->endDate) {
                                        $q->whereDate('created_at', '<=', $this->endDate);
                                    }
                                })
                                ->get();

                            $clients = $packageItems->groupBy(fn($item) => $item->package?->user_id)
                                ->map(function ($items) {
                                    $firstItem = $items->first();
                                    $client = $firstItem->package?->user;
                                    $package = $firstItem->package;
                                    $totalSpent = $items->sum(
                                        fn($item) =>
                                        (float) $item->total_amount * ($package->subtotal > 0 ? ((float) $package->final_amount / (float) $package->subtotal) : 1.0)
                                    );

                                    return [
                                        'name' => $client?->name ?? 'N/A',
                                        'mobile' => $client?->mobile ?? 'N/A',
                                        'email' => $client?->email ?? 'N/A',
                                        'total_qty' => $items->sum('quantity'),
                                        'total_spent' => $totalSpent,
                                    ];
                                })
                                ->sortByDesc('total_qty')
                                ->values();
                        } else {
                            $invoiceItems = InvoiceItem::query()
                                ->with(['invoice.client'])
                                ->where('product_id', $record->id)
                                ->whereHas('invoice', function ($q) {
                                    $q->where('status', '!=', 'cancelled');
                                    $clinicId = $this->clinicId;
                                    if (!$clinicId && !check_role('super_admin') && auth()->check()) {
                                        $clinicId = auth()->user()->clinic_id;
                                    }
                                    if ($clinicId) {
                                        $q->where('clinic_id', $clinicId);
                                    }
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
                        }

                        return view('filament.pages.actions.product-users', [
                            'clients' => $clients,
                        ]);
                    })
            ])
            ->defaultSort('sales_revenue', 'desc');
    }
}
