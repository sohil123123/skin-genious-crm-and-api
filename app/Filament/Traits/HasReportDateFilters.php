<?php

namespace App\Filament\Traits;

use Illuminate\Support\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use App\Models\Clinic;

trait HasReportDateFilters
{
    public ?string $reportType = 'monthly';
    public ?string $selectedMonth = null;
    public ?string $selectedYear = null;
    public ?string $selectedQuarter = null;
    public ?string $selectedFy = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $clinicId = null;

    protected function initReportFilters(): void
    {
        $this->selectedMonth = now()->format('m');
        $this->selectedYear = now()->format('Y');
        $this->selectedQuarter = $this->getCurrentQuarter();
        $this->selectedFy = $this->getCurrentFy();
        $this->calculateDateRange();
    }

    protected function getReportFiltersFormData(): array
    {
        return [
            'reportType' => $this->reportType,
            'selectedMonth' => $this->selectedMonth,
            'selectedYear' => $this->selectedYear,
            'selectedQuarter' => $this->selectedQuarter,
            'selectedFy' => $this->selectedFy,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'clinicId' => $this->clinicId,
        ];
    }

    protected function getReportFilterSchema(): array
    {
        return [
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
                            $this->triggerReportFilterUpdate();
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
                            $this->triggerReportFilterUpdate();
                        }),

                    Select::make('selectedYear')
                        ->label('Year')
                        ->options($this->getYearOptions())
                        ->visible(fn ($get) => in_array($get('reportType'), ['monthly', 'quarterly']))
                        ->reactive()
                        ->afterStateUpdated(function ($state) {
                            $this->selectedYear = $state;
                            $this->calculateDateRange();
                            $this->triggerReportFilterUpdate();
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
                            $this->triggerReportFilterUpdate();
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
                            $this->triggerReportFilterUpdate();
                        }),

                    // Custom date range filters
                    DatePicker::make('startDate')
                        ->label('From Date')
                        ->visible(fn ($get) => $get('reportType') === 'custom')
                        ->reactive()
                        ->afterStateUpdated(function ($state) {
                            $this->startDate = $state;
                            $this->triggerReportFilterUpdate();
                        }),

                    DatePicker::make('endDate')
                        ->label('To Date')
                        ->visible(fn ($get) => $get('reportType') === 'custom')
                        ->reactive()
                        ->afterStateUpdated(function ($state) {
                            $this->endDate = $state;
                            $this->triggerReportFilterUpdate();
                        }),

                    // Clinic filter (super_admin only)
                    Select::make('clinicId')
                        ->label('Clinic')
                        ->options(Clinic::active()->pluck('name', 'id'))
                        ->placeholder('All Clinics')
                        ->searchable()
                        ->reactive()
                        ->visible(fn () => auth()->user()->hasRole('super_admin'))
                        ->afterStateUpdated(function ($state) {
                            $this->clinicId = $state ? (int) $state : null;
                            $this->triggerReportFilterUpdate();
                        }),
                ]),
        ];
    }
    
    protected function triggerReportFilterUpdate(): void
    {
        if (method_exists($this, 'onReportFilterUpdated')) {
            $this->onReportFilterUpdated();
        } else {
            $this->resetTable();
        }
    }

    protected function calculateDateRange(): void
    {
        switch ($this->reportType) {
            case 'monthly':
                $date = Carbon::createFromDate((int)$this->selectedYear, (int)$this->selectedMonth, 1);
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
        return $year . '-' . substr((string)($year + 1), 2);
    }

    protected function getMonthOptions(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[str_pad((string)$i, 2, '0', STR_PAD_LEFT)] = Carbon::create(null, $i, 1)->format('F');
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
            $key = $i . '-' . substr((string)($i + 1), 2);
            $options[$key] = "FY {$key}";
        }
        return $options;
    }
}
