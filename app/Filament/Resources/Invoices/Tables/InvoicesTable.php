<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Section;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Services\InvoicePdfService;
use App\Services\LoyaltyPointService;
use App\Services\LoyaltyOtpService;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Group;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Setting;
use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\InvoicePayments\Schemas\InvoicePaymentForm;
use Illuminate\Support\Carbon;


class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        $isUserRelation = $table->getLivewire() instanceof InvoicesRelationManager;

        return $table
            ->deferLoading()
            // ->recordUrl(null)
            ->defaultSort('invoice_date', 'desc')
            ->recordClasses(fn($record) => match ($record->status) {
                // 'paid' => 'invoice-status-paid',
                'partial', 'unpaid' => 'invoice-status-partial',
                default => '',
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                // TextColumn::make('state_code')
                //     ->label('State')
                //     ->badge()
                //     ->searchable()
                //     ->sortable(),
                TextColumn::make('invoice_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'package' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),
                TextColumn::make('package.package_name')
                    ->label('Package Ref')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clinic.name')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->visible(fn() => check_role('super_admin'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.first_name')
                    ->label('Client')
                    ->badge()
                    ->icon('heroicon-o-user')
                    ->formatStateUsing(fn($record) => $record->client?->name ?? 'N/A')
                    ->searchable(['first_name', 'last_name', 'mobile']),
                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->money('INR')
                    ->badge()
                    ->color('success')
                    ->summarize(Sum::make()->label('Total Paid')->money('INR'))
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->money('INR')
                    ->badge()
                    ->color('danger')
                    ->summarize(Sum::make()->label('Total Due')->money('INR'))
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->money('INR')
                    ->summarize(Sum::make()->label('Total Amount')->money('INR'))
                    ->sortable(),
                // TextColumn::make('payment_mode')
                //     ->badge(),
                SelectColumn::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'unpaid' => 'Unpaid',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ])
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // 3) Other Filters: Improved layout with 2-column grid, dependencies, and role-based visibility
                Filter::make('advanced')
                    ->label('Advanced Filters')
                    ->form([
                        Grid::make(3)
                            ->schema([
                                // Clinic
                                Select::make('clinic_id')
                                    ->label('Clinic')
                                    ->relationship('clinic', 'name', fn ($query) => $query->active())
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select Clinic')
                                    ->native(true)
                                    ->live()
                                    ->visible(fn() => auth()->user()->hasRole('super_admin')),

                                // Client
                                Select::make('user_id')
                                    ->label('Client')
                                    ->options(function (callable $get) {
                                        $clinicId = $get('clinic_id');
                                        if (!$clinicId)
                                            $clinicId = auth()->user()->clinic_id;

                                        return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn($u) => [$u->id => $u->name]);
                                    })
                                    ->reactive()
                                    ->searchable()
                                    ->placeholder('Select Client')
                                    ->hidden($isUserRelation),

                                Select::make('invoice_type')
                                    ->label('Invoice Type')
                                    ->options([
                                        'standard' => 'Standard',
                                        'package' => 'Package',
                                    ])
                                    ->placeholder('All Types'),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'paid' => 'Paid',
                                        'partial' => 'Partial',
                                        'unpaid' => 'Unpaid',
                                        'pending' => 'Pending',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->multiple()
                                    ->preload()
                                    ->placeholder('All Statuses'),

                                Select::make('reportType')
                                    ->label('Date Filter Type')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'quarterly' => 'Quarterly',
                                        'financial_year' => 'Financial Year',
                                        'custom' => 'Custom Date Range',
                                    ])
                                    ->placeholder('All Dates')
                                    ->live(),

                                Select::make('selectedMonth')
                                    ->label('Month')
                                    ->options(self::getMonthOptions())
                                    ->visible(fn($get) => $get('reportType') === 'monthly')
                                    ->live(),

                                Select::make('selectedYear')
                                    ->label('Year')
                                    ->options(self::getYearOptions())
                                    ->visible(fn($get) => in_array($get('reportType'), ['monthly', 'quarterly']))
                                    ->live(),

                                Select::make('selectedQuarter')
                                    ->label('Quarter')
                                    ->options([
                                        'Q1' => 'Q1 (Apr-Jun)',
                                        'Q2' => 'Q2 (Jul-Sep)',
                                        'Q3' => 'Q3 (Oct-Dec)',
                                        'Q4' => 'Q4 (Jan-Mar)',
                                    ])
                                    ->visible(fn($get) => $get('reportType') === 'quarterly')
                                    ->live(),

                                Select::make('selectedFy')
                                    ->label('Financial Year')
                                    ->options(self::getFyOptions())
                                    ->visible(fn($get) => $get('reportType') === 'financial_year')
                                    ->live(),

                                DatePicker::make('startDate')
                                    ->label('From Date')
                                    ->visible(fn($get) => $get('reportType') === 'custom')
                                    ->live(),

                                DatePicker::make('endDate')
                                    ->label('To Date')
                                    ->visible(fn($get) => $get('reportType') === 'custom')
                                    ->live(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $query = $query
                            ->when($data['clinic_id'] ?? null, fn($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
                            ->when(!empty($data['status']), fn($q) => $q->whereIn('status', $data['status']))
                            ->when($data['invoice_type'] ?? null, fn($q, $type) => $q->where('invoice_type', $type));
                        // ->when($data['state_code'] ?? null, fn($q, $state) => $q->where('state_code', $state));
            
                        $reportType = $data['reportType'] ?? null;
                        if ($reportType) {
                            $startDate = null;
                            $endDate = null;

                            switch ($reportType) {
                                case 'monthly':
                                    $month = $data['selectedMonth'] ?? now()->format('m');
                                    $year = $data['selectedYear'] ?? now()->format('Y');
                                    $date = Carbon::createFromDate((int) $year, (int) $month, 1);
                                    $startDate = $date->startOfMonth()->toDateString();
                                    $endDate = $date->endOfMonth()->toDateString();
                                    break;

                                case 'quarterly':
                                    $quarter = $data['selectedQuarter'] ?? 'Q1';
                                    $year = (int) ($data['selectedYear'] ?? now()->format('Y'));
                                    $dates = match ($quarter) {
                                        'Q1' => ['start' => "{$year}-04-01", 'end' => "{$year}-06-30"],
                                        'Q2' => ['start' => "{$year}-07-01", 'end' => "{$year}-09-30"],
                                        'Q3' => ['start' => "{$year}-10-01", 'end' => "{$year}-12-31"],
                                        'Q4' => ['start' => ($year + 1) . "-01-01", 'end' => ($year + 1) . "-03-31"],
                                        default => ['start' => "{$year}-04-01", 'end' => "{$year}-06-30"],
                                    };
                                    $startDate = $dates['start'];
                                    $endDate = $dates['end'];
                                    break;

                                case 'financial_year':
                                    $fy = $data['selectedFy'] ?? null;
                                    if ($fy) {
                                        $parts = explode('-', $fy);
                                        $startYear = (int) $parts[0];
                                        $startDate = "{$startYear}-04-01";
                                        $endDate = ($startYear + 1) . "-03-31";
                                    }
                                    break;

                                case 'custom':
                                    $startDate = $data['startDate'] ?? null;
                                    $endDate = $data['endDate'] ?? null;
                                    break;
                            }

                            if ($startDate) {
                                $query->whereDate('invoice_date', '>=', $startDate);
                            }
                            if ($endDate) {
                                $query->whereDate('invoice_date', '<=', $endDate);
                            }
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['clinic_id'] ?? null) {
                            $clinic = Clinic::find($data['clinic_id']);
                            if ($clinic) {
                                $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
                            }
                        }

                        if ($data['user_id'] ?? null) {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $indicators[] = Indicator::make('Client: ' . $user->name)->removeField('user_id');
                            }
                        }

                        if (!empty($data['status'])) {
                            $statusLabels = array_map('ucfirst', $data['status']);
                            $indicators[] = Indicator::make('Status: ' . implode(', ', $statusLabels))->removeField('status');
                        }

                        if ($data['invoice_type'] ?? null) {
                            $indicators[] = Indicator::make('Type: ' . ucfirst($data['invoice_type']))->removeField('invoice_type');
                        }

                        // if ($data['state_code'] ?? null) {
                        //     $indicators[] = Indicator::make('State: ' . $data['state_code'])->removeField('state_code');
                        // }
            
                        if ($data['reportType'] ?? null) {
                            switch ($data['reportType']) {
                                case 'monthly':
                                    $monthVal = $data['selectedMonth'] ?? now()->format('m');
                                    $monthName = Carbon::create(null, (int) $monthVal, 1)->format('F');
                                    $year = $data['selectedYear'] ?? now()->format('Y');
                                    $indicators[] = Indicator::make("Date Range: {$monthName} {$year}")
                                        ->removeField('reportType')
                                        ->removeField('selectedMonth')
                                        ->removeField('selectedYear');
                                    break;
                                case 'quarterly':
                                    $quarter = $data['selectedQuarter'] ?? 'Q1';
                                    $year = $data['selectedYear'] ?? now()->format('Y');
                                    $indicators[] = Indicator::make("Date Range: {$quarter} {$year}")
                                        ->removeField('reportType')
                                        ->removeField('selectedQuarter')
                                        ->removeField('selectedYear');
                                    break;
                                case 'financial_year':
                                    $fy = $data['selectedFy'] ?? '';
                                    $indicators[] = Indicator::make("Date Range: FY {$fy}")
                                        ->removeField('reportType')
                                        ->removeField('selectedFy');
                                    break;
                                case 'custom':
                                    $start = $data['startDate'] ? Carbon::parse($data['startDate'])->format('d-m-Y') : '...';
                                    $end = $data['endDate'] ? Carbon::parse($data['endDate'])->format('d-m-Y') : '...';
                                    $indicators[] = Indicator::make("Date Range: {$start} to {$end}")
                                        ->removeField('reportType')
                                        ->removeField('startDate')
                                        ->removeField('endDate');
                                    break;
                            }
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersFormWidth('4xl')

            ->filtersTriggerAction(
                fn(Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                InvoicePaymentForm::getMakePaymentAction()->hidden(fn($record) => in_array($record->status, ['paid', 'cancelled'])),
                ActionGroup::make([
                    ViewAction::make()->modalWidth('7xl'),
                    EditAction::make()->modalWidth('7xl'),
                    DeleteAction::make(),
                    Action::make('payment_history')
                        ->label('Payment History')
                        ->icon('heroicon-o-currency-rupee')
                        // ->iconButton()
                        ->color('success')
                        // ->tooltip('Payment History')
                        ->url(fn($record) => route('filament.admin.resources.invoices.payments', ['record' => $record])),
                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        // ->tooltip('Download PDF')
                        ->action(function ($record) {
                            $pdfService = app(InvoicePdfService::class);
                            return $pdfService->download($record);
                        }),
                ]),

            ])
            ->bulkActions([ // Similarly for bulkActions
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first invoice, it will appear here.');
    }

    protected static function getMonthOptions(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[str_pad((string) $i, 2, '0', STR_PAD_LEFT)] = Carbon::create(null, $i, 1)->format('F');
        }
        return $months;
    }

    protected static function getYearOptions(): array
    {
        $currentYear = (int) now()->format('Y');
        $years = [];
        for ($i = $currentYear - 3; $i <= $currentYear + 1; $i++) {
            $years[(string) $i] = (string) $i;
        }
        return $years;
    }

    protected static function getFyOptions(): array
    {
        $currentYear = (int) now()->format('Y');
        $month = (int) now()->format('m');
        if ($month < 4)
            $currentYear--;

        $options = [];
        for ($i = $currentYear - 3; $i <= $currentYear + 1; $i++) {
            $key = $i . '-' . substr((string) ($i + 1), 2);
            $options[$key] = "FY {$key}";
        }
        return $options;
    }
}
