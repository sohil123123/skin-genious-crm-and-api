<?php

namespace App\Filament\Resources\InvoicePayments\Tables;

use App\Models\InvoicePayment;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;

class InvoicePaymentsTable
{
    public static function configure(Table $table): Table
    {
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
                    ->url(fn ($record) => "/admin/invoices/{$record->invoice_id}/edit"),

                TextColumn::make('invoice.client.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn ($record) => $record->invoice->client?->name ?? 'N/A')
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total Payments')->money('INR')),

                TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn ($state) => match($state) {
                        'cash' => 'success',
                        'upi' => 'info',
                        'card' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('reference_number')
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('invoice.status')
                    ->label('Invoice Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
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
            ->filters([
                SelectFilter::make('clinic_id')
                    ->label('Clinic')
                    ->relationship('invoice.clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),

                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'upi' => 'UPI',
                        'bank_transfer' => 'Bank Transfer',
                        'other' => 'Other',
                    ]),

                Filter::make('payment_date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    })
            ],layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
