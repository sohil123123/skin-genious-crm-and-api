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
use App\Services\InvoicePdfService;

use App\Models\User;
use App\Models\Clinic;
use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;


class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        $isUserRelation = $table->getLivewire() instanceof InvoicesRelationManager;

        return $table
            ->deferLoading()
            // ->recordUrl(null)
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
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
                    ->sortable(),
                TextColumn::make('amount_due')
                    ->money('INR')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->money('INR')
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
                            ->when($data['invoice_type'] ?? null, fn ($q, $type) => $q->where('invoice_type', $type));
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
            ->actions([ // Filament v3 uses actions() instead of recordActions? Or this is v4 with unified configure?
                // // The existing file had ->recordActions([...]) so I'll stick to that
                Action::make('make_payment')
                    ->label('Make Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->hidden(fn ($record) => in_array($record->status, ['paid', 'cancelled']))
                    ->modalHeading('Create Invoice Payment')
                    ->modalWidth('5xl')
                    ->form(function ($record) {
                        return [
                        Grid::make(12)->schema([
                            Section::make('Invoice Information')
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    \Filament\Forms\Components\TextInput::make('invoice_number')
                                        ->label('Invoice')
                                        ->default($record->invoice_number)
                                        ->disabled()
                                        ->dehydrated(false),
                                    \Filament\Forms\Components\Placeholder::make('current_balance_due')
                                        ->label('Current Balance Due')
                                        ->content('₹ ' . number_format($record->amount_due, 2)),
                                ])
                                ->columnSpan(['default' => 12, 'md' => 5]),

                            Section::make('Payment Details')
                                ->icon('heroicon-o-banknotes')
                                ->schema([
                                    \Filament\Forms\Components\DatePicker::make('payment_date')
                                        ->label('Payment Date')
                                        ->default(now())
                                        ->required(),
                                    \Filament\Forms\Components\TextInput::make('amount')
                                        ->label('Payment Amount')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0.01)
                                        ->maxValue($record->amount_due)
                                        ->default($record->amount_due)
                                        ->prefix('₹'),
                                    \Filament\Forms\Components\Select::make('payment_method')
                                        ->label('Payment Method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'card' => 'Card',
                                            'upi' => 'UPI',
                                            'bank_transfer' => 'Bank Transfer',
                                            'other' => 'Other',
                                        ])
                                        ->required()
                                        ->live(),
                                    \Filament\Forms\Components\TextInput::make('reference_number')
                                        ->label('Reference Number / TXN ID')
                                        ->placeholder('# Transaction ID, Check #, etc.')
                                        ->hidden(fn (Get $get) => $get('payment_method') === 'cash')
                                        ->required(fn (Get $get) => $get('payment_method') !== 'cash' && $get('payment_method') !== null),
                                    \Filament\Forms\Components\Textarea::make('notes')
                                        ->label('Payment Notes')
                                        ->placeholder('Add any relevant notes about this payment...')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpan(['default' => 12, 'md' => 7]),
                        ])
                    ];
                    })
                    ->action(function ($record, array $data) {
                        \App\Models\InvoicePayment::create([
                            'invoice_id' => $record->id,
                            'payment_date' => $data['payment_date'],
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'reference_number' => $data['reference_number'] ?? null,
                            'created_by' => auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Payment Recorded ✅')
                            ->body("₹" . number_format($data['amount'], 2) . " has been recorded for Invoice #{$record->id}.")
                            ->success()
                            ->send();
                    }),
                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->tooltip('Download PDF')
                    ->action(function ($record) {
                        $pdfService = app(InvoicePdfService::class);
                        return $pdfService->download($record);
                    }),

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
