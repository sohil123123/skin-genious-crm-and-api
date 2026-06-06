<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Exports\GstReportExport;
use App\Exports\HsnGstSummaryExport;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;

class GstReport extends Page implements HasTable, HasForms
{
    use HasPageShield;
    use InteractsWithTable;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.gst-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'GST Reports';

    protected static ?string $title = 'GST Filing Report';

    // Filter properties
    public ?string $reportType = 'monthly';
    public ?string $selectedMonth = null;
    public ?string $selectedYear = null;
    public ?string $selectedQuarter = null;
    public ?string $selectedFy = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $clinicId = null;

    public function mount(): void
    {
        $this->selectedMonth = now()->format('m');
        $this->selectedYear = now()->format('Y');
        $this->selectedQuarter = $this->getCurrentQuarter();
        $this->selectedFy = $this->getCurrentFy();
        $this->calculateDateRange();

        $this->form->fill([
            'reportType' => $this->reportType,
            'selectedMonth' => $this->selectedMonth,
            'selectedYear' => $this->selectedYear,
            'selectedQuarter' => $this->selectedQuarter,
            'selectedFy' => $this->selectedFy,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'clinicId' => $this->clinicId,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(6)
                    ->schema([
                        Select::make('reportType')
                            ->label('Report Type')
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'financial_year' => 'Financial Year',
                                'custom' => 'Custom Date Range',
                            ])
                            ->default('monthly')
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->reportType = $state;
                                $this->calculateDateRange();
                                $this->resetTable();
                            }),

                        // Monthly filters
                        Select::make('selectedMonth')
                            ->label('Month')
                            ->options($this->getMonthOptions())
                            ->visible(fn ($get) => $get('reportType') === 'monthly')
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedMonth = $state;
                                $this->calculateDateRange();
                                $this->resetTable();
                            }),

                        Select::make('selectedYear')
                            ->label('Year')
                            ->options($this->getYearOptions())
                            ->visible(fn ($get) => in_array($get('reportType'), ['monthly', 'quarterly']))
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedYear = $state;
                                $this->calculateDateRange();
                                $this->resetTable();
                            }),

                        // Quarterly filter
                        Select::make('selectedQuarter')
                            ->label('Quarter')
                            ->options([
                                'Q1' => 'Q1 (Apr-Jun)',
                                'Q2' => 'Q2 (Jul-Sep)',
                                'Q3' => 'Q3 (Oct-Dec)',
                                'Q4' => 'Q4 (Jan-Mar)',
                            ])
                            ->visible(fn ($get) => $get('reportType') === 'quarterly')
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedQuarter = $state;
                                $this->calculateDateRange();
                                $this->resetTable();
                            }),

                        // Financial Year filter
                        Select::make('selectedFy')
                            ->label('Financial Year')
                            ->options($this->getFyOptions())
                            ->visible(fn ($get) => $get('reportType') === 'financial_year')
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedFy = $state;
                                $this->calculateDateRange();
                                $this->resetTable();
                            }),

                        // Custom date range filters
                        DatePicker::make('startDate')
                            ->label('From Date')
                            ->visible(fn ($get) => $get('reportType') === 'custom')
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->startDate = $state;
                                $this->resetTable();
                            }),

                        DatePicker::make('endDate')
                            ->label('To Date')
                            ->visible(fn ($get) => $get('reportType') === 'custom')
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->endDate = $state;
                                $this->resetTable();
                            }),

                        // Clinic filter (super_admin only)
                        Select::make('clinicId')
                            ->label('Clinic')
                            ->options(\App\Models\Clinic::active()->pluck('name', 'id'))
                            ->placeholder('All Clinics')
                            ->searchable()
                            ->reactive()
                            ->visible(fn () => auth()->user()->hasRole('super_admin'))
                            ->afterStateUpdated(function ($state) {
                                $this->clinicId = $state;
                                $this->resetTable();
                            }),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportExcel'),

            Action::make('export_hsn_gst')
                ->label('HSN/GST Summary')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    return Excel::download(
                        new HsnGstSummaryExport($this->startDate, $this->endDate),
                        "HSN_GST_Summary_{$this->startDate}_to_{$this->endDate}.xlsx"
                    );
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = Invoice::query()
                    ->with(['client', 'clinic', 'items'])
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->where('grand_total', '>', 0);

                if ($this->startDate) {
                    $query->whereDate('invoice_date', '>=', $this->startDate);
                }
                if ($this->endDate) {
                    $query->whereDate('invoice_date', '<=', $this->endDate);
                }

                if ($this->clinicId) {
                    $query->where('clinic_id', $this->clinicId);
                } elseif (!check_role(config('project.roles.super_admin'))) {
                    $query->where('clinic_id', auth()->user()->clinic_id);
                }

                return $query;
            })
            ->defaultSort('invoice_date', 'desc')
            ->columns([

                TextColumn::make('clinic.name')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => check_role(config('project.roles.super_admin'))),

                TextColumn::make('client.first_name')
                    ->label('Client')
                    ->badge()
                    ->icon('heroicon-o-user')
                    ->state(function (Invoice $record): string {
                        if (!$record->client) return 'N/A';
                        return trim($record->client->first_name . ' ' . ($record->client->last_name ?? ''));
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('client', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->searchable(['first_name', 'last_name', 'mobile']),

                // TextColumn::make('client.state')
                //     ->label('State')
                //     ->state(function (Invoice $record): string {
                //         $clinicState = $record->clinic->state ?? config('project.company_state_code');
                //         return $record->client->state ?? $clinicState;
                //     })
                //     ->badge()
                //     ->color('gray'),

                TextColumn::make('invoice_number')
                    ->label('Invoice Number')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.invoices.edit', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('Invoice Date')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('taxable_value')
                    ->label('Taxable Amount')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->money('INR')->label('Total')),

                TextColumn::make('sgst_tax')
                    ->label('SGST Tax')
                    ->state(function (Invoice $record): float {
                        return $this->isSameState($record) ? round((float) $record->gst_total / 2, 2) : 0;
                    })
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'success' : null),

                TextColumn::make('cgst_tax')
                    ->label('CGST Tax')
                    ->state(function (Invoice $record): float {
                        return $this->isSameState($record) ? round((float) $record->gst_total / 2, 2) : 0;
                    })
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'success' : null),

                TextColumn::make('igst_tax')
                    ->label('IGST Tax')
                    ->state(function (Invoice $record): float {
                        return !$this->isSameState($record) ? round((float) $record->gst_total, 2) : 0;
                    })
                    ->money('INR')
                    ->color(fn ($state) => $state > 0 ? 'warning' : null),

                TextColumn::make('gst_rate')
                    ->label('GST Rate')
                    ->state(function (Invoice $record): string {
                        if ($record->items->isEmpty()) return '0%';
                        $rates = $record->items->groupBy('gst_percentage');
                        $predominantRate = $rates->sortByDesc(fn ($items) => $items->sum('line_total'))->keys()->first();
                        return rtrim(rtrim(number_format((float) $predominantRate, 2), '0'), '.') . '%';
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('grand_total')
                    ->label('Invoice Total')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()->money('INR')->label('Grand Total')),
            ])
            ->defaultSort('invoice_date', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Determine if invoice is same-state (SGST+CGST) or inter-state (IGST).
     */
    protected function isSameState(Invoice $record): bool
    {
        $companyState = config('project.company_state_code');
        $clinicState = $record->clinic->state ?? $companyState;
        $clientState = $record->client->state ?? null;

        return empty($clientState) || strtoupper($clinicState) === strtoupper($clientState);
    }

    /**
     * Get summary statistics for the current filter.
     */
    public function getSummaryData(): array
    {
        $companyState = config('project.company_state_code');

        $query = Invoice::query()
            ->with(['client', 'clinic'])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('grand_total', '>', 0);

        if ($this->startDate) {
            $query->whereDate('invoice_date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('invoice_date', '<=', $this->endDate);
        }

        if ($this->clinicId) {
            $query->where('clinic_id', $this->clinicId);
        } elseif (!auth()->user()->hasRole('super_admin')) {
            $query->where('clinic_id', auth()->user()->clinic_id);
        }

        $invoices = $query->get();

        $totalTaxable = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;
        $totalGst = 0;
        $grandTotal = 0;

        foreach ($invoices as $invoice) {
            $clinicState = $invoice->clinic->state ?? $companyState;
            $clientState = $invoice->client->state ?? null;
            $isSameState = empty($clientState) || strtoupper($clinicState) === strtoupper($clientState);

            $gstTotal = (float) $invoice->gst_total;

            $totalTaxable += (float) $invoice->taxable_value;
            $totalGst += $gstTotal;
            $grandTotal += (float) $invoice->grand_total;

            if ($isSameState) {
                $totalSgst += round($gstTotal / 2, 2);
                $totalCgst += round($gstTotal / 2, 2);
            } else {
                $totalIgst += $gstTotal;
            }
        }

        return [
            'total_taxable' => $totalTaxable,
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
            'total_gst' => $totalGst,
            'grand_total' => $grandTotal,
            'total_invoices' => $invoices->count(),
        ];
    }

    /**
     * Export GST report to Excel.
     */
    public function exportExcel()
    {
        $filename = $this->getExportFilename();

        return Excel::download(
            new GstReportExport(
                $this->startDate,
                $this->endDate,
                $this->getReportTitle(),
                $this->clinicId ? (int) $this->clinicId : null
            ),
            $filename
        );
    }

    // ===== Helper Methods =====

    protected function calculateDateRange(): void
    {
        switch ($this->reportType) {
            case 'monthly':
                $date = Carbon::createFromDate($this->selectedYear, $this->selectedMonth, 1);
                $this->startDate = $date->startOfMonth()->toDateString();
                $this->endDate = $date->endOfMonth()->toDateString();
                break;

            case 'quarterly':
                $dates = $this->getQuarterDates($this->selectedQuarter, $this->selectedYear);
                $this->startDate = $dates['start'];
                $this->endDate = $dates['end'];
                break;

            case 'financial_year':
                $dates = $this->getFyDates($this->selectedFy);
                $this->startDate = $dates['start'];
                $this->endDate = $dates['end'];
                break;

            case 'custom':
                // Keep as-is, user sets directly
                if (!$this->startDate) {
                    $this->startDate = now()->startOfMonth()->toDateString();
                }
                if (!$this->endDate) {
                    $this->endDate = now()->endOfMonth()->toDateString();
                }
                break;
        }
    }

    protected function getQuarterDates(string $quarter, string $year): array
    {
        $year = (int) $year;

        return match ($quarter) {
            'Q1' => ['start' => "{$year}-04-01", 'end' => "{$year}-06-30"],
            'Q2' => ['start' => "{$year}-07-01", 'end' => "{$year}-09-30"],
            'Q3' => ['start' => "{$year}-10-01", 'end' => "{$year}-12-31"],
            'Q4' => ['start' => ($year + 1) . "-01-01", 'end' => ($year + 1) . "-03-31"],
            default => ['start' => "{$year}-04-01", 'end' => "{$year}-06-30"],
        };
    }

    protected function getFyDates(string $fy): array
    {
        // FY format: "2025-26"
        $parts = explode('-', $fy);
        $startYear = (int) $parts[0];

        return [
            'start' => "{$startYear}-04-01",
            'end' => ($startYear + 1) . "-03-31",
        ];
    }

    protected function getCurrentQuarter(): string
    {
        $month = (int) now()->format('m');
        if ($month >= 4 && $month <= 6) return 'Q1';
        if ($month >= 7 && $month <= 9) return 'Q2';
        if ($month >= 10 && $month <= 12) return 'Q3';
        return 'Q4';
    }

    protected function getCurrentFy(): string
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('m');
        if ($month < 4) $year--;
        return $year . '-' . substr($year + 1, 2);
    }

    protected function getMonthOptions(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[str_pad($i, 2, '0', STR_PAD_LEFT)] = Carbon::create(null, $i, 1)->format('F');
        }
        return $months;
    }

    protected function getYearOptions(): array
    {
        $currentYear = (int) now()->format('Y');
        $years = [];
        for ($i = $currentYear - 3; $i <= $currentYear + 1; $i++) {
            $years[(string) $i] = (string) $i;
        }
        return $years;
    }

    protected function getFyOptions(): array
    {
        $currentYear = (int) now()->format('Y');
        $month = (int) now()->format('m');
        if ($month < 4) $currentYear--;

        $options = [];
        for ($i = $currentYear - 3; $i <= $currentYear + 1; $i++) {
            $key = $i . '-' . substr($i + 1, 2);
            $options[$key] = "FY {$key}";
        }
        return $options;
    }

    protected function getReportTitle(): string
    {
        return match ($this->reportType) {
            'monthly' => Carbon::createFromDate($this->selectedYear, $this->selectedMonth, 1)->format('F Y'),
            'quarterly' => $this->selectedQuarter . ' ' . $this->selectedYear . '-' . substr((int) $this->selectedYear + 1, 2),
            'financial_year' => 'FY ' . $this->selectedFy,
            'custom' => Carbon::parse($this->startDate)->format('d-m-Y') . ' to ' . Carbon::parse($this->endDate)->format('d-m-Y'),
            default => 'GST Report',
        };
    }

    protected function getExportFilename(): string
    {
        return match ($this->reportType) {
            'monthly' => 'gst-report-' . Carbon::createFromDate($this->selectedYear, $this->selectedMonth, 1)->format('F-Y') . '.xlsx',
            'quarterly' => 'gst-report-' . $this->selectedQuarter . '-' . $this->selectedYear . '-' . substr((int) $this->selectedYear + 1, 2) . '.xlsx',
            'financial_year' => 'gst-report-FY-' . $this->selectedFy . '.xlsx',
            'custom' => 'gst-report-' . Carbon::parse($this->startDate)->format('d-m-Y') . '-to-' . Carbon::parse($this->endDate)->format('d-m-Y') . '.xlsx',
            default => 'gst-report.xlsx',
        };
    }
}
