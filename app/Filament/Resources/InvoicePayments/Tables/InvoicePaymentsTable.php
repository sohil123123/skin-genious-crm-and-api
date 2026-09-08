<?php

namespace App\Filament\Resources\InvoicePayments\Tables;

use App\Models\InvoicePayment;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;
use Filament\Tables\Filters\Indicator;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Invoice;

use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;

class InvoicePaymentsTable
{
    public static function configure(Table $table): Table
    {
        $isUserRelation = $table->getLivewire() instanceof InvoicesRelationManager;

        return $table
            ->columns([
                TextColumn::make('transaction_id')
                    ->label('TXN ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable(['invoice_number'])
                    ->sortable()
                    ->url(fn($record) => "/admin/invoices/{$record->invoice_id}/edit"),

                TextColumn::make('invoice.invoice_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'package' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(?string $state): string => ucfirst($state ?? 'standard')),

                TextColumn::make('invoice.clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->visible(fn() => check_role(config('project.roles.super_admin')))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('invoice.client.first_name')
                    ->label('Client')
                    ->badge()
                    ->icon('heroicon-o-user')
                    ->formatStateUsing(fn($record) => $record->invoice->client?->name ?? 'N/A')
                    ->searchable(['first_name', 'last_name', 'mobile']),

                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Payments')->money('INR')),

                TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn($state) => match ($state) {
                        'cash' => 'success',
                        'upi' => 'info',
                        'card' => 'warning',
                        'loyalty_points' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('reference_number')
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('invoice.status')
                    ->label('Invoice Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        'draft' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('creator.first_name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // ->filters([
            //     SelectFilter::make('clinic_id')
            //         ->label('Clinic')
            //         ->relationship('invoice.clinic', 'name')
            //         ->searchable()
            //         ->preload()
            //         ->visible(fn () => auth()->user()->hasRole('super_admin')),

            //     SelectFilter::make('payment_method')
            //         ->options([
            //             'cash'           => 'Cash',
            //             'card'           => 'Card',
            //             'upi'            => 'UPI',
            //             'bank_transfer'  => 'Bank Transfer',
            //             'loyalty_points' => 'Loyalty Points',
            //             'other'          => 'Other',
            //         ]),

            //     Filter::make('payment_date')
            //         ->form([
            //             DatePicker::make('from'),
            //             DatePicker::make('until'),
            //         ])
            //         ->query(function (Builder $query, array $data): Builder {
            //             return $query
            //                 ->when(
            //                     $data['from'],
            //                     fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
            //                 )
            //                 ->when(
            //                     $data['until'],
            //                     fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
            //                 );
            //         })
            // ],layout: FiltersLayout::Modal)
            ->filters([
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
                                            ->options(Clinic::active()->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select clinic')
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

                                        // Client
                                        Select::make('invoice_id')
                                            ->label('Invoice')
                                            ->options(function (callable $get) {
                                                $userId = $get('user_id');
                                                if (!$userId)
                                                    $userId = auth()->user()->id;

                                                return Invoice::where('status', '!=', 'paid')->where('user_id', $userId)->pluck('invoice_number', 'id');
                                            })
                                            ->reactive()
                                            ->searchable()
                                            ->placeholder('Select Invoice')
                                            ->hidden($isUserRelation),

                                        Select::make('invoice_type')
                                            ->label('Invoice Type')
                                            ->options([
                                                'standard' => 'Standard',
                                                'package' => 'Package',
                                            ])
                                            ->placeholder('All Types'),

                                        DatePicker::make('from'),
                                        DatePicker::make('until'),

                                    ]),
                            ])
                            ->columns(1)
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['clinic_id'] ?? null, fn($q, $id) => $q->whereHas('invoice', fn($inv) => $inv->where('clinic_id', $id)))
                            ->when($data['user_id'] ?? null, fn($q, $id) => $q->whereHas('invoice', fn($inv) => $inv->where('user_id', $id)))
                            ->when($data['invoice_id'] ?? null, fn($q, $id) => $q->whereHas('invoice', fn($inv) => $inv->where('id', $id)))
                            ->when($data['invoice_type'] ?? null, fn($q, $type) => $q->whereHas('invoice', fn($inv) => $inv->where('invoice_type', $type)))
                            ->when($data['from'] ?? null, fn($q, $date) => $q->whereDate('payment_date', '>=', $date))
                            ->when($data['until'] ?? null, fn($q, $date) => $q->whereDate('payment_date', '<=', $date));
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

                        if ($data['invoice_id'] ?? null) {
                            $invoice = Invoice::find($data['invoice_id']);
                            if ($invoice) {
                                $indicators[] = Indicator::make('Invoice #: ' . $invoice->invoice_number)->removeField('invoice_id');
                            }
                        }

                        if ($data['invoice_type'] ?? null) {
                            $indicators[] = Indicator::make('Type: ' . ucfirst($data['invoice_type']))->removeField('invoice_type');
                        }

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From: ' . $data['from'])->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('To: ' . $data['until'])->removeField('until');
                        }
                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(
                fn(Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),

            ]);
    }
}
