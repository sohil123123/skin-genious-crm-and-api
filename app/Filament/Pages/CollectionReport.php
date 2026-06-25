<?php

namespace App\Filament\Pages;

use App\Models\InvoicePayment;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use App\Filament\ReportWidgets\CollectionChart;
use App\Filament\ReportWidgets\CollectionDistributionChart;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action as HeaderAction;
use Filament\Actions\Action as TableAction;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Filament\Traits\HasReportDateFilters;
use App\Exports\CollectionReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;

class CollectionReport extends Page implements HasTable, HasForms
{
    use HasPageShield;
    use InteractsWithTable;
    use InteractsWithForms;
    use HasReportDateFilters;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.pages.collection-report';

    protected static ?string $title = 'Collection Report';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

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
            CollectionChart::class,
            CollectionDistributionChart::class,
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
        $filename = 'collection-report-' . Carbon::parse($this->startDate ?? now())->format('d-m-Y') . '-to-' . Carbon::parse($this->endDate ?? now())->format('d-m-Y') . '.xlsx';

        return Excel::download(
            new CollectionReportExport($this->startDate, $this->endDate, $this->clinicId),
            $filename
        );
    }

    public function getSummaryData(): array
    {
        $baseQuery = function () {
            return InvoicePayment::query()
                ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
                ->where('invoices.status', '!=', 'cancelled')
                ->where(function (Builder $query) {
                    if ($this->clinicId) {
                        $query->where('invoices.clinic_id', $this->clinicId);
                    } elseif (!check_role('super_admin')) {
                        $query->where('invoices.clinic_id', auth()->user()->clinic_id);
                    }
                });
        };

        $today = $baseQuery()
            ->whereDate('invoice_payments.payment_date', now()->toDateString())
            ->sum('invoice_payments.amount');

        $thisWeek = $baseQuery()
            ->whereDate('invoice_payments.payment_date', '>=', now()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->whereDate('invoice_payments.payment_date', '<=', now()->endOfWeek(Carbon::SUNDAY)->toDateString())
            ->sum('invoice_payments.amount');

        $thisMonth = $baseQuery()
            ->whereDate('invoice_payments.payment_date', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('invoice_payments.payment_date', '<=', now()->endOfMonth()->toDateString())
            ->sum('invoice_payments.amount');

        $thisYear = $baseQuery()
            ->whereDate('invoice_payments.payment_date', '>=', now()->startOfYear()->toDateString())
            ->whereDate('invoice_payments.payment_date', '<=', now()->endOfYear()->toDateString())
            ->sum('invoice_payments.amount');

        return [
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'this_year' => $thisYear,
        ];
    }

    public static function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => '💵 Cash',
            'card' => '💳 Card',
            'upi' => '📱 UPI',
            'bank_transfer' => '🏦 Bank Transfer',
            'loyalty_points' => '⭐ Loyalty Points',
            'other' => '📋 Other',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return CollectionReportPayment::query()
                    ->selectRaw('payment_method, COUNT(invoice_payments.id) as transaction_count, SUM(invoice_payments.amount) as total_amount')
                    ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
                    ->where('invoices.status', '!=', 'cancelled')
                    ->when($this->startDate, fn(Builder $q) => $q->whereDate('invoice_payments.payment_date', '>=', $this->startDate))
                    ->when($this->endDate, fn(Builder $q) => $q->whereDate('invoice_payments.payment_date', '<=', $this->endDate))
                    ->where(function (Builder $query) {
                        if ($this->clinicId) {
                            $query->where('invoices.clinic_id', $this->clinicId);
                        } elseif (!check_role('super_admin')) {
                            $query->where('invoices.clinic_id', auth()->user()->clinic_id);
                        }
                    })
                    ->groupBy('payment_method');
            })
            ->columns([
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->formatStateUsing(fn(string $state) => self::getPaymentMethodLabel($state))
                    ->sortable(),
                TextColumn::make('transaction_count')
                    ->label('Transaction Count')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Transactions')),
                TextColumn::make('total_amount')
                    ->label('Total Collected')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Revenue')->money('INR')),
            ])
            ->actions([
                TableAction::make('view_payments')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->tooltip('View transactions')
                    ->color('info')
                    ->modalHeading(fn(CollectionReportPayment $record) => "Transactions: " . self::getPaymentMethodLabel($record->payment_method))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl')
                    ->modalContent(function (CollectionReportPayment $record) {
                        $payments = InvoicePayment::query()
                            ->with(['invoice.client', 'invoice.clinic'])
                            ->where('payment_method', $record->payment_method)
                            ->whereHas('invoice', function ($q) {
                                $q->where('status', '!=', 'cancelled');
                                if ($this->clinicId) {
                                    $q->where('clinic_id', $this->clinicId);
                                } elseif (!check_role('super_admin')) {
                                    $q->where('clinic_id', auth()->user()->clinic_id);
                                }
                            })
                            ->when($this->startDate, fn($q) => $q->whereDate('payment_date', '>=', $this->startDate))
                            ->when($this->endDate, fn($q) => $q->whereDate('payment_date', '<=', $this->endDate))
                            ->orderBy('payment_date', 'desc')
                            ->get();

                        return view('filament.pages.actions.collection-payments', [
                            'payments' => $payments,
                        ]);
                    })
            ])
            ->defaultSort('total_amount', 'desc')
            ->paginated(false);
    }
}

/**
 * Report helper model class to handle payment collection grouping by payment method.
 */
class CollectionReportPayment extends InvoicePayment
{
    protected $table = 'invoice_payments';
    protected $primaryKey = 'payment_method';
    protected $keyType = 'string';
    public $incrementing = false;
}
