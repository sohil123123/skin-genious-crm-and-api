<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

use App\Services\InvoicePdfService;

use App\Models\User;
use App\Models\Clinic;


class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            // ->recordUrl(null)
            ->columns([
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
                TextColumn::make('grand_total')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('payment_mode')
                    ->badge(),
                SelectColumn::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'paid' => 'Paid',
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
                                            ->placeholder('Select Client'),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'paid' => 'Paid',
                                                'pending' => 'Pending',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->placeholder('All Statuses'),

                                        Select::make('payment_mode')
                                            ->label('Payment Mode')
                                            ->options([
                                                'UPI' => 'UPI',
                                                'Cash' => 'Cash',
                                                'Card' => 'Card',
                                                'NetBanking' => 'NetBanking',
                                            ])
                                            ->placeholder('All Payment Modes'),

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
                            ->when($data['payment_mode'] ?? null, fn ($q, $mode) => $q->where('payment_mode', $mode));
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

                        if ($data['payment_mode'] ?? null) {
                            $indicators[] = Indicator::make('Payment Mode: ' . $data['payment_mode'])->removeField('payment_mode');
                        }

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
                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->tooltip('Download PDF')
                    ->action(function ($record) {
                        $pdfService = app(InvoicePdfService::class);
                        return $pdfService->download($record);
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([ // Similarly for bulkActions
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first invoice, it will appear here.');
    }
}
