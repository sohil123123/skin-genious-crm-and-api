<?php

namespace App\Filament\Resources\UserPackages\Tables;

use App\Models\User;
use App\Models\Invoice;
use App\Models\Appointment;
use App\Models\UserPackageItem;
use App\Models\Product;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\InvoicePayments\Schemas\InvoicePaymentForm;
use App\Filament\Resources\Users\RelationManagers\UserPackagesRelationManager;
use App\Filament\Resources\UserPackages\UserPackageResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Filament\Tables\Enums\RecordActionsPosition;

class UserPackagesTable
{
    public static function configure(Table $table): Table
    {
        $isUserRelation = $table->getLivewire() instanceof UserPackagesRelationManager;

        return $table
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn($record) => match (true) {
                $record->getOutstandingAmount() > 0 => 'invoice-status-partial',
                default => '',
            })
            ->columns([
                TextColumn::make('package_name')
                    ->label('Package')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => check_role('super_admin')),

                TextColumn::make('user.name')
                    ->label('Client')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-user')
                    ->searchable(['first_name', 'last_name', 'mobile'])
                    ->sortable(),

                TextColumn::make('services_count')
                    ->label('Services')
                    ->getStateUsing(fn($record) => $record->items->count())
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->tooltip(
                        fn($record) =>
                        $record->items->map(
                            fn($i) =>
                            ($i->service?->name ?? ($i->service_snapshot['name'] ?? 'Unknown'))
                        )->join(', ')
                    ),

                // Total, used and remaining in one column. They are three
                // readings of the same thing and are only ever compared with
                // each other, so three headings and three columns of padding
                // bought nothing.
                //
                // Each chip carries its own word rather than a bare number.
                // That is what lets the headings go — and it is also what makes
                // the chips distinguishable to the icon and colour callbacks,
                // which see one state item at a time and would otherwise have
                // no way to tell "4 total" from "4 remaining".
                TextColumn::make('total_sessions')
                    ->label('Sessions')
                    ->getStateUsing(fn($record): array => [
                        $record->getTotalSessions() . ' total',
                        $record->getTotalUsedSessions() . ' used',
                        $record->getTotalRemainingSessions() . ' left',
                    ])
                    ->badge()
                    ->icon(fn(string $state): string => match (true) {
                        str_ends_with($state, ' total') => 'heroicon-m-rectangle-stack',
                        str_ends_with($state, ' used') => 'heroicon-m-check-circle',
                        default => 'heroicon-m-clock',
                    })
                    ->color(fn(string $state, $record): string => match (true) {
                        str_ends_with($state, ' total') => 'gray',
                        str_ends_with($state, ' used') => 'warning',
                        // A package with nothing left is the one worth
                        // spotting from across the table.
                        default => $record->getTotalRemainingSessions() > 0 ? 'success' : 'danger',
                    })
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->withSum('items', 'quantity')->orderBy('items_sum_quantity', $direction)
                    )
                    ->summarize(self::sessionsSummarizer('Total', 'quantity')),

