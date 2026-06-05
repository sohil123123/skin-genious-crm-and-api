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


class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        $isUserRelation = $table->getLivewire() instanceof InvoicesRelationManager;

        return $table
            ->deferLoading()
            // ->recordUrl(null)
            ->recordClasses(fn ($record) => match ($record->status) {
                'paid' => '!bg-green-50 dark:!bg-green-900/20',
                default => '',
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('state_code')
                    ->label('State')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'package' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('package.package_name')
                    ->label('Package Ref')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clinic.name')
                    ->badge()
                    ->visible(fn () => check_role('super_admin'))
                    ->icon('heroicon-o-building-office')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.first_name')
                    ->label('Client')
                    ->badge()
                    ->icon('heroicon-o-user')
                    ->formatStateUsing(fn ($record) => $record->client?->name ?? 'N/A')
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
                        Section::make('Clinic & Clients')
                            ->icon('heroicon-o-building-office-2')
                            ->description('Filter by clinic and assigned clients.')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        // Clinic
                                        Select::make('clinic_id')
                                            ->label('Clinic')
                                            ->relationship('clinic', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select clinic')
                                            ->native(true)
                                            ->live()
                                            ->visible(fn () => auth()->user()->hasRole('super_admin')),

                                        // Client
                                        Select::make('user_id')
                                            ->label('Client')
                                            ->options(function (callable $get) {
                                                $clinicId = $get('clinic_id');
                                                if (!$clinicId)
                                                    $clinicId = auth()->user()->clinic_id;

                                                return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                            })
                                            ->reactive()
                                            ->searchable()
                                            ->placeholder('Select Client')
                                            ->hidden($isUserRelation),

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
                                            ->placeholder('All Statuses'),

                                        Select::make('invoice_type')
                                            ->label('Invoice Type')
                                            ->options([
                                                'standard' => 'Standard',
                                                'package' => 'Package',
                                            ])
                                            ->placeholder('All Types'),

                                        // Select::make('payment_mode')
                                        //     ->label('Payment Mode')
                                        //     ->options([
                                        //         'UPI' => 'UPI',
                                        //         'Cash' => 'Cash',
                                        //         'Card' => 'Card',
                                        //         'NetBanking' => 'NetBanking',
                                        //     ])
                                        //     ->placeholder('All Payment Modes'),

                                        Select::make('state_code')
                                            ->label('State Code')
                                            ->options(config('project.indian_states', []))
                                            ->searchable()
                                            ->placeholder('All States'),

                                    ]),
                            ])
                            ->columns(1)
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                            ->when($data['invoice_type'] ?? null, fn ($q, $type) => $q->where('invoice_type', $type))
                            ->when($data['state_code'] ?? null, fn ($q, $state) => $q->where('state_code', $state));
                            // ->when($data['payment_mode'] ?? null, fn ($q, $mode) => $q->where('payment_mode', $mode));
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

                        if ($data['status'] ?? null) {
                            $indicators[] = Indicator::make('Status: ' . $data['status'])->removeField('status');
                        }

                        if ($data['invoice_type'] ?? null) {
                            $indicators[] = Indicator::make('Type: ' . ucfirst($data['invoice_type']))->removeField('invoice_type');
                        }

                        if ($data['state_code'] ?? null) {
                            $indicators[] = Indicator::make('State: ' . $data['state_code'])->removeField('state_code');
                        }

                        // if ($data['payment_mode'] ?? null) {
                        //     $indicators[] = Indicator::make('Payment Mode: ' . $data['payment_mode'])->removeField('payment_mode');
                        // }

                        return $indicators;
                    }),
            ],layout: FiltersLayout::Modal)
            ->filtersFormColumns(1) // Reduced to 2 for better readability in modal; adjust as needed
            // ->filtersFormWidth('md:max-w-4xl')

            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                InvoicePaymentForm::getMakePaymentAction()->hidden(fn ($record) => in_array($record->status, ['paid', 'cancelled'])),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->tooltip('Download PDF')
                    ->action(function ($record) {
                        $pdfService = app(InvoicePdfService::class);
                        return $pdfService->download($record);
                    }),

                Action::make('payment_history')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->color('success')
                    ->tooltip('Payment History')
                    ->url(fn ($record) => route('filament.admin.resources.invoices.payments', ['record' => $record])),

                ActionGroup::make([
                    ViewAction::make()->modalWidth('7xl'),
                    EditAction::make()->modalWidth('7xl'),
                    DeleteAction::make(),
                ]),

            ])
            ->bulkActions([ // Similarly for bulkActions
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first invoice, it will appear here.');
    }
}
