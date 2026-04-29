<?php

namespace App\Filament\Resources\UserPackages\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
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
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),

                TextColumn::make('user.name')
                    ->label('Patient')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-user')
                    ->searchable(['first_name', 'last_name', 'mobile'])
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('Service')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-sparkles')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Total Sessions')
                    ->alignCenter()
                    ->suffix(' sessions')
                    ->sortable(),

                TextColumn::make('used_sessions')
                    ->label('Used')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('remaining_sessions')
                    ->label('Remaining')
                    ->getStateUsing(fn ($record) => $record->getRemainingSessions())
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record) => $record->getRemainingSessions() > 0 ? 'success' : 'danger'),

                TextColumn::make('final_amount')
                    ->label('Final Amount')
                    ->money('INR')
                    ->sortable(),

                TextColumn::make('expired_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('No expiry')
                    ->color(fn ($record) => $record->expired_at && $record->expired_at->isPast() ? 'danger' : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    ->onColor('success')
                    // ->sortable()
                    // ->requiresConfirmation()
                    ->disabled(fn () => ! auth()->user()?->can('toggle_user_status'))
                    // ->visible(auth()->user()->can('toggle_user_status'))
                    ->afterStateUpdated(function ($state, $record) {
                        // if (! auth()->user()->can('toggle_user_status')) {
                        //     Notification::make()
                        //         ->title('Access Denied')
                        //         ->body('You do not have permission to update user status.')
                        //         ->danger()
                        //         ->send();

                        //     $record->is_active = ! $state;
                        //     $record->save();

                        //     return;
                        // }

                        $record->is_active = $state;
                        $record->save();

                        // $record->update(['is_active' => !$record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Package Activated ✅' : 'Package Deactivated 🚫')
                            ->body("Package status has been updated successfully.")
                            ->success()
                            ->send();

                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->placeholder('All Packages'),

                Filter::make('expired')
                    ->label('Expired Packages')
                    ->query(fn (Builder $query) => $query->whereNotNull('expired_at')->where('expired_at', '<', now()))
                    ->toggle(),

                Filter::make('exhausted')
                    ->label('Fully Used Packages')
                    ->query(fn (Builder $query) => $query->whereColumn('used_sessions', '>=', 'quantity'))
                    ->toggle(),

                Filter::make('advanced')
                    ->label('Advanced Filters')
                    ->form([
                        Section::make('Patient & Service')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(1)->schema([
                                    Select::make('clinic_id')
                                        ->label('Clinic')
                                        ->relationship('clinic', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select Clinic')
                                        ->live()
                                        ->visible(fn () => auth()->user()->hasRole('super_admin')),

                                    Select::make('user_id')
                                        ->label('Patient')
                                        ->options(function (callable $get) {
                                            $clinicId = $get('clinic_id');
                                            if (!$clinicId) {
                                                $clinicId = auth()->user()->clinic_id;
                                            }

                                            return User::active()
                                                ->role('client')
                                                ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                                                ->get()
                                                ->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                        })
                                        ->searchable()
                                        ->placeholder('All Patients'),
                                ]),
                            ])
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id));
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
                                $indicators[] = \Filament\Tables\Filters\Indicator::make('Patient: ' . $user->name)
                                    ->removeField('user_id');
                            }
                        }
                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->actions([
                // ─── Record a Session Usage ─────────────────────────────
                Action::make('use_session')
                    ->label('Use Session')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->hidden(fn ($record) => !$record->is_active || $record->getRemainingSessions() <= 0)
                    ->form([
                        \Filament\Forms\Components\TextInput::make('sessions_used')
                            ->label('Sessions to Consume')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),

                        Select::make('appointment_id')
                            ->label('Link to Appointment (optional)')
                            ->options(fn ($record) =>
                                \App\Models\Appointment::where('user_id', $record->user_id)
                                    ->when($record->clinic_id, fn($q) => $q->where('clinic_id', $record->clinic_id))
                                    ->orderBy('start_datetime', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn ($a) => [$a->id => $a->start_datetime->format('d M Y') . ' - ' . ($a->type?->getLabel() ?? 'Appointment')])
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder('None'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->nullable()
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $sessions = (int) ($data['sessions_used'] ?? 1);

                        if ($record->used_sessions + $sessions > $record->quantity) {
                            Notification::make()
                                ->title('Over-usage Prevented ❌')
                                ->body("Only {$record->getRemainingSessions()} session(s) remaining. Cannot consume {$sessions}.")
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->usages()->create([
                            'sessions_used'  => $sessions,
                            'notes'          => $data['notes'] ?? null,
                            'appointment_id' => $data['appointment_id'] ?? null,
                            'recorded_by'    => auth()->id(),
                        ]);

                        $record->increment('used_sessions', $sessions);
                        $record->refresh();

                        // Auto-deactivate if exhausted
                        if ($record->used_sessions >= $record->quantity) {
                            $record->update(['is_active' => false]);
                        }

                        Notification::make()
                            ->title('Session Recorded ✅')
                            ->body("{$sessions} session(s) consumed. Remaining: {$record->getRemainingSessions()}.")
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading('No Packages Found')
            ->emptyStateDescription('Start by creating a patient package using the button above.');
    }
}