                // Billed, paid and still owed in one column, for the same
                // reason as the sessions: they are three readings of one figure
                // and are only ever read against each other.
                //
                // Each chip is labelled and the amounts are formatted here
                // rather than by ->money(), which would try to read "Paid
                // ₹44,950.00" back as a number. The label is also what tells
                // the callbacks apart — on a half-paid package the paid and
                // outstanding amounts are identical, so the figure alone could
                // not say which chip it was.
                TextColumn::make('final_amount')
                    ->label('Amount')
                    ->getStateUsing(function ($record): array {
                        $outstanding = $record->getOutstandingAmount();

                        return array_values(array_filter([
                            'Total ' . Number::currency((float) $record->final_amount, 'INR'),
                            'Paid ' . Number::currency((float) $record->getPaidAmount(), 'INR'),
                            // Only shown when something is actually owed: a
                            // "Due ₹0.00" chip on every settled package would
                            // train people to stop reading the column.
                            $outstanding > 0
                                ? 'Due ' . Number::currency((float) $outstanding, 'INR')
                                : null,
                        ]));
                    })
                    ->badge()
                    // Stacked: three money chips on one line are wider than the
                    // three columns they replace.
                    ->listWithLineBreaks()
                    ->icon(fn(string $state): string => match (true) {
                        str_starts_with($state, 'Total') => 'heroicon-m-banknotes',
                        str_starts_with($state, 'Paid') => 'heroicon-m-check-circle',
                        default => 'heroicon-m-exclamation-circle',
                    })
                    ->color(fn(string $state): string => match (true) {
                        str_starts_with($state, 'Total') => 'gray',
                        str_starts_with($state, 'Paid') => 'success',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('expired_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('No expiry')
                    ->color(fn($record) => $record->expired_at && $record->expired_at->isPast() ? 'danger' : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ToggleColumn::make('is_active')
                //     ->label('Status')
                //     ->onIcon('heroicon-o-bolt')
                //     ->offIcon('heroicon-o-power')
                //     ->offColor('dark-danger')
                //     ->onColor('success')
                //     ->disabled(fn() => !auth()->user()?->can('toggle_user_status'))
                //     ->afterStateUpdated(function ($state, $record) {
                //         $record->is_active = $state;
                //         $record->save();

                //         Notification::make()
                //             ->title($record->is_active ? 'Package Activated ✅' : 'Package Deactivated 🚫')
                //             ->body("Package status has been updated successfully.")
                //             ->success()
                //             ->send();
                //     }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('exhausted')
                    ->label('Fully Used Packages')
                    ->query(function (Builder $query) {
                        $query->whereDoesntHave('items', function ($q) {
                            $q->whereColumn('used_sessions', '<', 'quantity');
                        });
                    })
                    ->toggle(),

                Filter::make('advanced')
                    ->label('Advanced Filters')
                    ->form([
                        Section::make('Clinic & Clients')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(1)->schema([
                                    Select::make('clinic_id')
                                        ->label('Clinic')
                                        ->relationship('clinic', 'name', fn ($query) => $query->active())
                                        // ->searchable()
                                        ->preload()
                                        ->placeholder('Select Clinic')
                                        ->native(true)
                                        ->live()
                                        ->afterStateUpdated(fn(callable $set) => $set('user_id', null))
                                        ->visible(fn() => auth()->user()->hasRole('super_admin')),

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
                                        ->placeholder('All Clients')
                                        ->hidden($isUserRelation),

                                    Select::make('is_active')
                                        ->label('Status')
                                        ->options([
                                            1 => 'Active',
                                            0 => 'Inactive',
                                        ])
                                        ->placeholder('All'),
                                ]),
                            ])
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['clinic_id'] ?? null, fn($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
                            ->when(isset($data['is_active']) && $data['is_active'] !== '', fn($q) => $q->where('is_active', $data['is_active']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['clinic_id'] ?? null) {
                            $clinic = \App\Models\Clinic::find($data['clinic_id']);
                            if ($clinic) {
                                $indicators[] = \Filament\Tables\Filters\Indicator::make('Clinic: ' . $clinic->name)
                                    ->removeField('clinic_id');
                            }
                        }
                        if ($data['user_id'] ?? null) {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $indicators[] = \Filament\Tables\Filters\Indicator::make('Client: ' . $user->name)
                                    ->removeField('user_id');
                            }
                        }

                        if (isset($data['is_active']) && $data['is_active'] !== '') {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Status: ' . ($data['is_active'] ? 'Active' : 'Inactive'))
                                ->removeField('is_active');
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn(Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                InvoicePaymentForm::getMakePaymentAction()
                    ->visible(fn($record) => $record->is_active && $record->getOutstandingAmount() > 0),

                ActionGroup::make([
                    ViewAction::make()->modalWidth('7xl'),
                    EditAction::make()
                        ->modalWidth('7xl')
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Package Updated! ✨')
                                ->body('The package details have been refreshed successfully.')
                        ),
                    DeleteAction::make(),
                    Action::make('manage_invoices')
                        ->label('Invoices')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->url(fn($record) => UserPackageResource::getUrl('invoice', ['record' => $record])),
                    // ─── Record a Session Usage ─────────────────────────────
                    Action::make('use_session')
                        ->label('Use Session')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->hidden(fn($record) => !$record->is_active || $record->getTotalRemainingSessions() <= 0)
                        ->form(function ($record) {
                            $record->load('items.service');

                            // Build options: only items with remaining sessions
                            $serviceOptions = $record->items
                                ->filter(fn($item) => $item->getRemainingSessions() > 0)
                                ->mapWithKeys(fn($item) => [
                                    $item->id => ($item->service?->name ?? ($item->service_snapshot['name'] ?? 'Service'))
                                        . ' (' . $item->getRemainingSessions() . ' remaining)'
                                ]);

                            return [
                                Grid::make(2)->schema([
                                    Select::make('package_item_id')
                                        ->label('Select Service')
                                        ->options($serviceOptions)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->helperText('Choose which service to consume a session from.'),

                                    TextInput::make('sessions_used')
                                        ->label('Sessions to Consume')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->placeholder('Enter number of sessions')
                                        ->required(),

                                    // Select::make('appointment_id')
                                    //     ->label('Link to Appointment (optional)')
                                    //     ->options(fn () =>
                                    //         Appointment::where('user_id', $record->user_id)
                                    //             ->when($record->clinic_id, fn($q) => $q->where('clinic_id', $record->clinic_id))
                                    //             ->orderBy('start_datetime', 'desc')
                                    //             ->get()
                                    //             ->mapWithKeys(fn ($a) => [$a->id => $a->start_datetime->format('d M Y') . ' - ' . ($a->type?->getLabel() ?? 'Appointment')])
                                    //     )
                                    //     ->searchable()
                                    //     ->nullable()
                                    //     ->placeholder('None'),

                                    Textarea::make('notes')
                                        ->label('Notes')
                                        ->nullable()
                                        ->placeholder('Enter notes')
                                        ->columnSpan(2),
                                ]),
                            ];
                        })
                        ->action(function ($record, array $data) {
                            $sessions = (int) ($data['sessions_used'] ?? 1);
                            $itemId = $data['package_item_id'];

                            $item = UserPackageItem::find($itemId);
                            if (!$item || $item->user_package_id !== $record->id) {
                                Notification::make()
                                    ->title('Invalid Service Item ❌')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            if ($item->used_sessions + $sessions > $item->quantity) {
                                Notification::make()
                                    ->title('Over-usage Prevented ❌')
                                    ->body("Only {$item->getRemainingSessions()} session(s) remaining for this service. Cannot consume {$sessions}.")
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $item->consumeSessions($sessions, [
                                'notes' => $data['notes'] ?? null,
                                'appointment_id' => $data['appointment_id'] ?? null,
                            ]);

                            Notification::make()
                                ->title('Session Recorded ✅')
                                ->body("{$sessions} session(s) consumed from {$item->service?->name}. Remaining: {$item->getRemainingSessions()}.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            // ->bulkActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ])
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading('No Packages Found')
            ->emptyStateDescription('Start by creating a patient package using the button above.');
    }

    /**
     * Sessions live on `user_package_items`, so the session columns have no
     * database column of their own to aggregate. Each summarizer therefore
     * joins the items onto the (already filtered) package query and sums the
     * given expression across them.
     */
    protected static function sessionsSummarizer(string $label, string $expression): Summarizer
    {
        return Summarizer::make('sum')
            ->label($label)
            ->numeric()
            ->using(fn(QueryBuilder $query): int => (int) $query
                ->leftJoin('user_package_items', 'user_package_items.user_package_id', '=', 'user_packages.id')
                ->sum(DB::raw($expression)));
    }

    /**
     * Mirrors UserPackage::getPaidAmount() — the sum of `amount_paid` across
     * every invoice raised against the package.
     */
    protected static function paidAmountSummarizer(): Summarizer
    {
        return Summarizer::make('sum')
            ->label('Total Paid')
            ->money('INR')
            ->using(fn(QueryBuilder $query): float => (float) $query
                ->leftJoin('invoices', 'invoices.package_id', '=', 'user_packages.id')
                ->sum('invoices.amount_paid'));
    }

    /**
     * Mirrors UserPackage::getOutstandingAmount() — final amount less what has
     * been paid, floored at zero per package so an overpaid package cannot
     * cancel out the balance owed on another.
     */
    protected static function outstandingAmountSummarizer(): Summarizer
    {
        return Summarizer::make('sum')
            ->label('Total Outstanding')
            ->money('INR')
            ->using(function (QueryBuilder $query): float {
                $paidPerPackage = DB::table('invoices')
                    ->selectRaw('package_id, sum(amount_paid) as paid_total')
                    ->whereNotNull('package_id')
                    ->groupBy('package_id');

                return (float) $query
                    ->leftJoinSub($paidPerPackage, 'package_payments', 'package_payments.package_id', '=', 'user_packages.id')
                    ->selectRaw('coalesce(sum(greatest(user_packages.final_amount - coalesce(package_payments.paid_total, 0), 0)), 0) as aggregate')
                    ->value('aggregate');
            });
    }
}
