<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\UserPackageItem;
use App\Exports\ProductSalesReportExport;
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

    protected static ?int $navigationSort = 17;

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
            new ProductSalesReportExport($this->startDate, $this->endDate, $this->productType, $this->clinicId),
            $filename
        );
    }

    public static function getSalesReportQuery(?string $startDate, ?string $endDate, ?int $clinicId = null, ?string $productType = null): Builder
    {
        if ($clinicId === null && !check_role('super_admin') && auth()->check()) {
            $clinicId = auth()->user()->clinic_id;
        }

        $invoicePaymentsQtySubquery = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('invoice_payments', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('invoices.invoice_type')
                  ->orWhere('invoices.invoice_type', '!=', 'package');
            })
            ->whereNull('invoices.package_id')
            ->where('invoice_items.product_id', '=', DB::raw('products.id'))
            ->when($startDate, fn($q) => $q->whereDate('invoice_payments.payment_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('invoice_payments.payment_date', '<=', $endDate))
            ->when($clinicId, fn($q) => $q->where('invoices.clinic_id', $clinicId));

        $invoicePaymentsRevenueSubquery = clone $invoicePaymentsQtySubquery;

        $invoicePaymentsQtySubquery->selectRaw('COALESCE(SUM(invoice_items.quantity * COALESCE(invoice_payments.amount / NULLIF(invoices.grand_total, 0), 1)), 0)');
        $invoicePaymentsRevenueSubquery->selectRaw('COALESCE(SUM(invoice_items.line_total * COALESCE(invoice_payments.amount / NULLIF(invoices.grand_total, 0), 1)), 0)');

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
                (({$packageQtySubquery->toSql()}) + ({$invoicePaymentsQtySubquery->toSql()})) as sales_qty_sold
            ", array_merge(
                $packageQtySubquery->getBindings(),
                $invoicePaymentsQtySubquery->getBindings()
            ))
            ->selectRaw("
                (({$packageRevenueSubquery->toSql()}) + ({$invoicePaymentsRevenueSubquery->toSql()})) as sales_revenue
            ", array_merge(
                $packageRevenueSubquery->getBindings(),
                $invoicePaymentsRevenueSubquery->getBindings()
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

        // Standard invoice payments revenue (excluding package invoices)
        $invoiceRevenue = DB::table('invoice_payments')
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('invoices.invoice_type')
                  ->orWhere('invoices.invoice_type', '!=', 'package');
            })
            ->whereNull('invoices.package_id')
            ->whereDate('invoice_payments.payment_date', '>=', $start)
            ->whereDate('invoice_payments.payment_date', '<=', $end)
            ->when($clinicId, fn($q) => $q->where('invoices.clinic_id', $clinicId))
            ->sum('invoice_payments.amount');

        // Package revenue from packages created in date range
        $packageRevenue = DB::table('user_package_items')
            ->join('user_packages', 'user_package_items.user_package_id', '=', 'user_packages.id')
            ->whereDate('user_packages.created_at', '>=', $start)
            ->whereDate('user_packages.created_at', '<=', $end)
            ->when($clinicId, fn($q) => $q->where('user_packages.clinic_id', $clinicId))
            ->sum(DB::raw('user_package_items.total_amount * COALESCE(user_packages.final_amount / NULLIF(user_packages.subtotal, 0), 1)'));

        return (float) $invoiceRevenue + (float) $packageRevenue;
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
                    ->summarize(Sum::make()->label('Total Quantity')),
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
                        $clinicId = $this->clinicId;
                        if (!$clinicId && !check_role('super_admin') && auth()->check()) {
                            $clinicId = auth()->user()->clinic_id;
                        }

                        $packageItems = UserPackageItem::query()
                            ->with(['package.user'])
                            ->where('service_id', $record->id)
                            ->whereHas('package', function ($q) use ($clinicId) {
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

                        $invoicePayments = InvoicePayment::query()
                            ->with(['invoice.client', 'invoice.items'])
                            ->whereHas('invoice', function ($q) use ($clinicId, $record) {
                                $q->where('status', '!=', 'cancelled');
                                $q->where(function ($sub) {
                                    $sub->whereNull('invoice_type')
                                        ->orWhere('invoice_type', '!=', 'package');
                                });
                                $q->whereNull('package_id');
                                if ($clinicId) {
                                    $q->where('clinic_id', $clinicId);
                                }
                                $q->whereHas('items', fn($itemQ) => $itemQ->where('product_id', $record->id));
                            })
                            ->when($this->startDate, fn($q) => $q->whereDate('payment_date', '>=', $this->startDate))
                            ->when($this->endDate, fn($q) => $q->whereDate('payment_date', '<=', $this->endDate))
                            ->get();

                        $clientData = [];

                        foreach ($packageItems as $item) {
                            $package = $item->package;
                            $client = $package?->user;
                            $userId = $package?->user_id;

                            if (!$userId) continue;

                            $spent = (float) $item->total_amount * ($package->subtotal > 0 ? ((float) $package->final_amount / (float) $package->subtotal) : 1.0);

                            if (!isset($clientData[$userId])) {
                                $clientData[$userId] = [
                                    'name' => $client?->name ?? 'N/A',
                                    'mobile' => $client?->mobile ?? 'N/A',
                                    'email' => $client?->email ?? 'N/A',
                                    'total_qty' => 0,
                                    'total_spent' => 0.0,
                                ];
                            }
                            $clientData[$userId]['total_qty'] += (int) $item->quantity;
                            $clientData[$userId]['total_spent'] += $spent;
                        }

                        foreach ($invoicePayments as $payment) {
                            $invoice = $payment->invoice;
                            $client = $invoice?->client;
                            $userId = $invoice?->user_id;

                            if (!$userId || !$invoice) continue;

                            $grandTotal = (float) ($invoice->grand_total > 0 ? $invoice->grand_total : 1.0);
                            $paymentRatio = (float) $payment->amount / $grandTotal;

                            foreach ($invoice->items as $item) {
                                if ($item->product_id != $record->id) continue;

                                $itemSpent = (float) $item->line_total * $paymentRatio;
                                $itemQty = (float) $item->quantity * $paymentRatio;

                                if (!isset($clientData[$userId])) {
                                    $clientData[$userId] = [
                                        'name' => $client?->name ?? 'N/A',
                                        'mobile' => $client?->mobile ?? 'N/A',
                                        'email' => $client?->email ?? 'N/A',
                                        'total_qty' => 0,
                                        'total_spent' => 0.0,
                                    ];
                                }
                                $clientData[$userId]['total_qty'] += $itemQty;
                                $clientData[$userId]['total_spent'] += $itemSpent;
                            }
                        }

                        $clients = collect($clientData)->sortByDesc('total_spent')->values();

                        return view('filament.pages.actions.product-users', [
                            'clients' => $clients,
                        ]);
                    })
            ])
            ->defaultSort('sales_revenue', 'desc');
    }
}


